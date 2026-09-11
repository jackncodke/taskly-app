<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * Store a new task under one of the authenticated user's projects.
     */
    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        // Adding a task is a change to the project, so it takes the same
        // permission as editing one.
        Gate::authorize('update', $project);

        // Created through the relationship so the owning project comes from the
        // authorized route binding, never from the request payload. A new task
        // goes to the top, which is where the list already put the newest one
        // before the order became something people set by hand.
        $task = DB::transaction(function () use ($project, $request): Task {
            $project->tasks()->increment('position');

            $task = $project->tasks()->make($request->safe()->except('attachments'));
            $task->position = 0;
            $task->save();

            return $task;
        });

        $this->attachFiles($task, $request->file('attachments') ?? []);

        return back();
    }

    /**
     * Update one of the authenticated user's tasks.
     *
     * Every field is editable here, including the attachment list: files sent
     * with the request are added to the ones already stored, and existing files
     * are removed one at a time through TaskAttachmentController.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update($request->safe()->except('attachments'));

        $this->attachFiles($task, $request->file('attachments') ?? []);

        return back();
    }

    /**
     * Change only the status of a task.
     *
     * Separate from `update` so the dropdown in the list can send one field.
     * Going through `update` would mean resubmitting the whole task, and its
     * required title would reject a request that only means to move the task
     * to "Concluida".
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        return back();
    }

    /**
     * Store a new order for a project's tasks.
     *
     * The whole ordered list of ids is sent rather than "this task moved to
     * that index", so the result does not depend on the client and the server
     * agreeing about where the list started.
     */
    public function reorder(ReorderTasksRequest $request, Project $project): RedirectResponse
    {
        // Authorized in ReorderTasksRequest, which has to run before the rule
        // that reads this project's task ids.
        DB::transaction(function () use ($project, $request): void {
            foreach ($request->validated('tasks') as $position => $id) {
                // Scoped to the relationship, so an id from another project
                // matches nothing instead of being moved.
                $project->tasks()->whereKey($id)->update(['position' => $position]);
            }
        });

        return back();
    }

    /**
     * Delete one of the authenticated user's tasks.
     */
    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return back();
    }

    /**
     * Move uploaded files onto the private disk and record them.
     *
     * `store()` names the file from a hash rather than from what the visitor
     * called it, so a crafted filename cannot escape the directory. The
     * original name is kept as data, for display and for the download header.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function attachFiles(Task $task, array $files): void
    {
        foreach ($files as $file) {
            $task->attachments()->create([
                'disk' => 'local',
                'path' => $file->store("task-attachments/{$task->id}", 'local'),
                'original_name' => $file->getClientOriginalName(),
                // Guessed from the file's contents, not from the header the
                // client sent, which it can set to anything.
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }
}
