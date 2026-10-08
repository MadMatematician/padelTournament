<?php

namespace Tests\Unit\Classifica;

use App\Services\Classifica\ClassificaGiocatori;
use App\Services\Classifica\RendimentoGiocatore;
use PHPUnit\Framework\TestCase;

/**
 * Classifica individuale dei giocatori e migliori per categoria.
 */
class ClassificaGiocatoriTest extends TestCase
{
    /**
     * Una partita giocata, scritta in breve: giocatori delle due coppie, set e game ("2-0", "12-5").
     *
     * @param  list<int>  $a
     * @param  list<int>  $b
     * @return array{a: list<int>, b: list<int>, vincitrice: 'a'|'b', set_a: int, set_b: int, game_a: int, game_b: int}
     */
    private function partita(array $a, array $b, string $set, string $game): array
    {
        [$setA, $setB] = array_map('intval', explode('-', $set));
        [$gameA, $gameB] = array_map('intval', explode('-', $game));

        return ['a' => $a, 'b' => $b, 'vincitrice' => $setA > $setB ? 'a' : 'b', 'set_a' => $setA, 'set_b' => $setB, 'game_a' => $gameA, 'game_b' => $gameB];
    }

    /**
     * @param  list<RendimentoGiocatore>  $righe
     * @return array<int, int> giocatore => posizione, nell'ordine della classifica
     */
    private function posizioni(array $righe): array
    {
        $posizioni = [];
        foreach ($righe as $riga) {
            $posizioni[$riga->giocatore] = $riga->posizione;
        }

        return $posizioni;
    }

    public function test_somma_le_partite_di_ogni_giocatore_con_qualunque_compagno(): void
    {
        $classifica = (new ClassificaGiocatori)->calcola([1, 2, 3, 4], [
            $this->partita([1, 2], [3, 4], '2-0', '12-5'),
            // Coppie rimescolate: 1 ora gioca con 4.
            $this->partita([1, 4], [3, 2], '1-2', '15-17'),
        ]);

        $uno = collect($classifica)->firstWhere('giocatore', 1);

        $this->assertSame([2, 1, 1], [$uno->giocate, $uno->vinte, $uno->perse()]);
        $this->assertSame([3, 2, 27, 22], [$uno->setVinti, $uno->setPersi, $uno->gameVinti, $uno->gamePersi]);
        $this->assertSame([1, 5], [$uno->differenzaSet(), $uno->differenzaGame()]);
        $this->assertSame(0.5, $uno->quotaVittorie());

        // 2 ha vinto la prima con 1 e la seconda contro di lui, con 3: due vittorie su due.
        $due = collect($classifica)->firstWhere('giocatore', 2);
        $this->assertSame([2, 2, 4, 1, 29, 20], [$due->giocate, $due->vinte, $due->setVinti, $due->setPersi, $due->gameVinti, $due->gamePersi]);
        $this->assertSame(2, $classifica[0]->giocatore);
    }

    public function test_a_parita_di_finali_contano_vittorie_poi_set_poi_game_in_media_per_partita(): void
    {
        $classifica = (new ClassificaGiocatori)->calcola([1, 2, 3, 4, 5, 6, 7, 8], [
            // 1 vince 2 su 2; 2 vince 1 su 1: stessa quota di vittorie, 2 ha la differenza set per partita migliore.
            $this->partita([1], [5], '2-1', '15-12'),
            $this->partita([1], [6], '2-1', '15-12'),
            $this->partita([2], [7], '2-0', '12-10'),
            // 3 e 4: una vinta su due, stessi set; 4 ha la differenza game migliore.
            $this->partita([3], [8], '2-0', '12-10'),
            $this->partita([8], [3], '2-0', '12-10'),
            $this->partita([4], [8], '2-0', '12-2'),
            $this->partita([8], [4], '2-0', '12-10'),
        ]);

        $this->assertSame([2, 1, 4, 3], array_slice(array_keys($this->posizioni($classifica)), 0, 4));
        $this->assertSame([1, 2, 3, 4], array_slice(array_values($this->posizioni($classifica)), 0, 4));
    }

