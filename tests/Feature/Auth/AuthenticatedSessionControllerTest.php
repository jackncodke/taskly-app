<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

describe('create', function () {
    test('renders the login screen for guests', function () {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/login'));
    });

    test('redirects an authenticated user to the dashboard', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    });
});

describe('store', function () {
    test('authenticates the user and redirects to the dashboard', function () {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    });

    test('authenticates when the email is typed in a different case', function () {
        $user = User::factory()->create(['email' => 'ana@exemplo.com']);

        $this->post(route('login.store'), [
            'email' => '  Ana@Exemplo.COM  ',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    });

    test('issues a remember cookie when the user asks to stay connected', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertCookie(Auth::guard()->getRecallerName());
    });

    test('does not issue a remember cookie when the box is unchecked', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertCookieMissing(Auth::guard()->getRecallerName());
    });

    test('rejects an incorrect password with a credentials error', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'senha-errada',
        ])->assertInvalid(['email' => 'E-mail ou senha incorretos.']);

        $this->assertGuest();
    });

    test('rejects an email that is not registered', function () {
        $this->post(route('login.store'), [
            'email' => 'ninguem@exemplo.com',
            'password' => 'password',
        ])->assertInvalid(['email' => 'E-mail ou senha incorretos.']);

        $this->assertGuest();
    });

    test('requires an email and a password', function () {
        $this->post(route('login.store'), [])->assertInvalid([
            'email' => 'Informe seu e-mail.',
            'password' => 'Informe sua senha.',
        ]);
    });

    test('rejects a malformed email address', function () {
        $this->post(route('login.store'), [
            'email' => 'nao-e-um-email',
            'password' => 'password',
        ])->assertInvalid(['email' => 'Informe um e-mail válido.']);
    });

    test('locks the account out after five failed attempts', function () {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'senha-errada',
            ])->assertInvalid(['email' => 'E-mail ou senha incorretos.']);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertInvalid('email');

        expect(session('errors')->first('email'))
            ->toStartWith('Muitas tentativas de login.');

        $this->assertGuest();
    });

    test('does not lock out a different email from the same address', function () {
        $target = User::factory()->create();
        $other = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $target->email,
                'password' => 'senha-errada',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => $other->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($other);
    });
});

describe('destroy', function () {
    test('logs the user out and redirects to the login screen', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });

    test('redirects a guest to the login screen', function () {
        $this->post(route('logout'))->assertRedirect(route('login'));
    });
});
