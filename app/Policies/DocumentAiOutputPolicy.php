<?php

namespace App\Policies;

use App\Models\DocumentAiOutput;
use App\Models\User;

class DocumentAiOutputPolicy
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

    public function view(User $user, DocumentAiOutput $output): bool
    {
        $run = $output->run;

        if (! $run) {
            return false;
        }

        if (! $this->viewAny($user)) {
            return false;
        }

        if (! $user->hasRole('super_admin') && (int) $user->company_id !== (int) $run->company_id) {
            return false;
        }

        return $user->can('view', $run->document);
    }

    public function applySuggestions(User $user, DocumentAiOutput $output): bool
    {
        $run = $output->run;

        if (! $run) {
            return false;
        }

        if (! $user->hasAnyRole(['super_admin', 'admin', 'branch_admin', 'office_manager', 'archive_manager'])) {
            return false;
        }

        if (! $user->hasRole('super_admin') && (int) $user->company_id !== (int) $run->company_id) {
            return false;
        }

        return $user->can('view', $run->document);
    }

    public function markIncorrect(User $user, DocumentAiOutput $output): bool
    {
        return $this->view($user, $output);
    }

    public function viewEntities(User $user, DocumentAiOutput $output): bool
    {
        $run = $output->run;

        if (! $run) {
            return false;
        }

        if (! $user->hasAnyRole(['super_admin', 'admin', 'branch_admin', 'office_manager', 'archive_manager'])) {
            return false;
        }

        if (! $user->hasRole('super_admin') && (int) $user->company_id !== (int) $run->company_id) {
            return false;
        }

        return $user->can('view', $run->document);
    }
}
