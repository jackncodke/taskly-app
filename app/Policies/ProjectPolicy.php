<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Determine whether the user can open the project and read its tasks.
     */
    public function view(User $user, Project $project): Response
    {
        return $this->update($user, $project);
    }

    /**
     * Determine whether the user can update the project.
     *
     * Denied as "not found" so a failed attempt cannot be used to probe which
     * project ids exist.
     */
    public function update(User $user, Project $project): Response
    {
        return $user->id === $project->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): Response
    {
        return $this->update($user, $project);
    }
}
