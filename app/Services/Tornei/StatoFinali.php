<?php

namespace App\Services\Tornei;

use App\Enums\Fase;
use App\Models\Coppia;
use App\Models\Partita;
use Illuminate\Support\Collection;

/**
 * A che punto sono le finali di un torneo: il turno che si sta giocando, quanti risultati gli mancano e,
 * a torneo concluso, la coppia che ha vinto. Si ricava dalle partite delle finali.
 */
final readonly class StatoFinali
{
    public function __construct(
        /** Il turno in corso: il primo con una partita non ancora decisa (`null` a finali concluse). */
        public ?Fase $inCorso,
        /** Partite del turno in corso che non hanno ancora chi passa. */
        public int $daGiocare,
        /** Chi ha vinto la finale, cioè il torneo (`null` finché non è giocata). */
        public ?Coppia $vincitrice,
    ) {}

    /**
     * @param  Collection<int, Partita>  $partite  le partite delle finali di un torneo, in qualunque ordine
     */
    public static function daPartite(Collection $partite): self
    {
        $inCorso = null;
        $daGiocare = 0;

        foreach (Fase::finali() as $fase) {
            $daDecidere = $partite->filter(fn (Partita $partita) => $partita->fase === $fase && ! $partita->decisa())->count();

            if ($daDecidere > 0) {
                $inCorso = $fase;
                $daGiocare = $daDecidere;
                break;
            }
        }

        $finale = $partite->first(fn (Partita $partita) => $partita->fase === Fase::Finale);

        return new self($inCorso, $daGiocare, match ($finale?->vincitrice_id) {
            null => null,
            $finale->coppia_a_id => $finale->coppiaA,
            default => $finale->coppiaB,
        });
    }
}
