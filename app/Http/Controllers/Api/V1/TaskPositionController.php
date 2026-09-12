<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Where a single task sits on the board: which column, and where within it.
 *
 * Both halves are one resource because dragging a card changes them together.
 * A client could instead PATCH the task's status and then PUT the project's
 * task order, but the gap between the two requests is exactly the state this
 * endpoint exists to avoid: a card in the right column at the wrong place.
 *
 * The board is a view of the same single ordering the list uses, filtered by
 * status, which is why the whole ordered list comes along here as it does for
 * the task order resource.
 */
class TaskPositionController extends Controller
{
    /**
     * Move a task to another status and store the order that follows from it.
     */
    public function update(MoveTaskRequest $request, Project $project, Task $task): TaskResource
    {
        Gate::authorize('update', $task);

        DB::transaction(function () use ($project, $request, $task): void {
            $task->update(['status' => $request->validated('status')]);

            $project->applyTaskOrder($request->validated('tasks'));
        });

        return new TaskResource($task->refresh()->load('attachments'));
    }
}
