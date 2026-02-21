<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\Portal\RequestOtpLoginRequest;
use App\Http\Requests\Portal\VerifyOtpLoginRequest;
use App\Models\PortalLoginOtp;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PortalAuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('auth.portal-login');
    }

    public function requestOtp(RequestOtpLoginRequest $request): RedirectResponse
    {
        $receipt = Receipt::query()
            ->with('recipientUser')
            ->where('receipt_number', $request->string('receipt_number')->toString())
            ->where('recipient_email', $request->string('email')->toString())
            ->first();

        if (! $receipt || ! $receipt->recipientUser || ! $receipt->recipientUser->hasRole(Role::RegularUser->value)) {
            return back()->withErrors([
                'receipt_number' => 'No se encontró un recibido válido para esos datos.',
            ])->withInput();
        }

        PortalLoginOtp::query()
            ->where('user_id', $receipt->recipient_user_id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $otpCode = (string) random_int(100000, 999999);

        PortalLoginOtp::create([
            'user_id' => $receipt->recipient_user_id,
            'receipt_id' => $receipt->id,
            'identifier' => mb_strtolower($receipt->recipient_email),
            'code_hash' => Hash::make($otpCode),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = redirect()
            ->route('portal.auth.verify.form')
            ->with('otp_sent', true)
            ->with('receipt_number', $receipt->receipt_number)
            ->with('email', $receipt->recipient_email);

        if (app()->environment(['local', 'testing'])) {
            return $response->with('otp_preview', $otpCode);
        }

        return $response;
    }

    public function showVerifyForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        if (! session('otp_sent')) {
            return redirect()->route('portal.auth.login');
        }

        return view('auth.portal-verify-otp');
    }

    public function verifyOtp(VerifyOtpLoginRequest $request): RedirectResponse
    {
        $receipt = Receipt::query()
            ->where('receipt_number', $request->string('receipt_number')->toString())
            ->where('recipient_email', $request->string('email')->toString())
            ->first();

        if (! $receipt || ! $receipt->recipient_user_id) {
            return back()->withErrors([
                'receipt_number' => 'Recibido inválido o sin usuario asociado.',
            ])->withInput();
        }

        $otp = PortalLoginOtp::query()
            ->where('user_id', $receipt->recipient_user_id)
            ->where('identifier', mb_strtolower($request->string('email')->toString()))
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || now()->greaterThan($otp->expires_at)) {
            return back()->withErrors([
                'otp_code' => 'El código OTP expiró. Solicita uno nuevo.',
            ])->withInput();
        }

        if (! Hash::check($request->string('otp_code')->toString(), $otp->code_hash)) {
            $otp->increment('attempts');

            return back()->withErrors([
                'otp_code' => 'El código OTP no es válido.',
            ])->withInput();
        }

        $otp->update(['consumed_at' => now()]);

        Auth::loginUsingId($receipt->recipient_user_id, true);
        $request->session()->regenerate();

        return redirect('/portal');
    }
}
