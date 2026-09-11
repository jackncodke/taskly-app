<?php

namespace App\Http\Controllers;

use App\Models\TaskAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
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
            // Images render in the page; everything else downloads rather than
            // being interpreted by the browser. `nosniff` stops a file whose
            // contents disagree with its recorded type from being re-guessed
            // into something executable.
            ['X-Content-Type-Options' => 'nosniff'],
            $attachment->isImage() ? 'inline' : 'attachment',
        );
    }

    /**
     * Delete a single attachment from a task.
     */
    public function destroy(TaskAttachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        $attachment->delete();

        return back();
    }
}
