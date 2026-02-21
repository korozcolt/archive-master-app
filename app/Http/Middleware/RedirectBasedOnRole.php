<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * Redirige a los usuarios al dashboard apropiado según su rol
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $adminRoles = [
            Role::SuperAdmin->value,
            Role::Admin->value,
            Role::BranchAdmin->value,
        ];

        $portalRoles = [
            Role::OfficeManager->value,
            Role::ArchiveManager->value,
            Role::Receptionist->value,
            Role::RegularUser->value,
        ];

        if ($request->is('dashboard') && $user->hasAnyRole($adminRoles)) {
            return redirect('/admin');
        }

        if ($request->is('dashboard') && $user->hasAnyRole($portalRoles)) {
            return redirect('/portal');
        }

        if ($request->is('admin*') && $user->hasAnyRole($portalRoles)) {
            return redirect('/portal');
        }

        if ($request->is('portal*') && $user->hasAnyRole($adminRoles)) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
