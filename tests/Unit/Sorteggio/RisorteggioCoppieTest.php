<?php

namespace Tests\Unit\Sorteggio;

use App\Exceptions\SorteggioImpossibileException;
use App\Services\Sorteggio\CoppiaSorteggiata;
use App\Services\Sorteggio\GiocatoreSorteggio;
use App\Services\Sorteggio\RisorteggioCoppie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Nuovo sorteggio delle coppie a ogni turno delle finali: mai due giocatori che hanno già fatto coppia.
 */
class RisorteggioCoppieTest extends TestCase
{
    private function sorteggio(int $seme = 1): RisorteggioCoppie
    {
        return new RisorteggioCoppie(new Randomizer(new Mt19937($seme)));
    }

    /**
     * @param  list<string|null>  $lati  un giocatore per ogni lato elencato
     * @return list<GiocatoreSorteggio>
     */
    private function giocatori(int $primoId, array $lati): array
    {
        return array_map(
            fn (int $i) => new GiocatoreSorteggio($primoId + $i, $lati[$i]),
            array_keys($lati),
        );
    }

    /**
     * @param  list<CoppiaSorteggiata>  $coppie
     * @return array<int, int> prima fascia => seconda fascia
     */
    private function abbinamento(array $coppie): array
    {
        $abbinamento = [];
        foreach ($coppie as $coppia) {
            $abbinamento[$coppia->prima] = $coppia->seconda;
        }
        ksort($abbinamento);

        return $abbinamento;
    }

    /** @return iterable<string, array{int}> */
    public static function semi(): iterable
    {
        foreach (range(1, 30) as $seme) {
            yield 'seme '.$seme => [$seme];
        }
    }

    #[DataProvider('semi')]
    public function test_ogni_giocatore_sta_in_una_sola_coppia_e_nessuna_coppia_si_ripete(int $seme): void
    {
        $prima = $this->giocatori(1, array_fill(0, 8, null));
        $seconda = $this->giocatori(101, array_fill(0, 8, null));

        // Ognuno ha già avuto tre compagni: quello dei gironi (stesso indice) e i due "accanto".
        $giaInsieme = [];
        foreach (range(0, 7) as $i) {
            foreach ([0, 1, 2] as $passo) {
                $giaInsieme[] = [1 + $i, 101 + ($i + $passo) % 8];
            }
        }

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, $giaInsieme);

        $this->assertCount(8, $coppie);
        $this->assertEqualsCanonicalizing(range(1, 8), array_map(fn ($coppia) => $coppia->prima, $coppie));
        $this->assertEqualsCanonicalizing(range(101, 108), array_map(fn ($coppia) => $coppia->seconda, $coppie));

