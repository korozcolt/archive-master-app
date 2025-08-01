<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Status::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('statuses')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at')
            ],
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_final' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
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
            'name.required' => 'El nombre del estado es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'name.unique' => 'Ya existe un estado con este nombre en su empresa.',
            'description.string' => 'La descripción debe ser una cadena de texto.',
            'description.max' => 'La descripción no puede exceder los 1000 caracteres.',
            'color.string' => 'El color debe ser una cadena de texto.',
            'color.regex' => 'El color debe ser un código hexadecimal válido (ej: #FF0000).',
            'icon.string' => 'El icono debe ser una cadena de texto.',
            'icon.max' => 'El icono no puede exceder los 50 caracteres.',
            'sort_order.integer' => 'El orden debe ser un número entero.',
            'sort_order.min' => 'El orden debe ser mayor o igual a 0.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'is_final.boolean' => 'El estado final debe ser verdadero o falso.',
            'requires_approval.boolean' => 'El campo requiere aprobación debe ser verdadero o falso.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'is_active' => $this->is_active ?? true,
            'is_final' => $this->is_final ?? false,
            'requires_approval' => $this->requires_approval ?? false,
            'sort_order' => $this->sort_order ?? 0,
        ]);
    }

    /**
     * Get the validated data from the request with company_id.
     *
     * @return array
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        
        // Add company_id from authenticated user
        $validated['company_id'] = $this->user()->company_id;
        
        return $validated;
    }
}