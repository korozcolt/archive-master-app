<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ArchivePhase: string implements HasColor, HasIcon, HasLabel
{
    case Gestion = 'gestion';
    case Central = 'central';
    case Historico = 'historico';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Gestion => 'Archivo de gestión',
            self::Central => 'Archivo central',
            self::Historico => 'Archivo histórico',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Gestion => 'info',
            self::Central => 'warning',
            self::Historico => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Gestion => 'heroicon-o-folder',
            self::Central => 'heroicon-o-archive-box',
            self::Historico => 'heroicon-o-building-library',
        };
    }
}
