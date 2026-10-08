<?php

namespace App\Services\Sorteggio;

use App\Exceptions\SorteggioImpossibileException;
use Random\Randomizer;

/**
 * Divide le coppie in gironi.
 *
 * Il numero di gironi è il minimo che rispetta il massimo di squadre per girone; le coppie vengono
 * distribuite a caso e in modo uniforme (tra un girone e l'altro al più una coppia di differenza).
 * Due coppie che hanno un giocatore in comune (quello in prestito) finiscono sempre in gironi diversi.
 *
 * Classe pura: nessuna dipendenza da Laravel.
 */
final class SorteggioGironi
{
    /** Quante distribuzioni casuali provare prima di dichiarare impossibile il vincolo del prestito. */
    private const TENTATIVI = 50;

    public function __construct(
        private readonly Randomizer $caso = new Randomizer,
    ) {}

    /**
     * @param  array<int, list<int>>  $coppie  identificativo della coppia => identificativi dei suoi giocatori
     * @param  int  $massimo  numero massimo di squadre per girone
     * @return list<list<int>> per ogni girone, gli identificativi delle sue coppie
     *
     * @throws SorteggioImpossibileException
     */
    public function dividi(array $coppie, int $massimo): array
    {
        if ($massimo < 2) {
            throw new SorteggioImpossibileException('Un girone deve poter contenere almeno due squadre.');
        }

        if (count($coppie) < 2) {
            throw new SorteggioImpossibileException('Per formare i gironi servono almeno due coppie.');
        }

        $numeroGironi = intdiv(count($coppie) + $massimo - 1, $massimo);

        for ($tentativo = 0; $tentativo < self::TENTATIVI; $tentativo++) {
            $gironi = $this->prova($coppie, $numeroGironi);

            if ($gironi !== null) {
                return $gironi;
            }
        }

        throw new SorteggioImpossibileException(
            $numeroGironi === 1
                ? 'Con un solo girone il giocatore in prestito giocherebbe contro se stesso: riduci il numero massimo di squadre per girone oppure pareggia le fasce.'
                : 'Non si riesce a tenere in gironi diversi le due coppie dei giocatori in prestito: riduci il numero massimo di squadre per girone.'
        );
    }

    /**
     * Una distribuzione casuale; `null` se una coppia non trova un girone ammesso.
     *
     * @param  array<int, list<int>>  $coppie
     * @return list<list<int>>|null
     */
    private function prova(array $coppie, int $numeroGironi): ?array
    {
        $base = intdiv(count($coppie), $numeroGironi);
        $resto = count($coppie) % $numeroGironi;

        $posti = [];
        for ($g = 0; $g < $numeroGironi; $g++) {
            $posti[$g] = $base + ($g < $resto ? 1 : 0);
        }

        $gironi = array_fill(0, $numeroGironi, []);
        $giocatoriNelGirone = array_fill(0, $numeroGironi, []);

        foreach ($this->ordineDiAssegnazione($coppie) as $coppia) {
            // Tra i gironi ammessi, quello con più posti liberi (a parità, a caso): così restano equilibrati.
            $scelto = null;

            foreach ($this->caso->shuffleArray(array_keys($posti)) as $g) {
                if ($posti[$g] === 0 || array_intersect($coppie[$coppia], $giocatoriNelGirone[$g]) !== []) {
                    continue;
                }

                if ($scelto === null || $posti[$g] > $posti[$scelto]) {
                    $scelto = $g;
                }
            }

            if ($scelto === null) {
                return null;
            }

            $gironi[$scelto][] = $coppia;
            $giocatoriNelGirone[$scelto] = [...$giocatoriNelGirone[$scelto], ...$coppie[$coppia]];
            $posti[$scelto]--;
        }

        return $gironi;
    }

    /**
     * Prima le coppie vincolate (hanno un giocatore che sta anche in un'altra coppia), poi le altre:
     * finché i gironi sono vuoti è più facile separarle. Dentro ciascun gruppo l'ordine è casuale.
     *
     * @param  array<int, list<int>>  $coppie
     * @return list<int>
     */
    private function ordineDiAssegnazione(array $coppie): array
    {
        $presenze = array_count_values(array_merge(...array_values($coppie)));

        $vincolate = [];
        $libere = [];

        foreach ($coppie as $id => $giocatori) {
            $condivisa = false;
            foreach ($giocatori as $giocatore) {
                $condivisa = $condivisa || $presenze[$giocatore] > 1;
            }

            if ($condivisa) {
                $vincolate[] = $id;
            } else {
                $libere[] = $id;
            }
        }

        return [
            ...($vincolate === [] ? [] : $this->caso->shuffleArray($vincolate)),
            ...($libere === [] ? [] : $this->caso->shuffleArray($libere)),
        ];
    }
}
