<?php

use App\Models\Project;
use App\Models\User;

test('the application runs in brazilian portuguese', function () {
    expect(config('app.locale'))->toBe('pt_BR')
        ->and(config('app.fallback_locale'))->toBe('pt_BR');
});

test('every translation the framework ships is available in portuguese', function () {
    // The fallback locale is pt_BR too, so a key missing from these files has
    // nothing to fall back to and would be shown to the user raw, as
    // "validation.string". Compared against the framework's own list so a
    // Laravel upgrade that adds a rule fails here instead of in production.
    foreach (['validation', 'auth', 'passwords', 'pagination'] as $file) {
        $shipped = require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php");
        $translated = require lang_path("pt_BR/{$file}.php");

        expect(array_keys($translated))
            ->toContain(...array_keys($shipped));

        foreach ($shipped as $key => $value) {
            if (is_array($value) && $key !== 'custom' && $key !== 'attributes') {
                expect(array_keys($translated[$key]))
                    ->toContain(...array_keys($value));
            }
        }
    }
});

test('no translated message is left in english', function () {
    $translated = require lang_path('pt_BR/validation.php');

    // A handful of rules the framework words identically in both languages
    // would be false positives, so this looks for the giveaway opening.
    foreach (['string', 'required', 'email', 'max', 'min', 'boolean'] as $rule) {
        $message = $translated[$rule];

        expect(is_array($message) ? implode(' ', $message) : $message)
            ->not->toContain('The :attribute');
    }
});

test('a validation failure reads in portuguese', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->owner)
        ->post(route('tasks.store', $project), ['title' => ['nao e um texto']])
        ->assertInvalid(['title' => 'O campo título deve ser um texto.']);
});

test('a failed login reads in portuguese', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'senha-errada',
    ])->assertInvalid(['email' => 'E-mail ou senha incorretos.']);
});

test('the not found page reads in portuguese', function () {
    // Someone else's project is denied as "not found", so this is the error
    // page users actually reach.
    $project = Project::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('projects.show', $project))
        ->assertNotFound()
        ->assertSee('Página não encontrada');
});
