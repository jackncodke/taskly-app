<?php

use App\Models\User;

/**
 * A real bearer token for a new user.
 *
 * Deliberately not `Sanctum::actingAs()`: that helper also makes sanctum the
 * default guard, which would hide the thing these tests are about — the
 * throttle runs before the route's auth middleware and has to name the sanctum
 * guard itself to see who is calling.
 */
function tokenFor(?User $user = null): string
{
    return ($user ?? User::factory()->create())->createToken('api')->plainTextToken;
}

test('throttles the api routes and says how much is left', function () {
    $this->withToken(tokenFor())
        ->getJson(route('api.v1.projects.index'))
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 60)
        ->assertHeader('X-RateLimit-Remaining', 59);
});

test('gives each user their own allowance', function () {
    $first = tokenFor();
    $second = tokenFor();

    $this->withToken($first)->getJson(route('api.v1.projects.index'))->assertOk();

    // The guard caches whoever it resolved first, and a test reuses one
    // application across both requests where production would build a fresh
    // one per request. Without this the second token would be read as the
    // first user and the assertion below would pass for the wrong reason.
    $this->app['auth']->forgetGuards();

    // Keyed by the user, not the address both requests share.
    $this->withToken($second)
        ->getJson(route('api.v1.projects.index'))
        ->assertOk()
        ->assertHeader('X-RateLimit-Remaining', 59);
});

test('returns 429 once the allowance is spent', function () {
    $token = tokenFor();

    foreach (range(1, 60) as $ignored) {
        $this->withToken($token)->getJson(route('api.v1.projects.index'))->assertOk();
    }

    $this->withToken($token)
        ->getJson(route('api.v1.projects.index'))
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('throttles an unauthenticated call by its address', function () {
    $this->getJson(route('api.v1.statuses.index'))
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 60)
        ->assertHeader('X-RateLimit-Remaining', 59);
});
