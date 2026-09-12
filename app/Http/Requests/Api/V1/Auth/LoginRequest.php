<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The API's login, which issues a token instead of starting a session.
 *
 * Deliberately not an extension of the form's LoginRequest: that one signs the
 * visitor in through the session guard and regenerates the session id, neither
 * of which exists on a stateless route.
 *
 * The throttle key is built the same way as the form's on purpose, so the two
 * doors share one bucket of attempts per email and IP. Moving the guessing to
 * the API would otherwise hand an attacker a second allowance.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the email before validating it.
     *
     * The User model stores emails canonicalized, so the credential lookup has
     * to search for the same form or a user who types "Ana@Exemplo.com" cannot
     * sign in. It also keeps the throttle key stable across spellings.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower($this->string('email')->trim()->value()),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `device_name` names the token so a client can tell its own tokens apart
     * when listing or revoking them.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Get the validation messages for the defined rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe sua senha.',
        ];
    }

    /**
     * The name to give the issued token.
     */
    public function deviceName(): string
    {
        $name = $this->string('device_name')->trim()->value();

        return $name === '' ? 'api' : $name;
    }

    /**
     * Find the user the request's credentials belong to.
     *
     * @throws ValidationException
     */
    public function retrieveUser(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->string('email')->value())->first();

        // One message for both "no such user" and "wrong password", so the
        // response cannot be used to find out which emails are registered.
        if ($user === null || ! Hash::check($this->string('password')->value(), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha incorretos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), maxAttempts: 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas de login. Tente novamente em {$seconds} segundos.",
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Keyed by email and IP together so that a single attacker cannot lock a
     * victim out of their own account by guessing against their email alone.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('email')->value()).'|'.$this->ip()
        );
    }
}