        foreach ($coppie as $coppia) {
            $this->assertNotContains([$coppia->prima, $coppia->seconda], $giaInsieme);
            $this->assertFalse($coppia->ripetuta);
            $this->assertNull($coppia->prestito);
        }
    }

    public function test_tra_i_sorteggi_ammessi_ognuno_ha_la_stessa_probabilita(): void
    {
        $prima = $this->giocatori(1, [null, null, null]);
        $seconda = $this->giocatori(101, [null, null, null]);
        $giaInsieme = [[1, 101], [2, 102], [3, 103]];   // le coppie del turno prima
        $sorteggio = $this->sorteggio(2026);

        $abbinamenti = [];
        for ($giro = 0; $giro < 3000; $giro++) {
            $firma = implode('-', $this->abbinamento($sorteggio->sorteggia($prima, $seconda, $giaInsieme)));
            $abbinamenti[$firma] = ($abbinamenti[$firma] ?? 0) + 1;
        }

        // Senza coppie ripetute restano solo due abbinamenti dei sei: escono metà delle volte ciascuno.
        ksort($abbinamenti);
        $this->assertSame(['102-103-101', '103-101-102'], array_keys($abbinamenti));
        $this->assertGreaterThan(1400, min($abbinamenti));
        $this->assertLessThan(1600, max($abbinamenti));
    }

    public function test_senza_coppie_gia_viste_il_sorteggio_non_dipende_dagli_identificativi(): void
    {
        $prima = $this->giocatori(1, array_fill(0, 4, null));
        $seconda = $this->giocatori(101, array_fill(0, 4, null));
        $sorteggio = $this->sorteggio(7);

        $insieme = [];
        $primaCoppia = [];

        for ($giro = 0; $giro < 4800; $giro++) {
            $coppie = $sorteggio->sorteggia($prima, $seconda, []);
            $primaCoppia[$coppie[0]->prima] = ($primaCoppia[$coppie[0]->prima] ?? 0) + 1;

            foreach ($coppie as $coppia) {
                $insieme[$coppia->prima][$coppia->seconda] = ($insieme[$coppia->prima][$coppia->seconda] ?? 0) + 1;
            }
        }

        // Ogni giocatore di prima fascia con ciascuno di seconda: intorno a 1200 volte su 4800.
        foreach ($prima as $giocatore) {
            foreach ($seconda as $compagno) {
                $this->assertGreaterThan(1050, $insieme[$giocatore->id][$compagno->id] ?? 0);
                $this->assertLessThan(1350, $insieme[$giocatore->id][$compagno->id] ?? 0);
            }

            // Anche l'ordine delle coppie (il posto nel tabellone) è casuale.
            $this->assertGreaterThan(1050, $primaCoppia[$giocatore->id] ?? 0);
            $this->assertLessThan(1350, $primaCoppia[$giocatore->id] ?? 0);
        }
    }

    #[DataProvider('semi')]
    public function test_trova_l_unico_sorteggio_possibile_anche_se_e_uno_solo(int $seme): void
    {
        $numero = 10;
        $prima = $this->giocatori(1, array_fill(0, $numero, null));
        $seconda = $this->giocatori(101, array_fill(0, $numero, null));

        // Ogni giocatore di prima fascia ha già giocato con tutti quelli di seconda tranne uno.
        $giaInsieme = [];
        foreach (range(0, $numero - 1) as $i) {
            foreach (range(0, $numero - 1) as $j) {
                if ($j !== ($i + 3) % $numero) {
                    $giaInsieme[] = [1 + $i, 101 + $j];
                }
            }
        }

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, $giaInsieme);

        foreach ($coppie as $coppia) {
            $this->assertSame(101 + ($coppia->prima - 1 + 3) % $numero, $coppia->seconda);
            $this->assertFalse($coppia->ripetuta);
        }
    }

    #[DataProvider('semi')]
    public function test_se_e_inevitabile_le_coppie_ripetute_sono_il_minimo_e_vengono_segnalate(int $seme): void
    {
        // La finale dopo le semifinali 1+101 e 2+102: lo scambio darebbe 1+102 e 2+101, ma 1 e 102 erano già
        // insieme nei gironi. Meglio una coppia ripetuta (1+102) che due (le stesse delle semifinali).
        $coppie = $this->sorteggio($seme)->sorteggia(
            $this->giocatori(1, [null, null]),
            $this->giocatori(101, [null, null]),
            [[1, 101], [2, 102], [1, 102]],
        );

        $this->assertSame([1 => 102, 2 => 101], $this->abbinamento($coppie));

        foreach ($coppie as $coppia) {
            $this->assertSame($coppia->prima === 1, $coppia->ripetuta);
        }
    }

    public function test_se_le_ripetizioni_non_sono_consentite_il_sorteggio_impossibile_viene_rifiutato(): void
    {
        $this->expectException(SorteggioImpossibileException::class);
        $this->expectExceptionMessage('una coppia dovrebbe ripetersi');

        $this->sorteggio()->sorteggia(
            $this->giocatori(1, [null, null]),
            $this->giocatori(101, [null, null]),
            [[1, 101], [2, 102], [1, 102]],
            consentiRipetizioni: false,
        );
    }

    public function test_se_le_ripetizioni_non_sono_consentite_ma_non_servono_il_sorteggio_riesce(): void
    {
        $coppie = $this->sorteggio()->sorteggia(
            $this->giocatori(1, [null, null]),
            $this->giocatori(101, [null, null]),
            [[1, 101], [2, 102]],
            consentiRipetizioni: false,
        );

        $this->assertSame([1 => 102, 2 => 101], $this->abbinamento($coppie));
    }

    #[DataProvider('semi')]
    public function test_con_i_lati_preferisce_le_coppie_destra_piu_sinistra(int $seme): void
    {
        $prima = $this->giocatori(1, ['destra', 'destra', 'sinistra', 'sinistra', null, 'destra']);
        $seconda = $this->giocatori(101, ['destra', 'sinistra', 'sinistra', 'destra', 'sinistra', null]);
        $giaInsieme = [[1, 102], [2, 103], [3, 101], [4, 104], [5, 105], [6, 106]];

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, $giaInsieme, latiDiversi: true);

        $lato = [];
        foreach ([...$prima, ...$seconda] as $giocatore) {
            $lato[$giocatore->id] = $giocatore->lato;
        }

        foreach ($coppie as $coppia) {
            $this->assertNotContains([$coppia->prima, $coppia->seconda], $giaInsieme);
            $this->assertFalse($coppia->ripetuta);
            // Si può fare: tutte le coppie hanno lati diversi (o un giocatore senza preferenza).
            $this->assertTrue(
                $lato[$coppia->prima] === null || $lato[$coppia->seconda] === null || $lato[$coppia->prima] !== $lato[$coppia->seconda],
                $coppia->prima.' e '.$coppia->seconda.' hanno lo stesso lato',
            );
        }
    }

    #[DataProvider('semi')]
    public function test_per_i_lati_non_ripete_mai_una_coppia(int $seme): void
    {
        // Con i lati la coppia giusta sarebbe 1+102 e 2+101, ma hanno già giocato insieme: restano 1+101 e 2+102.
        $coppie = $this->sorteggio($seme)->sorteggia(
            $this->giocatori(1, ['destra', 'sinistra']),
            $this->giocatori(101, ['destra', 'sinistra']),
            [[1, 102], [2, 101]],
            latiDiversi: true,
        );

        $this->assertSame([1 => 101, 2 => 102], $this->abbinamento($coppie));
        $this->assertFalse($coppie[0]->ripetuta || $coppie[1]->ripetuta);
    }

    public function test_un_tabellone_da_trentadue_coppie_si_sorteggia_senza_ripetizioni(): void
    {
        $prima = $this->giocatori(1, array_fill(0, 32, null));
        $seconda = $this->giocatori(101, array_fill(0, 32, null));

        // Coppie dei gironi più una seconda coppia per i primi sei (i prestiti).
        $giaInsieme = array_map(fn (int $i) => [1 + $i, 101 + $i], range(0, 31));
        foreach (range(0, 5) as $i) {
            $giaInsieme[] = [1 + $i, 101 + $i + 10];
        }

        // Cinque turni di fila con chi resta: sedicesimi, ottavi, quarti, semifinali, finale.
        $sorteggio = $this->sorteggio(11);

        while (count($prima) >= 2) {
            $coppie = $sorteggio->sorteggia($prima, $seconda, $giaInsieme);
            $this->assertCount(count($prima), $coppie);

            foreach ($coppie as $coppia) {
                $this->assertNotContains([$coppia->prima, $coppia->seconda], $giaInsieme);
                $giaInsieme[] = [$coppia->prima, $coppia->seconda];
            }

            // Passa una coppia sì e una no.
            $vincenti = array_values(array_filter($coppie, fn (int $indice) => $indice % 2 === 0, ARRAY_FILTER_USE_KEY));
            $prima = array_map(fn ($coppia) => new GiocatoreSorteggio($coppia->prima), $vincenti);
            $seconda = array_map(fn ($coppia) => new GiocatoreSorteggio($coppia->seconda), $vincenti);

            if (count($prima) === 2) {
                break;   // per la finale le coppie nuove non sono garantite: vedi il test sulle ripetizioni inevitabili
            }
        }
    }

    /**
     * Quante coppie già viste avrebbe la finale, sommate sui quattro esiti possibili delle semifinali
     * (coppie 0–1 e 2–3 di fronte; le due vincitrici si scambiano i compagni).
     *
     * @param  list<CoppiaSorteggiata>  $semifinali
     * @param  list<array{int, int}>  $giaInsieme
     */
    private function ripetuteInFinale(array $semifinali, array $giaInsieme): int
    {
        $ripetute = 0;

        foreach ([0, 1] as $i) {
            foreach ([2, 3] as $k) {
                $ripetute += (int) in_array([$semifinali[$i]->prima, $semifinali[$k]->seconda], $giaInsieme, true);
                $ripetute += (int) in_array([$semifinali[$k]->prima, $semifinali[$i]->seconda], $giaInsieme, true);
            }
        }

        return $ripetute;
    }

    #[DataProvider('semi')]
    public function test_le_semifinali_lasciano_possibile_una_finale_senza_coppie_ripetute(int $seme): void
    {
        // Quattro coppie arrivano in semifinale dai gironi: 1+101, 2+102, 3+103, 4+104.
        $prima = $this->giocatori(1, array_fill(0, 4, null));
        $seconda = $this->giocatori(101, array_fill(0, 4, null));
        $giaInsieme = [[1, 101], [2, 102], [3, 103], [4, 104]];

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, $giaInsieme, versoLaFinale: true);

        $this->assertCount(4, $coppie);
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], array_map(fn ($coppia) => $coppia->prima, $coppie));
        $this->assertEqualsCanonicalizing([101, 102, 103, 104], array_map(fn ($coppia) => $coppia->seconda, $coppie));

        foreach ($coppie as $coppia) {
            $this->assertNotSame($coppia->prima + 100, $coppia->seconda);
            $this->assertFalse($coppia->ripetuta);
        }

        // Chiunque vinca, in finale le coppie con i compagni scambiati sono tutte nuove.
        $this->assertSame(0, $this->ripetuteInFinale($coppie, $giaInsieme));

        // Vuol dire che ogni semifinale mette di fronte due coppie dei gironi che si sono scambiate i compagni.
        foreach ([[0, 1], [2, 3]] as [$i, $k]) {
            $this->assertSame($coppie[$i]->prima + 100, $coppie[$k]->seconda);
            $this->assertSame($coppie[$k]->prima + 100, $coppie[$i]->seconda);
        }
    }

    public function test_tra_le_semifinali_ugualmente_buone_sceglie_il_caso(): void
    {
        $prima = $this->giocatori(1, array_fill(0, 4, null));
        $seconda = $this->giocatori(101, array_fill(0, 4, null));
        $giaInsieme = [[1, 101], [2, 102], [3, 103], [4, 104]];
        $sorteggio = $this->sorteggio(5);

        $avversariDelPrimo = [];
        $primoPosto = [];

        for ($giro = 0; $giro < 3000; $giro++) {
            $coppie = $sorteggio->sorteggia($prima, $seconda, $giaInsieme, versoLaFinale: true);
            $primoPosto[$coppie[0]->prima] = ($primoPosto[$coppie[0]->prima] ?? 0) + 1;

            foreach ([[0, 1], [1, 0], [2, 3], [3, 2]] as [$i, $k]) {
                if ($coppie[$i]->prima === 1) {
                    $avversariDelPrimo[$coppie[$k]->prima] = ($avversariDelPrimo[$coppie[$k]->prima] ?? 0) + 1;
                }
            }
        }

        // Il giocatore 1 trova di fronte, con la stessa frequenza, ognuno degli altri tre…
        ksort($avversariDelPrimo);
        $this->assertSame([2, 3, 4], array_keys($avversariDelPrimo));
        $this->assertGreaterThan(900, min($avversariDelPrimo));
        $this->assertLessThan(1100, max($avversariDelPrimo));

        // …e il posto nel tabellone non dipende dall'identificativo.
        $this->assertCount(4, $primoPosto);
        $this->assertGreaterThan(650, min($primoPosto));
        $this->assertLessThan(850, max($primoPosto));
    }

    #[DataProvider('semi')]
    public function test_le_semifinali_dopo_i_quarti_tengono_separati_i_compagni_dei_quarti(int $seme): void
    {
        // Dai quarti escono 1+102, 2+101, 3+104, 4+103; i compagni dei gironi sono stati eliminati.
        $giaInsieme = [[1, 102], [2, 101], [3, 104], [4, 103], [1, 110], [2, 111], [3, 112], [4, 113]];

        $coppie = $this->sorteggio($seme)->sorteggia(
            $this->giocatori(1, array_fill(0, 4, null)),
            $this->giocatori(101, array_fill(0, 4, null)),
            $giaInsieme,
            versoLaFinale: true,
        );

        foreach ($coppie as $coppia) {
            $this->assertNotContains([$coppia->prima, $coppia->seconda], $giaInsieme);
        }

        $this->assertSame(0, $this->ripetuteInFinale($coppie, $giaInsieme));
    }

    #[DataProvider('semi')]
    public function test_se_una_finale_tutta_nuova_non_e_garantita_le_semifinali_restano_senza_ripetizioni(int $seme): void
    {
        // Il giocatore 1 ha già giocato con 101 e con 102, tutti e due ancora in gara: qualunque semifinale si
        // sorteggi, un esito porta in finale una coppia già vista. Le semifinali però restano tutte nuove,
        // e si sceglie il sorteggio che ne lascia il meno possibile.
        $giaInsieme = [[1, 101], [1, 102], [2, 103], [3, 104], [4, 101]];

        $coppie = $this->sorteggio($seme)->sorteggia(
            $this->giocatori(1, array_fill(0, 4, null)),
            $this->giocatori(101, array_fill(0, 4, null)),
            $giaInsieme,
            versoLaFinale: true,
        );

        foreach ($coppie as $coppia) {
            $this->assertNotContains([$coppia->prima, $coppia->seconda], $giaInsieme);
            $this->assertFalse($coppia->ripetuta);
        }

        $this->assertSame($this->minimoInFinale($giaInsieme), $this->ripetuteInFinale($coppie, $giaInsieme));
    }

    /**
     * Il minimo di `ripetuteInFinale` tra tutte le semifinali senza coppie già viste (per forza bruta).
     *
     * @param  list<array{int, int}>  $giaInsieme
     */
    private function minimoInFinale(array $giaInsieme): int
    {
        $minimo = PHP_INT_MAX;

        foreach ([[0, 1, 2, 3], [0, 2, 1, 3], [0, 3, 1, 2]] as $posti) {
            foreach ($this->permutazioni([101, 102, 103, 104]) as $compagni) {
                $coppie = array_map(fn (int $posto) => new CoppiaSorteggiata(1 + $posto, $compagni[$posto]), $posti);

                foreach ($coppie as $coppia) {
                    if (in_array([$coppia->prima, $coppia->seconda], $giaInsieme, true)) {
                        continue 2;
                    }
                }

                $minimo = min($minimo, $this->ripetuteInFinale($coppie, $giaInsieme));
            }
        }

        return $minimo;
    }

    /**
     * @param  list<int>  $elementi
     * @return list<list<int>>
     */
    private function permutazioni(array $elementi): array
    {
        if (count($elementi) <= 1) {
            return [$elementi];
        }

        $permutazioni = [];
        foreach ($elementi as $indice => $elemento) {
            $resto = $elementi;
            unset($resto[$indice]);

            foreach ($this->permutazioni(array_values($resto)) as $coda) {
                $permutazioni[] = [$elemento, ...$coda];
            }
        }

        return $permutazioni;
    }

    public function test_servono_giocatori_delle_due_fasce_in_numero_uguale(): void
    {
        foreach ([[[], []], [[null, null], [null]]] as [$latiPrima, $latiSeconda]) {
            try {
                $this->sorteggio()->sorteggia($this->giocatori(1, $latiPrima), $this->giocatori(101, $latiSeconda), []);
                $this->fail('Il sorteggio doveva essere rifiutato.');
            } catch (SorteggioImpossibileException $errore) {
                $this->assertStringContainsString('in numero uguale', $errore->getMessage());
            }
        }
    }
}
