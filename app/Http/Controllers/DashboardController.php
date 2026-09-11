<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\TaskStatus;
use Illuminate\Http\Request;
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
        return Inertia::render('dashboard', [
            'projects' => $this->projectsOf($request),
            'selectedProject' => null,
            'tasks' => [],
            'statuses' => TaskStatus::options(),
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
        ]);
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
