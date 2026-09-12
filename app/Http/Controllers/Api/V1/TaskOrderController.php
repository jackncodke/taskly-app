<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderTasksRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * The order of a project's tasks, as a resource of its own.
 *
 * A PUT here replaces that order outright: the whole ordered list of ids is
 * sent rather than "this task moved to that index", so the result does not
 * depend on the client and the server agreeing about where the list started.
 */
class TaskOrderController extends Controller
{
    /**
     * Replace the order of a project's tasks.
     *
     * Returns the list as it now stands, so a client does not have to guess
     * whether its own optimistic reordering matched the stored one.
     */
    public function update(ReorderTasksRequest $request, Project $project): AnonymousResourceCollection
    {
        // Authorized in ReorderTasksRequest, which has to run before the rule
        // that reads this project's task ids.
        DB::transaction(function () use ($project, $request): void {
            $project->applyTaskOrder($request->validated('tasks'));
        });

        return TaskResource::collection(
            $project->tasks()->with('attachments')->orderBy('position')->orderByDesc('id')->get()
        );
    }
}
