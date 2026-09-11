<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A task payload with every required field filled in.
 *
 * The form requires a title, both descriptions and a deadline, so a test that
 * is not about validation still has to send all four. This keeps that noise in
 * one place, and keeps the deadline in the future, which the rules also demand.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function taskPayload(array $overrides = []): array
{
    return [
        'title' => 'Tarefa de teste',
        'short_description' => 'Um resumo de uma linha',
        'description' => 'A descrição completa da tarefa.',
        'due_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ...$overrides,
    ];
}
