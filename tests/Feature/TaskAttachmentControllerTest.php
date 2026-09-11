<?php

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

describe('show', function () {
    test('streams an own attachment', function () {
        Storage::fake('local');
        $attachment = TaskAttachment::factory()->create();
        Storage::disk('local')->put($attachment->path, 'bytes do arquivo');

        $response = $this->actingAs($attachment->task->project->owner)
            ->get("/attachments/{$attachment->id}");

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect($response->streamedContent())->toBe('bytes do arquivo');
    });

    test('sends a non image as a download rather than rendering it', function () {
        Storage::fake('local');
        $attachment = TaskAttachment::factory()->create([
            'mime_type' => 'application/pdf',
            'original_name' => 'nota.pdf',
        ]);
        Storage::disk('local')->put($attachment->path, '%PDF-1.4');

        $response = $this->actingAs($attachment->task->project->owner)
            ->get("/attachments/{$attachment->id}")
            ->assertOk();

        expect($response->headers->get('Content-Disposition'))
            ->toStartWith('attachment;');
    });

    test('renders an image inline', function () {
        Storage::fake('local');
        $attachment = TaskAttachment::factory()->create();
        Storage::disk('local')->put($attachment->path, 'png bytes');

        $response = $this->actingAs($attachment->task->project->owner)
            ->get("/attachments/{$attachment->id}")
            ->assertOk();

        expect($response->headers->get('Content-Disposition'))
            ->toStartWith('inline;');
    });

    test('returns 404 for an attachment belonging to someone else', function () {
        Storage::fake('local');
        $attachment = TaskAttachment::factory()->create();
        Storage::disk('local')->put($attachment->path, 'segredo');

        $this->actingAs(User::factory()->create())
            ->get("/attachments/{$attachment->id}")
            ->assertNotFound();
    });

    test('redirects a guest to the login screen', function () {
        $attachment = TaskAttachment::factory()->create();

        $this->get("/attachments/{$attachment->id}")
            ->assertRedirect(route('login'));
    });
});

describe('destroy', function () {
    test('deletes an own attachment and its file', function () {
        Storage::fake('local');
        $attachment = TaskAttachment::factory()->create();
        Storage::disk('local')->put($attachment->path, 'bytes');

        $this->actingAs($attachment->task->project->owner)
            ->delete("/attachments/{$attachment->id}")
            ->assertRedirect();

        expect(TaskAttachment::whereKey($attachment->id)->exists())->toBeFalse();
        Storage::disk('local')->assertMissing($attachment->path);
    });

    test('leaves the task itself untouched', function () {
        Storage::fake('local');
        $task = Task::factory()->create();
        $attachment = TaskAttachment::factory()->for($task)->create();

        $this->actingAs($task->project->owner)
            ->delete("/attachments/{$attachment->id}");

        expect(Task::whereKey($task->id)->exists())->toBeTrue();
    });

    test('returns 404 when deleting an attachment belonging to someone else', function () {
        Storage::fake('local');
        $attachment = TaskAttachment::factory()->create();
        Storage::disk('local')->put($attachment->path, 'bytes');

        $this->actingAs(User::factory()->create())
            ->delete("/attachments/{$attachment->id}")
            ->assertNotFound();

        expect(TaskAttachment::whereKey($attachment->id)->exists())->toBeTrue();
        Storage::disk('local')->assertExists($attachment->path);
    });
});
