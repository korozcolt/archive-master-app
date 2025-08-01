<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'nullable|exists:statuses,id',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date|after:today',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'confidentiality_level' => 'nullable|in:public,internal,confidential,restricted',
            'metadata' => 'nullable|array',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif|max:10240', // 10MB max
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El título del documento es obligatorio.',
            'title.max' => 'El título no puede exceder 255 caracteres.',
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'status_id.exists' => 'El estado seleccionado no existe.',
            'assigned_to.exists' => 'El usuario asignado no existe.',
            'due_date.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'priority.in' => 'La prioridad debe ser: low, medium, high o urgent.',
            'confidentiality_level.in' => 'El nivel de confidencialidad debe ser: public, internal, confidential o restricted.',
            'file.mimes' => 'El archivo debe ser de tipo: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, jpg, jpeg, png, gif.',
            'file.max' => 'El archivo no puede exceder 10MB.',
            'tags.*.exists' => 'Una o más etiquetas seleccionadas no existen.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status if not provided
        if (!$this->has('status_id')) {
            $defaultStatus = \App\Models\Status::where('company_id', auth()->user()->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();
            
            if ($defaultStatus) {
                $this->merge(['status_id' => $defaultStatus->id]);
            }
        }

        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge(['priority' => 'medium']);
        }

        // Set default confidentiality level if not provided
        if (!$this->has('confidentiality_level')) {
            $this->merge(['confidentiality_level' => 'internal']);
        }
    }
}