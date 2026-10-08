<?php

namespace Tests\Unit\Sorteggio;

use App\Services\Sorteggio\CalendarioGirone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalendarioGironeTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function dimensioni(): iterable
    {
        foreach (range(2, 9) as $coppie) {
            yield $coppie.' coppie' => [$coppie];
        }
    }

    #[DataProvider('dimensioni')]
    public function test_sola_andata_ogni_coppia_incontra_tutte_le_altre_una_volta(int $numero): void
    {
        $coppie = range(11, 10 + $numero);

        $partite = (new CalendarioGirone)->genera($coppie, false);

        $this->assertCount($numero * ($numero - 1) / 2, $partite);

        $incontri = [];
        foreach ($partite as $partita) {
            $this->assertNotSame($partita['a'], $partita['b']);
            $this->assertFalse($partita['ritorno']);
            $incontri[] = min($partita['a'], $partita['b']).'-'.max($partita['a'], $partita['b']);
        }

        $this->assertCount(count($partite), array_unique($incontri));
    }

    #[DataProvider('dimensioni')]
    public function test_in_una_giornata_nessuna_coppia_gioca_due_volte(int $numero): void
    {
        $partite = (new CalendarioGirone)->genera(range(1, $numero), true);

        $perGiornata = [];
        foreach ($partite as $partita) {
            $perGiornata[$partita['giornata']][] = $partita['a'];
            $perGiornata[$partita['giornata']][] = $partita['b'];
        }

        // Con le coppie pari giocano tutte a ogni giornata, con le dispari una riposa.
        $giornateAndata = $numero % 2 === 0 ? $numero - 1 : $numero;

        $this->assertSame(range(1, 2 * $giornateAndata), array_keys($perGiornata));

        foreach ($perGiornata as $inCampo) {
            $this->assertSame(count($inCampo), count(array_unique($inCampo)));
            $this->assertCount($numero % 2 === 0 ? $numero : $numero - 1, $inCampo);
        }
    }

    #[DataProvider('dimensioni')]
    public function test_andata_e_ritorno_ripete_ogni_incontro_a_campi_invertiti(int $numero): void
    {
        $partite = (new CalendarioGirone)->genera(range(1, $numero), true);

        $andata = array_values(array_filter($partite, fn (array $p) => ! $p['ritorno']));
        $ritorno = array_values(array_filter($partite, fn (array $p) => $p['ritorno']));

        $this->assertCount($numero * ($numero - 1) / 2, $andata);
        $this->assertCount(count($andata), $ritorno);

        $this->assertEqualsCanonicalizing(
            array_map(fn (array $p) => $p['a'].'-'.$p['b'], $andata),
            array_map(fn (array $p) => $p['b'].'-'.$p['a'], $ritorno),
        );

        // Il ritorno comincia dopo l'ultima giornata dell'andata.
        $this->assertLessThan(
            min(array_column($ritorno, 'giornata')),
            max(array_column($andata, 'giornata')),
        );
    }

    public function test_con_meno_di_due_coppie_non_ci_sono_partite(): void
    {
        $this->assertSame([], (new CalendarioGirone)->genera([7], true));
        $this->assertSame([], (new CalendarioGirone)->genera([], false));
    }
}
