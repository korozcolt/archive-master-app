<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasColor, HasIcon, HasLabel
{
    case SuperAdmin = 'super_admin';           // Acceso completo a todo el sistema
    case Admin = 'admin';                      // Administrador de empresa
    case BranchAdmin = 'branch_admin';         // Administrador de sucursal
    case OfficeManager = 'office_manager';     // Encargado de oficina
    case ArchiveManager = 'archive_manager';   // Encargado de archivo
    case Receptionist = 'receptionist';        // Recepcionista
    case RegularUser = 'regular_user';         // Usuario regular

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrador',
            self::Admin => 'Administrador',
            self::BranchAdmin => 'Administrador de Sucursal',
            self::OfficeManager => 'Encargado de Oficina',
            self::ArchiveManager => 'Encargado de Archivo',
            self::Receptionist => 'Recepcionista',
            self::RegularUser => 'Usuario Regular',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'primary',
            self::BranchAdmin => 'warning',
            self::OfficeManager => 'success',
            self::ArchiveManager => 'info',
            self::Receptionist => 'purple',
            self::RegularUser => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SuperAdmin => 'heroicon-o-key',
            self::Admin => 'heroicon-o-building-office',
            self::BranchAdmin => 'heroicon-o-building-storefront',
            self::OfficeManager => 'heroicon-o-briefcase',
            self::ArchiveManager => 'heroicon-o-archive-box',
            self::Receptionist => 'heroicon-o-inbox',
            self::RegularUser => 'heroicon-o-user',
        };
    }

    public function getColorHtml(): ?string
    {
        return match ($this) {
            self::SuperAdmin => 'bg-red-100',
            self::Admin => 'bg-blue-100',
            self::BranchAdmin => 'bg-yellow-100',
            self::OfficeManager => 'bg-green-100',
            self::ArchiveManager => 'bg-cyan-100',
            self::Receptionist => 'bg-purple-100',
            self::RegularUser => 'bg-gray-100',
        };
    }

    public function getLabelText(): ?string
    {
        return $this->getLabel();
    }

    public function getLabelHtml(): ?string
    {
        return '<span class="py-1 px-3 rounded '.$this->getColorHtml().'">'.$this->getLabelText().'</span>';
    }

    public function getPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => ['*'],
            self::Admin => [
                'manage-company', 'manage-branches', 'manage-departments', 'manage-users',
                'manage-documents', 'manage-categories', 'view-reports',
                'ai.settings.manage', 'ai.run.generate', 'ai.output.view',
                'ai.output.regenerate', 'ai.output.apply_suggestions',
            ],
            self::BranchAdmin => [
                'manage-branch', 'manage-departments', 'manage-users', 'manage-documents',
                'view-branch-reports',
                'ai.settings.manage', 'ai.run.generate', 'ai.output.view',
                'ai.output.regenerate', 'ai.output.apply_suggestions',
            ],
            self::OfficeManager => [
                'manage-department', 'manage-documents', 'assign-documents',
                'ai.run.generate', 'ai.output.view', 'ai.output.regenerate',
                'ai.output.apply_suggestions',
            ],
            self::ArchiveManager => [
                'manage-archives', 'view-documents', 'archive-documents',
                'ai.run.generate', 'ai.output.view', 'ai.output.regenerate',
                'ai.output.apply_suggestions',
            ],
            self::Receptionist => [
                'create-documents', 'view-documents', 'update-documents',
                'ai.run.generate', 'ai.output.view',
            ],
            self::RegularUser => [
                'view-own-documents', 'create-documents', 'ai.output.view',
            ],
        };
    }

    public static function getAllPermissions(): array
    {
        $allPermissions = [];
        foreach (self::cases() as $role) {
            $permissions = $role->getPermissions();
            if ($permissions !== ['*']) {
                $allPermissions = array_merge($allPermissions, $permissions);
            }
        }

        return array_unique($allPermissions);
    }
}
