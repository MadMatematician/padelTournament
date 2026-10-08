<?php

namespace App\Services\Classifica;

/**
 * Classifica individuale dei giocatori di un torneo e, da questa, i migliori di ogni categoria
 * (fascia d'età, genere…).
 *
 * Conta prima di tutto fin dove il giocatore è arrivato nelle finali; a parità, come sono andate le partite
 * che ha giocato (vedi RendimentoGiocatore::criteri()). Due giocatori pari su tutto hanno la stessa posizione.
 *
 * Classe pura: nessuna dipendenza da Laravel.
 */
final class ClassificaGiocatori
{
    /**
     * @param  list<int>  $giocatori  identificativi dei giocatori da classificare
     * @param  list<array{a: list<int>, b: list<int>, vincitrice: 'a'|'b', set_a: int, set_b: int, game_a: int, game_b: int}>  $partite
     *                                                                                                                                   partite giocate: i giocatori delle due coppie, chi ha vinto, set e game
     * @param  array<int, int>  $turni  giocatore => ordine dell'ultimo turno delle finali raggiunto
     * @return list<RendimentoGiocatore> dal migliore in giù
     */
    public function calcola(array $giocatori, array $partite, array $turni = []): array
    {
        $vuoto = ['giocate' => 0, 'vinte' => 0, 'set_vinti' => 0, 'set_persi' => 0, 'game_vinti' => 0, 'game_persi' => 0];
        $totali = array_fill_keys($giocatori, $vuoto);

        foreach ($partite as $partita) {
            foreach (['a' => 'b', 'b' => 'a'] as $lato => $altro) {
                foreach ($partita[$lato] as $giocatore) {
                    if (! isset($totali[$giocatore])) {
                        continue;
                    }

                    $totali[$giocatore]['giocate']++;
                    $totali[$giocatore]['vinte'] += (int) ($partita['vincitrice'] === $lato);
                    $totali[$giocatore]['set_vinti'] += $partita['set_'.$lato];
                    $totali[$giocatore]['set_persi'] += $partita['set_'.$altro];
                    $totali[$giocatore]['game_vinti'] += $partita['game_'.$lato];
                    $totali[$giocatore]['game_persi'] += $partita['game_'.$altro];
                }
            }
        }

        $righe = [];
        foreach ($totali as $giocatore => $totale) {
            $righe[] = new RendimentoGiocatore(
                $giocatore, 0, $turni[$giocatore] ?? RendimentoGiocatore::FUORI_DALLE_FINALI,
                $totale['giocate'], $totale['vinte'],
                $totale['set_vinti'], $totale['set_persi'], $totale['game_vinti'], $totale['game_persi'],
            );
        }

        return $this->ordina($righe);
    }

    /**
     * I migliori di ogni categoria, con la posizione dentro la categoria.
     *
     * @param  list<RendimentoGiocatore>  $classifica  la classifica di tutti (da `calcola`)
     * @param  array<int, int|string|null>  $categorie  giocatore => categoria (`null` = non indicata: resta fuori)
     * @param  int  $quanti  quante posizioni per categoria (i pari merito sull'ultima posizione ci sono tutti)
     * @return array<int|string, list<RendimentoGiocatore>> categoria => i suoi migliori, dal primo in giù;
     *                                                      senza chi non ha né partite né finali
     */
    public function migliori(array $classifica, array $categorie, int $quanti): array
    {
        $perCategoria = [];

        foreach ($classifica as $riga) {
            $categoria = $categorie[$riga->giocatore] ?? null;

            if ($categoria !== null && $categoria !== '' && $riga->inClassifica()) {
                $perCategoria[$categoria][] = $riga;
            }
        }

        return array_map(
            fn (array $righe) => array_values(array_filter(
                $this->ordina($righe),
                fn (RendimentoGiocatore $riga) => $riga->posizione <= $quanti,
            )),
            $perCategoria,
        );
    }

    /**
     * Ordina dal migliore in giù e assegna le posizioni (1, 2, 2, 4… con i pari merito).
     *
     * @param  list<RendimentoGiocatore>  $righe
     * @return list<RendimentoGiocatore>
     */
    private function ordina(array $righe): array
    {
        usort($righe, fn (RendimentoGiocatore $x, RendimentoGiocatore $y): int => [$y->criteri(), $x->giocatore] <=> [$x->criteri(), $y->giocatore]);

        $ordinate = [];

        foreach ($righe as $indice => $riga) {
            $pariMerito = $indice > 0 && $riga->criteri() === $righe[$indice - 1]->criteri();
            $ordinate[] = $riga->conPosizione($pariMerito ? $ordinate[$indice - 1]->posizione : $indice + 1);
        }

        return $ordinate;
    }
}
