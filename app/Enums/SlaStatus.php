<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SlaStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Running = 'running';
    case Warning = 'warning';
    case Overdue = 'overdue';
    case Paused = 'paused';
    case Closed = 'closed';
    case Frozen = 'frozen';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Running => 'En tiempo',
            self::Warning => 'Por vencer',
            self::Overdue => 'Vencido',
            self::Paused => 'Suspendido',
            self::Closed => 'Cerrado',
            self::Frozen => 'Histórico congelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Running => 'success',
            self::Warning => 'warning',
            self::Overdue => 'danger',
            self::Paused => 'info',
            self::Closed => 'primary',
            self::Frozen => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-document',
            self::Running => 'heroicon-o-check-circle',
            self::Warning => 'heroicon-o-exclamation-triangle',
            self::Overdue => 'heroicon-o-fire',
            self::Paused => 'heroicon-o-pause-circle',
            self::Closed => 'heroicon-o-lock-closed',
            self::Frozen => 'heroicon-o-archive-box',
        };
    }
}
