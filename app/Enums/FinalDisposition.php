<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum FinalDisposition: string implements HasColor, HasIcon, HasLabel
{
    case ConservacionTotal = 'conservacion_total';
    case Seleccion = 'seleccion';
    case Eliminacion = 'eliminacion';
    case Digitalizacion = 'digitalizacion';
    case Microfilmacion = 'microfilmacion';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ConservacionTotal => 'Conservación total',
            self::Seleccion => 'Selección',
            self::Eliminacion => 'Eliminación',
            self::Digitalizacion => 'Digitalización',
            self::Microfilmacion => 'Microfilmación',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ConservacionTotal => 'success',
            self::Seleccion => 'info',
            self::Eliminacion => 'danger',
            self::Digitalizacion => 'warning',
            self::Microfilmacion => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ConservacionTotal => 'heroicon-o-book-open',
            self::Seleccion => 'heroicon-o-funnel',
            self::Eliminacion => 'heroicon-o-trash',
            self::Digitalizacion => 'heroicon-o-document-text',
            self::Microfilmacion => 'heroicon-o-film',
        };
    }
}
