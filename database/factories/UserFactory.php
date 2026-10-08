<?php

namespace Database\Factories;

use App\Enums\Ruolo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Password in uso dalla factory (calcolata una sola volta).
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'utente'.fake()->unique()->numberBetween(1000, 999999),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'ruolo' => Ruolo::Utente,
            'remember_token' => Str::random(10),
        ];
    }

    public function amministratore(): static
    {
        return $this->state(fn (array $attributes) => [
            'ruolo' => Ruolo::Amministratore,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
