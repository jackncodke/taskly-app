<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\TaskStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

describe('index', function () {
    test('lists the tasks of an own project in the stored order', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $second = Task::factory()->for($project)->create(['position' => 1]);
        $first = Task::factory()->for($project)->create(['position' => 0]);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.tasks.index', $project))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    });

    test('narrows the list to one status', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $done = Task::factory()->for($project)->status(TaskStatus::Completed)->create();
        Task::factory()->for($project)->status(TaskStatus::NotStarted)->create();

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.tasks.index', [
            'project' => $project,
            'status' => 'completed',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $done->id);
    });

    test('returns 422 for an unknown status filter', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.tasks.index', [
            'project' => $project,
            'status' => 'inventado',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Status inválido.');
    });

    test('sends the status as a value and its label', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        Task::factory()->for($project)->status(TaskStatus::InProgress)->create();

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.tasks.index', $project))
            ->assertOk()
            ->assertJsonPath('data.0.status.value', 'in_progress')
            ->assertJsonPath('data.0.status.label', 'Em andamento');
    });

    test('includes the attachments of each task', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create();
        $attachment = TaskAttachment::factory()->for($task)->create(['original_name' => 'planta.png']);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.tasks.index', $project))
            ->assertOk()
            ->assertJsonPath('data.0.attachments.0.id', $attachment->id)
            ->assertJsonPath('data.0.attachments.0.name', 'planta.png')
            ->assertJsonPath('data.0.attachments.0.is_image', true)
            // The disk location is internal and never leaves the server.
            ->assertJsonMissingPath('data.0.attachments.0.path');
    });

    test('returns 404 for a project owned by someone else', function () {
        $project = Project::factory()->for(User::factory(), 'owner')->create();
        Task::factory()->for($project)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.projects.tasks.index', $project))->assertNotFound();
    });

    test('returns 401 without a token', function () {
        $project = Project::factory()->for(User::factory(), 'owner')->create();

        $this->getJson(route('api.v1.projects.tasks.index', $project))->assertUnauthorized();
    });
});

describe('store', function () {
    test('creates a task at the top of the project list', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $existing = Task::factory()->for($project)->create(['position' => 0]);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.projects.tasks.store', $project), taskPayload([
            'title' => 'Trocar o rodapé',
            'tags' => ['ui', 'urgente'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Trocar o rodapé')
            ->assertJsonPath('data.position', 0)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.tags', ['ui', 'urgente'])
            ->assertJsonPath('data.status.value', 'not_started');

        expect($existing->refresh()->position)->toBe(1);
    });

    test('ignores a project_id supplied by the request', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $other = Project::factory()->for($user, 'owner')->create();

        Sanctum::actingAs($user);

        $this->postJson(
            route('api.v1.projects.tasks.store', $project),
            taskPayload(['project_id' => $other->id]),
        )->assertCreated();

        expect(Task::sole()->project_id)->toBe($project->id);
    });

    test('accepts tags as a list', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();

        Sanctum::actingAs($user);

        $this->postJson(
            route('api.v1.projects.tasks.store', $project),
            taskPayload(['tags' => ['backend', 'bug']]),
        )->assertCreated();

        expect(Task::sole()->tags)->toBe(['backend', 'bug']);
    });

    test('returns 422 when required fields are missing', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.projects.tasks.store', $project), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'Informe o título da tarefa.')
            ->assertJsonPath('errors.short_description.0', 'Informe a descrição curta da tarefa.')
            ->assertJsonPath('errors.description.0', 'Informe a descrição completa da tarefa.')
            ->assertJsonPath('errors.due_at.0', 'Informe o prazo da tarefa.');

        expect(Task::count())->toBe(0);
    });

    test('rejects a deadline in the past', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();

        Sanctum::actingAs($user);

        $this->postJson(
            route('api.v1.projects.tasks.store', $project),
            taskPayload(['due_at' => now()->subDay()->toIso8601String()]),
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.due_at.0',
                'O prazo não pode ser anterior à data e hora atuais.',
            );

        expect(Task::count())->toBe(0);
    });

    test('stores uploaded attachments on the private disk', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.projects.tasks.store', $project), taskPayload([
            'attachments' => [UploadedFile::fake()->image('planta.png')],
        ]))
            ->assertCreated()
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.name', 'planta.png');

        $attachment = TaskAttachment::sole();

        expect($attachment->original_name)->toBe('planta.png')
            // Named from a hash, so a crafted filename cannot pick its path.
            ->and($attachment->path)->not->toContain('planta.png');

        Storage::disk('local')->assertExists($attachment->path);
    });

    test('returns 404 for a project owned by someone else', function () {
        $project = Project::factory()->for(User::factory(), 'owner')->create();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.projects.tasks.store', $project), taskPayload())
            ->assertNotFound();

        expect(Task::count())->toBe(0);
    });

    test('returns 401 without a token', function () {
        $project = Project::factory()->for(User::factory(), 'owner')->create();

        $this->postJson(route('api.v1.projects.tasks.store', $project), taskPayload())
            ->assertUnauthorized();

        expect(Task::count())->toBe(0);
    });
});

