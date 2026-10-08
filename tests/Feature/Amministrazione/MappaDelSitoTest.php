<?php

namespace Tests\Feature\Amministrazione;

use App\Http\Controllers\Amministrazione\TorneoController;
use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use App\Services\Mappa\MappaDelSito;
use App\Services\Mappa\SezioneMappa;
use App\Services\Mappa\VoceMappa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Rotta;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Mappa del sito (`/amministrazione/mappa`): costruita dalle rotte dei file di route, con la descrizione
 * della destinazione di ognuna.
 */
class MappaDelSitoTest extends TestCase
{
    use RefreshDatabase;

    private function amministratore(): User
    {
        return User::factory()->amministratore()->create();
    }

    private function mappa(): MappaDelSito
    {
        return app(MappaDelSito::class);
    }

    /**
     * La voce della mappa per una rotta, cercata per nome.
     */
    private function voce(string $nome): ?VoceMappa
    {
        return $this->mappa()->sezioni()
            ->flatMap(fn (SezioneMappa $sezione) => $sezione->voci)
            ->first(fn (VoceMappa $voce) => $voce->nome === $nome);
    }

    /**
     * Le rotte dell'applicazione: quelle che rispondono con un controller di `App\`.
     *
     * @return list<Rotta>
     */
    private function rotteDellApplicazione(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (Rotta $rotta) => str_starts_with($rotta->getActionName(), 'App\\'),
        ));
    }

    // ---------------------------------------------------------------- file di route

    public function test_ogni_rotta_dei_file_di_route_dichiara_la_sua_descrizione(): void
    {
        $rotte = $this->rotteDellApplicazione();

        $this->assertNotEmpty($rotte);

        foreach ($rotte as $rotta) {
            $descrizione = $rotta->getAction('descrizione');

            $this->assertIsString($descrizione, 'Manca ->descrizione() sulla rotta '.implode('|', $rotta->methods()).' '.$rotta->uri());
            $this->assertNotSame('', trim($descrizione));
        }
    }

    public function test_la_mappa_contiene_tutte_le_rotte_dell_applicazione_e_solo_quelle(): void
    {
        $nellaMappa = $this->mappa()->sezioni()
            ->flatMap(fn (SezioneMappa $sezione) => $sezione->voci)
            ->map(fn (VoceMappa $voce) => implode('|', $voce->metodi).' '.$voce->indirizzo)
            ->all();

        $attese = array_map(
            fn (Rotta $rotta) => implode('|', array_diff($rotta->methods(), ['HEAD'])).' /'.ltrim($rotta->uri(), '/'),
            $this->rotteDellApplicazione(),
        );

        $this->assertEqualsCanonicalizing($attese, $nellaMappa);

        // Le rotte aggiunte dal framework non sono pagine del sito.
        $this->assertNotContains('GET /up', $nellaMappa);
        $this->assertEmpty(array_filter($nellaMappa, fn (string $rotta) => str_contains($rotta, '/storage')));
    }

    public function test_le_sezioni_seguono_i_prefissi_dei_gruppi_con_la_radice_per_prima(): void
    {
        $sezioni = $this->mappa()->sezioni();

        $this->assertSame(
            ['/', '/amministrazione', '/api/test', '/area-utente', '/test'],
            $sezioni->map(fn (SezioneMappa $sezione) => $sezione->titolo())->all(),
        );

        // Dentro una sezione le rotte restano nell'ordine in cui sono scritte nel file.
        $radice = array_map(fn (VoceMappa $voce) => $voce->nome, $sezioni->first()->voci);

        $this->assertSame(['home', 'login', 'login.store', 'registrazione', 'registrazione.store', 'logout'], $radice);
    }

    public function test_una_rotta_nuova_entra_nella_mappa_senza_toccare_altro(): void
    {
        Route::get('circoli/elenco', [LoginController::class, 'create'])
            ->name('circoli.elenco')
            ->descrizione('Elenco dei circoli.');

        $voce = $this->voce('circoli.elenco');

        $this->assertNotNull($voce);
        $this->assertSame('Elenco dei circoli.', $voce->descrizione);
        $this->assertSame(['GET'], $voce->metodi);
        $this->assertSame('Tutti', $voce->accesso);
    }

    // ---------------------------------------------------------------- voci

    public function test_la_descrizione_e_quella_scritta_nel_file_di_route(): void
    {
        $voce = $this->voce('amministrazione.tornei.coppie.store');

        $this->assertSame(['POST'], $voce->metodi);
        $this->assertSame('/amministrazione/tornei/{torneo}/coppie', $voce->indirizzo);
        $this->assertSame(Route::getRoutes()->getByName('amministrazione.tornei.coppie.store')->getAction('descrizione'), $voce->descrizione);
        $this->assertSame('Amministrazione\SorteggioController@coppie', $voce->destinazione);
    }

    public function test_senza_descrizione_vale_il_commento_del_controller(): void
    {
        // Il metodo ha un commento: se ne prende il primo paragrafo, su una riga.
        Route::get('prova/commento-del-metodo', [TorneoController::class, 'show'])->name('prova.metodo');
        // Il metodo non ha un commento: non c'è nulla da mostrare.
        Route::get('prova/nessun-commento', [LoginController::class, 'create'])->name('prova.nessuno');

        $this->assertSame(
            'Pagina di gestione del torneo: giocatori iscritti (registrazione di nuovi, iscrizione dall\'anagrafica), formula e sorteggio (coppie, gironi, partite).',
            $this->voce('prova.metodo')->descrizione,
        );
        $this->assertNull($this->voce('prova.nessuno')->descrizione);
    }

    public function test_l_accesso_si_ricava_dai_middleware_della_rotta(): void
    {
        $this->assertSame('Tutti', $this->voce('home')->accesso);
        $this->assertSame('Solo ospiti', $this->voce('login')->accesso);
        $this->assertSame('Utenti autenticati', $this->voce('logout')->accesso);
        $this->assertSame('Amministratore', $this->voce('amministrazione.dashboard')->accesso);
        $this->assertSame('Utente', $this->voce('area-utente.dashboard')->accesso);

        Route::get('prova/due-ruoli', [LoginController::class, 'create'])
            ->middleware(['auth', 'ruolo:amministratore,utente'])
            ->name('prova.ruoli');

        $this->assertSame('Amministratore, Utente', $this->voce('prova.ruoli')->accesso);
    }

    public function test_solo_le_pagine_senza_parametri_hanno_un_link(): void
    {
        $this->assertSame(route('amministrazione.dashboard'), $this->voce('amministrazione.dashboard')->link);
        $this->assertSame(route('login'), $this->voce('login')->link);
        // Il parametro facoltativo non impedisce il link.
        $this->assertSame(url('/test'), $this->voce('test.torneo')->link);

        // Serve un torneo: nessun indirizzo da aprire.
        $this->assertNull($this->voce('amministrazione.tornei.show')->link);
        // Non è una pagina.
        $this->assertNull($this->voce('amministrazione.tornei.store')->link);
        $this->assertNull($this->voce('logout')->link);
    }

    public function test_le_rotte_di_test_sono_segnate_e_spariscono_fuori_dallo_sviluppo(): void
    {
        $this->assertTrue($this->voce('test.torneo')->soloSviluppo);
        $this->assertTrue($this->voce('api.test.torneo')->soloSviluppo);
        $this->assertFalse($this->voce('amministrazione.dashboard')->soloSviluppo);

        $this->app['env'] = 'production';

        $this->assertNull($this->voce('test.torneo'));
        $this->assertNull($this->voce('api.test.torneo'));
        $this->assertNotNull($this->voce('amministrazione.dashboard'));
        $this->assertNotContains('/test', $this->mappa()->sezioni()->map(fn (SezioneMappa $sezione) => $sezione->titolo())->all());
    }

    // ---------------------------------------------------------------- pagina

    public function test_l_amministratore_vede_la_mappa_del_sito(): void
    {
        $risposta = $this->actingAs($this->amministratore())
            ->get(route('amministrazione.mappa'))
            ->assertOk()
            ->assertSee('Mappa del sito')
            ->assertSee('lette dai file di route');

        foreach ($this->mappa()->sezioni() as $sezione) {
            foreach ($sezione->voci as $voce) {
                $risposta
                    ->assertSee($voce->indirizzo)
                    ->assertSee($voce->descrizione)
                    ->assertSee($voce->destinazione)
                    ->assertSee($voce->nome);
            }
        }

        $risposta
            ->assertSee('href="'.route('amministrazione.dashboard').'"', false)
            ->assertSee('solo sviluppo')
            ->assertSee('Solo ospiti')
            ->assertDontSee('/storage')
            ->assertDontSee('Senza descrizione');
    }

    public function test_la_mappa_segnala_la_rotta_senza_descrizione(): void
    {
        Route::get('prova/nessun-commento', [LoginController::class, 'create'])->name('prova.nessuno');

        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.mappa'))
            ->assertOk()
            ->assertSee('/prova/nessun-commento')
            ->assertSee('Senza descrizione');
    }

    public function test_il_menu_porta_alla_mappa_solo_l_amministratore(): void
    {
        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('amministrazione.mappa').'"', false);

        $this->actingAs(User::factory()->create())
            ->get(route('area-utente.dashboard'))
            ->assertOk()
            ->assertDontSee(route('amministrazione.mappa'))
            ->assertDontSee('Mappa del sito');
    }

    public function test_la_mappa_e_riservata_all_amministratore(): void
    {
        $this->get(route('amministrazione.mappa'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('amministrazione.mappa'))
            ->assertForbidden();
    }
}
