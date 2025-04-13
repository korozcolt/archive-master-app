<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum DocumentType: string implements HasLabel, HasColor, HasIcon {
    case Correspondence = 'correspondence'; // Correspondencia
    case Invoice = 'invoice';               // Factura
    case Contract = 'contract';             // Contrato
    case Report = 'report';                 // Informe
    case Internal = 'internal';             // Documento interno
    case Legal = 'legal';                   // Documento legal
    case Financial = 'financial';           // Documento financiero
    case Other = 'other';                   // Otro

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Correspondence => 'Correspondencia',
            self::Invoice => 'Factura',
            self::Contract => 'Contrato',
            self::Report => 'Informe',
            self::Internal => 'Documento Interno',
            self::Legal => 'Documento Legal',
            self::Financial => 'Documento Financiero',
            self::Other => 'Otro',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Correspondence => 'info',
            self::Invoice => 'success',
            self::Contract => 'primary',
            self::Report => 'warning',
            self::Internal => 'gray',
            self::Legal => 'purple',
            self::Financial => 'success',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string | null
    {
        return match ($this) {
            self::Correspondence => 'heroicon-o-envelope',
            self::Invoice => 'heroicon-o-currency-dollar',
            self::Contract => 'heroicon-o-document-text',
            self::Report => 'heroicon-o-clipboard-document-list',
            self::Internal => 'heroicon-o-document',
            self::Legal => 'heroicon-o-scale',
            self::Financial => 'heroicon-o-banknotes',
            self::Other => 'heroicon-o-document-duplicate',
        };
    }

    public function getColorHtml(): ?string
    {
        return match ($this) {
            self::Correspondence => 'bg-blue-100',
            self::Invoice => 'bg-green-100',
            self::Contract => 'bg-indigo-100',
            self::Report => 'bg-yellow-100',
            self::Internal => 'bg-gray-100',
            self::Legal => 'bg-purple-100',
            self::Financial => 'bg-green-100',
            self::Other => 'bg-gray-100',
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

    public function getWorkflowTemplate(): string
    {
        return match ($this) {
            self::Correspondence => 'correspondence_workflow',
            self::Invoice => 'invoice_workflow',
            self::Contract => 'contract_workflow',
            self::Report => 'report_workflow',
            self::Internal => 'internal_workflow',
            self::Legal => 'legal_workflow',
            self::Financial => 'financial_workflow',
            self::Other => 'default_workflow',
        };
    }
}
