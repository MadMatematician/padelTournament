<?php

namespace Tests\Feature\Test;

use App\Models\Giocatore;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rotte di test (`/test/{giocatori?}` e `/api/test/{giocatori?}`): tornei generati con la factory,
 * già con tutti i giocatori iscritti.
 */
class TorneoDiTestTest extends TestCase
{
    use RefreshDatabase;

    private function amministratore(): User
    {
        return User::factory()->amministratore()->create();
    }

    private function predefinito(): int
    {
        return config('tornei.test.giocatori.predefinito');
    }

    /** Fuori dagli ambienti di `tornei.test.ambienti`, come in produzione. */
    private function inProduzione(): void
    {
        $this->app['env'] = 'production';
    }

    // ---------------------------------------------------------------- factory

    public function test_la_factory_crea_un_torneo_con_la_lista_dei_giocatori_completa(): void
    {
        $torneo = Torneo::factory()->conGiocatori(12)->create();

        $this->assertSame(12, $torneo->giocatori()->count());
        $this->assertSame(12, Giocatore::count());
        $this->assertEquals([1 => 6, 2 => 6], $torneo->giocatori->countBy('fascia')->all());

        // Schede complete dei dati obbligatori in anagrafica.
        foreach ($torneo->giocatori as $giocatore) {
            $this->assertNotSame('', $giocatore->nome);
            $this->assertNotSame('', $giocatore->cognome);
            $this->assertArrayHasKey($giocatore->genere, config('tornei.giocatori.generi'));
            $this->assertNotNull($giocatore->anno_nascita);
        }
    }

    public function test_senza_numero_la_factory_usa_quello_predefinito(): void
    {
        $torneo = Torneo::factory()->conGiocatori()->create();

        $this->assertSame($this->predefinito(), $torneo->giocatori()->count());
    }

    public function test_con_un_numero_dispari_il_giocatore_in_piu_e_di_prima_fascia(): void
    {
        $torneo = Torneo::factory()->conGiocatori(17)->create();

        $this->assertEquals([1 => 9, 2 => 8], $torneo->giocatori->countBy('fascia')->all());
    }

    public function test_ogni_torneo_generato_ha_i_suoi_giocatori(): void
    {
        [$primo, $secondo] = Torneo::factory()->count(2)->conGiocatori(6)->create();

        $this->assertSame(12, Giocatore::count());
        $this->assertSame(6, $primo->giocatori()->count());
        $this->assertSame(6, $secondo->giocatori()->count());
        $this->assertEmpty(array_intersect($primo->giocatori->modelKeys(), $secondo->giocatori->modelKeys()));
    }

    // ---------------------------------------------------------------- sito

    public function test_la_rotta_di_test_crea_un_torneo_con_il_numero_predefinito_di_giocatori(): void
    {
        $risposta = $this->actingAs($this->amministratore())->get('/test');

        $torneo = Torneo::sole();

        $risposta
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHas('status');

        $this->assertSame($this->predefinito(), $torneo->giocatori()->count());
    }

    public function test_il_numero_di_giocatori_si_indica_nel_link(): void
    {
        $this->actingAs($this->amministratore())
            ->get('/test/24')
            ->assertRedirect(route('amministrazione.tornei.show', Torneo::sole()));

        $torneo = Torneo::sole();

        $this->assertSame(24, $torneo->giocatori()->count());
        $this->assertEquals([1 => 12, 2 => 12], $torneo->giocatori->countBy('fascia')->all());
        $this->assertSame(url('/test/24'), route('test.torneo', 24));
    }

    public function test_la_pagina_di_gestione_mostra_il_torneo_di_test_con_i_suoi_iscritti(): void
    {
        $risposta = $this->actingAs($this->amministratore())
            ->followingRedirects()
            ->get('/test/8')
            ->assertOk();

        $torneo = Torneo::sole();

        $risposta
            ->assertSee($torneo->nome)
            ->assertSee('creato con 8 giocatori iscritti');

        foreach ($torneo->giocatori as $giocatore) {
            $risposta->assertSee($giocatore->cognome);
        }
    }

    public function test_il_torneo_di_test_e_pronto_per_il_sorteggio(): void
    {
        $amministratore = $this->amministratore();

        $this->actingAs($amministratore)->get('/test/16');

        $torneo = Torneo::sole();

        $this->actingAs($amministratore)
            ->post(route('amministrazione.tornei.coppie.store', $torneo))
            ->assertSessionHas('status')
            ->assertSessionMissing('errore');

        $this->assertSame(8, $torneo->coppie()->count());
    }

