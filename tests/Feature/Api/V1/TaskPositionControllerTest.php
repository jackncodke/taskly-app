<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use Laravel\Sanctum\Sanctum;

test('moves a task to another status and stores the order together', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $moved = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create(['position' => 0]);
    $other = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create(['position' => 1]);

    Sanctum::actingAs($user);

    $this->putJson(route('api.v1.projects.tasks.position.update', [$project, $moved]), [
        'status' => 'in_progress',
        'tasks' => [$other->id, $moved->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $moved->id)
        ->assertJsonPath('data.status.value', 'in_progress')
        ->assertJsonPath('data.position', 1);

    expect($moved->refresh()->status)->toBe(TaskStatus::InProgress)
        ->and($moved->position)->toBe(1)
        ->and($other->refresh()->position)->toBe(0);
});

test('returns 422 for an unknown status and moves nothing', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $moved = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create(['position' => 0]);
    $other = Task::factory()->for($project)->create(['position' => 1]);

    Sanctum::actingAs($user);

    $this->putJson(route('api.v1.projects.tasks.position.update', [$project, $moved]), [
        'status' => 'inventado',
        'tasks' => [$other->id, $moved->id],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'Status inválido.');

    expect($moved->refresh()->status)->toBe(TaskStatus::NotStarted)
        ->and($moved->position)->toBe(0);
});

test('returns 422 for a list that is not an exact permutation and moves nothing', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $moved = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create(['position' => 0]);
    Task::factory()->for($project)->create(['position' => 1]);

    Sanctum::actingAs($user);

    $this->putJson(route('api.v1.projects.tasks.position.update', [$project, $moved]), [
        'status' => 'in_progress',
        'tasks' => [$moved->id],
    ])->assertUnprocessable();

    // Neither half of the move is written: a status that saved without its new
    // position would leave the card in the right column at the wrong place.
    expect($moved->refresh()->status)->toBe(TaskStatus::NotStarted)
        ->and($moved->position)->toBe(0);
});

test('returns 404 for a task that belongs to another project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $foreign = Task::factory()
        ->for(Project::factory()->for($user, 'owner'))
        ->status(TaskStatus::NotStarted)
        ->create(['position' => 0]);

    Sanctum::actingAs($user);

    $this->putJson(route('api.v1.projects.tasks.position.update', [$project, $foreign]), [
        'status' => 'in_progress',
        'tasks' => [$foreign->id],
    ])->assertNotFound();

    expect($foreign->refresh()->status)->toBe(TaskStatus::NotStarted);
});

test('returns 404 for a project owned by someone else', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create(['position' => 0]);

    Sanctum::actingAs(User::factory()->create());

    $this->putJson(route('api.v1.projects.tasks.position.update', [$project, $task]), [
        'status' => 'in_progress',
        'tasks' => [$task->id],
    ])->assertNotFound();

    expect($task->refresh()->status)->toBe(TaskStatus::NotStarted);
});

test('returns 401 without a token', function () {
    $project = Project::factory()->for(User::factory(), 'owner')->create();
    $task = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create(['position' => 0]);

    $this->putJson(route('api.v1.projects.tasks.position.update', [$project, $task]), [
        'status' => 'in_progress',
        'tasks' => [$task->id],
    ])->assertUnauthorized();

    expect($task->refresh()->status)->toBe(TaskStatus::NotStarted);
});
