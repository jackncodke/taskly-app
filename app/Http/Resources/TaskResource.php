<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Dates go out as ISO 8601 rather than in the display format the Inertia
     * pages use: an API client needs an unambiguous instant it can parse and
     * render in its own locale, not a string already formatted for pt-BR.
     *
     * The status is sent as an object so a client can show the label without
     * having to keep its own copy of the translations, while still switching
     * on the stable `value`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'position' => $this->position,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'due_at' => $this->due_at?->toIso8601String(),
            'tags' => $this->tags ?? [],
            'attachments' => TaskAttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
