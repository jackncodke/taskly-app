<?php

use App\Models\Project;
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
