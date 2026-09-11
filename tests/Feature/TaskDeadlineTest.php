<?php

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Carbon;

test('rejects a new task whose deadline is in the past', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->owner)
        ->post(route('tasks.store', $project), taskPayload([
            'title' => 'Tarefa atrasada',
            'due_at' => now()->subMinute()->format('Y-m-d\TH:i'),
        ]))
        ->assertInvalid('due_at');

    expect($project->tasks()->count())->toBe(0);
});

test('accepts a new task whose deadline is in the future', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->owner)
        ->post(route('tasks.store', $project), taskPayload([
            'title' => 'Tarefa futura',
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]))
        ->assertValid();

    expect($project->tasks()->count())->toBe(1);
});

test('accepts the current minute, which is what the picker offers', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-11 14:30:45'));

    $project = Project::factory()->create();

    $this->actingAs($project->owner)
        ->post(route('tasks.store', $project), taskPayload([
            'title' => 'Para agora',
            // The input has no seconds, so its earliest choice is 14:30 —
            // which is already behind a comparison against 14:30:45.
            'due_at' => '2026-09-11T14:30',
        ]))
        ->assertValid();
});

test('requires a deadline', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->owner)
        ->post(route('tasks.store', $project), taskPayload(['due_at' => '']))
        ->assertInvalid('due_at');

    expect($project->tasks()->count())->toBe(0);
});

test('rejects moving an existing deadline into the past', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create([
        'due_at' => now()->addWeek(),
    ]);

    $this->actingAs($project->owner)
        ->patch(route('tasks.update', $task), taskPayload([
            'due_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]))
        ->assertInvalid('due_at');

    expect($task->refresh()->due_at->toDateTimeString())
        ->not->toBe(now()->subDay()->startOfMinute()->toDateTimeString());
});

test('an already overdue task can still be edited without touching its deadline', function () {
    $project = Project::factory()->create();
    $overdue = now()->subWeek()->startOfMinute();
    $task = Task::factory()->for($project)->create(['due_at' => $overdue]);

    // A deadline slips into the past on its own, so keeping it must not block
    // every other edit the task needs.
    $this->actingAs($project->owner)
        ->patch(route('tasks.update', $task), taskPayload([
            'title' => 'Título corrigido',
            'due_at' => $overdue->format('Y-m-d\TH:i'),
        ]))
        ->assertValid();

    expect($task->refresh()->title)->toBe('Título corrigido')
        ->and($task->due_at->toDateTimeString())->toBe($overdue->toDateTimeString());
});

test('an overdue task cannot have its deadline cleared either', function () {
    $project = Project::factory()->create();
    $overdue = now()->subWeek()->startOfMinute();
    $task = Task::factory()->for($project)->create(['due_at' => $overdue]);

    // Keeping the stored deadline is allowed, but the field is required, so
    // emptying it is not a way around that.
    $this->actingAs($project->owner)
        ->patch(route('tasks.update', $task), taskPayload(['due_at' => '']))
        ->assertInvalid('due_at');

    expect($task->refresh()->due_at->toDateTimeString())
        ->toBe($overdue->toDateTimeString());
});

test('an overdue task cannot be given a different past deadline', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create(['due_at' => now()->subWeek()]);

    // Keeping the stored deadline is allowed; swapping it for another past one
    // is still setting a deadline in the past.
    $this->actingAs($project->owner)
        ->patch(route('tasks.update', $task), taskPayload([
            'due_at' => now()->subDays(2)->format('Y-m-d\TH:i'),
        ]))
        ->assertInvalid('due_at');
});

test('the form is told the earliest deadline it may offer', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-11 14:30:45'));

    $project = Project::factory()->create();

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('now', '2026-09-11T14:30'));
});
