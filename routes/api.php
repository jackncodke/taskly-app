<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskAttachmentController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskOrderController;
use App\Http\Controllers\Api\V1\TaskPositionController;
use App\Http\Controllers\Api\V1\TaskStatusController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| The REST face of the same application the Inertia pages serve. Both share
| the models, the policies and most of the form requests; what differs is the
| representation — JSON resources instead of Inertia props — and the
| authentication, which is a Sanctum token rather than a session cookie.
|
| Versioned from the first release so a later breaking change can ship as v2
| beside this one rather than in place of it.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('register', [RegisteredUserController::class, 'store'])->name('register');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login');

    Route::get('statuses', [TaskStatusController::class, 'index'])->name('statuses.index');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('user', [UserController::class, 'show'])->name('user.show');

        Route::apiResource('projects', ProjectController::class);

        // Tasks are listed and created under the project that owns them, and
        // addressed directly once they exist: an id is enough to find one, and
        // repeating the project in the path would only invite the two to
        // disagree.
        Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks.index');
        Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');

        Route::apiResource('tasks', TaskController::class)->only(['show', 'update', 'destroy']);

        // The order of a project's tasks, replaced outright.
        Route::put('projects/{project}/task-order', [TaskOrderController::class, 'update'])->name('projects.task-order.update');

        // Where one card sits on the board: its column and its place in it,
        // written together. Scoped so a task from another project is a 404
        // rather than something the permutation rule has to catch.
        Route::put('projects/{project}/tasks/{task}/position', [TaskPositionController::class, 'update'])
            ->scopeBindings()
            ->name('projects.tasks.position.update');

        Route::get('tasks/{task}/attachments', [TaskAttachmentController::class, 'index'])->name('tasks.attachments.index');
        Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
        Route::get('attachments/{attachment}', [TaskAttachmentController::class, 'show'])->name('attachments.show');
        Route::delete('attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('attachments.destroy');
    });
});
