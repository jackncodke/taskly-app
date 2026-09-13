<?php

use App\Achievement;
use App\DeadlineClock;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\TaskStatus;
use Illuminate\Support\Carbon;

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

test('counts the tasks of each project by status for the overview panel', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create([
        'description' => 'Reformar o site',
    ]);

    Task::factory()->for($project)->count(2)
        ->status(TaskStatus::NotStarted)->create();
    Task::factory()->for($project)->status(TaskStatus::InProgress)->create();
    Task::factory()->for($project)->count(3)
        ->status(TaskStatus::Completed)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('overview', 1)
            ->where('overview.0.id', $project->id)
            ->where('overview.0.description', 'Reformar o site')
            ->where('overview.0.counts.not_started', 2)
            ->where('overview.0.counts.in_progress', 1)
            ->where('overview.0.counts.completed', 3)
            // A status the project has no task at still gets a key, so the
            // panel can render a column for every status.
            ->where('overview.0.counts.cancelled', 0)
            ->where('overview.0.total', 6)
        );
});

test('does not count the tasks of another project towards a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')
        ->create(['created_at' => now()]);
    $other = Project::factory()->for($user, 'owner')
        ->create(['created_at' => now()->subDay()]);
    Task::factory()->for($other)->status(TaskStatus::Completed)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('overview', 2)
            ->where('overview.0.id', $project->id)
            ->where('overview.0.total', 0)
            ->where('overview.1.id', $other->id)
            ->where('overview.1.total', 1)
        );
});

test('does not count the tasks of a project owned by someone else', function () {
    $user = User::factory()->create();
    Task::factory()->for(Project::factory()->for(User::factory(), 'owner'))->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('overview', 0));
});

test('sends no overview once a project is selected', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create();

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->has('overview', 0));
});

test('lists overdue tasks that are still open for the alerts panel', function () {
    $project = Project::factory()->create();
    $overdue = Task::factory()->for($project)->create([
        'short_description' => 'Revisar o contrato',
        'due_at' => now()->subDay(),
    ]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.id', $overdue->id)
            ->where('alerts.0.short_description', 'Revisar o contrato')
            ->where('alerts.0.due_at_label', $overdue->due_at->format('d/m/Y H:i'))
            // The whole task goes out so clicking it can open the edit form.
            ->where('alerts.0.project_id', $project->id)
            ->has('alerts.0.attachments')
        );
});

test('does not alert about a task whose deadline has not passed', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['due_at' => now()->addDay()]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('alerts', 0));
});

test('does not alert about a deadline that has not passed on the interface clock', function () {
    // 23:00 of the 11th in São Paulo is 02:00 of the 12th in UTC, so a task
    // due at 23:30 still has half an hour where the visitor is while the
    // application's clock has it half an hour late.
    Carbon::setTestNow(Carbon::parse('2026-09-12 02:00:00'));

    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['due_at' => '2026-09-11 23:30:00']);

    $this->withUnencryptedCookie(DeadlineClock::TIMEZONE_COOKIE, 'America/Sao_Paulo')
        ->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('alerts', 0)
            // It is still ahead, so it belongs to the other panel.
            ->has('timeline.tasks', 1)
        );
});

test('starts the timeline at midnight on the interface clock', function () {
    // The application timezone has already turned the page to the 12th; the
    // visitor is still on the 11th, and "hoje" has to mean theirs.
    Carbon::setTestNow(Carbon::parse('2026-09-12 02:00:00'));

    $this->withUnencryptedCookie(DeadlineClock::TIMEZONE_COOKIE, 'America/Sao_Paulo')
        ->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('timeline.days.0.label', 'hoje')
            ->where('timeline.days.0.date_label', '11/09')
        );
});

test('does not alert about an overdue task that is completed or cancelled', function (TaskStatus $status) {
    $project = Project::factory()->create();
    Task::factory()->for($project)->status($status)->create([
        'due_at' => now()->subWeek(),
    ]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('alerts', 0));
})->with([
    'concluida' => TaskStatus::Completed,
    'cancelada' => TaskStatus::Cancelled,
]);

