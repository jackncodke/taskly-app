<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('replaces the order of a project tasks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $first = Task::factory()->for($project)->create(['position' => 0]);
    $second = Task::factory()->for($project)->create(['position' => 1]);
    $third = Task::factory()->for($project)->create(['position' => 2]);

    Sanctum::actingAs($user);

    $this->putJson(route('api.v1.projects.task-order.update', $project), [
        'tasks' => [$third->id, $first->id, $second->id],
    ])
        ->assertOk()
        // The list comes back in the order it now stands, so a client does not
        // have to guess whether its own reordering matched the stored one.
        ->assertJsonPath('data.0.id', $third->id)
        ->assertJsonPath('data.1.id', $first->id)
        ->assertJsonPath('data.2.id', $second->id);

    expect($third->refresh()->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(2);
});

test('returns 422 for a list that is not an exact permutation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $first = Task::factory()->for($project)->create(['position' => 0]);
    Task::factory()->for($project)->create(['position' => 1]);

    Sanctum::actingAs($user);

    // A partial list would silently scramble the order of whatever it left out.
    $this->putJson(route('api.v1.projects.task-order.update', $project), [
        'tasks' => [$first->id],
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.tasks.0',
            'A lista enviada não corresponde às tarefas do projeto.',
        );

    expect($first->refresh()->position)->toBe(0);
});

test('returns 422 for a list padded with a task from another project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $own = Task::factory()->for($project)->create(['position' => 0]);
    $foreign = Task::factory()->for(Project::factory()->for($user, 'owner'))->create(['position' => 0]);

    Sanctum::actingAs($user);

    $this->putJson(route('api.v1.projects.task-order.update', $project), [
        'tasks' => [$own->id, $foreign->id],
    ])->assertUnprocessable();

    expect($foreign->refresh()->position)->toBe(0);
});

test('returns 422 without a task list', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    Sanctum::actingAs($user);

    $this->putJson(route('api.v1.projects.task-order.update', $project), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tasks');
});

test('returns 404 for a project owned by someone else', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $first = Task::factory()->for($project)->create(['position' => 0]);
    $second = Task::factory()->for($project)->create(['position' => 1]);

    Sanctum::actingAs(User::factory()->create());

    $this->putJson(route('api.v1.projects.task-order.update', $project), [
        'tasks' => [$second->id, $first->id],
    ])->assertNotFound();

    expect($first->refresh()->position)->toBe(0);
});

test('returns 401 without a token', function () {
    $project = Project::factory()->for(User::factory(), 'owner')->create();
    $task = Task::factory()->for($project)->create(['position' => 0]);

    $this->putJson(route('api.v1.projects.task-order.update', $project), [
        'tasks' => [$task->id],
    ])->assertUnauthorized();
});
