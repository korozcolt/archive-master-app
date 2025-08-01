<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'manager_name' => $this->manager_name,
            'budget' => $this->budget,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            'company' => new CompanyResource($this->whenLoaded('company')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}