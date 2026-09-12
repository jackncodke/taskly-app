<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    /**
     * List the authenticated user's projects.
     *
     * Paginated because a list that grows without bound is the one thing an
     * API cannot take back later, and counted rather than loaded so the task
     * totals cost one query instead of one per project.
     *
     * `id` breaks ties on `created_at`, which two projects made in the same
     * second would otherwise share. Under pagination an unstable sort is not
     * merely untidy: rows shift between pages as the query runs again, so a
     * client walking the pages can see one project twice and miss another.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $request->user()
            ->projects()
            ->withCount('tasks')
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return ProjectResource::collection($projects);
    }

    /**
     * Store a new project for the authenticated user.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        // Created through the relationship so the owner comes from the token,
        // never from the request payload.
        $project = $request->user()->projects()->create($request->validated());

        return ProjectResource::make($project)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show one of the authenticated user's projects.
     */
    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return new ProjectResource($project->loadCount('tasks'));
    }

    /**
     * Update one of the authenticated user's projects.
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource($project);
    }

    /**
     * Delete one of the authenticated user's projects.
     */
    public function destroy(Project $project): Response
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }
}
