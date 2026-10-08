<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'ricordami' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => 'nome utente',
        ];
    }

    /**
     * Verifica le credenziali e apre la sessione.
     *
     * @throws ValidationException
     */
    public function autentica(): void
    {
        $this->verificaLimiteTentativi();

        $credenziali = [
            'username' => $this->usernameNormalizzato(),
            'password' => $this->string('password')->value(),
        ];

        if (! Auth::attempt($credenziali, $this->boolean('ricordami'))) {
            RateLimiter::hit($this->chiaveLimite(), config('sicurezza.login.blocco_secondi'));

            // Messaggio volutamente generico: non rivela se il nome utente esiste.
            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->chiaveLimite());
    }

    /**
     * @throws ValidationException
     */
    private function verificaLimiteTentativi(): void
    {
        if (! RateLimiter::tooManyAttempts($this->chiaveLimite(), config('sicurezza.login.tentativi_massimi'))) {
            return;
        }

        event(new Lockout($this));

        $secondi = RateLimiter::availableIn($this->chiaveLimite());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $secondi,
                'minutes' => (int) ceil($secondi / 60),
            ]),
        ]);
    }

    private function usernameNormalizzato(): string
    {
        return Str::lower(trim($this->string('username')->value()));
    }

    private function chiaveLimite(): string
    {
        return Str::transliterate($this->usernameNormalizzato().'|'.$this->ip());
    }
}
