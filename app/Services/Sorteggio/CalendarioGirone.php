<?php

namespace App\Services\Sorteggio;

/**
 * Calendario all'italiana di un girone: ogni coppia incontra tutte le altre, una volta (sola andata)
 * o due (andata e ritorno, a campi invertiti). Metodo del cerchio: una coppia resta ferma, le altre ruotano;
 * con un numero dispari di coppie a ogni giornata una riposa.
 *
 * Classe pura e deterministica: l'ordine delle giornate dipende solo dall'ordine in cui arrivano le coppie.
 */
final class CalendarioGirone
{
    /**
     * @param  list<int>  $coppie  identificativi delle coppie del girone
     * @return list<array{giornata: int, ritorno: bool, a: int, b: int}>
     */
    public function genera(array $coppie, bool $andataRitorno): array
    {
        $coppie = array_values($coppie);

        if (count($coppie) < 2) {
            return [];
        }

        if (count($coppie) % 2 === 1) {
            $coppie[] = null; // riposo
        }

        $numero = count($coppie);
        $giornate = $numero - 1;
        $partite = [];

        for ($giornata = 1; $giornata <= $giornate; $giornata++) {
            for ($i = 0; $i < $numero / 2; $i++) {
                $a = $coppie[$i];
                $b = $coppie[$numero - 1 - $i];

                if ($a === null || $b === null) {
                    continue;
                }

                // La coppia ferma (la prima) alterna il lato del tabellone da una giornata all'altra.
                if ($i === 0 && $giornata % 2 === 0) {
                    [$a, $b] = [$b, $a];
                }

                $partite[] = ['giornata' => $giornata, 'ritorno' => false, 'a' => $a, 'b' => $b];
            }

            // Rotazione: la prima resta, l'ultima passa al secondo posto.
            $coppie = [$coppie[0], $coppie[$numero - 1], ...array_slice($coppie, 1, $numero - 2)];
        }

        if ($andataRitorno) {
            foreach ($partite as $partita) {
                $partite[] = [
                    'giornata' => $partita['giornata'] + $giornate,
                    'ritorno' => true,
                    'a' => $partita['b'],
                    'b' => $partita['a'],
                ];
            }
        }

        return $partite;
    }
}
