<?php

namespace App\Policies;

use App\Models\DocumentAiRun;
use App\Models\User;

class DocumentAiRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin',
            'branch_admin',
            'office_manager',
            'archive_manager',
            'receptionist',
            'regular_user',
        ]);
    }

    public function view(User $user, DocumentAiRun $run): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if (! $user->hasRole('super_admin') && (int) $user->company_id !== (int) $run->company_id) {
            return false;
        }

        return $user->can('view', $run->document);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin',
            'branch_admin',
            'office_manager',
            'archive_manager',
            'receptionist',
        ]);
    }

    public function regenerate(User $user, DocumentAiRun $run): bool
    {
        if (! $user->hasAnyRole(['super_admin', 'admin', 'branch_admin', 'office_manager', 'archive_manager'])) {
            return false;
        }

        if (! $user->hasRole('super_admin') && (int) $user->company_id !== (int) $run->company_id) {
            return false;
        }

        return $user->can('view', $run->document);
    }
}
