<?php

namespace App\Policies;

use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskAttachmentPolicy
{
    /**
     * Determine whether the user can download the attachment.
     *
     * Attachments are served through the application rather than from a public
     * disk, so this is the only thing standing between a guessed id and
     * somebody else's file.
     */
    public function view(User $user, TaskAttachment $attachment): Response
    {
        return $user->id === $attachment->task->project->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the attachment.
     */
    public function delete(User $user, TaskAttachment $attachment): Response
    {
        return $this->view($user, $attachment);
    }
}
