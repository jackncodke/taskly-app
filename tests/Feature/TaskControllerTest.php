<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('store', function () {
    test('creates a task under the given project', function () {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post("/projects/{$project->id}/tasks", [
                'title' => 'Revisar contrato',
                'short_description' => 'Cláusulas 4 e 7',
                'description' => "Conferir prazos\ne multas.",
                'due_at' => '2026-10-01T14:30',
                'tags' => 'jurídico, urgente',
            ])
            ->assertRedirect();

        $task = $project->tasks()->sole();

        expect($task->title)->toBe('Revisar contrato')
            ->and($task->short_description)->toBe('Cláusulas 4 e 7')
            ->and($task->description)->toBe("Conferir prazos\ne multas.")
            ->and($task->due_at->format('Y-m-d H:i'))->toBe('2026-10-01 14:30')
            ->and($task->tags)->toBe(['jurídico', 'urgente']);
    });

    test('requires a title, both descriptions and a deadline', function () {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post("/projects/{$project->id}/tasks", ['title' => 'Sem detalhes'])
            ->assertInvalid(['short_description', 'description', 'due_at']);

        expect($project->tasks()->count())->toBe(0);
    });

    test('leaves tags optional', function () {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post("/projects/{$project->id}/tasks", taskPayload())
            ->assertValid();

        expect($project->tasks()->sole()->tags)->toBe([]);
    });

    test('requires a title', function () {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post("/projects/{$project->id}/tasks", taskPayload(['title' => '']))
            ->assertInvalid('title');

        expect($project->tasks()->count())->toBe(0);
    });

    test('drops blank and duplicated tags', function () {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post("/projects/{$project->id}/tasks", taskPayload([
                'tags' => ' bug , , bug ,ui,',
            ]));

        expect($project->tasks()->sole()->tags)->toBe(['bug', 'ui']);
    });

    test('stores uploaded attachments on the private disk', function () {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post("/projects/{$project->id}/tasks", taskPayload([
                'attachments' => [UploadedFile::fake()->image('foto.png')],
            ]))
            ->assertRedirect();

        $attachment = $project->tasks()->sole()->attachments()->sole();

        expect($attachment->original_name)->toBe('foto.png')
            ->and($attachment->disk)->toBe('local')
            ->and($attachment->mime_type)->toStartWith('image/');

        Storage::disk('local')->assertExists($attachment->path);
    });

    test('does not store a file of a disallowed type', function () {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post("/projects/{$project->id}/tasks", [
                'title' => 'Tarefa',
                'attachments' => [UploadedFile::fake()->create('script.php', 8)],
            ])
            ->assertInvalid('attachments.0');

        expect(TaskAttachment::count())->toBe(0);
    });

    test('returns 404 when adding a task to a project owned by someone else', function () {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/projects/{$project->id}/tasks", taskPayload(['title' => 'Invasao']))
            ->assertNotFound();

        expect($project->tasks()->count())->toBe(0);
    });

    test('redirects a guest to the login screen', function () {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/tasks", taskPayload())
            ->assertRedirect(route('login'));
    });
});

describe('update', function () {
    test('edits every field of an own task', function () {
        $task = Task::factory()->create();

        $this->actingAs($task->project->owner)
            ->patch("/tasks/{$task->id}", [
                'title' => 'Novo título',
                'short_description' => 'Novo resumo',
                'description' => 'Nova descrição completa',
                'due_at' => '2027-01-15T09:00',
                'tags' => 'depois',
            ])
            ->assertRedirect();

        $task->refresh();

        expect($task->title)->toBe('Novo título')
            ->and($task->short_description)->toBe('Novo resumo')
            ->and($task->description)->toBe('Nova descrição completa')
            ->and($task->due_at->format('Y-m-d H:i'))->toBe('2027-01-15 09:00')
            ->and($task->tags)->toBe(['depois']);
    });

    test('rejects an update that empties a required field', function () {
        $task = Task::factory()->create();
        $before = $task->only(['short_description', 'description']);

        $this->actingAs($task->project->owner)
            ->patch("/tasks/{$task->id}", [
                'title' => 'Só o título',
                'short_description' => '',
                'description' => '',
                'due_at' => '',
                'tags' => '',
            ])
            ->assertInvalid(['short_description', 'description', 'due_at']);

        expect($task->refresh()->only(['short_description', 'description']))
            ->toBe($before);
    });

    test('still lets an update empty the tags', function () {
        $task = Task::factory()->create();

        $this->actingAs($task->project->owner)
            ->patch("/tasks/{$task->id}", taskPayload(['tags' => '']))
            ->assertValid();

        expect($task->refresh()->tags)->toBe([]);
    });

    test('adds new attachments without removing the existing ones', function () {
        Storage::fake('local');
        $task = Task::factory()->create();
        $existing = TaskAttachment::factory()->for($task)->create();

        $this->actingAs($task->project->owner)
            ->patch("/tasks/{$task->id}", taskPayload([
                'attachments' => [
                    UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf'),
                ],
            ]))
            ->assertRedirect();

        expect($task->attachments()->count())->toBe(2)
            ->and($task->attachments()->whereKey($existing->id)->exists())->toBeTrue();
    });

    test('requires a title when updating', function () {
        $task = Task::factory()->create();

        $this->actingAs($task->project->owner)
            ->patch("/tasks/{$task->id}", taskPayload(['title' => '']))
            ->assertInvalid('title');
    });

    test('returns 404 when updating a task owned by someone else', function () {
        $task = Task::factory()->create(['title' => 'Original']);

        $this->actingAs(User::factory()->create())
            ->patch("/tasks/{$task->id}", taskPayload(['title' => 'Invadida']))
            ->assertNotFound();

        expect($task->refresh()->title)->toBe('Original');
    });
});

describe('destroy', function () {
    test('deletes an own task and its attachment files', function () {
        Storage::fake('local');
        $task = Task::factory()->create();
        $attachment = TaskAttachment::factory()->for($task)->create();
        Storage::disk('local')->put($attachment->path, 'conteudo');

        $this->actingAs($task->project->owner)
            ->delete("/tasks/{$task->id}")
            ->assertRedirect();

        expect(Task::whereKey($task->id)->exists())->toBeFalse()
            ->and(TaskAttachment::whereKey($attachment->id)->exists())->toBeFalse();

        Storage::disk('local')->assertMissing($attachment->path);
    });

    test('returns 404 when deleting a task owned by someone else', function () {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete("/tasks/{$task->id}")
            ->assertNotFound();

        expect(Task::whereKey($task->id)->exists())->toBeTrue();
    });

    test('removes the tasks and their files when the project is deleted', function () {
        Storage::fake('local');
        $task = Task::factory()->create();
        $attachment = TaskAttachment::factory()->for($task)->create();
        Storage::disk('local')->put($attachment->path, 'conteudo');

        $this->actingAs($task->project->owner)
            ->delete("/projects/{$task->project_id}")
            ->assertRedirect();

        expect(Task::whereKey($task->id)->exists())->toBeFalse();
        Storage::disk('local')->assertMissing($attachment->path);
    });
});
