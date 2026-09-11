<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

describe('create', function () {
    test('renders the registration screen for guests', function () {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/register'));
    });

    test('redirects an authenticated user to the dashboard', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    });
});

describe('store', function () {
    test('creates the user, logs them in and redirects to the dashboard', function () {
        Event::fake([Registered::class]);

        $response = $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@exemplo.com',
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'senha-super-secreta',
        ]);

        $user = User::firstWhere('email', 'ana@exemplo.com');

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Ana Souza')
            ->and(Hash::check('senha-super-secreta', $user->password))->toBeTrue();

        Event::assertDispatched(Registered::class);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    });

    test('issues a remember cookie when the user asks to stay connected', function () {
        $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@exemplo.com',
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'senha-super-secreta',
            'remember' => '1',
        ])->assertCookie(Auth::guard()->getRecallerName());
    });

    test('does not issue a remember cookie when the box is unchecked', function () {
        $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@exemplo.com',
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'senha-super-secreta',
        ])->assertCookieMissing(Auth::guard()->getRecallerName());
    });

    test('requires a name, an email and a password', function () {
        $this->post(route('register.store'), [])->assertInvalid([
            'name' => 'Informe seu nome.',
            'email' => 'Informe seu e-mail.',
            'password' => 'Informe uma senha.',
        ]);

        expect(User::count())->toBe(0);
    });

    test('rejects an email that is already registered', function () {
        $existing = User::factory()->create();

        $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => $existing->email,
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'senha-super-secreta',
        ])->assertInvalid(['email' => 'Este e-mail já está cadastrado.']);

        expect(User::count())->toBe(1);
        $this->assertGuest();
    });

    test('rejects a password that does not match its confirmation', function () {
        $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@exemplo.com',
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'outra-senha',
        ])->assertInvalid(['password' => 'A confirmação da senha não confere.']);

        expect(User::count())->toBe(0);
    });

    test('rejects a password shorter than eight characters', function () {
        $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@exemplo.com',
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])->assertInvalid(['password' => 'A senha deve ter no mínimo 8 caracteres.']);

        expect(User::count())->toBe(0);
    });

    test('does not let a request set attributes beyond the registration fields', function () {
        $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@exemplo.com',
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'senha-super-secreta',
            'email_verified_at' => now()->toDateTimeString(),
        ]);

        expect(User::firstWhere('email', 'ana@exemplo.com')->email_verified_at)->toBeNull();
    });

    test('rejects an email that is already registered in a different case', function () {
        User::factory()->create(['email' => 'ana@exemplo.com']);

        $this->post(route('register.store'), [
            'name' => 'Outra Ana',
            'email' => 'Ana@Exemplo.COM',
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'senha-super-secreta',
        ])->assertInvalid(['email' => 'Este e-mail já está cadastrado.']);

        expect(User::count())->toBe(1);
        $this->assertGuest();
    });

    test('stores the email lowercased and trimmed', function () {
        $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => '  Ana@Exemplo.COM  ',
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'senha-super-secreta',
        ]);

        expect(User::value('email'))->toBe('ana@exemplo.com');
    });

    test('reports a duplicate email that is inserted between validation and insert', function () {
        User::creating(function (User $user) {
            // Simulate a concurrent registration winning the race.
            User::withoutEvents(fn () => User::factory()->create(['email' => $user->email]));
        });

        // No query may follow the violation: Postgres aborts the surrounding
        // RefreshDatabase transaction, so the assertion stops at the response.
        $this->post(route('register.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@exemplo.com',
            'password' => 'senha-super-secreta',
            'password_confirmation' => 'senha-super-secreta',
        ])->assertInvalid(['email' => 'Este e-mail já está cadastrado.']);
    });
});
