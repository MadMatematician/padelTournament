<?php

namespace Tests\Feature\Amministrazione;

use App\Models\Torneo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GestioneTorneiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function datiValidi(array $modifiche = []): array
    {
        return array_merge([
            'nome' => 'Torneo di primavera',
            'tipologia' => config('tornei.tipologia_predefinita'),
            'data_inizio' => '2027-04-10',
            'numero_turni' => config('tornei.turni.predefinito'),
        ], $modifiche);
    }

    private function amministratore(): User
    {
        return User::factory()->amministratore()->create();
    }

    public function test_la_pagina_di_amministrazione_elenca_i_tornei(): void
    {
        $torneo = Torneo::factory()->create(['nome' => 'Coppa del circolo', 'data_inizio' => '2027-05-02']);

        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertSee('Coppa del circolo')
            ->assertSee('02/05/2027')
            ->assertSee('Nuovo torneo')
            ->assertSee(route('amministrazione.tornei.destroy', $torneo))
            ->assertSee('data-datatable', false);
    }

    public function test_l_amministratore_crea_un_torneo(): void
    {
        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.tornei.store'), $this->datiValidi())
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHas('status');

        $torneo = Torneo::sole();

        $this->assertSame('Torneo di primavera', $torneo->nome);
        $this->assertSame(config('tornei.tipologia_predefinita'), $torneo->tipologia);
        $this->assertSame('2027-04-10', $torneo->data_inizio->format('Y-m-d'));
        $this->assertSame(config('tornei.turni.predefinito'), $torneo->numero_turni);
    }

    public function test_la_data_di_inizio_e_facoltativa(): void
    {
        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.tornei.store'), $this->datiValidi(['data_inizio' => '']))
            ->assertRedirect(route('amministrazione.dashboard'));

        $this->assertNull(Torneo::sole()->data_inizio);
    }

    /**
     * @param  array<string, mixed>  $modifiche
     */
    #[DataProvider('datiNonValidi')]
    public function test_i_dati_non_validi_vengono_rifiutati(array $modifiche, string $campo): void
    {
        $this->actingAs($this->amministratore())
            ->from(route('amministrazione.dashboard'))
            ->post(route('amministrazione.tornei.store'), $this->datiValidi($modifiche))
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHasErrorsIn('torneo', [$campo]);

        $this->assertSame(0, Torneo::count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function datiNonValidi(): array
    {
        return [
            'senza nome' => [['nome' => ''], 'nome'],
            'nome troppo lungo' => [['nome' => str_repeat('a', 121)], 'nome'],
            'tipologia sconosciuta' => [['tipologia' => 'inesistente'], 'tipologia'],
            'data non valida' => [['data_inizio' => 'domani forse'], 'data_inizio'],
            'zero turni' => [['numero_turni' => 0], 'numero_turni'],
            'troppi turni' => [['numero_turni' => 999], 'numero_turni'],
        ];
    }

    public function test_dopo_un_errore_si_riapre_solo_la_finestra_del_torneo(): void
    {
        $risposta = $this->actingAs($this->amministratore())
            ->followingRedirects()
            ->from(route('amministrazione.dashboard'))
            ->post(route('amministrazione.tornei.store'), $this->datiValidi(['nome' => '', 'numero_turni' => 7]));

        $risposta->assertOk()->assertSee('Il campo nome del torneo è obbligatorio.');

        $html = $risposta->getContent();

        // Si riapre la finestra del torneo, con il valore inviato; quella del giocatore resta chiusa e pulita.
        $this->assertMatchesRegularExpression('/id="modale-torneo"[^>]*data-apri-modale/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="modale-giocatore"[^>]*data-apri-modale/', $html);
        $this->assertMatchesRegularExpression('/id="torneo-numero-turni"\s+value="7"/', $html);
        $this->assertMatchesRegularExpression('/id="giocatore-0-nome"\s+value=""/', $html);
        $this->assertSame(1, substr_count($html, 'invalid-feedback'));
    }

    public function test_l_amministratore_elimina_un_torneo(): void
    {
        $torneo = Torneo::factory()->create();
        $altro = Torneo::factory()->create();

        $this->actingAs($this->amministratore())
            ->delete(route('amministrazione.tornei.destroy', $torneo))
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHas('status');

        $this->assertModelMissing($torneo);
        $this->assertModelExists($altro);
    }

    public function test_eliminare_un_torneo_inesistente_da_404(): void
    {
        $this->actingAs($this->amministratore())
            ->delete(route('amministrazione.tornei.destroy', 999))
            ->assertNotFound();
    }

    public function test_un_utente_non_puo_gestire_i_tornei(): void
    {
        $torneo = Torneo::factory()->create();
        $utente = User::factory()->create();

        $this->actingAs($utente)
            ->post(route('amministrazione.tornei.store'), $this->datiValidi())
            ->assertForbidden();

        $this->actingAs($utente)
            ->delete(route('amministrazione.tornei.destroy', $torneo))
            ->assertForbidden();

        $this->assertSame(1, Torneo::count());
    }

    public function test_gli_ospiti_vengono_mandati_al_login(): void
    {
        $torneo = Torneo::factory()->create();

        $this->post(route('amministrazione.tornei.store'), $this->datiValidi())->assertRedirect(route('login'));
        $this->delete(route('amministrazione.tornei.destroy', $torneo))->assertRedirect(route('login'));

        $this->assertSame(1, Torneo::count());
    }
}
