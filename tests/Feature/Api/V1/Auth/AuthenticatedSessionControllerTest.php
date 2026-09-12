<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('issues a token for correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ]);

    $response = $this->postJson(route('api.v1.login'), [
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
        'device_name' => 'iPhone da Ana',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'ana@exemplo.com')
        // The hash is what gets stored; the plain token is returned once.
        ->assertJsonMissingPath('user.password');

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($user->tokens()->sole()->name)->toBe('iPhone da Ana');
});

test('accepts the token it issued on an authenticated route', function () {
    User::factory()->create([
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ]);

    $token = $this->postJson(route('api.v1.login'), [
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ])->json('token');

    $this->withToken($token)
        ->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertJsonPath('data.email', 'ana@exemplo.com');
});

test('names the token "api" when no device name is sent', function () {
    $user = User::factory()->create([
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ]);

    $this->postJson(route('api.v1.login'), [
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ])->assertCreated();

    expect($user->tokens()->sole()->name)->toBe('api');
});

test('finds the user whatever case the email is typed in', function () {
    User::factory()->create([
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ]);

    $this->postJson(route('api.v1.login'), [
        'email' => '  Ana@Exemplo.COM ',
        'password' => 'senha-secreta',
    ])->assertCreated();
});

test('returns 422 and issues no token for a wrong password', function () {
    $user = User::factory()->create([
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ]);

    $this->postJson(route('api.v1.login'), [
        'email' => 'ana@exemplo.com',
        'password' => 'senha-errada',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.');

    expect($user->tokens()->count())->toBe(0);
});

test('gives an unknown email the same message as a wrong password', function () {
    $this->postJson(route('api.v1.login'), [
        'email' => 'ninguem@exemplo.com',
        'password' => 'senha-secreta',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.');
});

test('returns 422 when email or password is missing', function () {
    $this->postJson(route('api.v1.login'), [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Informe seu e-mail.')
        ->assertJsonPath('errors.password.0', 'Informe sua senha.');
});

test('locks out after five wrong passwords', function () {
    User::factory()->create([
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ]);

    foreach (range(1, 5) as $ignored) {
        $this->postJson(route('api.v1.login'), [
            'email' => 'ana@exemplo.com',
            'password' => 'senha-errada',
        ])->assertUnprocessable();
    }

    // The right password no longer helps: the throttle is keyed by email and
    // IP, not by whether this particular attempt would have succeeded.
    $this->postJson(route('api.v1.login'), [
        'email' => 'ana@exemplo.com',
        'password' => 'senha-secreta',
    ])->assertUnprocessable()
        ->assertJsonPath(
            'errors.email.0',
            fn (string $message): bool => str_starts_with($message, 'Muitas tentativas de login.'),
        );
});

test('revokes only the token the logout was made with', function () {
    $user = User::factory()->create();
    $user->createToken('outro dispositivo');
    $revoke = $user->createToken('este dispositivo')->plainTextToken;

    $this->withToken($revoke)
        ->postJson(route('api.v1.logout'))
        ->assertOk()
        ->assertJsonPath('message', 'Sessão encerrada.');

    // Signing out of one device leaves the others signed in.
    expect($user->tokens()->pluck('name')->all())->toBe(['outro dispositivo']);
});

test('returns 401 for a token that was never issued', function () {
    $this->withToken('1|naoexiste')
        ->getJson(route('api.v1.user.show'))
        ->assertUnauthorized();
});

test('returns 401 when logging out without a token', function () {
    $this->postJson(route('api.v1.logout'))->assertUnauthorized();
});

test('returns the user the token belongs to', function () {
    $user = User::factory()->create(['name' => 'Ana']);

    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.user.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Ana')
        ->assertJsonMissingPath('data.password');
});

test('returns 401 for the current user without a token', function () {
    $this->getJson(route('api.v1.user.show'))->assertUnauthorized();
});
