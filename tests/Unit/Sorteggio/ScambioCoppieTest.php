<?php

namespace Tests\Unit\Sorteggio;

use App\Exceptions\SorteggioImpossibileException;
use App\Services\Sorteggio\CoppiaSorteggiata;
use App\Services\Sorteggio\GiocatoreSorteggio;
use App\Services\Sorteggio\ScambioCoppie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Coppie nuove a ogni turno delle finali con lo scambio dei compagni: nessuna coppia si ripete mai.
 */
class ScambioCoppieTest extends TestCase
{
    private function sorteggio(int $seme = 1): ScambioCoppie
    {
        return new ScambioCoppie(new Randomizer(new Mt19937($seme)));
    }

    /**
     * Coppie in gara "prima i + seconda 100+i", con i lati indicati (prima, seconda).
     *
     * @param  list<array{string|null, string|null}>|int  $lati  i lati di ogni coppia, oppure quante coppie senza lati
     * @return list<array{GiocatoreSorteggio, GiocatoreSorteggio}>
     */
    private function coppie(array|int $lati): array
    {
        $lati = is_int($lati) ? array_fill(0, $lati, [null, null]) : $lati;

        return array_map(
            fn (int $i) => [new GiocatoreSorteggio(1 + $i, $lati[$i][0]), new GiocatoreSorteggio(101 + $i, $lati[$i][1])],
            array_keys($lati),
        );
    }

    /**
     * @param  list<array{GiocatoreSorteggio, GiocatoreSorteggio}>  $coppie
     * @return list<array{int, int}>
     */
    private function identificativi(array $coppie): array
    {
        return array_map(fn (array $coppia) => [$coppia[0]->id, $coppia[1]->id], $coppie);
    }

    /** @return iterable<string, array{int}> */
    public static function semi(): iterable
    {
        foreach (range(1, 30) as $seme) {
            yield 'seme '.$seme => [$seme];
        }
    }

    #[DataProvider('semi')]
    public function test_ogni_incontro_e_tra_due_coppie_in_gara_che_si_scambiano_i_compagni(int $seme): void
    {
        $inGara = $this->coppie(8);
        $prima = $this->identificativi($inGara);

        $coppie = $this->sorteggio($seme)->sorteggia($inGara, $prima);

        $this->assertCount(8, $coppie);
        $this->assertEqualsCanonicalizing(range(1, 8), array_map(fn ($coppia) => $coppia->prima, $coppie));
        $this->assertEqualsCanonicalizing(range(101, 108), array_map(fn ($coppia) => $coppia->seconda, $coppie));

        foreach (array_chunk($coppie, 2) as [$a, $b]) {
            // Chi era con il giocatore di prima fascia della coppia A ora gioca nella coppia B, e viceversa.
            $this->assertContains([$a->prima, $b->seconda], $prima);
            $this->assertContains([$b->prima, $a->seconda], $prima);
            $this->assertNotContains([$a->prima, $a->seconda], $prima);
            $this->assertFalse($a->ripetuta || $b->ripetuta);
            $this->assertNull($a->prestito);
        }
    }

    public function test_quali_coppie_si_incontrano_lo_decide_il_caso(): void
    {
        $inGara = $this->coppie(4);
        $sorteggio = $this->sorteggio(2026);

        $avversarie = [];
        $primoPosto = [];

        for ($giro = 0; $giro < 3000; $giro++) {
            $coppie = $sorteggio->sorteggia($inGara, $this->identificativi($inGara));
            $primoPosto[$coppie[0]->prima] = ($primoPosto[$coppie[0]->prima] ?? 0) + 1;

            foreach (array_chunk($coppie, 2) as [$a, $b]) {
                // La coppia del giocatore 1 ha incontrato quella del giocatore…
                if ($a->prima === 1 || $b->prima === 1) {
                    $altra = $a->prima === 1 ? $b->prima : $a->prima;
                    $avversarie[$altra] = ($avversarie[$altra] ?? 0) + 1;
                }
            }
        }

        // Tre accoppiamenti possibili, un terzo delle volte ciascuno.
        ksort($avversarie);
        $this->assertSame([2, 3, 4], array_keys($avversarie));
        $this->assertGreaterThan(900, min($avversarie));
        $this->assertLessThan(1100, max($avversarie));

        // Anche il posto nel tabellone è a caso.
        $this->assertCount(4, $primoPosto);
        $this->assertGreaterThan(650, min($primoPosto));
        $this->assertLessThan(850, max($primoPosto));
    }

    #[DataProvider('semi')]
    public function test_in_un_tabellone_da_trentadue_coppie_nessuna_coppia_si_ripete_fino_alla_finale(int $seme): void
    {
        $caso = new Randomizer(new Mt19937(1000 + $seme));
        $sorteggio = $this->sorteggio($seme);

        $inGara = $this->coppie(32);
        $giaInsieme = $this->identificativi($inGara);

        // Sedicesimi, ottavi, quarti, semifinali, finale: cinque turni, vincitrici a caso.
        foreach ([32, 16, 8, 4, 2] as $attese) {
            $this->assertCount($attese, $inGara);

            $coppie = $sorteggio->sorteggia($inGara, $giaInsieme, consentiRipetizioni: false);

            foreach ($coppie as $coppia) {
                $this->assertNotContains([$coppia->prima, $coppia->seconda], $giaInsieme);
                $giaInsieme[] = [$coppia->prima, $coppia->seconda];
            }

            $inGara = array_map(
                function (array $incontro) use ($caso): array {
                    $vincitrice = $incontro[$caso->getInt(0, 1)];

                    return [new GiocatoreSorteggio($vincitrice->prima), new GiocatoreSorteggio($vincitrice->seconda)];
                },
                array_chunk($coppie, 2),
            );
        }

        $this->assertCount(1, $inGara);
        $this->assertCount(32 + 32 + 16 + 8 + 4 + 2, $giaInsieme);
        $this->assertCount(count($giaInsieme), array_unique(array_map(fn (array $coppia) => implode('-', $coppia), $giaInsieme)));
    }