describe('show', function () {
    test('returns an own task with its attachments', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create(['title' => 'Trocar o rodapé']);
        TaskAttachment::factory()->for($task)->create();

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.title', 'Trocar o rodapé')
            ->assertJsonCount(1, 'data.attachments');
    });

    test('returns 404 for a task in a project owned by someone else', function () {
        $task = Task::factory()->for(Project::factory()->for(User::factory(), 'owner'))->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.tasks.show', $task))->assertNotFound();
    });

    test('returns 401 without a token', function () {
        $task = Task::factory()->for(Project::factory()->for(User::factory(), 'owner'))->create();

        $this->getJson(route('api.v1.tasks.show', $task))->assertUnauthorized();
    });
});

describe('update', function () {
    test('changes only the fields the request sends', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create([
            'title' => 'Título antigo',
            'short_description' => 'Resumo antigo',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.tasks.update', $task), ['title' => 'Título novo'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Título novo');

        expect($task->refresh()->title)->toBe('Título novo')
            ->and($task->short_description)->toBe('Resumo antigo');
    });

    test('changes the status on its own', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create();

        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.tasks.update', $task), ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'completed')
            ->assertJsonPath('data.status.label', 'Concluída');

        expect($task->refresh()->status)->toBe(TaskStatus::Completed);
    });

    test('returns 422 for an unknown status', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create();

        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.tasks.update', $task), ['status' => 'inventado'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Status inválido.');

        expect($task->refresh()->status)->toBe(TaskStatus::NotStarted);
    });

    test('rejects a field sent blank rather than wiping it', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create(['title' => 'Título antigo']);

        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.tasks.update', $task), ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'Informe o título da tarefa.');

        expect($task->refresh()->title)->toBe('Título antigo');
    });

    test('lets an overdue task be edited without picking a new deadline', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create([
            'due_at' => now()->subWeek(),
            'title' => 'Título antigo',
        ]);

        Sanctum::actingAs($user);

        // The stored deadline is resent unchanged, which the rule allows: the
        // rule is about *setting* a deadline in the past.
        $this->patchJson(route('api.v1.tasks.update', $task), [
            'title' => 'Título novo',
            'due_at' => $task->due_at->toIso8601String(),
        ])->assertOk();

        expect($task->refresh()->title)->toBe('Título novo');
    });

    test('rejects moving a deadline further into the past', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create(['due_at' => now()->subWeek()]);

        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.tasks.update', $task), [
            'due_at' => now()->subMonth()->toIso8601String(),
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.due_at.0',
                'O prazo não pode ser anterior à data e hora atuais.',
            );
    });

    test('adds uploaded files to the ones already stored', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create();
        TaskAttachment::factory()->for($task)->create();

        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.tasks.update', $task), [
            'attachments' => [UploadedFile::fake()->image('nova.png')],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data.attachments');

        expect($task->attachments()->count())->toBe(2);
    });

    test('returns 404 for a task in a project owned by someone else', function () {
        $task = Task::factory()
            ->for(Project::factory()->for(User::factory(), 'owner'))
            ->create(['title' => 'Alheio']);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('api.v1.tasks.update', $task), ['title' => 'Invadido'])
            ->assertNotFound();

        expect($task->refresh()->title)->toBe('Alheio');
    });
});

describe('destroy', function () {
    test('deletes an own task and returns 204', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.tasks.destroy', $task))->assertNoContent();

        expect(Task::count())->toBe(0);
    });

    test('takes the task attachments off the disk with it', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.tasks.attachments.store', $task), [
            'attachments' => [UploadedFile::fake()->image('planta.png')],
        ])->assertCreated();

        $path = TaskAttachment::sole()->path;

        $this->deleteJson(route('api.v1.tasks.destroy', $task))->assertNoContent();

        expect(TaskAttachment::count())->toBe(0);
        Storage::disk('local')->assertMissing($path);
    });

    test('returns 404 for a task in a project owned by someone else', function () {
        $task = Task::factory()->for(Project::factory()->for(User::factory(), 'owner'))->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.tasks.destroy', $task))->assertNotFound();

        expect(Task::count())->toBe(1);
    });
});
