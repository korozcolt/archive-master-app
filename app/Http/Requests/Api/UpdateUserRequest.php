<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')->id;
        
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'password' => ['nullable', 'string', Password::min(8)->letters()->numbers()],
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
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'department_id.exists' => 'El departamento seleccionado no existe.',
            'language.in' => 'El idioma debe ser es o en.',
            'roles.*.exists' => 'Uno o más roles seleccionados no existen.',
        ];
    }
}