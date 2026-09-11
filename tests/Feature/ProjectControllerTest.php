<?php

use App\Models\Project;
use App\Models\User;

test('creates a project owned by the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['description' => 'Reformar o site'])
        ->assertRedirect();

    $project = Project::sole();

    expect($project->description)->toBe('Reformar o site')
        ->and($project->user_id)->toBe($user->id);
});

test('ignores a user_id supplied by the request', function () {
    $user = User::factory()->create();
    $victim = User::factory()->create();

    $this->actingAs($user)->post(route('projects.store'), [
        'description' => 'Projeto forjado',
        'user_id' => $victim->id,
    ]);

    expect(Project::sole()->user_id)->toBe($user->id);
});

test('requires a description', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [])
        ->assertInvalid(['description' => 'Informe a descrição do projeto.']);

    expect(Project::count())->toBe(0);
});

test('rejects a description longer than 255 characters', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), ['description' => str_repeat('a', 256)])
        ->assertInvalid([
            'description' => 'A descrição deve ter no máximo 255 caracteres.',
        ]);

    expect(Project::count())->toBe(0);
});

test('redirects a guest to the login screen', function () {
    $this->post(route('projects.store'), ['description' => 'Reformar o site'])
        ->assertRedirect(route('login'));

    expect(Project::count())->toBe(0);
});

test('updates the description of an own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create([
        'description' => 'Nome antigo',
    ]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'description' => 'Nome novo',
        ])
        ->assertRedirect();

    expect($project->refresh()->description)->toBe('Nome novo');
});

test('requires a description when updating', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create([
        'description' => 'Nome antigo',
    ]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['description' => ''])
        ->assertInvalid(['description' => 'Informe a descrição do projeto.']);

    expect($project->refresh()->description)->toBe('Nome antigo');
});

test('returns 404 when updating a project owned by someone else', function () {
    $project = Project::factory()->for(User::factory(), 'owner')->create([
        'description' => 'Alheio',
    ]);

    $this->actingAs(User::factory()->create())
        ->patch(route('projects.update', $project), [
            'description' => 'Invadido',
        ])
        ->assertNotFound();

    expect($project->refresh()->description)->toBe('Alheio');
});

test('deletes an own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect();

    expect(Project::count())->toBe(0);
});

test('returns 404 when deleting a project owned by someone else', function () {
    $project = Project::factory()->for(User::factory(), 'owner')->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('projects.destroy', $project))
        ->assertNotFound();

    expect(Project::count())->toBe(1);
});

test('redirects a guest away from updating or deleting', function () {
    $project = Project::factory()->for(User::factory(), 'owner')->create();

    $this->patch(route('projects.update', $project), ['description' => 'X'])
        ->assertRedirect(route('login'));

    $this->delete(route('projects.destroy', $project))
        ->assertRedirect(route('login'));

    expect(Project::count())->toBe(1);
});