    public function test_chi_va_piu_avanti_nelle_finali_sta_davanti_anche_con_meno_vittorie(): void
    {
        $classifica = (new ClassificaGiocatori)->calcola(
            [1, 2, 3, 4],
            [
                $this->partita([1], [2], '2-0', '12-0'),
                $this->partita([1], [3], '2-0', '12-0'),
                $this->partita([4], [2], '0-2', '0-12'),
            ],
            // 4 ha vinto il torneo, 2 è finalista, 3 si è fermato in semifinale, 1 non è entrato nelle finali.
            [4 => 5, 2 => 4, 3 => 3],
        );

        $this->assertSame([4 => 1, 2 => 2, 3 => 3, 1 => 4], $this->posizioni($classifica));
        $this->assertSame(RendimentoGiocatore::FUORI_DALLE_FINALI, $classifica[3]->turno);
    }

    public function test_due_giocatori_pari_su_tutto_hanno_la_stessa_posizione(): void
    {
        // 1 e 2 hanno sempre giocato insieme: stessi numeri. Poi 3 e 4, anche loro pari.
        $classifica = (new ClassificaGiocatori)->calcola([4, 3, 2, 1, 5], [
            $this->partita([1, 2], [3, 4], '2-0', '12-5'),
        ]);

        $this->assertSame([1 => 1, 2 => 1, 5 => 3, 3 => 4, 4 => 4], $this->posizioni($classifica));
    }

    public function test_le_partite_di_chi_non_e_in_elenco_non_contano(): void
    {
        $classifica = (new ClassificaGiocatori)->calcola([1], [$this->partita([1, 99], [98, 97], '2-0', '12-5')]);

        $this->assertCount(1, $classifica);
        $this->assertSame(1, $classifica[0]->vinte);
    }

    public function test_i_migliori_di_ogni_categoria_hanno_la_posizione_dentro_la_categoria(): void
    {
        $regola = new ClassificaGiocatori;
        $classifica = $regola->calcola([1, 2, 3, 4, 5, 6, 7], [
            $this->partita([1], [2], '2-0', '12-0'),
            $this->partita([3], [4], '2-0', '12-6'),
            $this->partita([5], [6], '2-1', '14-12'),
        ]);

        // Classifica generale: 1, 3, 5, 6, 4, 2 (7 non ha giocato).
        $migliori = $regola->migliori(
            $classifica,
            [1 => 'over', 2 => 'under', 3 => 'under', 4 => 'over', 5 => 'under', 6 => 'under', 7 => 'over', 99 => 'over'],
            2,
        );

        $this->assertSame(['over', 'under'], array_keys($migliori));
        $this->assertSame([1 => 1, 4 => 2], $this->posizioni($migliori['over']));
        // Nella sua categoria 3 è primo anche se in generale è secondo; restano solo i primi due.
        $this->assertSame([3 => 1, 5 => 2], $this->posizioni($migliori['under']));
    }

    public function test_tra_i_migliori_i_pari_merito_ci_sono_tutti_e_chi_non_ha_categoria_resta_fuori(): void
    {
        $regola = new ClassificaGiocatori;
        $classifica = $regola->calcola([1, 2, 3, 4, 5], [
            $this->partita([1, 2], [3, 4], '2-0', '12-5'),
        ]);

        $migliori = $regola->migliori($classifica, [1 => 'f', 2 => 'f', 3 => 'f', 4 => null, 5 => 'f'], 1);

        // 1 e 2 sono primi a pari merito; 3 è terzo e non rientra; 4 non ha categoria; 5 non ha giocato.
        $this->assertSame(['f'], array_keys($migliori));
        $this->assertSame([1 => 1, 2 => 1], $this->posizioni($migliori['f']));

        // Una categoria vuota (stringa vuota) vale come non indicata.
        $this->assertSame([], $regola->migliori($classifica, [1 => '', 2 => null], 3));
    }

    public function test_chi_e_nelle_finali_senza_aver_ancora_giocato_e_in_classifica(): void
    {
        $regola = new ClassificaGiocatori;
        $classifica = $regola->calcola([1, 2], [], [1 => 3]);

        $this->assertTrue($classifica[0]->inClassifica());
        $this->assertFalse($classifica[1]->inClassifica());
        $this->assertSame([1 => 1], $this->posizioni($regola->migliori($classifica, [1 => 'm', 2 => 'm'], 3)['m']));
    }

    public function test_le_categorie_possono_essere_numeri(): void
    {
        $regola = new ClassificaGiocatori;
        $classifica = $regola->calcola([1, 2], [$this->partita([1], [2], '2-0', '12-5')]);

        $this->assertSame([1, 2], array_keys($regola->migliori($classifica, [1 => 1, 2 => 2], 3)));
    }
}
