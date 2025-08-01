<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $company = $this->route('company');
        return $this->user()->can('update', $company);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $company = $this->route('company');
        
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('companies')
                    ->ignore($company->id)
                    ->whereNull('deleted_at')
            ],
            'description' => 'sometimes|nullable|string|max:1000',
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('companies')
                    ->ignore($company->id)
                    ->whereNull('deleted_at')
            ],
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'website' => 'sometimes|nullable|url|max:255',
            'logo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'timezone' => 'sometimes|nullable|string|max:50',
            'language' => 'sometimes|nullable|string|size:2|in:es,en',
            'currency' => 'sometimes|nullable|string|size:3',
            'date_format' => 'sometimes|nullable|string|max:20',
            'time_format' => 'sometimes|nullable|string|max:20',
            'is_active' => 'sometimes|nullable|boolean',
            'settings' => 'sometimes|nullable|array',
            'settings.document_retention_days' => 'sometimes|nullable|integer|min:1|max:3650',
            'settings.max_file_size_mb' => 'sometimes|nullable|integer|min:1|max:100',
            'settings.allowed_file_types' => 'sometimes|nullable|array',
            'settings.allowed_file_types.*' => 'sometimes|string|max:10',
            'settings.require_approval_for_deletion' => 'sometimes|nullable|boolean',
            'settings.enable_version_control' => 'sometimes|nullable|boolean',
            'settings.enable_notifications' => 'sometimes|nullable|boolean',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la empresa es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'name.unique' => 'Ya existe una empresa con este nombre.',
            'description.string' => 'La descripción debe ser una cadena de texto.',
            'description.max' => 'La descripción no puede exceder los 1000 caracteres.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.max' => 'El email no puede exceder los 255 caracteres.',
            'email.unique' => 'Ya existe una empresa con este email.',
            'phone.string' => 'El teléfono debe ser una cadena de texto.',
            'phone.max' => 'El teléfono no puede exceder los 20 caracteres.',
            'address.string' => 'La dirección debe ser una cadena de texto.',
            'address.max' => 'La dirección no puede exceder los 500 caracteres.',
            'website.url' => 'El sitio web debe ser una URL válida.',
            'website.max' => 'El sitio web no puede exceder los 255 caracteres.',
            'logo.image' => 'El logo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser un archivo de tipo: jpeg, png, jpg, gif, svg.',
            'logo.max' => 'El logo no puede ser mayor a 2MB.',
            'timezone.string' => 'La zona horaria debe ser una cadena de texto.',
            'timezone.max' => 'La zona horaria no puede exceder los 50 caracteres.',
            'language.string' => 'El idioma debe ser una cadena de texto.',
            'language.size' => 'El idioma debe tener exactamente 2 caracteres.',
            'language.in' => 'El idioma debe ser es (español) o en (inglés).',
            'currency.string' => 'La moneda debe ser una cadena de texto.',
            'currency.size' => 'La moneda debe tener exactamente 3 caracteres.',
            'date_format.string' => 'El formato de fecha debe ser una cadena de texto.',
            'date_format.max' => 'El formato de fecha no puede exceder los 20 caracteres.',
            'time_format.string' => 'El formato de hora debe ser una cadena de texto.',
            'time_format.max' => 'El formato de hora no puede exceder los 20 caracteres.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'settings.array' => 'La configuración debe ser un objeto.',
            'settings.document_retention_days.integer' => 'Los días de retención deben ser un número entero.',
            'settings.document_retention_days.min' => 'Los días de retención deben ser al menos 1.',
            'settings.document_retention_days.max' => 'Los días de retención no pueden exceder 3650 días (10 años).',
            'settings.max_file_size_mb.integer' => 'El tamaño máximo de archivo debe ser un número entero.',
            'settings.max_file_size_mb.min' => 'El tamaño máximo de archivo debe ser al menos 1MB.',
            'settings.max_file_size_mb.max' => 'El tamaño máximo de archivo no puede exceder 100MB.',
            'settings.allowed_file_types.array' => 'Los tipos de archivo permitidos deben ser una lista.',
            'settings.allowed_file_types.*.string' => 'Cada tipo de archivo debe ser una cadena de texto.',
            'settings.allowed_file_types.*.max' => 'Cada tipo de archivo no puede exceder los 10 caracteres.',
            'settings.require_approval_for_deletion.boolean' => 'Requerir aprobación para eliminación debe ser verdadero o falso.',
            'settings.enable_version_control.boolean' => 'Habilitar control de versiones debe ser verdadero o falso.',
            'settings.enable_notifications.boolean' => 'Habilitar notificaciones debe ser verdadero o falso.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values for settings if not provided
        if ($this->has('settings') && is_array($this->settings)) {
            $defaultSettings = [
                'document_retention_days' => 365,
                'max_file_size_mb' => 10,
                'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png'],
                'require_approval_for_deletion' => true,
                'enable_version_control' => true,
                'enable_notifications' => true,
            ];
            
            $settings = array_merge($defaultSettings, $this->settings);
            $this->merge(['settings' => $settings]);
        }
    }
}