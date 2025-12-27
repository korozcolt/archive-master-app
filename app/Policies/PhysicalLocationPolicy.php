<?php

namespace App\Policies;

use App\Models\PhysicalLocation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhysicalLocationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view locations
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PhysicalLocation $physicalLocation): bool
    {
        return $user->hasRole('super_admin') || $user->company_id === $physicalLocation->company_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'branch_manager', 'archivist']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PhysicalLocation $physicalLocation): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->company_id !== $physicalLocation->company_id) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'branch_manager', 'archivist']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PhysicalLocation $physicalLocation): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->company_id !== $physicalLocation->company_id) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'branch_manager']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PhysicalLocation $physicalLocation): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->company_id !== $physicalLocation->company_id) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'branch_manager']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PhysicalLocation $physicalLocation): bool
    {
        return $user->hasRole('super_admin');
    }
}
