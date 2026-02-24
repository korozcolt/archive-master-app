<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'position' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'language' => 'nullable|string|in:es,en',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'department_id.exists' => 'El departamento seleccionado no existe.',
            'language.in' => 'El idioma debe ser es o en.',
            'roles.*.exists' => 'Uno o más roles seleccionados no existen.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        if (! $this->has('language')) {
            $this->merge(['language' => 'es']);
        }

        if (! $this->has('timezone')) {
            $this->merge(['timezone' => 'America/Bogota']);
        }

        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
