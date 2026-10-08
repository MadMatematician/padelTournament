<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistrazioneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nome utente ed email si confrontano sempre in minuscolo.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => Str::lower(trim((string) $this->input('username'))),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:'.config('sicurezza.username.lunghezza_minima'),
                'max:'.config('sicurezza.username.lunghezza_massima'),
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique(User::class, 'username'),
            ],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome e cognome',
            'username' => 'nome utente',
            'email' => 'email',
            'password' => 'password',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Il nome utente può contenere solo lettere, numeri, punto, trattino e trattino basso, e deve iniziare e finire con una lettera o un numero.',
        ];
    }
}
