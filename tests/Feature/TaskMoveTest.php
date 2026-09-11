<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;

test('moves a task to another status and stores the new order', function () {
    $project = Project::factory()->create();
    [$first, $second, $third] = Task::factory()->count(3)->for($project)->create([
        'status' => TaskStatus::NotStarted,
    ]);

    $this->actingAs($project->owner)
        ->patch(route('tasks.move', [$project, $third]), [
            'status' => TaskStatus::InProgress->value,
            'tasks' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect();

    expect($third->refresh()->status)->toBe(TaskStatus::InProgress)
        ->and($third->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(2);
});

test('leaves the status of the other tasks alone', function () {
    $project = Project::factory()->create();
    $moved = Task::factory()->for($project)->create(['status' => TaskStatus::NotStarted]);
    $other = Task::factory()->for($project)->create(['status' => TaskStatus::Cancelled]);

    $this->actingAs($project->owner)
        ->patch(route('tasks.move', [$project, $moved]), [
            'status' => TaskStatus::Completed->value,
            'tasks' => [$other->id, $moved->id],
        ]);

    expect($moved->refresh()->status)->toBe(TaskStatus::Completed)
        ->and($other->refresh()->status)->toBe(TaskStatus::Cancelled);
});

test('the board order follows the stored order within each status', function () {
    $project = Project::factory()->create();
    [$first, $second] = Task::factory()->count(2)->for($project)->create([
        'status' => TaskStatus::NotStarted,
    ]);

    $this->actingAs($project->owner)
        ->patch(route('tasks.move', [$project, $second]), [
            'status' => TaskStatus::NotStarted->value,
            'tasks' => [$second->id, $first->id],
        ]);

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('tasks.0.id', $second->id)
            ->where('tasks.1.id', $first->id)
        );
});

test('rejects an unknown status and leaves the task untouched', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::NotStarted]);

    $this->actingAs($project->owner)
        ->patch(route('tasks.move', [$project, $task]), [
            'status' => 'arquivada',
            'tasks' => [$task->id],
        ])
        ->assertInvalid('status');

    expect($task->refresh()->status)->toBe(TaskStatus::NotStarted);
});

test('rejects a list that is not an exact permutation of the project tasks', function () {
    $project = Project::factory()->create();
    [$first, $second] = Task::factory()->count(2)->for($project)->create([
        'status' => TaskStatus::NotStarted,
    ]);

    $this->actingAs($project->owner)
        ->patch(route('tasks.move', [$project, $first]), [
            'status' => TaskStatus::InProgress->value,
            'tasks' => [$first->id],
        ])
        ->assertInvalid('tasks');

    expect($first->refresh()->status)->toBe(TaskStatus::NotStarted)
        ->and($second->refresh()->status)->toBe(TaskStatus::NotStarted);
});

test('the status and the order are written together or not at all', function () {
    $project = Project::factory()->create();
    [$first, $second] = Task::factory()->count(2)->for($project)->create([
        'status' => TaskStatus::NotStarted,
    ]);

    $this->actingAs($project->owner)
        ->patch(route('tasks.order', $project), [
            'tasks' => [$first->id, $second->id],
        ]);

    // The rejected move must not leave the status saved without its position.
    $this->actingAs($project->owner)
        ->patch(route('tasks.move', [$project, $second]), [
            'status' => TaskStatus::Completed->value,
            'tasks' => [$second->id],
        ])
        ->assertInvalid('tasks');

    expect($second->refresh()->status)->toBe(TaskStatus::NotStarted)
        ->and($second->position)->toBe(1)
        ->and($first->refresh()->position)->toBe(0);
});

test('returns 404 for a task that belongs to another project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $own = Task::factory()->for($project)->create();
    $foreign = Task::factory()->for(
        Project::factory()->for($user, 'owner')
    )->create(['status' => TaskStatus::NotStarted]);

    $this->actingAs($user)
        ->patch(route('tasks.move', [$project, $foreign]), [
            'status' => TaskStatus::Completed->value,
            'tasks' => [$own->id],
        ])
        ->assertNotFound();

    expect($foreign->refresh()->status)->toBe(TaskStatus::NotStarted);
});

test('returns 404 when moving a task in a project owned by someone else', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::NotStarted]);

    $this->actingAs(User::factory()->create())
        ->patch(route('tasks.move', [$project, $task]), [
            'status' => TaskStatus::Completed->value,
            'tasks' => [$task->id],
        ])
        ->assertNotFound();

    expect($task->refresh()->status)->toBe(TaskStatus::NotStarted);
});

test('redirects a guest to the login screen', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();

    $this->patch(route('tasks.move', [$project, $task]), [
        'status' => TaskStatus::Completed->value,
        'tasks' => [$task->id],
    ])->assertRedirect(route('login'));
});
