<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');
        return $this->user()->can('update', $category);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($category->id)
                    ->whereNull('deleted_at')
            ],
            'description' => 'sometimes|nullable|string|max:1000',
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'not_in:' . $category->id, // Prevent self-reference
                Rule::exists('categories', 'id')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($category) {
                    if ($value && $this->wouldCreateCircularReference($category, $value)) {
                        $fail('No se puede establecer esta categoría como padre porque crearía una referencia circular.');
                    }
                },
            ],
            'color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'sometimes|nullable|string|max:50',
            'sort_order' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|nullable|boolean',
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
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'name.unique' => 'Ya existe una categoría con este nombre en su empresa.',
            'description.string' => 'La descripción debe ser una cadena de texto.',
            'description.max' => 'La descripción no puede exceder los 1000 caracteres.',
            'parent_id.integer' => 'El ID de la categoría padre debe ser un número entero.',
            'parent_id.not_in' => 'Una categoría no puede ser padre de sí misma.',
            'parent_id.exists' => 'La categoría padre seleccionada no existe o no pertenece a su empresa.',
            'color.string' => 'El color debe ser una cadena de texto.',
            'color.regex' => 'El color debe ser un código hexadecimal válido (ej: #FF0000).',
            'icon.string' => 'El icono debe ser una cadena de texto.',
            'icon.max' => 'El icono no puede exceder los 50 caracteres.',
            'sort_order.integer' => 'El orden debe ser un número entero.',
            'sort_order.min' => 'El orden debe ser mayor o igual a 0.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }

    /**
     * Check if setting the parent would create a circular reference.
     *
     * @param \App\Models\Category $category
     * @param int $parentId
     * @return bool
     */
    private function wouldCreateCircularReference($category, $parentId): bool
    {
        // Get all descendant IDs of the current category
        $descendantIds = $this->getDescendantIds($category->id);
        
        // If the proposed parent is a descendant, it would create a circular reference
        return in_array($parentId, $descendantIds);
    }

    /**
     * Get all descendant category IDs.
     *
     * @param int $categoryId
     * @return array
     */
    private function getDescendantIds($categoryId): array
    {
        $descendants = [];
        $children = \App\Models\Category::where('parent_id', $categoryId)
            ->where('company_id', $this->user()->company_id)
            ->pluck('id')
            ->toArray();
        
        foreach ($children as $childId) {
            $descendants[] = $childId;
            $descendants = array_merge($descendants, $this->getDescendantIds($childId));
        }
        
        return $descendants;
    }
}