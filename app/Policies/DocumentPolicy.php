<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole(['admin', 'branch_admin', 'office_manager', 'archive_manager'])) {
            return true;
        }

        return $user->hasRole(['receptionist', 'regular_user']);
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->company_id !== $document->company_id) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('branch_admin')) {
            return $document->branch_id === null || $document->branch_id === $user->branch_id;
        }

        if ($user->hasRole('office_manager')) {
            return $document->department_id === $user->department_id;
        }

        if ($user->hasRole('archive_manager')) {
            return $document->physical_location_id !== null;
        }

        return $document->created_by === $user->id || $document->assigned_to === $user->id;
    }
}
