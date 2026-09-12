<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('index', function () {
    test('lists only the projects of the token owner, newest first', function () {
        $user = User::factory()->create();
        $older = Project::factory()->for($user, 'owner')->create(['description' => 'Antigo']);
        $newer = Project::factory()->for($user, 'owner')->create(['description' => 'Novo']);
        Project::factory()->for(User::factory(), 'owner')->create(['description' => 'De outro']);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonMissing(['description' => 'De outro']);
    });

    test('counts the tasks of each project without listing them', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        Task::factory()->count(3)->for($project)->create();

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.index'))
            ->assertOk()
            ->assertJsonPath('data.0.tasks_count', 3)
            ->assertJsonMissingPath('data.0.tasks');
    });

    test('paginates the list', function () {
        $user = User::factory()->create();
        Project::factory()->count(3)->for($user, 'owner')->create();

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    });

    test('returns 401 without a token', function () {
        $this->getJson(route('api.v1.projects.index'))->assertUnauthorized();
    });
});

describe('store', function () {
    test('creates a project owned by the token owner', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.projects.store'), ['description' => 'Reformar o site'])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Reformar o site');

        $project = Project::sole();

        expect($project->description)->toBe('Reformar o site')
            ->and($project->user_id)->toBe($user->id);
    });

    test('ignores a user_id supplied by the request', function () {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.projects.store'), [
            'description' => 'Projeto forjado',
            'user_id' => $victim->id,
        ])->assertCreated();

        expect(Project::sole()->user_id)->toBe($user->id);
    });

    test('returns 422 without a description', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.projects.store'), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.description.0', 'Informe a descrição do projeto.');

        expect(Project::count())->toBe(0);
    });

    test('returns 401 without a token', function () {
        $this->postJson(route('api.v1.projects.store'), ['description' => 'Reformar o site'])
            ->assertUnauthorized();

        expect(Project::count())->toBe(0);
    });
});

describe('show', function () {
    test('returns an own project with its task count', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create(['description' => 'Meu']);
        Task::factory()->count(2)->for($project)->create();

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.show', $project))
            ->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.description', 'Meu')
            ->assertJsonPath('data.tasks_count', 2);
    });

    test('returns 404 for a project owned by someone else', function () {
        $project = Project::factory()->for(User::factory(), 'owner')->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.projects.show', $project))->assertNotFound();
    });
});

describe('update', function () {
    test('updates the description of an own project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create(['description' => 'Nome antigo']);

        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.projects.update', $project), ['description' => 'Nome novo'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Nome novo');

        expect($project->refresh()->description)->toBe('Nome novo');
    });

    test('returns 422 without a description', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create(['description' => 'Nome antigo']);

        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.projects.update', $project), ['description' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.description.0', 'Informe a descrição do projeto.');

        expect($project->refresh()->description)->toBe('Nome antigo');
    });

    test('returns 404 for a project owned by someone else', function () {
        $project = Project::factory()->for(User::factory(), 'owner')->create(['description' => 'Alheio']);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('api.v1.projects.update', $project), ['description' => 'Invadido'])
            ->assertNotFound();

        expect($project->refresh()->description)->toBe('Alheio');
    });
});

describe('destroy', function () {
    test('deletes an own project and returns 204', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();

        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.projects.destroy', $project))->assertNoContent();

        expect(Project::count())->toBe(0);
    });

    test('takes the project tasks with it', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        Task::factory()->count(2)->for($project)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.projects.destroy', $project))->assertNoContent();

        expect(Task::count())->toBe(0);
    });

    test('returns 404 for a project owned by someone else', function () {
        $project = Project::factory()->for(User::factory(), 'owner')->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.projects.destroy', $project))->assertNotFound();

        expect(Project::count())->toBe(1);
    });
});
