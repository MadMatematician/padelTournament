<?php

namespace App\Services\Classifica;

/**
 * Classifica di un girone a partire dalle partite giocate.
 *
 * Ordine: punti; a parità, punti negli scontri diretti tra le coppie a pari punti; poi differenza set,
 * differenza game e game vinti (su tutte le partite); in ultimo l'identificativo della coppia,
 * così l'ordine è stabile da una lettura all'altra.
 *
 * Classe pura: nessuna dipendenza da Laravel.
 */
final class ClassificaGirone
{
    /**
     * @param  list<int>  $coppie  tutte le coppie del girone, anche quelle che non hanno ancora giocato
     * @param  list<array{a: int, b: int, vincitrice: int, set_a: int, set_b: int, game_a: int, game_b: int}>  $partite  solo le partite con un risultato
     * @return list<RigaClassifica> dalla prima all'ultima
     */
    public function calcola(array $coppie, array $partite, int $puntiVittoria, int $puntiSconfitta = 0): array
    {
        $vuota = ['giocate' => 0, 'vinte' => 0, 'perse' => 0, 'punti' => 0, 'set_vinti' => 0, 'set_persi' => 0, 'game_vinti' => 0, 'game_persi' => 0];
        $dati = array_fill_keys($coppie, $vuota);

        foreach ($partite as $partita) {
            foreach ([['a', 'b'], ['b', 'a']] as [$io, $altra]) {
                $coppia = $partita[$io];

                if (! isset($dati[$coppia])) {
                    continue;
                }

                $vinta = $partita['vincitrice'] === $coppia;

                $dati[$coppia]['giocate']++;
                $dati[$coppia][$vinta ? 'vinte' : 'perse']++;
                $dati[$coppia]['punti'] += $vinta ? $puntiVittoria : $puntiSconfitta;
                $dati[$coppia]['set_vinti'] += $partita['set_'.$io];
                $dati[$coppia]['set_persi'] += $partita['set_'.$altra];
                $dati[$coppia]['game_vinti'] += $partita['game_'.$io];
                $dati[$coppia]['game_persi'] += $partita['game_'.$altra];
            }
        }

        // Punti negli scontri diretti: contano solo le partite tra coppie che hanno gli stessi punti.
        $diretti = array_fill_keys($coppie, 0);

        foreach ($partite as $partita) {
            if (! isset($dati[$partita['a']], $dati[$partita['b']]) || $dati[$partita['a']]['punti'] !== $dati[$partita['b']]['punti']) {
                continue;
            }

            foreach (['a', 'b'] as $io) {
                $diretti[$partita[$io]] += $partita['vincitrice'] === $partita[$io] ? $puntiVittoria : $puntiSconfitta;
            }
        }

        $ordine = $coppie;

        usort($ordine, fn (int $x, int $y): int => [
            $dati[$y]['punti'],
            $diretti[$y],
            $dati[$y]['set_vinti'] - $dati[$y]['set_persi'],
            $dati[$y]['game_vinti'] - $dati[$y]['game_persi'],
            $dati[$y]['game_vinti'],
            $x,
        ] <=> [
            $dati[$x]['punti'],
            $diretti[$x],
            $dati[$x]['set_vinti'] - $dati[$x]['set_persi'],
            $dati[$x]['game_vinti'] - $dati[$x]['game_persi'],
            $dati[$x]['game_vinti'],
            $y,
        ]);

        $righe = [];

        foreach ($ordine as $indice => $coppia) {
            $righe[] = new RigaClassifica(
                coppia: $coppia,
                posizione: $indice + 1,
                giocate: $dati[$coppia]['giocate'],
                vinte: $dati[$coppia]['vinte'],
                perse: $dati[$coppia]['perse'],
                punti: $dati[$coppia]['punti'],
                setVinti: $dati[$coppia]['set_vinti'],
                setPersi: $dati[$coppia]['set_persi'],
                gameVinti: $dati[$coppia]['game_vinti'],
                gamePersi: $dati[$coppia]['game_persi'],
            );
        }

        return $righe;
    }
}
