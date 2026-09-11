<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    /**
     * Store a new project for the authenticated user.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        // Created through the relationship so the owner comes from the session,
        // never from the request payload.
        $request->user()->projects()->create($request->validated());

        return back();
    }

    /**
     * Update one of the authenticated user's projects.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        return back();
    }

    /**
     * Delete one of the authenticated user's projects.
     */
    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return back();
    }
}
