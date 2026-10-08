<?php

namespace Tests\Unit\Punteggio;

use App\Services\Punteggio\FormatoPartita;
use App\Services\Punteggio\PunteggioNonValidoException;
use App\Services\Punteggio\ValutatorePunteggio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValutatorePunteggioTest extends TestCase
{
    /** Al meglio di 3 set a 6 game, tie-break a 7 punti: il formato classico. */
    private function classico(): FormatoPartita
    {
        return new FormatoPartita(set: 3, game: 6, tieBreak: true, puntiTieBreak: 7);
    }

    /**
     * Trasforma "6-4 7-6(7-5)" nelle righe che arrivano dal form.
     *
     * @return list<array<string, int|null>>
     */
    private function righe(string $punteggio): array
    {
        return array_map(function (string $set): array {
            preg_match('/^(\d+)-(\d+)(?:\((\d+)-(\d+)\))?$/', $set, $parti);

            return [
                'a' => (int) $parti[1],
                'b' => (int) $parti[2],
                'tb_a' => isset($parti[3]) ? (int) $parti[3] : null,
                'tb_b' => isset($parti[4]) ? (int) $parti[4] : null,
            ];
        }, explode(' ', $punteggio));
    }

    public function test_una_partita_vinta_in_due_set(): void
    {
        $esito = (new ValutatorePunteggio)->valuta($this->classico(), $this->righe('6-4 7-5'));

        $this->assertSame('a', $esito->vincitore);
        $this->assertSame([2, 0], [$esito->setA, $esito->setB]);
        $this->assertSame([13, 9], [$esito->gameA, $esito->gameB]);
        $this->assertCount(2, $esito->set);
    }

    public function test_una_partita_vinta_al_terzo_set_dalla_seconda_coppia(): void
    {
        $esito = (new ValutatorePunteggio)->valuta($this->classico(), $this->righe('6-3 4-6 6-7(5-7)'));

        $this->assertSame('b', $esito->vincitore);
        $this->assertSame([1, 2], [$esito->setA, $esito->setB]);
        $this->assertSame([16, 16], [$esito->gameA, $esito->gameB]);
        $this->assertSame(['a' => 6, 'b' => 7, 'tb_a' => 5, 'tb_b' => 7], $esito->set[2]);
    }

    public function test_le_righe_vuote_in_coda_vengono_ignorate(): void
    {
        $vuota = ['a' => '', 'b' => '', 'tb_a' => '', 'tb_b' => ''];

        $esito = (new ValutatorePunteggio)->valuta($this->classico(), [
            ['a' => '6', 'b' => '2', 'tb_a' => '', 'tb_b' => ''],
            ['a' => '6', 'b' => '0'],
            $vuota,
        ]);

        $this->assertSame('a', $esito->vincitore);
        $this->assertCount(2, $esito->set);
        $this->assertSame(['a' => 6, 'b' => 2, 'tb_a' => null, 'tb_b' => null], $esito->set[0]);
    }

    /** @return array<string, array{string}> */
    public static function setValidiAlClassico(): array
    {
        return [
            '6-0' => ['6-0'], '6-4' => ['6-4'], '7-5' => ['7-5'], '7-6 senza punti' => ['7-6'],
            '7-6 al tie-break 7-0' => ['7-6(7-0)'], '7-6 al tie-break 7-5' => ['7-6(7-5)'],
            '7-6 al tie-break 9-7' => ['7-6(9-7)'], '7-6 al tie-break 15-13' => ['7-6(15-13)'],
        ];
    }

    #[DataProvider('setValidiAlClassico')]
    public function test_i_set_validi_del_formato_classico(string $set): void
    {
        $esito = (new ValutatorePunteggio)->valuta($this->classico(), $this->righe($set.' 6-0'));

        $this->assertSame('a', $esito->vincitore);
    }

    /** @return array<string, array{string, int|null}> punteggio, indice del set sbagliato */
    public static function punteggiNonValidiAlClassico(): array
    {
        return [
            'parità' => ['6-6 6-0', 0],
            'vinto con un solo game di scarto' => ['6-5 6-0', 0],
            'troppi game' => ['8-6 6-0', 0],
            'sette a quattro' => ['7-4 6-0', 0],
            'pochi game' => ['5-3 6-0', 0],
            'errore nel secondo set' => ['6-4 9-7', 1],
            'tie-break su un set non finito 7-6' => ['6-4(7-3) 6-0', 0],
            'tie-break vinto senza due punti di scarto' => ['7-6(7-6) 6-0', 0],
            'tie-break oltre i 7 con più di due punti di scarto' => ['7-6(9-5) 6-0', 0],
            'tie-break sotto i 7 punti' => ['7-6(5-3) 6-0', 0],
            'tie-break vinto da chi ha perso il set' => ['7-6(5-7) 6-0', 0],
            'un set solo non basta' => ['6-4', null],
            'un set pari non basta' => ['6-4 4-6', null],
            'terzo set a partita già finita' => ['6-4 6-4 6-0', 2],
        ];
    }

    #[DataProvider('punteggiNonValidiAlClassico')]
    public function test_i_punteggi_non_validi_vengono_rifiutati_indicando_il_set(string $punteggio, ?int $set): void
    {
        try {
            (new ValutatorePunteggio)->valuta($this->classico(), $this->righe($punteggio));
            $this->fail('Il punteggio '.$punteggio.' doveva essere rifiutato.');
        } catch (PunteggioNonValidoException $errore) {
            $this->assertSame($set, $errore->set);
            $this->assertNotSame('', $errore->getMessage());
        }
    }

    public function test_i_set_vanno_compilati_in_ordine(): void
    {
        $this->expectException(PunteggioNonValidoException::class);
        $this->expectExceptionMessage('manca il 1° set');

        (new ValutatorePunteggio)->valuta($this->classico(), [['a' => '', 'b' => ''], ['a' => 6, 'b' => 0], ['a' => 6, 'b' => 0]]);
    }

    public function test_un_set_compilato_a_meta_viene_rifiutato(): void
    {
        try {
            (new ValutatorePunteggio)->valuta($this->classico(), [['a' => 6, 'b' => 4], ['a' => 6, 'b' => '']]);
            $this->fail('Doveva essere rifiutato.');
        } catch (PunteggioNonValidoException $errore) {
            $this->assertSame(1, $errore->set);
        }
    }

    public function test_un_solo_punteggio_del_tie_break_non_basta(): void
    {
        $this->expectException(PunteggioNonValidoException::class);

        (new ValutatorePunteggio)->valuta($this->classico(), [['a' => 7, 'b' => 6, 'tb_a' => 7, 'tb_b' => ''], ['a' => 6, 'b' => 0]]);
    }

    public function test_senza_nessun_set_il_risultato_viene_rifiutato(): void
    {
        $this->expectException(PunteggioNonValidoException::class);
        $this->expectExceptionMessage('almeno un set');

        (new ValutatorePunteggio)->valuta($this->classico(), [['a' => '', 'b' => ''], []]);
    }

    public function test_set_unico(): void
    {
        $formato = new FormatoPartita(set: 1, game: 6, tieBreak: true, puntiTieBreak: 7);
        $valutatore = new ValutatorePunteggio;

        $esito = $valutatore->valuta($formato, $this->righe('4-6'));

        $this->assertSame('b', $esito->vincitore);
        $this->assertSame([0, 1], [$esito->setA, $esito->setB]);

        $this->expectException(PunteggioNonValidoException::class);
        $valutatore->valuta($formato, $this->righe('6-4 6-4'));
    }

    public function test_al_meglio_di_cinque_set_servono_tre_set(): void
    {
        $formato = new FormatoPartita(set: 5, game: 6, tieBreak: true, puntiTieBreak: 7);
        $valutatore = new ValutatorePunteggio;

        $esito = $valutatore->valuta($formato, $this->righe('6-4 3-6 6-2 6-7 7-5'));

        $this->assertSame('a', $esito->vincitore);
        $this->assertSame([3, 2], [$esito->setA, $esito->setB]);

        $this->expectException(PunteggioNonValidoException::class);
        $valutatore->valuta($formato, $this->righe('6-4 6-4'));
    }

    public function test_senza_tie_break_con_i_due_game_di_scarto_si_va_avanti_finche_servono(): void
    {
        $formato = new FormatoPartita(set: 1, game: 6, tieBreak: false, puntiTieBreak: 7, dueGameDiScarto: true);
        $valutatore = new ValutatorePunteggio;

        foreach (['6-4', '7-5', '8-6', '12-10'] as $valido) {
            $this->assertSame('a', $valutatore->valuta($formato, $this->righe($valido))->vincitore, $valido);
        }

        foreach (['6-5', '7-6', '8-5', '9-6', '7-6(7-3)'] as $nonValido) {
            try {
                $valutatore->valuta($formato, $this->righe($nonValido));
                $this->fail($nonValido.' doveva essere rifiutato.');
            } catch (PunteggioNonValidoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_senza_tie_break_e_senza_due_game_di_scarto_vince_chi_arriva_per_primo(): void
    {
        // I due game di scarto sono facoltativi e, se non indicati, non servono.
        $formato = new FormatoPartita(set: 1, game: 4, tieBreak: false, puntiTieBreak: 7);
        $valutatore = new ValutatorePunteggio;

        $this->assertFalse($formato->dueGameDiScarto);
        $this->assertSame('Set unico a 4 game, senza tie-break (vince chi arriva per primo a 4 game)', $formato->descrizione());

        foreach (['4-3', '4-2', '4-0'] as $valido) {
            $this->assertSame('a', $valutatore->valuta($formato, $this->righe($valido))->vincitore, $valido);
        }
        $this->assertSame('b', $valutatore->valuta($formato, $this->righe('3-4'))->vincitore);

        // Il set finisce a 4: non si va oltre, non si chiude prima, niente tie-break.
        foreach (['5-3', '5-4', '6-4', '3-2', '4-4', '4-3(7-5)'] as $nonValido) {
            try {
                $valutatore->valuta($formato, $this->righe($nonValido));
                $this->fail($nonValido.' doveva essere rifiutato.');
            } catch (PunteggioNonValidoException $eccezione) {
                $this->assertSame(0, $eccezione->set, $nonValido);
            }
        }

        try {
            $valutatore->valuta($formato, $this->righe('5-3'));
        } catch (PunteggioNonValidoException $eccezione) {
            $this->assertSame(
                'Con set a 4 game il punteggio 5-3 non è valido (senza tie-break e senza due game di scarto il set finisce appena una coppia arriva a 4 game).',
                $eccezione->getMessage(),
            );
        }
    }

    public function test_al_meglio_di_tre_set_senza_due_game_di_scarto(): void
    {
        $formato = new FormatoPartita(set: 3, game: 6, tieBreak: false, puntiTieBreak: 7);
        $esito = (new ValutatorePunteggio)->valuta($formato, $this->righe('6-5 4-6 6-5'));

        $this->assertSame(['a', 2, 1, 16, 16], [$esito->vincitore, $esito->setA, $esito->setB, $esito->gameA, $esito->gameB]);
    }

    public function test_con_il_tie_break_i_due_game_di_scarto_non_contano(): void
    {
        $valutatore = new ValutatorePunteggio;

        // Con il tie-break le regole sono le stesse, spunta o no: 7-5 e 7-6 validi, 6-5 no.
        foreach ([false, true] as $dueGame) {
            $formato = new FormatoPartita(set: 1, game: 6, tieBreak: true, puntiTieBreak: 7, dueGameDiScarto: $dueGame);

            $this->assertSame('a', $valutatore->valuta($formato, $this->righe('7-5'))->vincitore);
            $this->assertSame('a', $valutatore->valuta($formato, $this->righe('7-6(7-4)'))->vincitore);

            try {
                $valutatore->valuta($formato, $this->righe('6-5'));
                $this->fail('6-5 doveva essere rifiutato.');
            } catch (PunteggioNonValidoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_set_corti_a_quattro_game(): void
    {
        $formato = new FormatoPartita(set: 3, game: 4, tieBreak: true, puntiTieBreak: 7);
        $valutatore = new ValutatorePunteggio;

        $this->assertSame('a', $valutatore->valuta($formato, $this->righe('4-2 5-3'))->vincitore);
        $this->assertSame('a', $valutatore->valuta($formato, $this->righe('4-0 5-4(7-4)'))->vincitore);

        $this->expectException(PunteggioNonValidoException::class);
        $valutatore->valuta($formato, $this->righe('6-4 6-4'));
    }

    public function test_tie_break_personalizzato_a_dieci_punti(): void
    {
        $formato = new FormatoPartita(set: 1, game: 6, tieBreak: true, puntiTieBreak: 10);
        $valutatore = new ValutatorePunteggio;

        $this->assertSame('a', $valutatore->valuta($formato, $this->righe('7-6(10-8)'))->vincitore);
        $this->assertSame('b', $valutatore->valuta($formato, $this->righe('6-7(10-12)'))->vincitore);

        // A 7 punti il tie-break non è finito.
        $this->expectException(PunteggioNonValidoException::class);
        $this->expectExceptionMessage('10 punti');
        $valutatore->valuta($formato, $this->righe('7-6(7-5)'));
    }
}