test('alerts about an overdue task that is not started or in progress', function (TaskStatus $status) {
    $project = Project::factory()->create();
    Task::factory()->for($project)->status($status)->create([
        'due_at' => now()->subWeek(),
    ]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('alerts', 1));
})->with([
    'nao iniciada' => TaskStatus::NotStarted,
    'em andamento' => TaskStatus::InProgress,
]);

test('does not alert about a task without a deadline', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->minimal()->create();

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('alerts', 0));
});

test('does not alert about an overdue task belonging to another user', function () {
    Task::factory()->create(['due_at' => now()->subDay()]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('alerts', 0));
});

test('lists the most overdue task first', function () {
    $project = Project::factory()->create();
    $late = Task::factory()->for($project)->create(['due_at' => now()->subDay()]);
    $later = Task::factory()->for($project)->create(['due_at' => now()->subWeek()]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('alerts.0.id', $later->id)
            ->where('alerts.1.id', $late->id)
        );
});

test('sends no alerts once a project is selected', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['due_at' => now()->subDay()]);

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->has('alerts', 0));
});

test('plots the tasks due over the next seven days on the timeline', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create([
        'short_description' => 'Enviar a proposta',
        'due_at' => today()->addDays(2)->setTime(12, 0),
    ]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('timeline.tasks', 1)
            ->where('timeline.tasks.0.id', $task->id)
            ->where('timeline.tasks.0.short_description', 'Enviar a proposta')
            ->where('timeline.tasks.0.status_label', 'Não iniciada')
            ->where('timeline.tasks.0.due_at_label', $task->due_at->format('d/m/Y H:i'))
            // Midday of the third of eight days: two whole days in, plus half
            // of the third, over a window of eight.
            ->where('timeline.tasks.0.offset', 31.25)
        );
});

test('sends a day mark for today and for each of the seven days ahead', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('timeline.days', 8)
            ->where('timeline.days.0.label', 'hoje')
            ->where('timeline.days.0.date_label', today()->format('d/m'))
            ->where('timeline.days.0.offset', 0)
            ->where('timeline.days.7.date_label', today()->addDays(7)->format('d/m'))
            ->where('timeline.days.7.offset', 87.5)
        );
});

test('does not plot a task due after the seventh day ahead', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create([
        'due_at' => today()->addDays(8)->addHour(),
    ]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('timeline.tasks', 0));
});

test('does not plot a task whose deadline has already passed', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['due_at' => now()->subHour()]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('timeline.tasks', 0));
});

test('does not plot an upcoming task that is completed or cancelled', function (TaskStatus $status) {
    $project = Project::factory()->create();
    Task::factory()->for($project)->status($status)->create([
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('timeline.tasks', 0));
})->with([
    'concluida' => TaskStatus::Completed,
    'cancelada' => TaskStatus::Cancelled,
]);

test('does not plot an upcoming task belonging to another user', function () {
    Task::factory()->create(['due_at' => now()->addDay()]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('timeline.tasks', 0));
});

test('plots the soonest deadline first', function () {
    $project = Project::factory()->create();
    $later = Task::factory()->for($project)->create(['due_at' => now()->addDays(3)]);
    $sooner = Task::factory()->for($project)->create(['due_at' => now()->addDay()]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('timeline.tasks.0.id', $sooner->id)
            ->where('timeline.tasks.1.id', $later->id)
        );
});

test('sends no timeline once a project is selected', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['due_at' => now()->addDay()]);

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->has('timeline.days', 0)
            ->has('timeline.tasks', 0)
        );
});

test('sends the level, the streak and the whole badge catalogue', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create([
        'status' => TaskStatus::Completed,
        'due_at' => now()->addDay(),
        'completed_at' => now(),
    ]);

    $this->actingAs($project->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('progress.level', 1)
            ->where('progress.xp', 15)
            ->where('progress.completed', 1)
            ->where('progress.streak', 1)
            ->has('progress.achievements', count(Achievement::cases()))
            ->where('progress.achievements.0.value', Achievement::FirstTask->value)
            ->where('progress.achievements.0.unlocked', true)
            ->where('progress.achievements.0.is_recent', true)
            ->where('progress.achievements.1.unlocked', false)
            ->where('progress.achievements.1.unlocked_at_label', null)
        );
});

test('sends no progress once a project is selected', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->where('progress', null));
});
