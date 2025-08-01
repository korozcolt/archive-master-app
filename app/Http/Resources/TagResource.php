<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            'company' => new CompanyResource($this->whenLoaded('company')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            
            'documents_count' => $this->when(isset($this->documents_count), $this->documents_count),
        ];
    }
}