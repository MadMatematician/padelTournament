<?php

namespace Tests\Unit\Finali;

use App\Exceptions\SorteggioImpossibileException;
use App\Services\Classifica\RigaClassifica;
use App\Services\Finali\Candidata;
use App\Services\Finali\CriterioRipescaggio;
use App\Services\Finali\Qualificazione;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class QualificazioneTest extends TestCase
{
    /**
     * Una riga di classifica: basta ciò che serve al confronto tra gironi.
     */
    private function riga(int $coppia, int $posizione, int $giocate = 3, int $punti = 0, int $diffSet = 0, int $diffGame = 0): RigaClassifica
    {
        return new RigaClassifica(
            coppia: $coppia, posizione: $posizione, giocate: $giocate,
            vinte: intdiv($punti, 2), perse: $giocate - intdiv($punti, 2), punti: $punti,
            setVinti: max($diffSet, 0), setPersi: max(-$diffSet, 0),
            gameVinti: max($diffGame, 0), gamePersi: max(-$diffGame, 0),
        );
    }

    /**
     * Gironi da quattro coppie con identificativi 101-104, 201-204, …: la prima cifra è il girone,
     * l'ultima la posizione. I punti scendono con la posizione.
     *
     * @return array<int, list<RigaClassifica>>
     */
    private function gironi(int $quanti, int $squadre = 4): array
    {
        $classifiche = [];

        foreach (range(1, $quanti) as $girone) {
            foreach (range(1, $squadre) as $posizione) {
                $classifiche[$girone][] = $this->riga($girone * 100 + $posizione, $posizione, $squadre - 1, ($squadre - $posizione) * 2);
            }
        }

        return $classifiche;
    }

    /**
     * @param  list<Candidata>  $candidate
     * @return list<int>
     */
    private function coppie(array $candidate): array
    {
        return array_map(fn (Candidata $candidata) => $candidata->coppia, $candidate);
    }

    public function test_con_quattro_gironi_e_due_qualificate_i_quarti_sono_completi(): void
    {
        $accesso = (new Qualificazione)->calcola($this->gironi(4), 2, CriterioRipescaggio::Punti);

        $this->assertSame(8, $accesso->dimensione);
        $this->assertEqualsCanonicalizing([101, 201, 301, 401, 102, 202, 302, 402], $this->coppie($accesso->dirette));
        $this->assertSame([], $accesso->ripescate);
        $this->assertSame(0, $accesso->bye());
        $this->assertSame(0, $accesso->ripescaggiNecessari());

        // Teste di serie: prima tutte le prime, poi tutte le seconde.
        $this->assertEqualsCanonicalizing([101, 201, 301, 401], array_slice($this->coppie($accesso->teste()), 0, 4));
    }

    public function test_con_tre_gironi_e_due_qualificate_servono_due_ripescate_tra_le_terze(): void
    {
        $classifiche = $this->gironi(3);
        // Le terze: quella del girone 2 ha più punti, quella del girone 3 una differenza set migliore della 1.
        $classifiche[1][2] = $this->riga(103, 3, 3, 2, diffSet: -2, diffGame: 5);
        $classifiche[2][2] = $this->riga(203, 3, 3, 4, diffSet: -3, diffGame: -9);
        $classifiche[3][2] = $this->riga(303, 3, 3, 2, diffSet: -1, diffGame: -6);

        $accesso = (new Qualificazione)->calcola($classifiche, 2, CriterioRipescaggio::Punti);

        $this->assertSame(8, $accesso->dimensione);
        $this->assertCount(6, $accesso->dirette);
        $this->assertSame(2, $accesso->ripescaggiNecessari());
        $this->assertSame([203, 303], $this->coppie($accesso->ripescate));
        $this->assertSame(0, $accesso->bye());

        // Le ripescate sono le ultime teste di serie.
        $this->assertSame([203, 303], array_slice($this->coppie($accesso->teste()), 6));
    }

    public function test_il_criterio_della_differenza_game_cambia_le_ripescate(): void
    {
        $classifiche = $this->gironi(3);
        $classifiche[1][2] = $this->riga(103, 3, 3, 2, diffSet: -2, diffGame: 5);
        $classifiche[2][2] = $this->riga(203, 3, 3, 4, diffSet: -3, diffGame: -9);
        $classifiche[3][2] = $this->riga(303, 3, 3, 2, diffSet: -1, diffGame: -6);

        $accesso = (new Qualificazione)->calcola($classifiche, 2, CriterioRipescaggio::Game);

        $this->assertSame([103, 303], $this->coppie($accesso->ripescate));
    }

    public function test_il_confronto_tra_gironi_usa_le_medie_per_partita(): void
    {
        // Girone da 4 (tre partite) e girone da 3 (due partite): la terza del girone piccolo ha meno punti
        // in assoluto ma una media migliore.
        $classifiche = [
            1 => [$this->riga(101, 1, 3, 6), $this->riga(102, 2, 3, 4), $this->riga(103, 3, 3, 2), $this->riga(104, 4, 3, 0)],
            2 => [$this->riga(201, 1, 2, 4), $this->riga(202, 2, 2, 2), $this->riga(203, 3, 2, 0)],
            3 => [$this->riga(301, 1, 2, 4), $this->riga(302, 2, 2, 0), $this->riga(303, 3, 2, 2)],
        ];

        $accesso = (new Qualificazione)->calcola($classifiche, 1, CriterioRipescaggio::Punti);

        // Tre prime → tabellone da 4 → una ripescata tra le seconde: 102 ha 4/3 = 1,33 punti a partita, 202 ne ha 1.
        $this->assertSame(4, $accesso->dimensione);
        $this->assertSame([102], $this->coppie($accesso->ripescate));

        // Con due punti in meno la 102 scende a 0,67 di media: passa la 202 (1 di media).
        $classifiche[1][1] = $this->riga(102, 2, 3, 2);

        $this->assertSame([202], $this->coppie((new Qualificazione)->calcola($classifiche, 1, CriterioRipescaggio::Punti)->ripescate));
    }

    public function test_i_ripescaggi_scendono_di_piazzamento_solo_quando_serve(): void
    {
        // Cinque gironi da quattro, due qualificate: dieci dirette, tabellone da sedici, sei ripescate:
        // le cinque terze e la migliore delle quarte.
        $classifiche = $this->gironi(5);
        $classifiche[4][3] = $this->riga(404, 4, 3, 0, diffSet: -2);
        foreach ([1, 2, 3, 5] as $girone) {
            $classifiche[$girone][3] = $this->riga($girone * 100 + 4, 4, 3, 0, diffSet: -6);
        }

        $accesso = (new Qualificazione)->calcola($classifiche, 2, CriterioRipescaggio::Punti);

        $this->assertSame(16, $accesso->dimensione);
        $this->assertCount(10, $accesso->dirette);
        $this->assertSame(6, $accesso->ripescaggiNecessari());
        $this->assertEqualsCanonicalizing([103, 203, 303, 403, 503], array_slice($this->coppie($accesso->ripescate), 0, 5));
        $this->assertSame(404, $accesso->ripescate[5]->coppia);
        $this->assertSame(0, $accesso->bye());
    }

    public function test_se_le_coppie_non_bastano_restano_posti_vuoti(): void
    {
        // Tre gironi da due coppie, tutte qualificate: sei squadre in un tabellone da otto, nessuna da ripescare.
        $accesso = (new Qualificazione)->calcola($this->gironi(3, 2), 2, CriterioRipescaggio::Punti);

        $this->assertSame(8, $accesso->dimensione);
        $this->assertCount(6, $accesso->dirette);
        $this->assertSame([], $accesso->ripescate);
        $this->assertSame(2, $accesso->ripescaggiNecessari());
        $this->assertSame(2, $accesso->bye());
    }

    public function test_le_coppie_escluse_lasciano_il_posto_alla_successiva(): void
    {
        // Nel girone 1 la seconda è esclusa: accede la terza, e il ripescaggio riguarda la quarta.
        $accesso = (new Qualificazione)->calcola($this->gironi(3), 2, CriterioRipescaggio::Punti, escluse: [102, 301]);

        $dirette = $this->coppie($accesso->dirette);

        $this->assertNotContains(102, $dirette);
        $this->assertNotContains(301, $dirette);
        $this->assertContains(103, $dirette);
        $this->assertContains(303, $dirette);
        $this->assertEqualsCanonicalizing([101, 103, 201, 202, 302, 303], $dirette);

        // Piazzamento utile: la 302 è la prima del suo girone tra quelle che possono accedere.
        $perCoppia = [];
        foreach ($accesso->dirette as $candidata) {
            $perCoppia[$candidata->coppia] = $candidata->livello;
        }
        $this->assertSame(1, $perCoppia[302]);
        $this->assertSame(2, $perCoppia[103]);

        // Ripescaggi tra le terze utili (104, 203, 304): la 203 ha due punti, le altre zero e a parità
        // completa passa quella sorteggiata prima (identificativo più basso).
        $this->assertSame([203, 104], $this->coppie($accesso->ripescate));
    }

    public function test_con_il_sorteggio_le_ripescate_cambiano_ma_non_le_qualificate(): void
    {
        $esiti = [];
        $dirette = null;

        foreach (range(1, 30) as $seme) {
            $accesso = (new Qualificazione(new Randomizer(new Mt19937($seme))))
                ->calcola($this->gironi(3), 2, CriterioRipescaggio::Sorteggio);

            $ripescate = $this->coppie($accesso->ripescate);
            sort($ripescate);
            $esiti[implode('-', $ripescate)] = true;

            // Le ripescate sono sempre due delle tre terze.
            $this->assertCount(2, $ripescate);
            $this->assertSame([], array_diff($ripescate, [103, 203, 303]));

            // Le teste di serie delle qualificate non dipendono dal sorteggio.
            $dirette ??= $this->coppie($accesso->dirette);
            $this->assertSame($dirette, $this->coppie($accesso->dirette));
        }

        $this->assertCount(3, $esiti);
    }

    public function test_una_sola_qualificata_per_girone(): void
    {
        $accesso = (new Qualificazione)->calcola($this->gironi(2), 1, CriterioRipescaggio::Punti);

        $this->assertSame(2, $accesso->dimensione);
        $this->assertEqualsCanonicalizing([101, 201], $this->coppie($accesso->dirette));
    }

    public function test_un_solo_girone_con_una_qualificata_non_basta(): void
    {
        $this->expectException(SorteggioImpossibileException::class);

        (new Qualificazione)->calcola($this->gironi(1), 1, CriterioRipescaggio::Punti);
    }

    public function test_troppe_qualificate_per_il_tabellone_vengono_rifiutate(): void
    {
        $this->expectException(SorteggioImpossibileException::class);
        $this->expectExceptionMessage('al massimo 32');

        (new Qualificazione)->calcola($this->gironi(9), 4, CriterioRipescaggio::Punti);
    }
}
