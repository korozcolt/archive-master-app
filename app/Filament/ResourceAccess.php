<?php

namespace App\Filament;

use Illuminate\Support\Facades\Auth;

final class ResourceAccess
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $permissions
     */
    public static function allows(array $roles = [], array $permissions = []): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($roles !== [] && $user->hasRole($roles)) {
            return true;
        }

        if ($permissions !== []) {
            return $user->hasAnyPermission($permissions);
        }

        return false;
    }
}
