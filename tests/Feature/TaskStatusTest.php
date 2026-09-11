<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;

test('a new task starts as not started', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->owner)
        ->post("/projects/{$project->id}/tasks", ['title' => 'Nova']);

    expect($project->tasks()->sole()->status)->toBe(TaskStatus::NotStarted);
});

test('moves a task to each of the available statuses', function (TaskStatus $status) {
    $task = Task::factory()->create();

    $this->actingAs($task->project->owner)
        ->patch(route('tasks.status', $task), ['status' => $status->value])
        ->assertRedirect();

    expect($task->refresh()->status)->toBe($status);
})->with(TaskStatus::cases());

test('changing the status leaves the other fields alone', function () {
    $task = Task::factory()->create([
        'title' => 'Intocada',
        'short_description' => 'Resumo',
        'tags' => ['bug'],
    ]);

    $this->actingAs($task->project->owner)
        ->patch(route('tasks.status', $task), [
            'status' => TaskStatus::Completed->value,
        ]);

    $task->refresh();

    expect($task->title)->toBe('Intocada')
        ->and($task->short_description)->toBe('Resumo')
        ->and($task->tags)->toBe(['bug'])
        ->and($task->position)->toBe(0);
});

test('rejects a status that is not one of the four', function () {
    $task = Task::factory()->create();

    $this->actingAs($task->project->owner)
        ->patch(route('tasks.status', $task), ['status' => 'arquivada'])
        ->assertInvalid('status');

    expect($task->refresh()->status)->toBe(TaskStatus::NotStarted);
});

test('requires a status', function () {
    $task = Task::factory()->create();

    $this->actingAs($task->project->owner)
        ->patch(route('tasks.status', $task), [])
        ->assertInvalid('status');
});

test('returns 404 when changing the status of a task owned by someone else', function () {
    $task = Task::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('tasks.status', $task), [
            'status' => TaskStatus::Cancelled->value,
        ])
        ->assertNotFound();

    expect($task->refresh()->status)->toBe(TaskStatus::NotStarted);
});

test('redirects a guest to the login screen', function () {
    $task = Task::factory()->create();

    $this->patch(route('tasks.status', $task), [
        'status' => TaskStatus::Completed->value,
    ])->assertRedirect(route('login'));
});

test('the dashboard sends each task status and the options for the dropdown', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->status(TaskStatus::InProgress)->create();

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('tasks.0.status', 'in_progress')
            ->where('tasks.0.status_label', 'Em andamento')
            ->has('statuses', 4)
            ->where('statuses.0.value', 'not_started')
            ->where('statuses.0.label', 'Não iniciada')
            ->where('statuses.3.label', 'Cancelada')
        );
});
