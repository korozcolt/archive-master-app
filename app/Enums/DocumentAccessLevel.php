<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DocumentAccessLevel: string implements HasColor, HasIcon, HasLabel
{
    case Publico = 'publico';
    case Interno = 'interno';
    case Reservado = 'reservado';
    case ClasificadoConfidencial = 'clasificado_confidencial';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::Interno => 'Interno',
            self::Reservado => 'Reservado',
            self::ClasificadoConfidencial => 'Clasificado / Confidencial',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Publico => 'success',
            self::Interno => 'info',
            self::Reservado => 'warning',
            self::ClasificadoConfidencial => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Publico => 'heroicon-o-globe-alt',
            self::Interno => 'heroicon-o-building-office-2',
            self::Reservado => 'heroicon-o-shield-exclamation',
            self::ClasificadoConfidencial => 'heroicon-o-lock-closed',
        };
    }
}
