<?php

namespace Tests\Unit\Sorteggio;

use App\Exceptions\SorteggioImpossibileException;
use App\Services\Sorteggio\SorteggioGironi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class SorteggioGironiTest extends TestCase
{
    private function sorteggio(int $seme = 1): SorteggioGironi
    {
        return new SorteggioGironi(new Randomizer(new Mt19937($seme)));
    }

    /**
     * Coppie senza giocatori in comune: la coppia n è formata dai giocatori 1000+n e 2000+n.
     *
     * @return array<int, list<int>>
     */
    private function coppie(int $quante): array
    {
        $coppie = [];
        foreach (range(1, $quante) as $n) {
            $coppie[$n] = [1000 + $n, 2000 + $n];
        }

        return $coppie;
    }

    /**
     * @return array<string, array{int, int, list<int>}> coppie, massimo per girone, dimensioni attese
     */
    public static function distribuzioni(): array
    {
        return [
            '8 coppie, massimo 4' => [8, 4, [4, 4]],
            '10 coppie, massimo 4' => [10, 4, [4, 3, 3]],
            '9 coppie, massimo 4' => [9, 4, [3, 3, 3]],
            '5 coppie, massimo 4' => [5, 4, [3, 2]],
            '4 coppie, massimo 4' => [4, 4, [4]],
            '3 coppie, massimo 6' => [3, 6, [3]],
            '13 coppie, massimo 3' => [13, 3, [3, 3, 3, 2, 2]],
            '2 coppie, massimo 2' => [2, 2, [2]],
        ];
    }

    /**
     * @param  list<int>  $attese
     */
    #[DataProvider('distribuzioni')]
    public function test_i_gironi_sono_il_minimo_necessario_e_sono_equilibrati(int $quante, int $massimo, array $attese): void
    {
        $gironi = $this->sorteggio()->dividi($this->coppie($quante), $massimo);

        $dimensioni = array_map('count', $gironi);
        rsort($dimensioni);

        $this->assertSame($attese, $dimensioni);
        // Ogni coppia in un solo girone.
        $this->assertEqualsCanonicalizing(range(1, $quante), array_merge(...$gironi));
    }

    public function test_la_distribuzione_e_casuale(): void
    {
        $esiti = [];
        foreach (range(1, 10) as $seme) {
            $gironi = array_map(function (array $girone) {
                sort($girone);

                return implode(',', $girone);
            }, $this->sorteggio($seme)->dividi($this->coppie(8), 4));
            sort($gironi);
            $esiti[implode(' | ', $gironi)] = true;
        }

        $this->assertGreaterThan(5, count($esiti));
    }

    /** @return iterable<string, array{int}> */
    public static function semi(): iterable
    {
        foreach (range(1, 40) as $seme) {
            yield 'seme '.$seme => [$seme];
        }
    }

    #[DataProvider('semi')]
    public function test_le_due_coppie_di_un_giocatore_in_prestito_stanno_in_gironi_diversi(int $seme): void
    {
        // 7 coppie: i giocatori 2001 e 2002 sono in prestito nelle coppie 6 e 7.
        $coppie = $this->coppie(5) + [6 => [1006, 2001], 7 => [1007, 2002]];

        $gironi = $this->sorteggio($seme)->dividi($coppie, 4);

        $this->assertEqualsCanonicalizing([4, 3], array_map('count', $gironi));

        $gironeDi = [];
        foreach ($gironi as $indice => $girone) {
            foreach ($girone as $coppia) {
                $gironeDi[$coppia] = $indice;
            }
        }

        $this->assertNotSame($gironeDi[1], $gironeDi[6]);
        $this->assertNotSame($gironeDi[2], $gironeDi[7]);
    }

    #[DataProvider('semi')]
    public function test_il_vincolo_regge_anche_quando_tutte_le_coppie_sono_vincolate(int $seme): void
    {
        // 4 contro 2: ogni giocatore di seconda fascia ha due coppie, in due gironi da due.
        $coppie = [1 => [11, 21], 2 => [12, 22], 3 => [13, 21], 4 => [14, 22]];

        $gironi = $this->sorteggio($seme)->dividi($coppie, 2);

        $this->assertCount(2, $gironi);

        foreach ($gironi as $girone) {
            $giocatori = array_merge(...array_map(fn (int $coppia) => $coppie[$coppia], $girone));
            $this->assertSame(count($giocatori), count(array_unique($giocatori)));
        }
    }

    public function test_con_un_solo_girone_il_prestito_e_impossibile(): void
    {
        $coppie = $this->coppie(2) + [3 => [1003, 2001]];

        $this->expectException(SorteggioImpossibileException::class);
        $this->expectExceptionMessage('un solo girone');

        $this->sorteggio()->dividi($coppie, 4);
    }

    public function test_servono_almeno_due_coppie(): void
    {
        $this->expectException(SorteggioImpossibileException::class);

        $this->sorteggio()->dividi($this->coppie(1), 4);
    }

    public function test_un_girone_ha_almeno_due_squadre(): void
    {
        $this->expectException(SorteggioImpossibileException::class);

        $this->sorteggio()->dividi($this->coppie(4), 1);
    }
}