    #[DataProvider('semi')]
    public function test_con_i_lati_preferisce_gli_incontri_che_danno_coppie_destra_piu_sinistra(int $seme): void
    {
        // Scambiandosi i compagni, A e B danno coppie destra + sinistra, e così C e D; ogni altro incontro no.
        $inGara = $this->coppie([
            ['destra', 'destra'],      // A
            ['sinistra', 'sinistra'],  // B
            ['destra', 'destra'],      // C
            ['sinistra', 'sinistra'],  // D
        ]);

        $coppie = $this->sorteggio($seme)->sorteggia($inGara, $this->identificativi($inGara), latiDiversi: true);

        foreach (array_chunk($coppie, 2) as [$a, $b]) {
            // Giocatori dispari a destra, pari a sinistra: ogni coppia nuova ne ha uno per tipo.
            $this->assertNotSame($a->prima % 2, $a->seconda % 2);
            $this->assertNotSame($b->prima % 2, $b->seconda % 2);
        }
    }

    public function test_senza_i_lati_gli_stessi_giocatori_si_incontrano_in_tutti_i_modi(): void
    {
        $inGara = $this->coppie([['destra', 'destra'], ['sinistra', 'sinistra'], ['destra', 'destra'], ['sinistra', 'sinistra']]);
        $sorteggio = $this->sorteggio(3);

        $avversarie = [];
        for ($giro = 0; $giro < 300; $giro++) {
            foreach (array_chunk($sorteggio->sorteggia($inGara, $this->identificativi($inGara)), 2) as [$a, $b]) {
                if ($a->prima === 1 || $b->prima === 1) {
                    $avversarie[$a->prima === 1 ? $b->prima : $a->prima] = true;
                }
            }
        }

        $this->assertCount(3, $avversarie);
    }

    #[DataProvider('semi')]
    public function test_evita_l_incontro_che_rimetterebbe_insieme_una_coppia_gia_vista(int $seme): void
    {
        // Dati non nati da questo sorteggio: il giocatore 1 ha già giocato anche con 102.
        // Le coppie di 1 e 2 non possono quindi incontrarsi: resta scelta tra gli altri due accoppiamenti.
        $inGara = $this->coppie(4);
        $giaInsieme = [...$this->identificativi($inGara), [1, 102]];

        $coppie = $this->sorteggio($seme)->sorteggia($inGara, $giaInsieme, consentiRipetizioni: false);

        foreach ($coppie as $coppia) {
            $this->assertNotContains([$coppia->prima, $coppia->seconda], $giaInsieme);
            $this->assertFalse($coppia->ripetuta);
        }
    }

    public function test_tanti_incontri_con_un_vincolo_trovano_comunque_coppie_nuove(): void
    {
        // Più di otto coppie: gli accoppiamenti non si provano tutti, ma un campione basta e avanza.
        $inGara = $this->coppie(12);
        $giaInsieme = [...$this->identificativi($inGara), [1, 102], [1, 103], [1, 104], [2, 101]];

        foreach (range(1, 40) as $seme) {
            foreach ($this->sorteggio($seme)->sorteggia($inGara, $giaInsieme) as $coppia) {
                $this->assertFalse($coppia->ripetuta, 'seme '.$seme);
            }
        }
    }

    public function test_una_coppia_ripetuta_inevitabile_viene_segnalata(): void
    {
        // Restano due coppie, e 1 ha già giocato anche con 102: lo scambio dà 1+102 (già vista) e 2+101 (nuova).
        $coppie = $this->sorteggio()->sorteggia($this->coppie(2), [[1, 101], [2, 102], [1, 102]]);

        $perGiocatore = [];
        foreach ($coppie as $coppia) {
            $perGiocatore[$coppia->prima] = $coppia;
        }

        $this->assertSame(102, $perGiocatore[1]->seconda);
        $this->assertTrue($perGiocatore[1]->ripetuta);
        $this->assertSame(101, $perGiocatore[2]->seconda);
        $this->assertFalse($perGiocatore[2]->ripetuta);
    }

    public function test_se_le_ripetizioni_non_sono_consentite_il_sorteggio_impossibile_viene_rifiutato(): void
    {
        $this->expectException(SorteggioImpossibileException::class);
        $this->expectExceptionMessage('una coppia dovrebbe ripetersi');

        $this->sorteggio()->sorteggia($this->coppie(2), [[1, 101], [2, 102], [1, 102]], consentiRipetizioni: false);
    }

    public function test_le_coppie_in_gara_devono_essere_in_numero_pari(): void
    {
        foreach ([0, 1, 3] as $numero) {
            try {
                $this->sorteggio()->sorteggia($this->coppie($numero), []);
                $this->fail('Il sorteggio doveva essere rifiutato.');
            } catch (SorteggioImpossibileException $errore) {
                $this->assertStringContainsString('in numero pari', $errore->getMessage());
            }
        }
    }

    public function test_il_risultato_e_un_elenco_di_coppie_sorteggiate(): void
    {
        $this->assertContainsOnlyInstancesOf(CoppiaSorteggiata::class, $this->sorteggio()->sorteggia($this->coppie(2), []));
    }
}
