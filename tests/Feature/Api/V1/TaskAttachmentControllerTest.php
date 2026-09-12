<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * A task belonging to the given user, through a project they own.
 */
function ownedTask(User $user): Task
{
    return Task::factory()->for(Project::factory()->for($user, 'owner'))->create();
}

describe('index', function () {
    test('lists the files attached to an own task', function () {
        $user = User::factory()->create();
        $task = ownedTask($user);
        $attachment = TaskAttachment::factory()->for($task)->create(['original_name' => 'planta.png']);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.tasks.attachments.index', $task))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attachment->id)
            ->assertJsonPath('data.0.name', 'planta.png')
            ->assertJsonMissingPath('data.0.path')
            ->assertJsonMissingPath('data.0.disk');
    });

    test('returns 404 for a task owned by someone else', function () {
        $task = ownedTask(User::factory()->create());
        TaskAttachment::factory()->for($task)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.tasks.attachments.index', $task))->assertNotFound();
    });
});

describe('store', function () {
    test('attaches files to an own task', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $task = ownedTask($user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [
                UploadedFile::fake()->image('planta.png'),
                UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'planta.png')
            ->assertJsonPath('data.0.is_image', true)
            ->assertJsonPath('data.1.name', 'contrato.pdf')
            ->assertJsonPath('data.1.is_image', false);

        expect($task->attachments()->count())->toBe(2);

        foreach ($task->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
        }
    });

    test('keeps the original name as data rather than as the stored path', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $task = ownedTask($user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [UploadedFile::fake()->image('../../escapar.png')],
        ])->assertCreated();

        $attachment = TaskAttachment::sole();

        // The path is named from a hash, so a crafted filename cannot climb out
        // of the task's directory.
        expect($attachment->path)->toStartWith("task-attachments/{$task->id}/")
            ->and($attachment->path)->not->toContain('..');
    });

    test('returns 422 without any file', function () {
        $user = User::factory()->create();
        $task = ownedTask($user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.attachments.0', 'Envie ao menos um arquivo.');

        expect(TaskAttachment::count())->toBe(0);
    });

    test('rejects a file type that is not allowed', function () {
        $user = User::factory()->create();
        $task = ownedTask($user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [UploadedFile::fake()->create('script.php', 10, 'application/x-php')],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachments.0' => 'Formato de arquivo não permitido.']);

        expect(TaskAttachment::count())->toBe(0);
    });

    test('rejects a file over 10 MB', function () {
        $user = User::factory()->create();
        $task = ownedTask($user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [UploadedFile::fake()->create('grande.pdf', 10_241, 'application/pdf')],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachments.0' => 'Cada arquivo deve ter no máximo 10 MB.']);

        expect(TaskAttachment::count())->toBe(0);
    });

    test('returns 404 for a task owned by someone else', function () {
        Storage::fake('local');

        $task = ownedTask(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [UploadedFile::fake()->image('planta.png')],
        ])->assertNotFound();

        expect(TaskAttachment::count())->toBe(0);
    });
});

describe('show', function () {
    test('streams an own attachment inline when it is an image', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $task = ownedTask($user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [UploadedFile::fake()->image('planta.png')],
        ])->assertCreated();

        $this->get(route('api.v1.attachments.show', TaskAttachment::sole()))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('content-disposition', 'inline; filename=planta.png');
    });

    test('makes a non-image download instead of rendering', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $task = ownedTask($user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf')],
        ])->assertCreated();

        $this->get(route('api.v1.attachments.show', TaskAttachment::sole()))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=contrato.pdf');
    });

    test('returns 404 for an attachment owned by someone else', function () {
        $task = ownedTask(User::factory()->create());
        $attachment = TaskAttachment::factory()->for($task)->create();

        Sanctum::actingAs(User::factory()->create());

        // A guessed id must not reveal that somebody else's file exists.
        $this->getJson(route('api.v1.attachments.show', $attachment))->assertNotFound();
    });

    test('returns 401 without a token', function () {
        $attachment = TaskAttachment::factory()->for(ownedTask(User::factory()->create()))->create();

        $this->getJson(route('api.v1.attachments.show', $attachment))->assertUnauthorized();
    });
});

describe('destroy', function () {
    test('deletes an own attachment and takes its file off the disk', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $task = ownedTask($user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [UploadedFile::fake()->image('planta.png')],
        ])->assertCreated();

        $attachment = TaskAttachment::sole();
        $path = $attachment->path;

        $this->deleteJson(route('api.v1.attachments.destroy', $attachment))->assertNoContent();

        expect(TaskAttachment::count())->toBe(0);
        Storage::disk('local')->assertMissing($path);
    });

    test('leaves the task itself alone', function () {
        $user = User::factory()->create();
        $task = ownedTask($user);
        $attachment = TaskAttachment::factory()->for($task)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.attachments.destroy', $attachment))->assertNoContent();

        expect($task->exists())->toBeTrue();
    });

    test('returns 404 for an attachment owned by someone else', function () {
        $attachment = TaskAttachment::factory()->for(ownedTask(User::factory()->create()))->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.attachments.destroy', $attachment))->assertNotFound();

        expect(TaskAttachment::count())->toBe(1);
    });
});
