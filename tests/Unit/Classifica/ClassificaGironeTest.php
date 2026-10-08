<?php

namespace Tests\Unit\Classifica;

use App\Services\Classifica\ClassificaGirone;
use App\Services\Classifica\RigaClassifica;
use PHPUnit\Framework\TestCase;

class ClassificaGironeTest extends TestCase
{
    /**
     * Una partita giocata: "6-4 6-3" dal punto di vista della coppia `a`.
     *
     * @return array{a: int, b: int, vincitrice: int, set_a: int, set_b: int, game_a: int, game_b: int}
     */
    private function partita(int $a, int $b, string $punteggio): array
    {
        $setA = $setB = $gameA = $gameB = 0;

        foreach (explode(' ', $punteggio) as $set) {
            [$x, $y] = array_map('intval', explode('-', $set));
            $gameA += $x;
            $gameB += $y;
            $x > $y ? $setA++ : $setB++;
        }

        return ['a' => $a, 'b' => $b, 'vincitrice' => $setA > $setB ? $a : $b, 'set_a' => $setA, 'set_b' => $setB, 'game_a' => $gameA, 'game_b' => $gameB];
    }

    /**
     * @param  list<RigaClassifica>  $righe
     * @return list<int>
     */
    private function ordine(array $righe): array
    {
        return array_map(fn (RigaClassifica $riga) => $riga->coppia, $righe);
    }

    public function test_conta_partite_punti_set_e_game(): void
    {
        $righe = (new ClassificaGirone)->calcola([1, 2, 3], [
            $this->partita(1, 2, '6-4 6-3'),
            $this->partita(3, 1, '6-7 6-2 3-6'),
        ], puntiVittoria: 2);

        $this->assertSame([1, 3, 2], $this->ordine($righe));

        [$prima, $seconda, $terza] = $righe;

        $this->assertSame([1, 2, 3], [$prima->posizione, $seconda->posizione, $terza->posizione]);
        $this->assertSame([2, 2, 0, 4], [$prima->giocate, $prima->vinte, $prima->perse, $prima->punti]);
        $this->assertSame([4, 1], [$prima->setVinti, $prima->setPersi]);
        $this->assertSame([27, 22], [$prima->gameVinti, $prima->gamePersi]);
        $this->assertSame([3, 5], [$prima->differenzaSet(), $prima->differenzaGame()]);

        // Chi ha perso al terzo set sta davanti a chi ha perso in due.
        $this->assertSame([1, 0, 1, 0], [$seconda->giocate, $seconda->vinte, $seconda->perse, $seconda->punti]);
        $this->assertSame(-1, $seconda->differenzaSet());
        $this->assertSame(-2, $terza->differenzaSet());
    }

    public function test_chi_non_ha_ancora_giocato_compare_in_classifica(): void
    {
        $righe = (new ClassificaGirone)->calcola([5, 6, 7], [$this->partita(6, 7, '6-0 6-0')], puntiVittoria: 2);

        // La 5 non ha giocato: sta dietro a chi ha vinto e davanti a chi ha perso male.
        $this->assertSame([6, 5, 7], $this->ordine($righe));
        $this->assertSame(0, $righe[1]->giocate);
    }

    public function test_senza_partite_l_ordine_e_quello_delle_coppie(): void
    {
        $this->assertSame([3, 8, 9], $this->ordine((new ClassificaGirone)->calcola([8, 3, 9], [], puntiVittoria: 2)));
    }

