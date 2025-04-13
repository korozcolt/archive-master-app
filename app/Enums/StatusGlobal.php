<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum StatusGlobal: string implements HasLabel, HasColor, HasIcon {

    case Active = 'active'; // Activo
    case Inactive = 'inactive'; // Inactivo
    case Deleted = 'deleted'; // Eliminado
    case Draft = 'draft'; // Borrador
    case Published = 'published'; // Publicado

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Deleted => 'Eliminado',
            self::Draft => 'Borrador',
            self::Published => 'Publicado',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'danger',
            self::Deleted => 'danger',
            self::Draft => 'secondary',
            self::Published => 'primary',
        };
    }

    public function getIcon(): string | null
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-x-circle',
            self::Deleted => 'heroicon-o-trash',
            self::Draft => 'heroicon-o-pencil',
            self::Published => 'heroicon-o-globe-alt',
        };
    }

    public function getColorHtml(): ?string
    {
        return match ($this) {
            self::Active => 'bg-green-100',
            self::Inactive => 'bg-orange-100',
            self::Deleted => 'bg-red-100',
            self::Draft => 'bg-gray-100',
            self::Published => 'bg-blue-100',
        };
    }

    public function getLabelText(): ?string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Deleted => 'Eliminado',
            self::Draft => 'Borrador',
            self::Published => 'Publicado',
        };
    }

    public function getLabelHtml(): ?string
    {
        return '<span class="py-1 px-3 rounded '.$this->getColorHtml().'">'.$this->getLabelText().'</span>';
    }

    public function isEditable(): bool
    {
        return match($this) {
            self::Draft => true,
            default => false
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::Draft => true,
            self::Active => $newStatus === self::Inactive,
            self::Inactive => $newStatus === self::Active,
            self::Published => $newStatus === self::Draft,
        };
    }
}
