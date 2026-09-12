<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAttachmentsRequest;
use App\Http\Resources\TaskAttachmentResource;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    /**
     * List the files attached to one of the authenticated user's tasks.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TaskAttachmentResource::collection($task->attachments);
    }

    /**
     * Attach files to one of the authenticated user's tasks.
     *
     * Uploading is its own endpoint rather than a field on the task update, so
     * a client can add a file without resubmitting the task.
     */
    public function store(StoreAttachmentsRequest $request, Task $task): JsonResponse
    {
        // Attaching a file is a change to the task, so it takes the same
        // permission as editing one.
        Gate::authorize('update', $task);

        $attachments = array_map(
            $task->attachUploadedFile(...),
            $request->file('attachments'),
        );

        return TaskAttachmentResource::collection($attachments)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Stream an attachment to its owner.
     *
     * Files live on the private disk and are served through this action so the
     * policy runs on every read. Putting them on the public disk would make the
     * URL itself the only secret, which defeats the per-user isolation the rest
     * of the application enforces.
     */
    public function show(TaskAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            // Images render inline; everything else downloads rather than being
            // interpreted by the browser. `nosniff` stops a file whose contents
            // disagree with its recorded type from being re-guessed into
            // something executable.
            ['X-Content-Type-Options' => 'nosniff'],
            $attachment->isImage() ? 'inline' : 'attachment',
        );
    }

    /**
     * Delete a single attachment from a task.
     */
    public function destroy(TaskAttachment $attachment): Response
    {
        Gate::authorize('delete', $attachment);

        $attachment->delete();

        return response()->noContent();
    }
}
