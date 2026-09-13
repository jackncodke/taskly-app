<?php

use App\Achievement;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use Laravel\Sanctum\Sanctum;

/**
 * Give the user a project holding one completed task per day listed.
 *
 * Written straight onto the row rather than through a request, so a test can
 * describe a history that would otherwise take days of real time to build.
 *
 * @param  array<int, string>  $days  completion timestamps
 * @param  array<string, mixed>  $overrides  extra attributes for every task
 */
function completeTasksOn(User $user, array $days, array $overrides = []): Project
{
    $project = Project::factory()->for($user, 'owner')->create();

    foreach ($days as $day) {
        Task::factory()->for($project)->create([
            ...$overrides,
            'status' => TaskStatus::Completed,
            'completed_at' => $day,
        ]);
    }

    return $project;
}

test('stamps the completion when the status dropdown closes a task', function () {
    $task = Task::factory()->status(TaskStatus::NotStarted)->create();

    $this->actingAs($task->project->owner)
        ->patch(route('tasks.status', $task), ['status' => TaskStatus::Completed->value])
        ->assertRedirect();

    expect($task->refresh()->completed_at)->not->toBeNull();
});

test('stamps the completion when the board drops a task on completed', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create();

    $this->actingAs($project->owner)
        ->patch(route('tasks.move', [$project, $task]), [
            'status' => TaskStatus::Completed->value,
            'tasks' => [$task->id],
        ])
        ->assertRedirect();

    expect($task->refresh()->completed_at)->not->toBeNull();
});

test('stamps the completion when the api drops a task on completed', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $task = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create();

    Sanctum::actingAs($user);

    $this->putJson(route('api.v1.projects.tasks.position.update', [$project, $task]), [
        'status' => TaskStatus::Completed->value,
        'tasks' => [$task->id],
    ])->assertOk();

    expect($task->refresh()->completed_at)->not->toBeNull();
});

test('stamps the completion when the api closes a task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    $task = Task::factory()->for($project)->status(TaskStatus::NotStarted)->create();

    Sanctum::actingAs($user);

    $this->patchJson(route('api.v1.tasks.update', $task), ['status' => 'completed'])
        ->assertOk();

    expect($task->refresh()->completed_at)->not->toBeNull();
});

test('clears the completion when a task is reopened', function () {
    $task = Task::factory()->status(TaskStatus::Completed)->create();

    expect($task->completed_at)->not->toBeNull();

    $this->actingAs($task->project->owner)
        ->patch(route('tasks.status', $task), ['status' => TaskStatus::InProgress->value]);

    expect($task->refresh()->completed_at)->toBeNull();
});

test('leaves the completion alone when another field is edited', function () {
    $task = Task::factory()->status(TaskStatus::Completed)->create();
    $completedAt = $task->completed_at;

    $this->travel(1)->days();

    $this->actingAs($task->project->owner)
        ->patch("/tasks/{$task->id}", taskPayload(['title' => 'Outro título']));

    expect($task->refresh()->completed_at->toDateTimeString())
        ->toBe($completedAt->toDateTimeString());
});

test('awards ten experience a task and five more for beating the deadline', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    // Finished the day before it was due.
    Task::factory()->for($project)->create([
        'status' => TaskStatus::Completed,
        'due_at' => now()->addDay(),
        'completed_at' => now(),
    ]);

    // Finished the day after it was due.
    Task::factory()->for($project)->create([
        'status' => TaskStatus::Completed,
        'due_at' => now()->subDays(2),
        'completed_at' => now()->subDay(),
    ]);

    // Never had a deadline, so it is neither early nor late.
    Task::factory()->for($project)->create([
        'status' => TaskStatus::Completed,
        'due_at' => null,
        'completed_at' => now(),
    ]);

    expect($user->progress())
        ->completed->toBe(3)
        ->on_time->toBe(1)
        ->xp->toBe(35);
});

test('counts nothing for a task that is still open', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();
    Task::factory()->for($project)->count(3)->status(TaskStatus::InProgress)->create();

    expect($user->progress())->completed->toBe(0)->xp->toBe(0)->level->toBe(1);
});

test('reaches a level once its experience is earned', function (int $xp, int $level) {
    $user = User::factory()->create();
    completeTasksOn($user, array_fill(0, intdiv($xp, 10), now()->toDateTimeString()), [
        'due_at' => null,
    ]);

    expect($user->progress())->xp->toBe($xp)->level->toBe($level);
})->with([
    'nothing done yet' => [0, 1],
    'just short of the second' => [40, 1],
    'the second' => [50, 2],
    'the third' => [150, 3],
    'past the third' => [200, 3],
]);

test('reports how far into the level the experience is', function () {
    $user = User::factory()->create();
    // 8 tasks with no deadline: 80 XP, which is 30 of the 100 between the
    // second level at 50 and the third at 150.
    completeTasksOn($user, array_fill(0, 8, now()->toDateTimeString()), ['due_at' => null]);

    expect($user->progress())
        ->level->toBe(2)
        ->xp_into_level->toBe(30)
        ->xp_for_next_level->toBe(100)
        ->level_percent->toBe(30.0);
});

