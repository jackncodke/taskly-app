<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;

test('renders the dashboard for an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

test('redirects a guest to the login screen', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('lists the projects belonging to the authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create([
        'description' => 'Reformar o site',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $project->id)
            ->where('projects.0.description', 'Reformar o site')
        );
});

test('does not list projects belonging to another user', function () {
    $user = User::factory()->create();
    Project::factory()->for(User::factory(), 'owner')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('projects', 0));
});

test('lists the most recently created project first', function () {
    $user = User::factory()->create();
    $older = Project::factory()->for($user, 'owner')
        ->create(['created_at' => now()->subDay()]);
    $newer = Project::factory()->for($user, 'owner')
        ->create(['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.id', $newer->id)
            ->where('projects.1.id', $older->id)
        );
});

test('sends no selected project and no tasks until one is chosen', function () {
    $user = User::factory()->create();
    Task::factory()->for(Project::factory()->for($user, 'owner'))->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedProject', null)
            ->has('tasks', 0)
        );
});

test('lists the tasks of the selected project', function () {
    $project = Project::factory()->create(['description' => 'Reformar o site']);
    $task = Task::factory()->for($project)->create([
        'title' => 'Trocar o logotipo',
        'short_description' => 'Versao vetorial',
        'due_at' => '2026-12-24 18:30:00',
        'tags' => ['design'],
    ]);

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('selectedProject.id', $project->id)
            ->where('selectedProject.description', 'Reformar o site')
            ->has('tasks', 1)
            ->where('tasks.0.id', $task->id)
            ->where('tasks.0.title', 'Trocar o logotipo')
            ->where('tasks.0.short_description', 'Versao vetorial')
            // Sent in the format a `datetime-local` input accepts, plus a
            // separate display string.
            ->where('tasks.0.due_at', '2026-12-24T18:30')
            ->where('tasks.0.due_at_label', '24/12/2026 18:30')
            ->where('tasks.0.tags', ['design'])
        );
});

test('does not list tasks of another project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    Task::factory()->for(Project::factory()->for($user, 'owner'))->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->has('tasks', 0));
});

test('returns 404 when opening a project owned by someone else', function () {
    $project = Project::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('projects.show', $project))
        ->assertNotFound();
});

test('exposes each attachment through an authorized url', function () {
    $task = Task::factory()->create();
    $attachment = TaskAttachment::factory()->for($task)->create([
        'original_name' => 'planta.png',
    ]);

    $this->actingAs($task->project->owner)
        ->get(route('projects.show', $task->project))
        ->assertInertia(fn ($page) => $page
            ->where('tasks.0.attachments.0.name', 'planta.png')
            ->where('tasks.0.attachments.0.is_image', true)
            ->where('tasks.0.attachments.0.url', route('attachments.show', $attachment))
        );
});

test('lists the most recently created task first', function () {
    $project = Project::factory()->create();
    $older = Task::factory()->for($project)->create(['created_at' => now()->subDay()]);
    $newer = Task::factory()->for($project)->create(['created_at' => now()]);

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('tasks.0.id', $newer->id)
            ->where('tasks.1.id', $older->id)
        );
});
