<?php

namespace App\Models;

use App\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $position
 * @property string $title
 * @property string|null $short_description
 * @property string|null $description
 * @property TaskStatus $status
 * @property Carbon|null $due_at
 * @property list<string> $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'short_description', 'description', 'status', 'due_at', 'tags'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * The column carries this same default, which still covers any writer that
     * bypasses Eloquent. Repeating it here means a task holds a real
     * TaskStatus from the moment it is made, instead of null until the row is
     * read back — which is what a just-created task serialized straight into a
     * response would otherwise carry.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => TaskStatus::NotStarted->value,
    ];

    /**
     * Delete the attachments through the model so their files leave the disk.
     *
     * The foreign key cascade would remove the rows without ever loading a
     * model, so the uploaded files would survive as orphans nobody can reach.
     */
    protected static function booted(): void
    {
        static::deleting(function (Task $task): void {
            $task->attachments->each->delete();
        });
    }

    /**
     * The project the task belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The files attached to the task.
     *
     * @return HasMany<TaskAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Move an uploaded file onto the private disk and record it.
     *
     * `store()` names the file from a hash rather than from what the visitor
     * called it, so a crafted filename cannot escape the directory. The
     * original name is kept as data, for display and for the download header.
     */
    public function attachUploadedFile(UploadedFile $file): TaskAttachment
    {
        return $this->attachments()->create([
            'disk' => 'local',
            'path' => $file->store("task-attachments/{$this->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            // Guessed from the file's contents, not from the header the client
            // sent, which it can set to anything.
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Move a list of uploaded files onto the private disk and record them.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function attachUploadedFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->attachUploadedFile($file);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'status' => TaskStatus::class,
            'tags' => 'array',
        ];
    }
}
