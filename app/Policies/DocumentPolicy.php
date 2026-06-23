<?php

namespace App\Policies;

use App\Enums\DocumentAccessLevel;
use App\Enums\Role;
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

        if ($document->created_by === $user->id) {
            return true;
        }

        if ($document->isHistoricalEntry()) {
            if ($user->hasAnyRole(['super_admin', 'admin', 'branch_admin', Role::ArchiveManager->value, Role::ArchiveOperator->value])) {
                return true;
            }

            if ($user->hasRole(Role::RegularUser->value)) {
                return false;
            }

            return in_array($document->access_level?->value, [
                DocumentAccessLevel::Publico->value,
                DocumentAccessLevel::Interno->value,
            ], true);
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
            return true;
        }

        if ($user->hasRole(Role::ArchiveOperator->value) && in_array($document->archive_phase?->value, [ArchivePhase::Central->value, ArchivePhase::Historico->value], true)) {
            return true;
        }

        if ($user->hasRole(Role::Receptionist->value) && $document->receipts()->exists()) {
            return true;
        }

        return $document->created_by === $user->id
            || $document->assigned_to === $user->id
            || ($user->hasRole(Role::RegularUser->value) && $document->receipts()
                ->where('recipient_user_id', $user->id)
                ->exists());
    }
}
