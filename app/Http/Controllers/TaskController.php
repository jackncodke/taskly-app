<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\ReorderTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
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
        // authorized route binding, never from the request payload.
        $task = $project->prependTask($request->safe()->except('attachments'));

        $task->attachUploadedFiles($request->file('attachments') ?? []);

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

        $task->attachUploadedFiles($request->file('attachments') ?? []);

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
            $project->applyTaskOrder($request->validated('tasks'));
        });

        return back();
    }

    /**
     * Move a task to another status and store the order that follows from it.
     *
     * Dragging a card across the board changes two things at once, so both are
     * written in one transaction: a status that saved without its new position
     * would leave the card in the right column but the wrong place in it.
     *
     * The board is a view of the same single ordering the list uses, filtered
     * by status, which is why the whole ordered list comes along here exactly
     * as it does for `reorder`.
     */
    public function move(MoveTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        DB::transaction(function () use ($project, $request, $task): void {
            $task->update(['status' => $request->validated('status')]);

            $project->applyTaskOrder($request->validated('tasks'));
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
}
