<?php

namespace App\Http\Resources;

use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskAttachment
 */
class TaskAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `url` points at the API's own download route, which runs the policy on
     * every read. The stored `path` and `disk` are deliberately left out: they
     * are internal, and a client has no use for a location it cannot reach.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'is_image' => $this->isImage(),
            'url' => route('api.v1.attachments.show', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
