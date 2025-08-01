<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'title' => $this->title,
            'description' => $this->description,
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'changes_summary' => $this->changes_summary,
            'is_current' => $this->is_current,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            'document' => new DocumentResource($this->whenLoaded('document')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
        ];
    }
}