<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

test('stores a new order for the project tasks', function () {
    $project = Project::factory()->create();
    [$first, $second, $third] = Task::factory()->count(3)->for($project)->create();

    $this->actingAs($project->owner)
        ->patch(route('tasks.order', $project), [
            'tasks' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect();

    expect($third->refresh()->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(2);
});

test('the dashboard lists the tasks in the stored order', function () {
    $project = Project::factory()->create();
    [$first, $second, $third] = Task::factory()->count(3)->for($project)->create();

    $this->actingAs($project->owner)
        ->patch(route('tasks.order', $project), [
            'tasks' => [$third->id, $first->id, $second->id],
        ]);

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('tasks.0.id', $third->id)
            ->where('tasks.1.id', $first->id)
            ->where('tasks.2.id', $second->id)
        );
});

test('the order survives a later edit of the task', function () {
    $project = Project::factory()->create();
    [$first, $second] = Task::factory()->count(2)->for($project)->create();

    $this->actingAs($project->owner)
        ->patch(route('tasks.order', $project), [
            'tasks' => [$second->id, $first->id],
        ]);

    $this->actingAs($project->owner)
        ->patch("/tasks/{$second->id}", ['title' => 'Outro título']);

    expect($second->refresh()->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1);
});

test('a new task goes to the top and pushes the others down', function () {
    $project = Project::factory()->create();
    $existing = Task::factory()->for($project)->create();

    $this->actingAs($project->owner)
        ->post("/projects/{$project->id}/tasks", ['title' => 'Recém-criada']);

    $created = $project->tasks()->where('title', 'Recém-criada')->sole();

    expect($created->position)->toBe(0)
        ->and($existing->refresh()->position)->toBe(1);
});

test('rejects a list that omits one of the tasks', function () {
    $project = Project::factory()->create();
    [$first, $second] = Task::factory()->count(2)->for($project)->create();

    $this->actingAs($project->owner)
        ->patch(route('tasks.order', $project), [
            'tasks' => [$second->id, $first->id],
        ]);

    $orderBefore = $project->tasks()->orderBy('position')->pluck('id')->all();

    $this->actingAs($project->owner)
        ->patch(route('tasks.order', $project), ['tasks' => [$second->id]])
        ->assertInvalid('tasks');

    expect($project->tasks()->orderBy('position')->pluck('id')->all())
        ->toBe($orderBefore);
});

test('rejects a list containing a task from another project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $own = Task::factory()->for($project)->create();
    $foreign = Task::factory()->for(
        Project::factory()->for($user, 'owner')
    )->create();

    $this->actingAs($user)
        ->patch(route('tasks.order', $project), [
            'tasks' => [$foreign->id, $own->id],
        ])
        ->assertInvalid('tasks');

    expect($foreign->refresh()->position)->toBe(0);
});

test('returns 404 when reordering a project owned by someone else', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('tasks.order', $project), ['tasks' => [$task->id]])
        ->assertNotFound();
});

test('redirects a guest to the login screen', function () {
    $project = Project::factory()->create();

    $this->patch(route('tasks.order', $project), ['tasks' => [1]])
        ->assertRedirect(route('login'));
});
