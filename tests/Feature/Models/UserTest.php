<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('stores the email lowercased when created', function () {
    $user = User::create([
        'name' => 'Ana Souza',
        'email' => 'Ana@Exemplo.COM',
        'password' => 'senha-super-secreta',
    ]);

    expect($user->refresh()->email)->toBe('ana@exemplo.com');
});

test('stores the email lowercased when updated', function () {
    $user = User::factory()->create(['email' => 'ana@exemplo.com']);

    $user->update(['email' => 'NOVA@Exemplo.COM']);

    expect($user->refresh()->email)->toBe('nova@exemplo.com');
});

test('stores the email lowercased when the factory is given one', function () {
    $user = User::factory()->create(['email' => 'Ana@Exemplo.COM']);

    expect($user->refresh()->email)->toBe('ana@exemplo.com');
});

test('trims surrounding whitespace from the email', function () {
    $user = User::factory()->create(['email' => '  ana@exemplo.com  ']);

    expect($user->refresh()->email)->toBe('ana@exemplo.com');
});

test('the unique index rejects a case variant of an existing email', function () {
    User::factory()->create(['email' => 'ana@exemplo.com']);

    expect(fn () => User::factory()->create(['email' => 'ANA@EXEMPLO.COM']))
        ->toThrow(UniqueConstraintViolationException::class);
});
