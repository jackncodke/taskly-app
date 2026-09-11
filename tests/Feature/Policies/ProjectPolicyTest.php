<?php

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;

test('allows the owner to update and delete the project', function () {
    $owner = User::factory()->make(['id' => 1]);
    $project = Project::factory()->make(['user_id' => 1]);
    $policy = new ProjectPolicy;

    expect($policy->update($owner, $project)->allowed())->toBeTrue()
        ->and($policy->delete($owner, $project)->allowed())->toBeTrue();
});

test('denies a non-owner as not found so project ids stay unguessable', function () {
    $stranger = User::factory()->make(['id' => 2]);
    $project = Project::factory()->make(['user_id' => 1]);
    $policy = new ProjectPolicy;

    expect($policy->update($stranger, $project)->allowed())->toBeFalse()
        ->and($policy->update($stranger, $project)->status())->toBe(404)
        ->and($policy->delete($stranger, $project)->status())->toBe(404);
});
