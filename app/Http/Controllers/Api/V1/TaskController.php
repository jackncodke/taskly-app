<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * List the tasks of one of the authenticated user's projects.
     *
     * `status` narrows the list to one column of the board. The ordering is
     * the one people set by dragging; `id` only breaks ties between rows that
     * have never been reordered.
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        $request->validate([
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
        ], [
            'status.enum' => 'Status inválido.',
        ]);

        $tasks = $project->tasks()
            // Eager loaded so the attachment lists cost one query instead of
            // one per task.
            ->with('attachments')
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->value()),
            )
            ->orderBy('position')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return TaskResource::collection($tasks);
    }

    /**
     * Store a new task under one of the authenticated user's projects.
     */
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        // Adding a task is a change to the project, so it takes the same
        // permission as editing one.
        Gate::authorize('update', $project);

        // Created through the relationship so the owning project comes from the
        // authorized route binding, never from the request payload.
        $task = $project->prependTask($request->safe()->except('attachments'));

        $task->attachUploadedFiles($request->file('attachments') ?? []);

        return TaskResource::make($task->load('attachments'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show one of the authenticated user's tasks.
     */
    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->load('attachments'));
    }

    /**
     * Update part of one of the authenticated user's tasks.
     *
     * A PATCH, so any subset of the fields may be sent — including `status`
     * on its own, which is what the form's separate single-field endpoint
     * exists for on the web side.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        Gate::authorize('update', $task);

        $task->update($request->safe()->except('attachments'));

        $task->attachUploadedFiles($request->file('attachments') ?? []);

        return new TaskResource($task->load('attachments'));
    }

    /**
     * Delete one of the authenticated user's tasks.
     */
    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
