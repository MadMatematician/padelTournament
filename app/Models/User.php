<?php

namespace App\Models;

use App\Enums\Ruolo;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Attributi assegnabili in massa.
     * `ruolo` è escluso di proposito: non deve mai arrivare da un form.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    /**
     * Valori predefiniti (allineati al default della colonna).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ruolo' => Ruolo::Utente->value,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ruolo' => Ruolo::class,
        ];
    }

    public function haRuolo(Ruolo ...$ruoli): bool
    {
        return in_array($this->ruolo, $ruoli, true);
    }

    public function eAmministratore(): bool
    {
        return $this->haRuolo(Ruolo::Amministratore);
    }

    /** Nome della rotta a cui l'utente atterra dopo l'accesso. */
    public function rottaIniziale(): string
    {
        return $this->ruolo->rottaIniziale();
    }
}
