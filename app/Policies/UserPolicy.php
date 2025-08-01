<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'super_admin', 'branch_admin', 'office_manager']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id && 
               ($user->hasRole(['admin', 'super_admin', 'branch_admin', 'office_manager']) || $user->id === $model->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'super_admin', 'branch_admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id && 
               ($user->hasRole(['admin', 'super_admin', 'branch_admin']) || $user->id === $model->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id && 
               $user->hasRole(['admin', 'super_admin']) && 
               $user->id !== $model->id; // Can't delete themselves
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id && 
               $user->hasRole(['admin', 'super_admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole(['super_admin']) && 
               $user->id !== $model->id; // Can't delete themselves
    }
}