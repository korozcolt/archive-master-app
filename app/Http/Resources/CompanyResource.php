<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'logo' => $this->logo,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'tax_id' => $this->tax_id,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'statuses' => StatusResource::collection($this->whenLoaded('statuses')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            
            // Statistics (when requested)
            'users_count' => $this->when(isset($this->users_count), $this->users_count),
            'documents_count' => $this->when(isset($this->documents_count), $this->documents_count),
            'branches_count' => $this->when(isset($this->branches_count), $this->branches_count),
        ];
    }
}