    #[DataProvider('numeriFuoriDaiLimiti')]
    public function test_un_numero_fuori_dai_limiti_viene_rifiutato(string $numero): void
    {
        $this->actingAs($this->amministratore())
            ->get('/test/'.$numero)
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHas('errore');

        $this->assertSame(0, Torneo::count());
        $this->assertSame(0, Giocatore::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function numeriFuoriDaiLimiti(): array
    {
        return [
            'zero' => ['0'],
            'uno solo' => ['1'],
            'troppi' => ['129'],
            'numero enorme' => ['99999999999999999999'],
        ];
    }

    public function test_i_limiti_arrivano_dalla_configurazione(): void
    {
        config(['tornei.test.giocatori.massimo' => 10]);

        $amministratore = $this->amministratore();

        $this->actingAs($amministratore)->get('/test/12')->assertSessionHas('errore', 'Un torneo di test ha al massimo 10 giocatori.');
        $this->assertSame(0, Torneo::count());

        $this->actingAs($amministratore)->get('/test/10')->assertSessionMissing('errore');
        $this->assertSame(10, Torneo::sole()->giocatori()->count());
    }

    public function test_un_link_senza_numero_valido_da_404(): void
    {
        $amministratore = $this->amministratore();

        $this->actingAs($amministratore)->get('/test/venti')->assertNotFound();
        $this->actingAs($amministratore)->get('/test/-4')->assertNotFound();
        $this->actingAs($amministratore)->get('/test/12/altro')->assertNotFound();

        $this->assertSame(0, Torneo::count());
    }

    public function test_solo_l_amministratore_usa_la_rotta_di_test_del_sito(): void
    {
        $this->get('/test/8')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get('/test/8')->assertForbidden();

        $this->assertSame(0, Torneo::count());
        $this->assertSame(0, Giocatore::count());
    }

    public function test_dopo_il_login_si_torna_alla_rotta_di_test_richiesta(): void
    {
        $amministratore = $this->amministratore();

        $this->get('/test/6')->assertRedirect(route('login'));

        $this->post(route('login.store'), ['username' => $amministratore->username, 'password' => 'password'])
            ->assertRedirect(url('/test/6'));
    }

    public function test_in_produzione_la_rotta_di_test_del_sito_non_esiste(): void
    {
        $this->inProduzione();

        $this->get('/test')->assertNotFound();
        $this->actingAs($this->amministratore())->get('/test/8')->assertNotFound();

        $this->assertSame(0, Torneo::count());
        $this->assertSame(0, Giocatore::count());
    }

    // ---------------------------------------------------------------- API

    public function test_l_api_di_test_restituisce_il_torneo_con_tutti_i_giocatori(): void
    {
        $risposta = $this->get('/api/test')->assertCreated();

        $torneo = Torneo::sole();

        $risposta
            ->assertJsonPath('data.id', $torneo->id)
            ->assertJsonPath('data.nome', $torneo->nome)
            ->assertJsonPath('data.tipologia', config('tornei.tipologia_predefinita'))
            ->assertJsonPath('data.gestione', route('amministrazione.tornei.show', $torneo))
            ->assertJsonPath('data.iscritti', $this->predefinito())
            ->assertJsonCount($this->predefinito(), 'data.giocatori')
            ->assertJsonStructure(['data' => [
                'id', 'nome', 'tipologia', 'data_inizio', 'numero_turni', 'gestione', 'iscritti', 'iscritti_per_fascia',
                'giocatori' => [['id', 'nome', 'cognome', 'genere', 'anno_nascita', 'fascia', 'lato', 'circolo']],
            ]]);

        $this->assertSame($this->predefinito(), $torneo->giocatori()->count());
    }

    public function test_anche_nell_api_il_numero_di_giocatori_si_indica_nel_link(): void
    {
        $risposta = $this->getJson('/api/test/10')
            ->assertCreated()
            ->assertJsonPath('data.iscritti', 10)
            ->assertJsonPath('data.iscritti_per_fascia', ['1' => 5, '2' => 5])
            ->assertJsonCount(10, 'data.giocatori');

        $torneo = Torneo::sole();

        // Gli stessi giocatori iscritti al torneo, prima fascia in testa.
        $this->assertEqualsCanonicalizing($torneo->giocatori->modelKeys(), $risposta->json('data.giocatori.*.id'));
        $this->assertSame([1, 1, 1, 1, 1, 2, 2, 2, 2, 2], $risposta->json('data.giocatori.*.fascia'));
        $this->assertSame(url('/api/test/10'), route('api.test.torneo', 10));
    }

    public function test_l_api_rifiuta_in_json_un_numero_fuori_dai_limiti(): void
    {
        // Anche senza intestazione Accept, come dalla barra degli indirizzi del browser.
        $this->get('/api/test/1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['giocatori' => 'Un torneo di test ha almeno 2 giocatori.']);

        $this->getJson('/api/test/500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['giocatori' => 'Un torneo di test ha al massimo 128 giocatori.']);

        $this->assertSame(0, Torneo::count());
        $this->assertSame(0, Giocatore::count());
    }

    public function test_in_produzione_l_api_di_test_non_esiste(): void
    {
        $this->inProduzione();

        $this->get('/api/test')->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/test/8')->assertNotFound();

        $this->assertSame(0, Torneo::count());
        $this->assertSame(0, Giocatore::count());
    }
}
