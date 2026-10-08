<?php

namespace Database\Factories;

use App\Models\Giocatore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Giocatore>
 */
class GiocatoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->firstName(),
            'cognome' => fake()->lastName(),
            'genere' => fake()->randomElement(array_keys(config('tornei.giocatori.generi'))),
            'anno_nascita' => (int) now()->year - fake()->numberBetween(18, 65),
            'fascia' => fake()->randomElement(array_keys(config('tornei.giocatori.fasce'))),
            'lato' => fake()->randomElement([null, ...array_keys(config('tornei.giocatori.lati'))]),
            'circolo' => fake()->randomElement([null, 'Padel Club Centro', 'Circolo Tennis Lungomare']),
        ];
    }

    public function circolo(?string $circolo): static
    {
        return $this->state(fn (array $attributes) => [
            'circolo' => $circolo,
        ]);
    }

    public function fascia(int $fascia): static
    {
        return $this->state(fn (array $attributes) => [
            'fascia' => $fascia,
        ]);
    }
}
