<?php

use App\DeadlineClock;
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

test('accepts a deadline still ahead on the interface clock though behind the application one', function () {
    // 22:30 of the 11th in São Paulo is already 01:30 of the 12th in UTC, so
    // the whole evening looks past to a rule read on the application's clock.
    Carbon::setTestNow(Carbon::parse('2026-09-12 01:30:00'));

    $project = Project::factory()->create();

    $this->withUnencryptedCookie(DeadlineClock::TIMEZONE_COOKIE, 'America/Sao_Paulo')
        ->actingAs($project->owner)
        ->post(route('tasks.store', $project), taskPayload([
            'title' => 'Ainda hoje à noite',
            'due_at' => '2026-09-11T23:00',
        ]))
        ->assertValid();

    expect($project->tasks()->count())->toBe(1);
});

test('rejects a deadline already behind on the interface clock though ahead of the application one', function () {
    // The mirror image: 14:30 UTC is 23:30 in Tokyo, so 20:00 is hours gone
    // there while it still reads as the future in UTC.
    Carbon::setTestNow(Carbon::parse('2026-09-11 14:30:00'));

    $project = Project::factory()->create();

    $this->withUnencryptedCookie(DeadlineClock::TIMEZONE_COOKIE, 'Asia/Tokyo')
        ->actingAs($project->owner)
        ->post(route('tasks.store', $project), taskPayload([
            'title' => 'Já passou em Tóquio',
            'due_at' => '2026-09-11T20:00',
        ]))
        ->assertInvalid('due_at');

    expect($project->tasks()->count())->toBe(0);
});

test('rejects moving an existing deadline into the past on the interface clock', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-11 14:30:00'));

    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create(['due_at' => '2026-09-18 10:00:00']);

    $this->withUnencryptedCookie(DeadlineClock::TIMEZONE_COOKIE, 'Asia/Tokyo')
        ->actingAs($project->owner)
        ->patch(route('tasks.update', $task), taskPayload([
            'due_at' => '2026-09-11T20:00',
        ]))
        ->assertInvalid('due_at');
});

test('falls back to the application timezone when the cookie is not a zone', function () {
    // The cookie is written by the browser, so it is visitor input: a value
    // PHP does not know as a zone must not become one.
    Carbon::setTestNow(Carbon::parse('2026-09-11 14:30:00'));

    $project = Project::factory()->create();

    $this->withUnencryptedCookie(DeadlineClock::TIMEZONE_COOKIE, 'Nowhere/Made_Up')
        ->actingAs($project->owner)
        ->post(route('tasks.store', $project), taskPayload([
            'due_at' => '2026-09-11T14:29',
        ]))
        ->assertInvalid('due_at');
});

test('the earliest deadline the form may offer is read on the interface clock', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-11 14:30:45'));

    $project = Project::factory()->create();

    $this->withUnencryptedCookie(DeadlineClock::TIMEZONE_COOKIE, 'America/Sao_Paulo')
        ->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('now', '2026-09-11T11:30'));
});
