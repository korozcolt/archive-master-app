<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'position' => $this->position,
            'phone' => $this->phone,
            'profile_photo' => $this->profile_photo,
            'language' => $this->language,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at,
            'email_verified_at' => $this->email_verified_at,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'company' => new CompanyResource($this->whenLoaded('company')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->pluck('name');
            }),
            
            // Statistics (when requested)
            'documents_count' => $this->when(isset($this->documents_count), $this->documents_count),
            'assigned_documents_count' => $this->when(isset($this->assigned_documents_count), $this->assigned_documents_count),
        ];
    }
}