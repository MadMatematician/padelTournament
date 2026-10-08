<?php

namespace App\Services\Tornei;

use App\Enums\Fase;
use App\Models\Partita;
use Illuminate\Support\Collection;

/**
 * Le partite a cui manca il risultato, nell'ordine in cui la finestra del risultato le propone dopo una partita.
 *
 * Si resta nello stesso ambito della partita di partenza: i gironi (in ordine di girone, giornata e calendario)
 * oppure le finali (turno per turno, dall'alto del tabellone). L'ordine è circolare: dopo l'ultima partita si
 * ricomincia dalla prima, così con «Successiva» si raggiungono tutte le partite ancora da giocare.
 *
 * Dopo un salvataggio (`primaIlGruppo`) vengono prima le partite dello stesso girone, o dello stesso turno: si
 * cambia girone solo quando quello corrente ha tutti i risultati.
 */
class CodaRisultati
{
    /**
     * @return Collection<int, Partita> con girone e coppie (e i loro giocatori) caricati
     */
    public function dopo(Partita $partita, bool $primaIlGruppo = false): Collection
    {
        $partite = $this->ambito($partita);
        $posto = $partite->search(fn (Partita $altra) => $altra->is($partita));

        // Dalla partita successiva in poi, e poi da capo fino a quella di partenza (esclusa).
        $coda = $posto === false
            ? $partite
            : $partite->slice($posto + 1)->concat($partite->slice(0, $posto));

        $coda = $coda->filter(fn (Partita $altra) => $altra->completa() && ! $altra->giocata())->values();

        if (! $primaIlGruppo) {
            return $coda;
        }

        [$delGruppo, $altre] = $coda->partition(fn (Partita $altra) => $altra->chiaveGruppo() === $partita->chiaveGruppo());

        return $delGruppo->concat($altre)->values();
    }

    /**
     * Tutte le partite dell'ambito, nell'ordine in cui si giocano.
     *
     * @return Collection<int, Partita>
     */
    private function ambito(Partita $partita): Collection
    {
        $torneo = $partita->torneo;
        $coppie = ['coppiaA.primaFascia', 'coppiaA.secondaFascia', 'coppiaB.primaFascia', 'coppiaB.secondaFascia'];

        if ($partita->fase === Fase::Gironi) {
            return $torneo->partiteDiGirone()->with(['girone', ...$coppie])->get()
                ->sortBy(fn (Partita $altra) => [$altra->girone?->posizione ?? 0, $altra->giornata, $altra->id])
                ->values();
        }

        $turni = array_flip(array_map(fn (Fase $fase) => $fase->value, Fase::finali()));

        return $torneo->partiteDelleFinali()->with($coppie)->get()
            ->sortBy(fn (Partita $altra) => [$turni[$altra->fase->value], $altra->posizione, $altra->id])
            ->values();
    }
}
