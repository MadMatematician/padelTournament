<?php

namespace Tests\Feature\Amministrazione;

use App\Models\Coppia;
use App\Models\Giocatore;
use App\Models\Girone;
use App\Models\Partita;
use App\Models\Torneo;
use App\Models\User;
use App\Services\Sorteggio\LatiComplementari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FormulaClassica;
use Tests\TestCase;

/**
 * Formula e sorteggio di un torneo: coppie → gironi → partite, prestito con fasce non pari,
 * chiusura delle iscrizioni.
 */
class SorteggioTorneoTest extends TestCase
{
    use FormulaClassica;
    use RefreshDatabase;

    private User $amministratore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->amministratore = User::factory()->amministratore()->create();
    }

    /**
     * Un torneo con gli iscritti indicati per fascia.
     *
     * @param  array<string, mixed>  $formula
     */
    private function torneo(int $prima, int $seconda, array $formula = []): Torneo
    {
        $torneo = Torneo::factory()->create($formula);

        $torneo->giocatori()->attach(Giocatore::factory()->fascia(1)->count($prima)->create(['lato' => null]));
        $torneo->giocatori()->attach(Giocatore::factory()->fascia(2)->count($seconda)->create(['lato' => null]));

        return $torneo;
    }

    /** @return Collection<int, Giocatore> */
    private function iscritti(Torneo $torneo, int $fascia): Collection
    {
        return $torneo->giocatori()->where('fascia', $fascia)->orderBy('giocatori.id')->get();
    }

    private function generaCoppie(Torneo $torneo, array $dati = [])
    {
        return $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.coppie.store', $torneo), $dati);
    }

    private function generaGironi(Torneo $torneo)
    {
        return $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.gironi.store', $torneo));
    }

    private function generaPartite(Torneo $torneo)
    {
        return $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.partite.store', $torneo));
    }

    /**
     * Tutti i campi del form della formula, con i valori predefiniti e le modifiche indicate.
     *
     * @param  array<string, mixed>  $modifiche
     * @return array<string, mixed>
     */
    private function formula(array $modifiche = []): array
    {
        $formati = [];
        foreach (array_keys(config('tornei.fasi')) as $fase) {
            $formati[$fase] = ['set' => '3', 'game' => 6, 'tie_break' => 'standard', 'punti_tie_break' => 7];
        }

        return array_replace_recursive([
            'lati_complementari' => 'ignora',
            'squadre_per_girone' => 4,
            'andata_ritorno' => '0',
            'qualificate_per_girone' => 2,
            'criterio_ripescaggio' => 'punti',
            'formati' => $formati,
        ], $modifiche);
    }

    private function paginaDel(Torneo $torneo): string
    {
        return route('amministrazione.tornei.show', $torneo).'#sorteggio';
    }

    // --- Pagina e formula --------------------------------------------------------------------------

    public function test_la_pagina_mostra_formula_e_passi_del_sorteggio(): void
    {
        $torneo = $this->torneo(4, 4);

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Formula')
            ->assertSee('Squadre per girone (massimo)')
            ->assertSee('Genera coppie')
            ->assertSee('Genera gironi')
            ->assertSee('Genera partite')
            ->assertSee(route('amministrazione.tornei.coppie.store', $torneo))
            ->assertDontSee('Annulla sorteggio')
            ->assertDontSee('modale-prestito')
            ->getContent();

        // Lati ignorati (sorteggio del tutto casuale) e sola andata; 4 squadre per girone è la formula classica (trait).
        $this->assertMatchesRegularExpression('/id="formula-squadre"\s+value="4"/', $html);
        $this->assertMatchesRegularExpression('/<option value="0" selected>Sola andata<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<option value="ignora" selected>/', $html);
        $this->assertSame(LatiComplementari::Ignora, $torneo->refresh()->lati_complementari);
        $this->assertSame(config('tornei.tipologie.sociale.sorteggio.lati_complementari'), $torneo->lati_complementari->value);

        // Finché non ci sono le coppie si può generare solo quelle.
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*(?<!disabled)>\s*<i class="bi bi-people"[^>]*><\/i>\s*Genera coppie/', $html);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*disabled[^>]*>\s*<i class="bi bi-diagram-3"[^>]*><\/i>\s*Genera gironi/', $html);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*disabled[^>]*>\s*<i class="bi bi-calendar3"[^>]*><\/i>\s*Genera partite/', $html);
    }

    public function test_senza_una_fascia_le_coppie_non_si_possono_generare(): void
    {
        $torneo = $this->torneo(3, 0);

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Servono giocatori di entrambe le fasce.')
            ->getContent();

        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*disabled[^>]*>\s*<i class="bi bi-people"[^>]*><\/i>\s*Genera coppie/', $html);

        $this->generaCoppie($torneo)
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('errore');

        $this->assertSame(0, Coppia::count());
    }

    public function test_l_amministratore_salva_la_formula(): void
    {
        $torneo = $this->torneo(2, 2);

        $this->actingAs($this->amministratore)
            ->patch(route('amministrazione.tornei.formula.update', $torneo), $this->formula(['squadre_per_girone' => 6, 'andata_ritorno' => '1', 'lati_complementari' => 'preferito']))
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('status', 'Formula salvata: fino a 6 squadre per girone, andata e ritorno, 2 squadre per girone alle finali.');

        $torneo->refresh();

        $this->assertSame(6, $torneo->squadre_per_girone);
        $this->assertTrue($torneo->andata_ritorno);
        $this->assertSame(LatiComplementari::Preferito, $torneo->lati_complementari);

        $html = $this->actingAs($this->amministratore)->get(route('amministrazione.tornei.show', $torneo))->getContent();

        $this->assertMatchesRegularExpression('/id="formula-squadre"\s+value="6"/', $html);
        $this->assertMatchesRegularExpression('/<option value="1" selected>Andata e ritorno<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<option value="preferito" selected>/', $html);
        $this->assertStringContainsString('preferendo le coppie destra + sinistra', $html);
    }

    /**
     * @param  array<string, mixed>  $dati
     */
    #[DataProvider('formuleNonValide')]
    public function test_una_formula_non_valida_viene_rifiutata(array $dati, string $campo): void
    {
        $torneo = $this->torneo(0, 0);

        $this->actingAs($this->amministratore)
            ->from(route('amministrazione.tornei.show', $torneo))
            ->patch(route('amministrazione.tornei.formula.update', $torneo), $this->formula($dati))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHasErrorsIn('formula', [$campo]);

        $this->assertSame(4, $torneo->refresh()->squadre_per_girone);
        $this->assertFalse($torneo->andata_ritorno);
        $this->assertSame(LatiComplementari::Ignora, $torneo->lati_complementari);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function formuleNonValide(): array
    {
        return [
            'una sola squadra' => [['squadre_per_girone' => 1], 'squadre_per_girone'],
            'troppe squadre' => [['squadre_per_girone' => 99], 'squadre_per_girone'],
            'squadre mancanti' => [['squadre_per_girone' => ''], 'squadre_per_girone'],
            'squadre non numeriche' => [['squadre_per_girone' => 'tante'], 'squadre_per_girone'],
            'formula sconosciuta' => [['andata_ritorno' => '2'], 'andata_ritorno'],
            'formula mancante' => [['andata_ritorno' => ''], 'andata_ritorno'],
            'lati sconosciuti' => [['lati_complementari' => 'a piacere'], 'lati_complementari'],
            'lati mancanti' => [['lati_complementari' => ''], 'lati_complementari'],
        ];
    }

    // --- Coppie, fasce pari -------------------------------------------------------------------------

    public function test_genera_le_coppie_con_un_giocatore_per_fascia(): void
    {
        $torneo = $this->torneo(6, 6);
        $altroTorneo = $this->torneo(2, 2);

        $this->generaCoppie($torneo)
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('status', '6 coppie generate.');

        $coppie = $torneo->coppie()->get();

        $this->assertCount(6, $coppie);
        $this->assertEqualsCanonicalizing($this->iscritti($torneo, 1)->modelKeys(), $coppie->pluck('prima_fascia_id')->all());
        $this->assertEqualsCanonicalizing($this->iscritti($torneo, 2)->modelKeys(), $coppie->pluck('seconda_fascia_id')->all());
        $this->assertTrue($coppie->every(fn (Coppia $coppia) => $coppia->prestito_id === null && $coppia->girone_id === null));

        // Il sorteggio riguarda solo questo torneo.
        $this->assertSame(0, $altroTorneo->coppie()->count());
    }

    // --- Coppie: lati preferiti secondo la formula ----------------------------------------------------

    /**
     * Due giocatori per fascia, uno di destra e uno di sinistra: le coppie destra + sinistra sono
     * obbligate (un solo abbinamento possibile), quelle a caso no.
     */
    private function torneoConLatiIncrociati(string $lati): Torneo
    {
        $torneo = Torneo::factory()->create(['lati_complementari' => $lati]);

        foreach ([1, 2] as $fascia) {
            foreach (['destra', 'sinistra'] as $lato) {
                $torneo->giocatori()->attach(Giocatore::factory()->fascia($fascia)->create(['lato' => $lato]));
            }
        }

        return $torneo;
    }

    /** Quante coppie del torneo hanno i due giocatori sullo stesso lato. */
    private function coppieConLoStessoLato(Torneo $torneo): int
    {
        return $torneo->coppie()->with(['primaFascia', 'secondaFascia'])->get()
            ->filter(fn (Coppia $coppia) => $coppia->primaFascia->lato === $coppia->secondaFascia->lato)
            ->count();
    }

    public function test_di_norma_i_lati_non_contano_e_il_sorteggio_e_del_tutto_casuale(): void
    {
        $torneo = $this->torneoConLatiIncrociati('ignora');
        $esiti = [];

        foreach (range(1, 40) as $giro) {
            $this->generaCoppie($torneo)->assertSessionHas('status', '2 coppie generate.');
            $esiti[$this->coppieConLoStessoLato($torneo)] = true;
        }

        // Escono sia l'abbinamento destra + sinistra sia quello con i lati uguali: nessuno dei due è obbligato.
        ksort($esiti);
        $this->assertSame([0, 2], array_keys($esiti));
    }

    public function test_con_i_lati_preferiti_escono_le_coppie_destra_piu_sinistra(): void
    {
        $torneo = $this->torneoConLatiIncrociati('preferito');

        foreach (range(1, 20) as $giro) {
            $this->generaCoppie($torneo);

            $this->assertSame(0, $this->coppieConLoStessoLato($torneo), 'giro '.$giro);
        }
    }

    public function test_con_i_lati_obbligatori_un_abbinamento_impossibile_viene_rifiutato(): void
    {
        $torneo = Torneo::factory()->create(['lati_complementari' => 'obbligatorio']);
        $torneo->giocatori()->attach(Giocatore::factory()->fascia(1)->count(2)->create(['lato' => 'destra']));
        $torneo->giocatori()->attach(Giocatore::factory()->fascia(2)->count(2)->create(['lato' => 'destra']));

        $this->generaCoppie($torneo)
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('errore', 'Con i giocatori iscritti non si riescono a formare solo coppie destra + sinistra.');

        $this->assertSame(0, Coppia::count());

        // Lo stesso torneo, con i lati solo preferiti, si sorteggia.
        $torneo->update(['lati_complementari' => 'preferito']);

        $this->generaCoppie($torneo)->assertSessionHas('status', '2 coppie generate.');
    }

    public function test_cambiare_i_lati_nella_formula_vale_dal_sorteggio_successivo(): void
    {
        $torneo = $this->torneoConLatiIncrociati('ignora');

        $this->actingAs($this->amministratore)
            ->patch(route('amministrazione.tornei.formula.update', $torneo), $this->formula(['lati_complementari' => 'preferito']))
            ->assertSessionHas('status');

        foreach (range(1, 20) as $giro) {
            $this->generaCoppie($torneo);

            $this->assertSame(0, $this->coppieConLoStessoLato($torneo), 'giro '.$giro);
        }
    }

    public function test_dopo_il_sorteggio_la_pagina_elenca_le_coppie(): void
    {
        $torneo = $this->torneo(2, 2);
        $this->generaCoppie($torneo);

        $coppia = $torneo->coppie()->with(['primaFascia', 'secondaFascia'])->first();

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Rigenera coppie')
            ->assertSee('Annulla sorteggio')
            ->assertSee($coppia->primaFascia->cognome.' '.$coppia->primaFascia->nome)
            ->assertSee($coppia->secondaFascia->cognome.' '.$coppia->secondaFascia->nome)
            ->assertSee('2 coppie.')
            ->assertSee('Genera gironi')
            ->assertViewHas('coppie', fn ($coppie) => $coppie->count() === 2);
    }

    public function test_rifare_le_coppie_cancella_gironi_e_partite(): void
    {
        $torneo = $this->torneo(4, 4, ['squadre_per_girone' => 2]);

        $this->generaCoppie($torneo);
        $this->generaGironi($torneo);
        $this->generaPartite($torneo);

        $this->assertSame(2, $torneo->gironi()->count());
        $this->assertSame(2, $torneo->partite()->count());

        $vecchie = $torneo->coppie()->pluck('id')->all();

        $this->generaCoppie($torneo)->assertSessionHas('status');

        $this->assertSame(4, $torneo->coppie()->count());
        $this->assertSame([], array_intersect($vecchie, $torneo->coppie()->pluck('id')->all()));
        $this->assertSame(0, Girone::count());
        $this->assertSame(0, Partita::count());
    }

    // --- Coppie, fasce non pari: il prestito ---------------------------------------------------------

    public function test_con_le_fasce_non_pari_la_pagina_propone_la_finestra_del_prestito(): void
    {
        $torneo = $this->torneo(5, 3, ['squadre_per_girone' => 3]);
        $candidato = $this->iscritti($torneo, 2)->first();
        $nonCandidato = $this->iscritti($torneo, 1)->first();

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Fasce non pari')
            ->assertSee('2 giocatori di prima fascia restano senza compagno.')
            ->assertSee('Sorteggia il prestito')
            ->assertSee('Usa i giocatori indicati')
            ->assertViewHas('squilibrio', fn ($squilibrio) => $squilibrio->inEccesso === 2
                && $squilibrio->fasciaInEccesso === 1
                && $squilibrio->fasciaMancante === 2
                && $squilibrio->prestitoPossibile())
            ->getContent();

        // «Genera coppie» apre la finestra invece di inviare il form; la finestra è chiusa.
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-bs-target="#modale-prestito"[^>]*>\s*<i[^>]*><\/i>\s*Genera coppie/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="modale-prestito"[^>]*data-apri-modale/', $html);
        // In prestito può andare solo chi è della fascia con meno giocatori.
        $this->assertStringContainsString('id="prestito-giocatore-'.$candidato->id.'"', $html);
        $this->assertStringNotContainsString('id="prestito-giocatore-'.$nonCandidato->id.'"', $html);
        $this->assertStringContainsString('name="prestito" value="sorteggia"', $html);
        $this->assertStringContainsString('name="prestito" value="indicati"', $html);
    }

    public function test_con_le_fasce_non_pari_senza_una_scelta_le_coppie_non_vengono_generate(): void
    {
        $torneo = $this->torneo(5, 3, ['squadre_per_girone' => 3]);

        $risposta = $this->actingAs($this->amministratore)
            ->followingRedirects()
            ->post(route('amministrazione.tornei.coppie.store', $torneo));

        $risposta->assertOk()->assertSee('Le fasce non sono pari: scegli come completare le coppie.');

        // La finestra del prestito si riapre da sola.
        $this->assertMatchesRegularExpression('/id="modale-prestito"[^>]*data-apri-modale/', $risposta->getContent());
        $this->assertSame(0, Coppia::count());
    }

    public function test_il_prestito_sorteggiato_completa_le_coppie(): void
    {
        $torneo = $this->torneo(5, 3, ['squadre_per_girone' => 3]);

        $this->generaCoppie($torneo, ['prestito' => 'sorteggia'])
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('status', '5 coppie generate, 2 con un giocatore in prestito.');

        $coppie = $torneo->coppie()->get();
        $seconda = $this->iscritti($torneo, 2)->modelKeys();

        // Ogni giocatore di prima fascia ha la sua coppia…
        $this->assertCount(5, $coppie);
        $this->assertEqualsCanonicalizing($this->iscritti($torneo, 1)->modelKeys(), $coppie->pluck('prima_fascia_id')->all());

        // …due coppie sono completate da un giocatore di seconda fascia in prestito, uno diverso per coppia…
        $conPrestito = $coppie->whereNotNull('prestito_id');
        $this->assertCount(2, $conPrestito);
        $this->assertCount(2, $conPrestito->pluck('prestito_id')->unique());

        foreach ($conPrestito as $coppia) {
            $this->assertSame($coppia->seconda_fascia_id, $coppia->prestito_id);
            $this->assertContains($coppia->prestito_id, $seconda);
        }

        // …e chi è in prestito ha anche la sua coppia ordinaria.
        $ordinarie = $coppie->whereNull('prestito_id');
        $this->assertEqualsCanonicalizing($seconda, $ordinarie->pluck('seconda_fascia_id')->all());
    }

    public function test_va_in_prestito_chi_viene_indicato(): void
    {
        $torneo = $this->torneo(3, 5, ['squadre_per_girone' => 3]);
        // Qui è la prima fascia ad avere meno giocatori: i prestiti vengono da lì.
        $indicati = $this->iscritti($torneo, 1)->take(2)->modelKeys();

        $this->generaCoppie($torneo, ['prestito' => 'indicati', 'giocatori_prestito' => $indicati])
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('status');

        $conPrestito = $torneo->coppie()->whereNotNull('prestito_id')->get();

        $this->assertSame(5, $torneo->coppie()->count());
        $this->assertEqualsCanonicalizing($indicati, $conPrestito->pluck('prestito_id')->all());
        $this->assertEqualsCanonicalizing($indicati, $conPrestito->pluck('prima_fascia_id')->all());
    }

    public function test_il_prestito_di_un_solo_giocatore_ha_il_suo_messaggio(): void
    {
        $torneo = $this->torneo(3, 2, ['squadre_per_girone' => 2]);

        $this->generaCoppie($torneo, ['prestito' => 'sorteggia'])
            ->assertSessionHas('status', '3 coppie generate, una con un giocatore in prestito.');
    }

    public function test_i_prestiti_indicati_male_vengono_rifiutati_e_la_finestra_si_riapre(): void
    {
        $torneo = $this->torneo(5, 3, ['squadre_per_girone' => 3]);
        $seconda = $this->iscritti($torneo, 2)->modelKeys();
        $prima = $this->iscritti($torneo, 1)->modelKeys();
        $estraneo = Giocatore::factory()->fascia(2)->create();

        $tentativi = [
            'nessuno indicato' => [],
            'troppo pochi' => [$seconda[0]],
            'troppi' => $seconda,
            'della fascia sbagliata' => [$seconda[0], $prima[0]],
            'non iscritto' => [$seconda[0], $estraneo->id],
            'ripetuto' => [$seconda[0], $seconda[0]],
        ];

        foreach ($tentativi as $caso => $indicati) {
            $this->generaCoppie($torneo, ['prestito' => 'indicati', 'giocatori_prestito' => $indicati])
                ->assertRedirect()
                ->assertSessionHasErrorsIn('sorteggio');

            $this->assertSame(0, Coppia::count(), $caso);
        }

        // La finestra si riapre con l'errore e con i giocatori che erano stati spuntati.
        $risposta = $this->actingAs($this->amministratore)
            ->followingRedirects()
            ->post(route('amministrazione.tornei.coppie.store', $torneo), ['prestito' => 'indicati', 'giocatori_prestito' => [$seconda[0]]]);

        $risposta->assertOk()->assertSee('Vanno indicati 2 giocatori in prestito, tutti diversi.');

        $html = $risposta->getContent();

        $this->assertMatchesRegularExpression('/id="modale-prestito"[^>]*data-apri-modale/', $html);
        $this->assertMatchesRegularExpression('/id="prestito-giocatore-'.$seconda[0].'"[^>]*\schecked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="prestito-giocatore-'.$seconda[1].'"[^>]*\schecked/', $html);
    }

    public function test_una_scelta_sconosciuta_viene_rifiutata(): void
    {
        $torneo = $this->torneo(5, 3, ['squadre_per_girone' => 3]);

        $this->generaCoppie($torneo, ['prestito' => 'a caso'])
            ->assertSessionHasErrorsIn('sorteggio', ['prestito']);

        $this->assertSame(0, Coppia::count());
    }

    public function test_con_un_solo_girone_il_prestito_non_e_possibile(): void
    {
        // 3 coppie e fino a 4 squadre per girone: un solo girone, chi è in prestito giocherebbe contro se stesso.
        $torneo = $this->torneo(3, 2, ['squadre_per_girone' => 4]);

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('ci sarebbe un solo girone')
            ->assertViewHas('squilibrio', fn ($squilibrio) => ! $squilibrio->prestitoPossibile())
            ->getContent();

        $this->assertStringNotContainsString('name="prestito"', $html);

        $this->generaCoppie($torneo, ['prestito' => 'sorteggia'])
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('errore');

        $this->assertSame(0, Coppia::count());

        // Riducendo le squadre per girone i gironi diventano due e il prestito si può fare.
        $torneo->update(['squadre_per_girone' => 2]);

        $this->generaCoppie($torneo, ['prestito' => 'sorteggia'])->assertSessionHas('status');

        $this->assertSame(3, $torneo->coppie()->count());
    }

    public function test_con_fasce_troppo_sbilanciate_il_prestito_non_e_possibile(): void
    {
        // 5 contro 2: servirebbero 3 prestiti, ma ogni giocatore può andare in prestito una volta sola.
        $torneo = $this->torneo(5, 2, ['squadre_per_girone' => 2]);

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Le fasce sono troppo sbilanciate');

        $this->generaCoppie($torneo, ['prestito' => 'sorteggia'])->assertSessionHas('errore');

        $this->assertSame(0, Coppia::count());
    }

    public function test_con_le_fasce_pari_i_dati_del_prestito_vengono_ignorati(): void
    {
        $torneo = $this->torneo(3, 3);

        $this->generaCoppie($torneo, ['prestito' => 'indicati', 'giocatori_prestito' => [$this->iscritti($torneo, 2)->first()->id]])
            ->assertSessionHas('status', '3 coppie generate.');

        $this->assertSame(0, $torneo->coppie()->whereNotNull('prestito_id')->count());
    }

    // --- Gironi ---------------------------------------------------------------------------------------

    public function test_i_gironi_dipendono_dal_numero_di_coppie_e_dal_massimo_di_squadre(): void
    {
        $torneo = $this->torneo(10, 10, ['squadre_per_girone' => 4]);
        $this->generaCoppie($torneo);

        $this->generaGironi($torneo)
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('status', 'Generati 3 gironi.');

        $gironi = $torneo->gironi()->withCount('coppie')->get();

        // 10 coppie, al massimo 4 per girone: 3 gironi il più possibile uguali.
        $this->assertSame(['Girone A', 'Girone B', 'Girone C'], $gironi->pluck('nome')->all());
        $this->assertEqualsCanonicalizing([4, 3, 3], $gironi->pluck('coppie_count')->all());
        $this->assertSame(0, $torneo->coppie()->whereNull('girone_id')->count());
    }

    public function test_cambiare_la_formula_e_rifare_i_gironi_ne_cambia_il_numero_e_cancella_le_partite(): void
    {
        $torneo = $this->torneo(6, 6, ['squadre_per_girone' => 6]);
        $this->generaCoppie($torneo);

        $this->generaGironi($torneo)->assertSessionHas('status', 'Generato un girone.');
        $this->generaPartite($torneo);

        $this->assertSame(15, $torneo->partite()->count());

        $this->actingAs($this->amministratore)
            ->patch(route('amministrazione.tornei.formula.update', $torneo), $this->formula(['squadre_per_girone' => 3]));

        // Salvare la formula non tocca ciò che è già stato generato…
        $this->assertSame(1, $torneo->gironi()->count());
        $this->assertSame(15, $torneo->partite()->count());

        // …vale da quando si rifanno i gironi.
        $this->generaGironi($torneo->refresh());

        $this->assertSame(2, $torneo->gironi()->count());
        $this->assertSame(0, Partita::count());
        $this->assertSame(6, $torneo->coppie()->whereNotNull('girone_id')->count());
    }

    public function test_le_due_coppie_di_chi_e_in_prestito_stanno_in_gironi_diversi(): void
    {
        foreach (range(1, 15) as $giro) {
            $torneo = $this->torneo(7, 5, ['squadre_per_girone' => 4]);

            $this->generaCoppie($torneo, ['prestito' => 'sorteggia']);
            $this->generaGironi($torneo)->assertSessionHas('status', 'Generati 2 gironi.');

            $coppie = $torneo->coppie()->get();

            foreach ($coppie->whereNotNull('prestito_id') as $conPrestito) {
                $ordinaria = $coppie->first(fn (Coppia $coppia) => $coppia->prestito_id === null
                    && $coppia->seconda_fascia_id === $conPrestito->prestito_id);

                $this->assertNotNull($ordinaria);
                $this->assertNotSame($ordinaria->girone_id, $conPrestito->girone_id, 'giro '.$giro);
            }
        }
    }

    public function test_se_la_formula_cambia_e_resta_un_solo_girone_i_gironi_con_prestito_vengono_rifiutati(): void
    {
        $torneo = $this->torneo(3, 2, ['squadre_per_girone' => 2]);
        $this->generaCoppie($torneo, ['prestito' => 'sorteggia']);

        $torneo->update(['squadre_per_girone' => 6]);

        $this->generaGironi($torneo)
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('errore');

        $this->assertSame(0, Girone::count());
    }

    public function test_senza_coppie_non_si_generano_i_gironi(): void
    {
        $torneo = $this->torneo(4, 4);

        $this->generaGironi($torneo)->assertSessionHas('errore', 'Prima dei gironi vanno generate le coppie.');

        $this->assertSame(0, Girone::count());
    }

    public function test_con_una_sola_coppia_non_si_generano_i_gironi(): void
    {
        $torneo = $this->torneo(1, 1);
        $this->generaCoppie($torneo);

        $this->generaGironi($torneo)->assertSessionHas('errore', 'Per formare i gironi servono almeno due coppie.');

        $this->assertSame(0, Girone::count());
    }

    // --- Partite --------------------------------------------------------------------------------------

    public function test_le_partite_sono_all_italiana_dentro_ogni_girone(): void
    {
        $torneo = $this->torneo(7, 7, ['squadre_per_girone' => 4]);
        $this->generaCoppie($torneo);
        $this->generaGironi($torneo);

        // Due gironi, da 4 e da 3 coppie: 6 + 3 partite.
        $this->generaPartite($torneo)
            ->assertRedirect($this->paginaDel($torneo))
            ->assertSessionHas('status', '9 partite generate (sola andata).');

        foreach ($torneo->gironi()->with(['coppie', 'partite'])->get() as $girone) {
            $coppie = $girone->coppie->modelKeys();
            $incontri = [];

            foreach ($girone->partite as $partita) {
                // Si affrontano solo coppie dello stesso girone, mai una coppia con se stessa.
                $this->assertContains($partita->coppia_a_id, $coppie);
                $this->assertContains($partita->coppia_b_id, $coppie);
                $this->assertNotSame($partita->coppia_a_id, $partita->coppia_b_id);
                $this->assertFalse($partita->ritorno);

                $incontri[] = min($partita->coppia_a_id, $partita->coppia_b_id).'-'.max($partita->coppia_a_id, $partita->coppia_b_id);
            }

            // Ogni coppia incontra tutte le altre una volta sola.
            $this->assertCount(count($coppie) * (count($coppie) - 1) / 2, $incontri);
            $this->assertCount(count($incontri), array_unique($incontri));
        }
    }

    public function test_con_andata_e_ritorno_ogni_incontro_si_gioca_due_volte(): void
    {
        $torneo = $this->torneo(4, 4, ['squadre_per_girone' => 4, 'andata_ritorno' => true]);
        $this->generaCoppie($torneo);
        $this->generaGironi($torneo);

        $this->generaPartite($torneo)->assertSessionHas('status', '12 partite generate (andata e ritorno).');

        $partite = $torneo->partite()->get();
        $andata = $partite->where('ritorno', false);
        $ritorno = $partite->where('ritorno', true);

        $this->assertCount(6, $andata);
        $this->assertCount(6, $ritorno);
        $this->assertEqualsCanonicalizing(
            $andata->map(fn (Partita $partita) => $partita->coppia_a_id.'-'.$partita->coppia_b_id)->all(),
            $ritorno->map(fn (Partita $partita) => $partita->coppia_b_id.'-'.$partita->coppia_a_id)->all(),
        );
        // 3 giornate di andata, poi 3 di ritorno.
        $this->assertSame([1, 2, 3], $andata->pluck('giornata')->unique()->sort()->values()->all());
        $this->assertSame([4, 5, 6], $ritorno->pluck('giornata')->unique()->sort()->values()->all());
    }

    public function test_rifare_le_partite_sostituisce_il_calendario(): void
    {
        $torneo = $this->torneo(4, 4);
        $this->generaCoppie($torneo);
        $this->generaGironi($torneo);
        $this->generaPartite($torneo);
        $this->generaPartite($torneo);

        $this->assertSame(6, Partita::count());
    }

    public function test_senza_gironi_non_si_generano_le_partite(): void
    {
        $torneo = $this->torneo(4, 4);
        $this->generaCoppie($torneo);

        $this->generaPartite($torneo)->assertSessionHas('errore', 'Prima delle partite vanno generati i gironi.');

        $this->assertSame(0, Partita::count());
    }

    public function test_la_pagina_mostra_gironi_e_calendario(): void
    {
        $torneo = $this->torneo(4, 4, ['squadre_per_girone' => 2, 'andata_ritorno' => true]);
        $this->generaCoppie($torneo);
        $this->generaGironi($torneo);
        $this->generaPartite($torneo);

        $partita = $torneo->partite()->with(['coppiaA.primaFascia', 'coppiaA.secondaFascia', 'coppiaB.primaFascia', 'coppiaB.secondaFascia'])->first();

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Girone A')
            ->assertSee('Girone B')
            ->assertSee('2 coppie · 2 partite, 0 con il risultato')
            ->assertSee($partita->coppiaA->nome().' – '.$partita->coppiaB->nome())
            ->assertSee('ritorno')
            ->assertSee('4 partite in calendario, 0 con il risultato.')
            ->assertSee('Rigenera gironi')
            ->assertSee('Rigenera partite')
            ->assertViewHas('gironi', fn ($gironi) => $gironi->count() === 2);
    }

    // --- Annullamento e chiusura delle iscrizioni -----------------------------------------------------

    public function test_annullare_il_sorteggio_cancella_tutto_e_lascia_gli_iscritti(): void
    {
        $torneo = $this->torneo(4, 4);
        $altroTorneo = $this->torneo(2, 2);

        foreach ([$torneo, $altroTorneo] as $daSorteggiare) {
            $this->generaCoppie($daSorteggiare);
            $this->generaGironi($daSorteggiare);
            $this->generaPartite($daSorteggiare);
        }

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.sorteggio.destroy', $torneo))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHas('status');

        $this->assertSame(0, $torneo->coppie()->count());
        $this->assertSame(0, $torneo->gironi()->count());
        $this->assertSame(0, $torneo->partite()->count());
        $this->assertSame(8, $torneo->giocatori()->count());

        // L'altro torneo non viene toccato.
        $this->assertSame(2, $altroTorneo->coppie()->count());
        $this->assertSame(1, $altroTorneo->gironi()->count());
        $this->assertSame(1, $altroTorneo->partite()->count());
    }

    public function test_dopo_il_sorteggio_le_iscrizioni_sono_chiuse(): void
    {
        $torneo = $this->torneo(2, 2);
        $iscritto = $this->iscritti($torneo, 1)->first();
        $inAnagrafica = Giocatore::factory()->create();
        $this->generaCoppie($torneo);

        $nuovo = ['giocatori' => [['nome' => 'Mario', 'cognome' => 'Rossi', 'genere' => 'm', 'anno_nascita' => 1985, 'fascia' => 1, 'lato' => '', 'circolo' => '']]];

        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), $nuovo)
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHas('errore');

        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), ['giocatori' => [$inAnagrafica->id]])
            ->assertSessionHas('errore');

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.giocatori.destroy', [$torneo, $iscritto]))
            ->assertSessionHas('errore');

        $this->assertSame(4, $torneo->giocatori()->count());
        $this->assertSame(5, Giocatore::count());

        // La pagina non offre più i comandi per cambiare gli iscritti.
        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Iscrizioni chiuse')
            ->assertDontSee('Registra giocatori')
            ->assertDontSee("Dall'anagrafica")
            ->assertDontSee(route('amministrazione.tornei.giocatori.destroy', [$torneo, $iscritto]));
    }

    public function test_annullato_il_sorteggio_le_iscrizioni_si_riaprono(): void
    {
        $torneo = $this->torneo(2, 2);
        $iscritto = $this->iscritti($torneo, 1)->first();
        $this->generaCoppie($torneo);

        $this->actingAs($this->amministratore)->delete(route('amministrazione.tornei.sorteggio.destroy', $torneo));

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.giocatori.destroy', [$torneo, $iscritto]))
            ->assertSessionHas('status');

        $this->assertSame(3, $torneo->giocatori()->count());

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertSee('Registra giocatori');
    }

    public function test_un_giocatore_in_una_coppia_non_si_elimina_dall_anagrafica(): void
    {
        $torneo = $this->torneo(2, 2);
        $giocatore = $this->iscritti($torneo, 2)->first();
        $this->generaCoppie($torneo);

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.giocatori.destroy', $giocatore))
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHas('errore');

        $this->assertModelExists($giocatore);
        $this->assertSame(2, $torneo->coppie()->count());

        // Il messaggio compare nella pagina di amministrazione.
        $this->actingAs($this->amministratore)
            ->followingRedirects()
            ->delete(route('amministrazione.giocatori.destroy', $giocatore))
            ->assertSee('annulla prima quel sorteggio');

        // Annullato il sorteggio, la scheda si può eliminare.
        $this->actingAs($this->amministratore)->delete(route('amministrazione.tornei.sorteggio.destroy', $torneo));
        $this->actingAs($this->amministratore)->delete(route('amministrazione.giocatori.destroy', $giocatore))->assertSessionHas('status');

        $this->assertModelMissing($giocatore);
    }

    public function test_eliminare_il_torneo_elimina_il_suo_sorteggio_ma_non_i_giocatori(): void
    {
        $torneo = $this->torneo(4, 4);
        $this->generaCoppie($torneo);
        $this->generaGironi($torneo);
        $this->generaPartite($torneo);

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.destroy', $torneo))
            ->assertRedirect(route('amministrazione.dashboard'));

        $this->assertModelMissing($torneo);
        $this->assertSame(0, Coppia::count());
        $this->assertSame(0, Girone::count());
        $this->assertSame(0, Partita::count());
        $this->assertSame(8, Giocatore::count());
    }

    // --- Permessi ---------------------------------------------------------------------------------------

    public function test_un_utente_non_puo_toccare_formula_e_sorteggio(): void
    {
        $torneo = $this->torneo(2, 2);
        $utente = User::factory()->create();

        $this->actingAs($utente)->patch(route('amministrazione.tornei.formula.update', $torneo), $this->formula(['squadre_per_girone' => 8, 'andata_ritorno' => '1', 'lati_complementari' => 'preferito']))->assertForbidden();
        $this->actingAs($utente)->post(route('amministrazione.tornei.coppie.store', $torneo))->assertForbidden();
        $this->actingAs($utente)->post(route('amministrazione.tornei.gironi.store', $torneo))->assertForbidden();
        $this->actingAs($utente)->post(route('amministrazione.tornei.partite.store', $torneo))->assertForbidden();
        $this->actingAs($utente)->delete(route('amministrazione.tornei.sorteggio.destroy', $torneo))->assertForbidden();

        $this->assertSame(4, $torneo->refresh()->squadre_per_girone);
        $this->assertSame(0, Coppia::count());
    }

    public function test_gli_ospiti_vengono_mandati_al_login(): void
    {
        $torneo = $this->torneo(2, 2);

        $this->patch(route('amministrazione.tornei.formula.update', $torneo), $this->formula(['squadre_per_girone' => 8, 'andata_ritorno' => '1', 'lati_complementari' => 'preferito']))->assertRedirect(route('login'));
        $this->post(route('amministrazione.tornei.coppie.store', $torneo))->assertRedirect(route('login'));
        $this->post(route('amministrazione.tornei.gironi.store', $torneo))->assertRedirect(route('login'));
        $this->post(route('amministrazione.tornei.partite.store', $torneo))->assertRedirect(route('login'));
        $this->delete(route('amministrazione.tornei.sorteggio.destroy', $torneo))->assertRedirect(route('login'));

        $this->assertSame(0, Coppia::count());
    }

    public function test_un_torneo_inesistente_da_404(): void
    {
        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.coppie.store', 999))->assertNotFound();
        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.gironi.store', 999))->assertNotFound();
        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.partite.store', 999))->assertNotFound();
    }
}
