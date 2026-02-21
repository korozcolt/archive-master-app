<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ReceiptController extends Controller
{
    public function show(Receipt $receipt): Response
    {
        $this->authorizeReceiptAccess($receipt);

        return response()->view('receipts.show', [
            'receipt' => $receipt->load(['document', 'issuer', 'recipientUser']),
        ]);
    }

    public function download(Receipt $receipt): Response
    {
        $this->authorizeReceiptAccess($receipt);

        $pdf = Pdf::loadView('receipts.pdf', [
            'receipt' => $receipt->load(['document', 'issuer', 'recipientUser']),
        ]);

        return $pdf->download($receipt->receipt_number.'.pdf');
    }

    private function authorizeReceiptAccess(Receipt $receipt): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasAnyRole(['super_admin', 'admin', 'branch_admin'])) {
            return;
        }

        if ($user->id === $receipt->issued_by || $user->id === $receipt->recipient_user_id) {
            return;
        }

        abort(403);
    }
}