test('ignores the tasks of another user', function () {
    $user = User::factory()->create();
    completeTasksOn(User::factory()->create(), [now()->toDateTimeString()]);

    expect($user->progress())->completed->toBe(0)->streak->toBe(0);
});

test('counts the days in a row something was finished', function () {
    $user = User::factory()->create();
    completeTasksOn($user, [
        now()->toDateTimeString(),
        now()->subDay()->toDateTimeString(),
        now()->subDays(2)->toDateTimeString(),
    ]);

    expect($user->progress())->streak->toBe(3)->longest_streak->toBe(3);
});

test('counts a day once however many tasks it holds', function () {
    $user = User::factory()->create();
    completeTasksOn($user, [
        now()->setTime(9, 0)->toDateTimeString(),
        now()->setTime(14, 0)->toDateTimeString(),
        now()->setTime(21, 0)->toDateTimeString(),
    ]);

    expect($user->progress())->streak->toBe(1);
});

test('keeps the streak alive on a day with nothing finished yet', function () {
    $user = User::factory()->create();
    completeTasksOn($user, [
        now()->subDay()->toDateTimeString(),
        now()->subDays(2)->toDateTimeString(),
    ]);

    expect($user->progress())->streak->toBe(2);
});

test('ends the streak once a whole day has been missed', function () {
    $user = User::factory()->create();
    completeTasksOn($user, [
        now()->subDays(2)->toDateTimeString(),
        now()->subDays(3)->toDateTimeString(),
    ]);

    expect($user->progress())->streak->toBe(0)->longest_streak->toBe(2);
});

test('remembers the longest streak after a shorter one replaces it', function () {
    $user = User::factory()->create();
    completeTasksOn($user, [
        // Today and yesterday, then a gap, then four days in a row.
        now()->toDateTimeString(),
        now()->subDay()->toDateTimeString(),
        now()->subDays(5)->toDateTimeString(),
        now()->subDays(6)->toDateTimeString(),
        now()->subDays(7)->toDateTimeString(),
        now()->subDays(8)->toDateTimeString(),
    ]);

    expect($user->progress())->streak->toBe(2)->longest_streak->toBe(4);
});

test('hands out the first badge the moment a task is completed', function () {
    $task = Task::factory()->status(TaskStatus::NotStarted)->create();
    $user = $task->project->owner;

    expect($user->achievements()->count())->toBe(0);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => TaskStatus::Completed->value]);

    expect($user->achievements()->pluck('achievement')->all())
        ->toBe([Achievement::FirstTask]);
});

test('hands out a badge once however often it is earned again', function () {
    $task = Task::factory()->status(TaskStatus::NotStarted)->create();
    $user = $task->project->owner;

    foreach ([TaskStatus::Completed, TaskStatus::InProgress, TaskStatus::Completed] as $status) {
        $this->actingAs($user)
            ->patch(route('tasks.status', $task), ['status' => $status->value]);
    }

    expect($user->achievements()->where('achievement', Achievement::FirstTask)->count())
        ->toBe(1);
});

test('keeps a badge after the work that earned it is deleted', function () {
    $task = Task::factory()->status(TaskStatus::NotStarted)->create();
    $user = $task->project->owner;

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => TaskStatus::Completed->value]);

    $task->project->delete();

    expect($user->progress())->completed->toBe(0)
        ->and($user->achievements()->count())->toBe(1);
});

test('earns the week of focus on the seventh day in a row', function () {
    $user = User::factory()->create();
    $days = array_map(
        fn (int $day): string => now()->subDays($day + 1)->toDateTimeString(),
        range(0, 5)
    );
    completeTasksOn($user, $days);

    $user->unlockEarnedAchievements();

    expect($user->achievements()->pluck('achievement')->all())
        ->not->toContain(Achievement::WeekStreak);

    completeTasksOn($user, [now()->toDateTimeString()]);

    expect($user->refresh()->achievements()->pluck('achievement')->all())
        ->toContain(Achievement::WeekStreak);
});

test('earns the clean sweep only on a project of three tasks or more', function () {
    $user = User::factory()->create();
    completeTasksOn($user, [now()->toDateTimeString(), now()->toDateTimeString()]);

    expect($user->progress())->clean_projects->toBe(0);

    completeTasksOn($user, array_fill(0, 3, now()->toDateTimeString()));

    expect($user->progress())->clean_projects->toBe(1)
        ->and($user->achievements()->pluck('achievement')->all())
        ->toContain(Achievement::CleanSweep);
});

test('does not sweep a project that still has something open', function () {
    $user = User::factory()->create();
    $project = completeTasksOn($user, array_fill(0, 3, now()->toDateTimeString()));
    Task::factory()->for($project)->status(TaskStatus::InProgress)->create();

    expect($user->progress())->clean_projects->toBe(0);
});
