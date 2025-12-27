<?php

namespace App\Rules;

use App\Models\Document;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UniqueOriginalDocument implements ValidationRule
{
    protected ?int $documentId;
    protected ?int $companyId;
    protected string $type;
    protected ?string $referenceField;

    /**
     * Create a new rule instance.
     *
     * @param string $type 'digital' o 'physical'
     * @param int|null $companyId Company ID para scope
     * @param int|null $documentId ID del documento actual (para excluir en updates)
     * @param string|null $referenceField Campo de referencia para comparar (opcional)
     */
    public function __construct(
        string $type = 'digital',
        ?int $companyId = null,
        ?int $documentId = null,
        ?string $referenceField = null
    ) {
        $this->type = $type;
        $this->companyId = $companyId ?? Auth::user()?->company_id;
        $this->documentId = $documentId;
        $this->referenceField = $referenceField;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Solo validar si el valor es "original"
        if ($value !== 'original') {
            return;
        }

        // Construir la query base
        $query = Document::query()
            ->where('company_id', $this->companyId);

        // Excluir el documento actual si estamos editando
        if ($this->documentId) {
            $query->where('id', '!=', $this->documentId);
        }

        // Aplicar filtro según el tipo
        if ($this->type === 'digital') {
            $query->where('digital_document_type', 'original');

            // Si se especificó un campo de referencia, validar que no exista otro original con el mismo valor
            if ($this->referenceField) {
                $exists = $query->where($this->referenceField, request()->input($this->referenceField))
                    ->exists();

                if ($exists) {
                    $fail("Ya existe un documento digital original con este {$this->referenceField} en la empresa.");
                    return;
                }
            }

            // Validación general: advertir si ya hay muchos originales digitales
            $count = $query->count();
            if ($count > 100) {
                // Solo advertencia en logs, no falla la validación
                Log::warning('Empresa tiene muchos documentos digitales originales', [
                    'company_id' => $this->companyId,
                    'count' => $count,
                ]);
            }

        } elseif ($this->type === 'physical') {
            $query->where('physical_document_type', 'original');

            // Para documentos físicos, validar por número de documento o barcode
            if ($this->referenceField) {
                $exists = $query->where($this->referenceField, request()->input($this->referenceField))
                    ->exists();

                if ($exists) {
                    $fail("Ya existe un documento físico original con este {$this->referenceField} en la empresa.");
                    return;
                }
            }

            // Validación adicional: un documento físico original no puede existir si ya hay uno digital original con el mismo número
            $documentNumber = request()->input('document_number');
            if ($documentNumber) {
                $existsDigitalOriginal = Document::query()
                    ->where('company_id', $this->companyId)
                    ->where('document_number', $documentNumber)
                    ->where('digital_document_type', 'original')
                    ->when($this->documentId, fn($q) => $q->where('id', '!=', $this->documentId))
                    ->exists();

                if ($existsDigitalOriginal) {
                    $fail('Ya existe un documento digital original con este número. El documento físico debe ser marcado como copia.');
                    return;
                }
            }
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        if ($this->type === 'digital') {
            return 'Solo puede existir un documento digital original por identificador único en la empresa.';
        }

        return 'Solo puede existir un documento físico original por identificador único en la empresa.';
    }

    /**
     * Validación estática para uso directo
     */
    public static function validateDigitalOriginal(
        ?int $companyId = null,
        ?int $documentId = null,
        ?string $referenceField = 'document_number'
    ): self {
        return new self('digital', $companyId, $documentId, $referenceField);
    }

    /**
     * Validación estática para documentos físicos originales
     */
    public static function validatePhysicalOriginal(
        ?int $companyId = null,
        ?int $documentId = null,
        ?string $referenceField = 'barcode'
    ): self {
        return new self('physical', $companyId, $documentId, $referenceField);
    }
}
