<?php

use App\Models\User;

test('registers a user and issues their first token', function () {
    $response = $this->postJson(route('api.v1.register'), [
        'name' => 'Ana Souza',
        'email' => 'ana@exemplo.com',
        'password' => 'senha-bem-secreta',
        'password_confirmation' => 'senha-bem-secreta',
    ]);

    $user = User::sole();

    $response->assertCreated()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.name', 'Ana Souza')
        ->assertJsonMissingPath('user.password');

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($user->tokens()->sole()->name)->toBe('api')
        ->and($user->password)->not->toBe('senha-bem-secreta');
});

test('stores the email lowercased and trimmed', function () {
    $this->postJson(route('api.v1.register'), [
        'name' => 'Ana Souza',
        'email' => '  Ana@Exemplo.COM ',
        'password' => 'senha-bem-secreta',
        'password_confirmation' => 'senha-bem-secreta',
    ])->assertCreated();

    expect(User::sole()->email)->toBe('ana@exemplo.com');
});

test('returns 422 when required fields are missing', function () {
    $this->postJson(route('api.v1.register'), [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Informe seu nome.')
        ->assertJsonPath('errors.email.0', 'Informe seu e-mail.')
        ->assertJsonPath('errors.password.0', 'Informe uma senha.');

    expect(User::count())->toBe(0);
});

test('rejects an email already registered, in any case', function () {
    User::factory()->create(['email' => 'ana@exemplo.com']);

    $this->postJson(route('api.v1.register'), [
        'name' => 'Outra Ana',
        'email' => 'ANA@exemplo.com',
        'password' => 'senha-bem-secreta',
        'password_confirmation' => 'senha-bem-secreta',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Este e-mail já está cadastrado.');

    expect(User::count())->toBe(1);
});

test('rejects a password that does not match its confirmation', function () {
    $this->postJson(route('api.v1.register'), [
        'name' => 'Ana Souza',
        'email' => 'ana@exemplo.com',
        'password' => 'senha-bem-secreta',
        'password_confirmation' => 'outra-senha',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'A confirmação da senha não confere.');

    expect(User::count())->toBe(0);
});
