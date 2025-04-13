<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum DocumentStatus: string implements HasLabel, HasColor, HasIcon {
    case Received = 'received';       // Documento recibido
    case InProcess = 'in_process';    // En procesamiento
    case UnderReview = 'under_review'; // En revisión
    case Approved = 'approved';       // Aprobado
    case Rejected = 'rejected';       // Rechazado
    case Archived = 'archived';       // Archivado
    case Expired = 'expired';         // Vencido
    case Draft = 'draft';             // Borrador

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Received => 'Recibido',
            self::InProcess => 'En Proceso',
            self::UnderReview => 'En Revisión',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Archived => 'Archivado',
            self::Expired => 'Vencido',
            self::Draft => 'Borrador',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Received => 'info',
            self::InProcess => 'warning',
            self::UnderReview => 'primary',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Archived => 'gray',
            self::Expired => 'danger',
            self::Draft => 'secondary',
        };
    }

    public function getIcon(): string | null
    {
        return match ($this) {
            self::Received => 'heroicon-o-inbox',
            self::InProcess => 'heroicon-o-cog',
            self::UnderReview => 'heroicon-o-magnifying-glass',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
            self::Archived => 'heroicon-o-archive-box',
            self::Expired => 'heroicon-o-clock',
            self::Draft => 'heroicon-o-pencil',
        };
    }

    public function getColorHtml(): ?string
    {
        return match ($this) {
            self::Received => 'bg-blue-100',
            self::InProcess => 'bg-yellow-100',
            self::UnderReview => 'bg-indigo-100',
            self::Approved => 'bg-green-100',
            self::Rejected => 'bg-red-100',
            self::Archived => 'bg-gray-100',
            self::Expired => 'bg-red-100',
            self::Draft => 'bg-gray-200',
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

    public function isActive(): bool
    {
        return $this !== self::Archived && $this !== self::Expired && $this !== self::Rejected;
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::Received, self::InProcess, self::UnderReview, self::Draft]);
    }

    public function canArchive(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }

    public function canChangeStatus(): bool
    {
        return !in_array($this, [self::Archived, self::Expired]);
    }
}
