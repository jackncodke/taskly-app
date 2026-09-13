<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with no project selected.
     */
    public function index(Request $request): Response
    {
        $projects = $this->projectsOf($request);

        return Inertia::render('dashboard', [
            'projects' => $projects,
            'selectedProject' => null,
            'tasks' => [],
            'statuses' => TaskStatus::options(),
            'overview' => $this->taskCountsByProject($projects),
            'alerts' => $this->overdueTasks($projects),
            'timeline' => $this->upcomingTasks($projects),
            'now' => $this->earliestDeadline(),
        ]);
    }

    /**
     * Show the dashboard with one project selected and its tasks listed.
     */
    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('dashboard', [
            'projects' => $this->projectsOf($request),
            'selectedProject' => $project->only(['id', 'description']),
            'tasks' => $project->tasks()
                // Eager loaded so the attachment list below costs one query
                // instead of one per task.
                ->with('attachments')
                // The order people set by dragging. `id` only breaks ties
                // between rows that have never been reordered.
                ->orderBy('position')
                ->orderByDesc('id')
                ->get()
                ->map($this->taskPayload(...))
                ->all(),
            'statuses' => TaskStatus::options(),
            // The panels only render with no project selected, so there is
            // nothing to count here. The keys still go out so both renders of
            // this component agree on the shape of its props.
            'overview' => [],
            'alerts' => [],
            'timeline' => ['days' => [], 'tasks' => []],
            'now' => $this->earliestDeadline(),
        ]);
    }

    /**
     * The earliest deadline the form may offer, as the `datetime-local` input
     * wants it.
     *
     * Sent from here rather than read off the browser's clock so the `min` the
     * picker enforces and the rule the server enforces are the same instant.
     */
    private function earliestDeadline(): string
    {
        return Carbon::parse(StoreTaskRequest::earliestDeadline())
            ->format('Y-m-d\TH:i');
    }

    /**
     * The sidebar project list for the signed-in user.
     *
     * @return array<int, array{id: int, description: string}>
     */
    private function projectsOf(Request $request): array
    {
        return $request->user()
            ->projects()
            ->latest()
            ->get(['id', 'description'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'description' => $project->description,
            ])
            ->all();
    }

    /**
     * The task counts per project and status behind the "Visão Geral" panel.
     *
     * One grouped query covers every project: counting in PHP would mean
     * loading every task of every project, and asking per project would grow a
     * query for each row of the panel.
     *
     * Every status of the enum is filled in, including the ones the project
     * has no task at, so the panel can render a column per status without
     * having to guess at a missing key.
     *
     * @param  array<int, array{id: int, description: string}>  $projects
     * @return array<int, array{id: int, description: string, counts: array<string, int>, total: int}>
     */
    private function taskCountsByProject(array $projects): array
    {
        $totals = Task::query()
            ->whereIn('project_id', array_column($projects, 'id'))
            ->selectRaw('project_id, status, count(*) as total')
            ->groupBy('project_id', 'status')
            ->get()
            ->groupBy('project_id');

        return array_map(function (array $project) use ($totals): array {
            $rows = $totals->get($project['id'], collect());

            $counts = [];

            foreach (TaskStatus::cases() as $status) {
                $counts[$status->value] = (int) ($rows
                    ->firstWhere('status', $status)
                    ?->getAttribute('total') ?? 0);
            }

            return [
                ...$project,
                'counts' => $counts,
                'total' => array_sum($counts),
            ];
        }, $projects);
    }

    /**
     * The overdue tasks behind the "Alertas" panel.
     *
     * Overdue means a deadline already past on a task nobody has closed: a
     * completed or a cancelled task is settled, however late it ended up, so
     * neither is an alert.
     *
     * The whole task goes out, not just the two fields the panel prints,
     * because clicking one opens the same edit form the task list opens, and
     * that form writes every field back.
     *
     * @param  array<int, array{id: int, description: string}>  $projects
     * @return array<int, array<string, mixed>>
     */
    private function overdueTasks(array $projects): array
    {
        return Task::query()
            // Eager loaded so the attachment list costs one query instead of
            // one per task.
            ->with('attachments')
            ->whereIn('project_id', array_column($projects, 'id'))
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            // A null deadline is not late; the comparison drops those rows on
            // its own.
            ->where('due_at', '<', now())
            // Most overdue first, which is the order they need attention in.
            ->orderBy('due_at')
            ->get()
            ->map(fn (Task $task): array => [
                ...$this->taskPayload($task),
                'project_id' => $task->project_id,
            ])
            ->all();
    }

    /**
     * The week ahead behind the "Timeline" panel.
     *
     * The window runs from the start of today to the end of the seventh day
     * ahead. Starting it at midnight rather than at the current instant is what
     * lets the day marks sit at even intervals, so the chart reads as eight
     * equal columns instead of a first column that is short by however late in
     * the day the page was opened.
     *
     * `offset` is where a deadline falls across that window, from 0 to 100. It
     * is worked out here, against the same clock that formats every other date
     * in this application, rather than from the visitor's browser time.
     *
     * @param  array<int, array{id: int, description: string}>  $projects
     * @return array{days: array<int, array{label: string, date_label: string, offset: float}>, tasks: array<int, array<string, mixed>>}
     */
    private function upcomingTasks(array $projects): array
    {
        $days = 8;
        $start = today();
        $end = $start->copy()->addDays($days);
        $window = $start->diffInSeconds($end);

        $marks = array_map(function (int $day) use ($start, $days): array {
            $date = $start->copy()->addDays($day);

            return [
                'label' => $day === 0 ? 'hoje' : $date->translatedFormat('D'),
                'date_label' => $date->format('d/m'),
                'offset' => round($day / $days * 100, 4),
            ];
        }, range(0, $days - 1));

        $tasks = Task::query()
            ->whereIn('project_id', array_column($projects, 'id'))
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            // From now, not from the start of the window: a deadline earlier
            // today has already passed, and belongs to the alerts panel.
            ->whereBetween('due_at', [now(), $end])
            ->orderBy('due_at')
            ->get()
            // Only what the chart draws. The whole task is not sent because
            // nothing here opens a form with it.
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'short_description' => $task->short_description,
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'due_at_label' => $task->due_at?->format('d/m/Y H:i'),
                'offset' => round($start->diffInSeconds($task->due_at) / $window * 100, 4),
            ])
            ->all();

        return ['days' => $marks, 'tasks' => $tasks];
    }

    /**
     * Shape a task for the front end.
     *
     * The deadline is sent twice on purpose: `due_at` feeds the
     * `datetime-local` input, which only accepts `Y-m-d\TH:i`, while
     * `due_at_label` is the display string. Formatting both server side keeps
     * the value in the application's timezone instead of whichever one the
     * visitor's browser happens to be in.
     *
     * @return array<string, mixed>
     */
    private function taskPayload(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'short_description' => $task->short_description,
            'description' => $task->description,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'due_at' => $task->due_at?->format('Y-m-d\TH:i'),
            'due_at_label' => $task->due_at?->format('d/m/Y H:i'),
            'tags' => $task->tags,
            'attachments' => $task->attachments
                ->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'size' => $attachment->size,
                    'is_image' => $attachment->isImage(),
                    'url' => route('attachments.show', $attachment),
                ])
                ->all(),
        ];
    }
}
