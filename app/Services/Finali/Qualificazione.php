<?php

namespace App\Services\Finali;

use App\Exceptions\SorteggioImpossibileException;
use App\Services\Classifica\RigaClassifica;
use Random\Randomizer;

/**
 * Dalle classifiche dei gironi a chi accede alle finali.
 *
 * - Accedono le prime N coppie di ogni girone (le coppie escluse non contano: passa la successiva).
 * - Il primo turno ha tante squadre quante la più piccola potenza di due che contiene le qualificate.
 * - I posti che mancano si riempiono con i ripescaggi: le migliori tra le prime escluse di ogni girone,
 *   poi tra le seconde escluse, e così via, confrontate con il criterio scelto.
 * - Se le coppie non bastano, restano posti vuoti: le prime teste di serie passano il turno senza giocare.
 *
 * Classe pura: nessuna dipendenza da Laravel.
 */
final class Qualificazione
{
    public function __construct(
        private readonly Randomizer $caso = new Randomizer,
    ) {}

    /**
     * @param  array<int, list<RigaClassifica>>  $classifiche  girone => righe dalla prima all'ultima
     * @param  int  $perGirone  coppie che accedono da ogni girone
     * @param  list<int>  $escluse  coppie che giocano il girone ma non accedono alle finali
     * @param  int  $massimo  numero massimo di squadre del primo turno
     *
     * @throws SorteggioImpossibileException
     */
    public function calcola(array $classifiche, int $perGirone, CriterioRipescaggio $criterio, array $escluse = [], int $massimo = 32): AccessoFinali
    {
        if ($perGirone < 1) {
            throw new SorteggioImpossibileException('Da ogni girone deve accedere alle finali almeno una squadra.');
        }

        // livello => candidate di quel piazzamento utile, una per girone
        $perLivello = [];

        foreach ($classifiche as $girone => $righe) {
            $livello = 0;

            foreach ($righe as $riga) {
                if (in_array($riga->coppia, $escluse, true)) {
                    continue;
                }

                $livello++;
                $perLivello[$livello][] = new Candidata($riga->coppia, $girone, $livello, $riga);
            }
        }

        ksort($perLivello);

        // Per le teste di serie conta sempre il rendimento: il sorteggio riguarda solo i ripescaggi.
        $perRendimento = $criterio === CriterioRipescaggio::Sorteggio ? CriterioRipescaggio::Punti : $criterio;

        $dirette = [];
        foreach ($perLivello as $livello => $candidate) {
            if ($livello <= $perGirone) {
                $dirette = [...$dirette, ...$this->ordina($candidate, $perRendimento)];
            }
        }

        if (count($dirette) < 2) {
            throw new SorteggioImpossibileException('Per le finali servono almeno due squadre qualificate.');
        }

        $dimensione = 2;
        while ($dimensione < count($dirette)) {
            $dimensione *= 2;
        }

        if ($dimensione > $massimo) {
            throw new SorteggioImpossibileException(
                'Si qualificano '.count($dirette).' squadre, ma il tabellone ne prevede al massimo '.$massimo
                    .': riduci le squadre che accedono da ogni girone.'
            );
        }

        $ripescate = [];
        $mancanti = $dimensione - count($dirette);

        foreach ($perLivello as $livello => $candidate) {
            if ($livello <= $perGirone || $mancanti === 0) {
                continue;
            }

            $scelte = array_slice($this->ordina($candidate, $criterio), 0, $mancanti);
            $ripescate = [...$ripescate, ...$scelte];
            $mancanti -= count($scelte);
        }

        return new AccessoFinali($dirette, $ripescate, $dimensione);
    }

    /**
     * Ordina coppie di gironi diversi dalla migliore alla peggiore.
     *
     * @param  list<Candidata>  $candidate
     * @return list<Candidata>
     */
    private function ordina(array $candidate, CriterioRipescaggio $criterio): array
    {
        if ($criterio === CriterioRipescaggio::Sorteggio) {
            return $candidate === [] ? [] : $this->caso->shuffleArray($candidate);
        }

        usort($candidate, fn (Candidata $x, Candidata $y): int => [...$this->rendimento($y->riga, $criterio), $x->coppia]
            <=> [...$this->rendimento($x->riga, $criterio), $y->coppia]);

        return $candidate;
    }

    /**
     * Valori medi per partita giocata, nell'ordine in cui il criterio li confronta.
     *
     * @return list<float>
     */
    private function rendimento(RigaClassifica $riga, CriterioRipescaggio $criterio): array
    {
        $giocate = max($riga->giocate, 1);

        $punti = $riga->punti / $giocate;
        $set = $riga->differenzaSet() / $giocate;
        $game = $riga->differenzaGame() / $giocate;
        $vinti = $riga->gameVinti / $giocate;

        return $criterio === CriterioRipescaggio::Game
            ? [$game, $set, $punti, $vinti]
            : [$punti, $set, $game, $vinti];
    }
}
