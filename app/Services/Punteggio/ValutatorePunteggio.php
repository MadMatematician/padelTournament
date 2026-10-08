<?php

namespace App\Services\Punteggio;

/**
 * Controlla un punteggio inserito a mano contro il formato della partita e ne ricava l'esito.
 *
 * Regole di un set a G game:
 *  - con il tie-break: si vince a G con almeno due game di scarto (6-4, 6-0) oppure G+1 a G-1 (7-5);
 *    sul G pari si gioca il tie-break e si chiude G+1 a G (7-6);
 *  - senza tie-break, con i due game di scarto: come sopra, ma sul pari si va avanti finché uno ha due game
 *    in più (8-6, 9-7);
 *  - senza tie-break e senza i due game di scarto: vince chi arriva per primo a G game, anche con un solo
 *    game in più (6-5; a 4 game: 4-3). Il set non va mai oltre G;
 *  - i punti del tie-break sono facoltativi: se indicati, vince chi arriva a P punti con due di scarto.
 * Una partita "al meglio di N set" finisce appena una coppia vince più della metà dei set.
 *
 * Classe pura: nessuna dipendenza da Laravel.
 */
final class ValutatorePunteggio
{
    /**
     * @param  array<int, array{a?: mixed, b?: mixed, tb_a?: mixed, tb_b?: mixed}>  $righe  una riga per set, indice da 0;
     *                                                                                      le righe vuote in coda sono ignorate
     *
     * @throws PunteggioNonValidoException
     */
    public function valuta(FormatoPartita $formato, array $righe): EsitoPartita
    {
        ksort($righe);
        $righe = array_map($this->normalizza(...), $righe);

        $ultima = -1;
        foreach ($righe as $indice => $riga) {
            if (! $this->vuota($riga)) {
                $ultima = $indice;
            }
        }

        if ($ultima < 0) {
            throw new PunteggioNonValidoException('Inserisci il punteggio di almeno un set.');
        }

        $daVincere = $formato->setDaVincere();
        $set = [];
        $setA = $setB = $gameA = $gameB = 0;

        for ($indice = 0; $indice <= $ultima; $indice++) {
            $riga = $righe[$indice] ?? null;
            $numero = $indice + 1;

            if ($riga === null || $this->vuota($riga)) {
                throw new PunteggioNonValidoException('Compila i set in ordine: manca il '.$numero.'° set.', $indice);
            }

            if ($setA >= $daVincere || $setB >= $daVincere) {
                throw new PunteggioNonValidoException(
                    'La partita era già finita al set precedente: il '.$numero.'° set non va inserito.',
                    $indice,
                );
            }

            if ($riga['a'] === null || $riga['b'] === null) {
                throw new PunteggioNonValidoException('Indica i game di tutte e due le coppie.', $indice);
            }

            $this->controllaSet($formato, $riga, $indice);

            $set[] = $riga;
            $gameA += $riga['a'];
            $gameB += $riga['b'];
            $riga['a'] > $riga['b'] ? $setA++ : $setB++;
        }

        if ($setA < $daVincere && $setB < $daVincere) {
            throw new PunteggioNonValidoException(
                'Risultato incompleto: per vincere '.($daVincere === 1 ? 'serve un set' : 'servono '.$daVincere.' set').'.',
            );
        }

        return new EsitoPartita($set, $setA > $setB ? 'a' : 'b', $setA, $setB, $gameA, $gameB);
    }

    /**
     * @param  array{a: int, b: int, tb_a: int|null, tb_b: int|null}  $riga
     *
     * @throws PunteggioNonValidoException
     */
    private function controllaSet(FormatoPartita $formato, array $riga, int $indice): void
    {
        ['a' => $a, 'b' => $b, 'tb_a' => $tbA, 'tb_b' => $tbB] = $riga;

        if ($a === $b) {
            throw new PunteggioNonValidoException('Un set non può finire in parità.', $indice);
        }

        $game = $formato->game;
        $vinti = max($a, $b);
        $persi = min($a, $b);

        $alTieBreak = $formato->tieBreak && $vinti === $game + 1 && $persi === $game;

        $valido = match (true) {
            $formato->tieBreak => ($vinti === $game && $persi <= $game - 2)
                || ($vinti === $game + 1 && $persi === $game - 1)
                || $alTieBreak,
            $formato->dueGameDiScarto => ($vinti === $game && $persi <= $game - 2)
                || ($vinti > $game && $vinti - $persi === 2),
            // Né tie-break né due game di scarto: il set finisce appena una coppia arriva a G game.
            default => $vinti === $game,
        };

        if (! $valido) {
            throw new PunteggioNonValidoException(
                'Con set a '.$game.' game il punteggio '.$a.'-'.$b.' non è valido'
                    .match (true) {
                        $formato->tieBreak => ' (sul '.$game.' pari si gioca il tie-break: '.($game + 1).'-'.$game.').',
                        $formato->dueGameDiScarto => ' (senza tie-break si vince con due game di scarto).',
                        default => ' (senza tie-break e senza due game di scarto il set finisce appena una coppia arriva a '.$game.' game).',
                    },
                $indice,
            );
        }

        if ($tbA === null && $tbB === null) {
            return;
        }

        if (! $alTieBreak) {
            throw new PunteggioNonValidoException(
                $formato->tieBreak
                    ? 'I punti del tie-break si indicano solo se il set è finito '.($game + 1).'-'.$game.'.'
                    : 'In questa fase non è previsto il tie-break.',
                $indice,
            );
        }

        if ($tbA === null || $tbB === null) {
            throw new PunteggioNonValidoException('Indica i punti del tie-break di tutte e due le coppie, oppure lasciali vuoti.', $indice);
        }

        $punti = $formato->puntiTieBreak;
        $piu = max($tbA, $tbB);
        $meno = min($tbA, $tbB);

        if (! (($piu === $punti && $meno <= $punti - 2) || ($piu > $punti && $piu - $meno === 2))) {
            throw new PunteggioNonValidoException(
                'Tie-break non valido: si vince a '.$punti.' punti con almeno due di scarto.',
                $indice,
            );
        }

        if (($tbA > $tbB) !== ($a > $b)) {
            throw new PunteggioNonValidoException('Il tie-break deve averlo vinto la coppia che ha vinto il set.', $indice);
        }
    }

    /**
     * @return array{a: int|null, b: int|null, tb_a: int|null, tb_b: int|null}
     */
    private function normalizza(mixed $riga): array
    {
        $riga = is_array($riga) ? $riga : [];
        $numero = fn (string $chiave): ?int => isset($riga[$chiave]) && $riga[$chiave] !== '' && is_numeric($riga[$chiave])
            ? (int) $riga[$chiave]
            : null;

        return ['a' => $numero('a'), 'b' => $numero('b'), 'tb_a' => $numero('tb_a'), 'tb_b' => $numero('tb_b')];
    }

    /**
     * @param  array{a: int|null, b: int|null, tb_a: int|null, tb_b: int|null}  $riga
     */
    private function vuota(array $riga): bool
    {
        return $riga['a'] === null && $riga['b'] === null && $riga['tb_a'] === null && $riga['tb_b'] === null;
    }
}