    public function test_a_pari_punti_decide_lo_scontro_diretto(): void
    {
        // 1 e 2 hanno una vittoria ciascuna; la 2 ha game migliori, ma la 1 ha vinto lo scontro diretto.
        $righe = (new ClassificaGirone)->calcola([1, 2, 3, 4], [
            $this->partita(1, 2, '7-6 7-6'),
            $this->partita(2, 3, '6-0 6-0'),
            $this->partita(4, 1, '6-0 6-0'),
            $this->partita(4, 2, '6-0 6-0'),
            $this->partita(4, 3, '6-0 6-0'),
            $this->partita(3, 1, '6-4 6-4'),
        ], puntiVittoria: 2);

        // 4 a punteggio pieno; 1, 2 e 3 a una vittoria: negli scontri diretti tra loro sono di nuovo pari
        // (una vittoria a testa), quindi decide la differenza set e poi game.
        $this->assertSame(4, $righe[0]->coppia);
        $this->assertSame([2, 2, 2], [$righe[1]->punti, $righe[2]->punti, $righe[3]->punti]);

        // Solo due a pari punti: decide lo scontro diretto, anche contro la differenza game.
        $righe = (new ClassificaGirone)->calcola([1, 2, 3], [
            $this->partita(1, 2, '7-6 7-6'),
            $this->partita(2, 3, '6-0 6-0'),
            $this->partita(3, 1, '6-0 6-0'),
        ], puntiVittoria: 2);

        $this->assertSame([2, 2, 2], array_map(fn (RigaClassifica $riga) => $riga->punti, $righe));

        $righe = (new ClassificaGirone)->calcola([1, 2, 3], [
            $this->partita(1, 2, '7-6 7-6'),
            $this->partita(2, 3, '6-0 6-0'),
            $this->partita(1, 3, '0-6 0-6'),
            $this->partita(3, 2, '0-6 0-6'),
        ], puntiVittoria: 2);

        // 2 a quattro punti; 1 e 3 a due punti: tra loro ha vinto la 3.
        $this->assertSame([2, 3, 1], $this->ordine($righe));
    }

    public function test_due_coppie_a_pari_punti_la_vincente_dello_scontro_diretto_sta_davanti(): void
    {
        $righe = (new ClassificaGirone)->calcola([1, 2, 3, 4], [
            $this->partita(1, 2, '7-6 7-6'),   // la 1 batte la 2 di misura
            $this->partita(2, 3, '6-0 6-0'),   // la 2 stravince le altre
            $this->partita(2, 4, '6-0 6-0'),
            $this->partita(1, 3, '7-5 7-5'),
            $this->partita(4, 1, '6-0 6-0'),   // la 1 perde male dalla 4
            $this->partita(3, 4, '6-4 6-4'),
        ], puntiVittoria: 2);

        // 1 e 2 a quattro punti: la 2 ha set e game migliori, ma lo scontro diretto l'ha vinto la 1.
        $this->assertSame([1, 2], [$righe[0]->coppia, $righe[1]->coppia]);
        $this->assertGreaterThan($righe[0]->differenzaGame(), $righe[1]->differenzaGame());
    }

    public function test_a_parita_anche_negli_scontri_diretti_decidono_set_e_game(): void
    {
        // Tre coppie in cerchio: una vittoria a testa, tutte per 2-0.
        $righe = (new ClassificaGirone)->calcola([1, 2, 3], [
            $this->partita(1, 2, '6-4 6-4'),
            $this->partita(2, 3, '6-1 6-1'),
            $this->partita(3, 1, '6-3 6-3'),
        ], puntiVittoria: 2);

        // Differenza set uguale (0): decide la differenza game. 2: +10-4 = +6; 3: +6-10 = -4; 1: +4-6 = -2.
        $this->assertSame([2, 1, 3], $this->ordine($righe));
    }

    public function test_i_punti_per_vittoria_e_sconfitta_si_possono_cambiare(): void
    {
        $righe = (new ClassificaGirone)->calcola([1, 2], [$this->partita(1, 2, '6-4 6-4')], puntiVittoria: 3, puntiSconfitta: 1);

        $this->assertSame([3, 1], [$righe[0]->punti, $righe[1]->punti]);
    }

    public function test_andata_e_ritorno_contano_tutte_e_due(): void
    {
        $righe = (new ClassificaGirone)->calcola([1, 2], [
            $this->partita(1, 2, '6-4 6-4'),
            $this->partita(2, 1, '6-0 6-0'),
        ], puntiVittoria: 2);

        // Una vittoria a testa anche negli scontri diretti: decide la differenza game.
        $this->assertSame([2, 1], $this->ordine($righe));
        $this->assertSame([2, 2], [$righe[0]->giocate, $righe[1]->giocate]);
    }

    public function test_le_partite_con_coppie_di_altri_gironi_vengono_ignorate(): void
    {
        $righe = (new ClassificaGirone)->calcola([1, 2], [$this->partita(1, 99, '6-0 6-0')], puntiVittoria: 2);

        $this->assertSame(1, $righe[0]->giocate);
        $this->assertSame(0, $righe[1]->giocate);
        $this->assertCount(2, $righe);
    }
}
