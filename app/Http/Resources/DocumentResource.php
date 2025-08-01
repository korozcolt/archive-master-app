<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
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
            'document_number' => $this->document_number,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'barcode' => $this->barcode,
            'qr_code' => $this->qr_code,
            'version' => $this->version,
            'is_archived' => $this->is_archived,
            'archived_at' => $this->archived_at,
            'due_date' => $this->due_date,
            'priority' => $this->priority,
            'confidentiality_level' => $this->confidentiality_level,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'company' => new CompanyResource($this->whenLoaded('company')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'status' => new StatusResource($this->whenLoaded('status')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'workflow_history' => WorkflowHistoryResource::collection($this->whenLoaded('workflowHistory')),
        ];
    }
}