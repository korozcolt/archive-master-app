<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateDocumentRequest extends FormRequest
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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'status_id' => 'nullable|exists:statuses,id',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'confidentiality_level' => 'nullable|in:public,internal,confidential,restricted',
            'metadata' => 'nullable|array',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'is_archived' => 'nullable|boolean',
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
            'due_date.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'priority.in' => 'La prioridad debe ser: low, medium, high o urgent.',
            'confidentiality_level.in' => 'El nivel de confidencialidad debe ser: public, internal, confidential o restricted.',
            'file.mimes' => 'El archivo debe ser de tipo: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, jpg, jpeg, png, gif.',
            'tags.*.exists' => 'Una o más etiquetas seleccionadas no existen.',
        ];
    }
}
