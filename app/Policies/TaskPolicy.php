<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Determine whether the user can read the task.
     *
     * Reading and writing take the same check: a task is private to the owner
     * of its project either way.
     */
    public function view(User $user, Task $task): Response
    {
        return $this->update($user, $task);
    }

    /**
     * Determine whether the user can update the task.
     *
     * A task is reachable only through its project, so ownership of the
     * project is the whole of the check. Denied as "not found" so a failed
     * attempt cannot be used to probe which task ids exist.
     */
    public function update(User $user, Task $task): Response
    {
        return $user->id === $task->project->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): Response
    {
        return $this->update($user, $task);
    }
}
