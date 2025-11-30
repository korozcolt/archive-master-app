<?php

namespace App\Http\Middleware;

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
        if ($request->user()) {
            // Si es admin y está intentando acceder a /dashboard, redirigir a /admin
            if ($request->is('dashboard') && $request->user()->hasAnyRole(['admin', 'super_admin', 'branch_admin', 'office_manager'])) {
                return redirect('/admin');
            }

            // Si es usuario regular y está intentando acceder a /admin, redirigir a /dashboard
            if ($request->is('admin*') && $request->user()->hasAnyRole(['regular_user', 'receptionist', 'archive_manager'])) {
                return redirect('/dashboard');
            }
        }

        return $next($request);
    }
}
