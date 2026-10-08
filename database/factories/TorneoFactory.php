<?php

namespace Database\Factories;

use App\Models\Giocatore;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Torneo>
 */
class TorneoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Torneo '.fake()->unique()->city(),
            'tipologia' => config('tornei.tipologia_predefinita'),
            'data_inizio' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'numero_turni' => config('tornei.turni.predefinito'),
        ];
    }

    /**
     * Torneo con la lista dei giocatori già completa: `$numero` schede nuove in anagrafica, tutte iscritte,
     * divise in parti uguali tra le fasce. Se il numero non si divide, i giocatori in più vanno alle prime
     * fasce (17 giocatori = 9 di prima e 8 di seconda: il caso del prestito).
     *
     * Senza numero vale `tornei.test.giocatori.predefinito`.
     */
    public function conGiocatori(?int $numero = null): static
    {
        $numero ??= (int) config('tornei.test.giocatori.predefinito');
        $fasce = array_keys(config('tornei.giocatori.fasce'));

        $perFascia = intdiv($numero, count($fasce));
        $inPiu = $numero % count($fasce);

        $factory = $this;

        foreach ($fasce as $posizione => $fascia) {
            $quanti = $perFascia + ($posizione < $inPiu ? 1 : 0);

            if ($quanti > 0) {
                $factory = $factory->hasAttached(Giocatore::factory()->count($quanti)->fascia($fascia), [], 'giocatori');
            }
        }

        return $factory;
    }
}
