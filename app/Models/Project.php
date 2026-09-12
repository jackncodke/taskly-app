<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_id
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['description'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Delete the tasks through the model so their attachments leave the disk.
     *
     * The database cascade alone would drop the rows without firing the model
     * events that clean up uploaded files.
     */
    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            $project->tasks->each->delete();
        });
    }

    /**
     * The tasks that belong to the project.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Create a task at the top of the project's list.
     *
     * A new task goes first, which is where the list already put the newest
     * one before the order became something people set by hand. Shifting the
     * others and inserting happen in one transaction so a failure cannot leave
     * two tasks claiming position 0.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function prependTask(array $attributes): Task
    {
        return DB::transaction(function () use ($attributes): Task {
            $this->tasks()->increment('position');

            $task = $this->tasks()->make($attributes);
            $task->position = 0;
            $task->save();

            return $task;
        });
    }

    /**
     * Write the given ids as the project's task order.
     *
     * Each update is scoped to the relationship, so an id from another project
     * matches nothing instead of being moved.
     *
     * @param  array<int, int>  $ids
     */
    public function applyTaskOrder(array $ids): void
    {
        foreach ($ids as $position => $id) {
            $this->tasks()->whereKey($id)->update(['position' => $position]);
        }
    }

    /**
     * The user the project belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
