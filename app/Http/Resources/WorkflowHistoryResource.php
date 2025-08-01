<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'comments' => $this->comments,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            'document' => new DocumentResource($this->whenLoaded('document')),
            'user' => new UserResource($this->whenLoaded('user')),
            'from_status_details' => new StatusResource($this->whenLoaded('fromStatusDetails')),
            'to_status_details' => new StatusResource($this->whenLoaded('toStatusDetails')),
        ];
    }
}