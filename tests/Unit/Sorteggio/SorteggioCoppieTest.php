<?php

namespace Tests\Unit\Sorteggio;

use App\Exceptions\SorteggioImpossibileException;
use App\Services\Sorteggio\CoppiaSorteggiata;
use App\Services\Sorteggio\GiocatoreSorteggio;
use App\Services\Sorteggio\LatiComplementari;
use App\Services\Sorteggio\SorteggioCoppie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class SorteggioCoppieTest extends TestCase
{
    private function sorteggio(int $seme = 1): SorteggioCoppie
    {
        return new SorteggioCoppie(new Randomizer(new Mt19937($seme)));
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
     */
    private function lato(array $giocatori, int $id): ?string
    {
        foreach ($giocatori as $giocatore) {
            if ($giocatore->id === $id) {
                return $giocatore->lato;
            }
        }

        $this->fail('Giocatore '.$id.' sconosciuto.');
    }

    /** @return iterable<string, array{int}> */
    public static function semi(): iterable
    {
        foreach (range(1, 25) as $seme) {
            yield 'seme '.$seme => [$seme];
        }
    }

    #[DataProvider('semi')]
    public function test_con_fasce_pari_ogni_giocatore_sta_in_una_sola_coppia(int $seme): void
    {
        $prima = $this->giocatori(1, array_fill(0, 8, null));
        $seconda = $this->giocatori(101, array_fill(0, 8, null));

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, LatiComplementari::Preferito);

        $this->assertCount(8, $coppie);
        $this->assertEqualsCanonicalizing(range(1, 8), array_map(fn (CoppiaSorteggiata $c) => $c->prima, $coppie));
        $this->assertEqualsCanonicalizing(range(101, 108), array_map(fn (CoppiaSorteggiata $c) => $c->seconda, $coppie));
        $this->assertSame([], array_filter($coppie, fn (CoppiaSorteggiata $c) => $c->prestito !== null));
    }

    public function test_il_sorteggio_non_e_sempre_lo_stesso(): void
    {
        $prima = $this->giocatori(1, array_fill(0, 6, null));
        $seconda = $this->giocatori(101, array_fill(0, 6, null));

        $esiti = [];
        foreach (range(1, 10) as $seme) {
            $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, LatiComplementari::Ignora);
            $abbinamenti = array_map(fn (CoppiaSorteggiata $c) => $c->prima.'-'.$c->seconda, $coppie);
            sort($abbinamenti);
            $esiti[implode(' ', $abbinamenti)] = true;
        }

        $this->assertGreaterThan(5, count($esiti));
    }

    /**
     * Ogni giocatore di prima fascia deve poter finire con chiunque di seconda, con la stessa frequenza:
     * il sorteggio non dipende dall'ordine (cioè dagli identificativi) con cui arrivano i giocatori.
     */
    #[DataProvider('modi')]
    public function test_senza_vincoli_di_lato_ogni_abbinamento_e_ugualmente_probabile(LatiComplementari $lati): void
    {
        $numero = 4;
        $sorteggi = 4800;
        $prima = $this->giocatori(1, array_fill(0, $numero, null));
        $seconda = $this->giocatori(101, array_fill(0, $numero, null));
        $sorteggio = $this->sorteggio(2026);

        $insieme = [];
        $abbinamenti = [];

        for ($giro = 0; $giro < $sorteggi; $giro++) {
            $firma = [];

            foreach ($sorteggio->sorteggia($prima, $seconda, $lati) as $coppia) {
                $insieme[$coppia->prima][$coppia->seconda] = ($insieme[$coppia->prima][$coppia->seconda] ?? 0) + 1;
                $firma[$coppia->prima] = $coppia->seconda;
            }

            ksort($firma);
            $abbinamenti[implode('-', $firma)] = ($abbinamenti[implode('-', $firma)] ?? 0) + 1;
        }

        // Tutti i 4! = 24 abbinamenti escono, ciascuno intorno a 200 volte su 4800.
        $this->assertCount(24, $abbinamenti);
        $this->assertGreaterThan(140, min($abbinamenti));
        $this->assertLessThan(260, max($abbinamenti));

        // Ogni giocatore di prima fascia con ciascuno di seconda: intorno a 1200 volte su 4800.
        foreach ($prima as $giocatore) {
            foreach ($seconda as $compagno) {
                $volte = $insieme[$giocatore->id][$compagno->id] ?? 0;

                $this->assertGreaterThan(1050, $volte);
                $this->assertLessThan(1350, $volte);
            }
        }
    }

    /** @return array<string, array{LatiComplementari}> */
    public static function modi(): array
    {
        return [
            'lati ignorati' => [LatiComplementari::Ignora],
            'lati preferiti' => [LatiComplementari::Preferito],
            'lati obbligatori' => [LatiComplementari::Obbligatorio],
        ];
    }

    public function test_con_i_lati_ignorati_escono_anche_coppie_con_lo_stesso_lato(): void
    {
        $prima = $this->giocatori(1, ['destra', 'sinistra']);
        $seconda = $this->giocatori(101, ['sinistra', 'destra']);

        $esiti = [];
        foreach (range(1, 40) as $seme) {
            $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, LatiComplementari::Ignora);
            $esiti[$this->lato($prima, $coppie[0]->prima) === $this->lato($seconda, $coppie[0]->seconda) ? 'uguali' : 'diversi'] = true;
        }

        $this->assertCount(2, $esiti);
    }

    #[DataProvider('semi')]
    public function test_con_i_lati_preferiti_forma_il_maggior_numero_di_coppie_complementari(int $seme): void
    {
        // 3 destri e 1 sinistro contro 1 destro e 3 sinistri: esiste un abbinamento tutto complementare,
        // ma un sorteggio a caso lo troverebbe di rado.
        $prima = $this->giocatori(1, ['destra', 'destra', 'destra', 'sinistra']);
        $seconda = $this->giocatori(101, ['destra', 'sinistra', 'sinistra', 'sinistra']);

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, LatiComplementari::Preferito);

        foreach ($coppie as $coppia) {
            $this->assertNotSame($this->lato($prima, $coppia->prima), $this->lato($seconda, $coppia->seconda));
        }
    }

    #[DataProvider('semi')]
    public function test_chi_non_ha_un_lato_preferito_completa_qualunque_coppia(int $seme): void
    {
        $prima = $this->giocatori(1, ['destra', 'destra', 'destra']);
        $seconda = $this->giocatori(101, ['destra', null, 'sinistra']);

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, LatiComplementari::Preferito);

        // Il massimo possibile è due coppie complementari: il destro di seconda fascia ha solo compagni destri.
        $stessoLato = array_filter($coppie, fn (CoppiaSorteggiata $c) => $this->lato($seconda, $c->seconda) === 'destra');

        $this->assertCount(3, $coppie);
        $this->assertCount(1, $stessoLato);
    }

    public function test_con_i_lati_obbligatori_rifiuta_un_abbinamento_impossibile(): void
    {
        $prima = $this->giocatori(1, ['destra', 'destra']);
        $seconda = $this->giocatori(101, ['destra', 'sinistra']);

        $this->expectException(SorteggioImpossibileException::class);

        $this->sorteggio()->sorteggia($prima, $seconda, LatiComplementari::Obbligatorio);
    }

    public function test_con_i_lati_obbligatori_trova_l_abbinamento_quando_esiste(): void
    {
        $prima = $this->giocatori(1, ['destra', 'sinistra', null]);
        $seconda = $this->giocatori(101, ['destra', 'sinistra', 'destra']);

        foreach (range(1, 25) as $seme) {
            $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, LatiComplementari::Obbligatorio);

            foreach ($coppie as $coppia) {
                $latoPrima = $this->lato($prima, $coppia->prima);
                $this->assertTrue($latoPrima === null || $latoPrima !== $this->lato($seconda, $coppia->seconda));
            }
        }
    }

    #[DataProvider('semi')]
    public function test_con_una_fascia_piu_numerosa_i_giocatori_in_piu_ricevono_un_compagno_in_prestito(int $seme): void
    {
        $prima = $this->giocatori(1, array_fill(0, 7, null));
        $seconda = $this->giocatori(101, array_fill(0, 5, null));

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, LatiComplementari::Preferito);

        // Tante coppie quanti i giocatori della fascia più numerosa, ognuno in una sola coppia.
        $this->assertCount(7, $coppie);
        $this->assertEqualsCanonicalizing(range(1, 7), array_map(fn (CoppiaSorteggiata $c) => $c->prima, $coppie));

        $ordinarie = array_values(array_filter($coppie, fn (CoppiaSorteggiata $c) => $c->prestito === null));
        $conPrestito = array_values(array_filter($coppie, fn (CoppiaSorteggiata $c) => $c->prestito !== null));

        // Ogni giocatore della fascia meno numerosa ha la sua coppia ordinaria…
        $this->assertEqualsCanonicalizing(range(101, 105), array_map(fn (CoppiaSorteggiata $c) => $c->seconda, $ordinarie));

        // …e due di loro, diversi, ne completano una seconda: in quella sono loro il giocatore in prestito.
        $this->assertCount(2, $conPrestito);
        $inPrestito = array_map(fn (CoppiaSorteggiata $c) => $c->prestito, $conPrestito);
        $this->assertCount(2, array_unique($inPrestito));

        foreach ($conPrestito as $coppia) {
            $this->assertSame($coppia->seconda, $coppia->prestito);
        }
    }

    public function test_il_prestito_funziona_anche_quando_e_la_seconda_fascia_la_piu_numerosa(): void
    {
        $prima = $this->giocatori(1, [null, null]);
        $seconda = $this->giocatori(101, [null, null, null]);

        $coppie = $this->sorteggio()->sorteggia($prima, $seconda, LatiComplementari::Ignora);

        $this->assertCount(3, $coppie);
        $this->assertEqualsCanonicalizing([101, 102, 103], array_map(fn (CoppiaSorteggiata $c) => $c->seconda, $coppie));

        $conPrestito = array_values(array_filter($coppie, fn (CoppiaSorteggiata $c) => $c->prestito !== null));

        $this->assertCount(1, $conPrestito);
        $this->assertSame($conPrestito[0]->prima, $conPrestito[0]->prestito);
    }

    #[DataProvider('semi')]
    public function test_va_in_prestito_il_giocatore_indicato(int $seme): void
    {
        $prima = $this->giocatori(1, array_fill(0, 6, null));
        $seconda = $this->giocatori(101, array_fill(0, 4, null));

        $coppie = $this->sorteggio($seme)->sorteggia($prima, $seconda, LatiComplementari::Preferito, [103, 101]);

        $inPrestito = array_values(array_filter(array_map(fn (CoppiaSorteggiata $c) => $c->prestito, $coppie)));

        $this->assertEqualsCanonicalizing([101, 103], $inPrestito);
    }

    /**
     * @param  list<int>  $prestiti
     */
    #[DataProvider('prestitiNonValidi')]
    public function test_i_prestiti_indicati_male_vengono_rifiutati(array $prestiti): void
    {
        $prima = $this->giocatori(1, array_fill(0, 6, null));
        $seconda = $this->giocatori(101, array_fill(0, 4, null));

        $this->expectException(SorteggioImpossibileException::class);

        $this->sorteggio()->sorteggia($prima, $seconda, LatiComplementari::Preferito, $prestiti);
    }

    /** @return array<string, array{list<int>}> */
    public static function prestitiNonValidi(): array
    {
        return [
            'nessuno' => [[]],
            'troppo pochi' => [[101]],
            'troppi' => [[101, 102, 103]],
            'lo stesso due volte' => [[101, 101]],
            'della fascia più numerosa' => [[101, 3]],
            'non iscritto' => [[101, 999]],
        ];
    }

    public function test_con_fasce_pari_non_si_indicano_prestiti(): void
    {
        $this->expectException(SorteggioImpossibileException::class);

        $this->sorteggio()->sorteggia($this->giocatori(1, [null]), $this->giocatori(101, [null]), LatiComplementari::Ignora, [101]);
    }

    public function test_ogni_giocatore_puo_andare_in_prestito_una_sola_volta(): void
    {
        // 5 contro 2: servirebbero 3 prestiti, ma i giocatori di seconda fascia sono solo 2.
        $this->expectException(SorteggioImpossibileException::class);

        $this->sorteggio()->sorteggia($this->giocatori(1, array_fill(0, 5, null)), $this->giocatori(101, [null, null]), LatiComplementari::Ignora);
    }

    public function test_serve_almeno_un_giocatore_per_fascia(): void
    {
        $this->expectException(SorteggioImpossibileException::class);

        $this->sorteggio()->sorteggia($this->giocatori(1, [null, null]), [], LatiComplementari::Ignora);
    }
}
