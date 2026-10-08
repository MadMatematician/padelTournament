<?php

namespace Tests\Feature\Amministrazione;

use App\Enums\Fase;
use App\Models\Giocatore;
use App\Models\Girone;
use App\Models\Partita;
use App\Models\Torneo;
use App\Models\User;
use App\Services\Punteggio\ValutatorePunteggio;
use App\Services\Tornei\CodaRisultati;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\RisultatiService;
use App\Services\Tornei\TorneoSocialeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FormulaClassica;
use Tests\TestCase;

/**
 * Inserimento continuo dei risultati: il salvataggio avviene in sottofondo (risposta JSON) e la finestra del
 * risultato passa alla prossima partita senza risultato, dello stesso girone finché ce ne sono e poi degli
 * altri; «Successiva» passa oltre senza salvare.
 */
class InserimentoContinuoRisultatiTest extends TestCase
{
    use FormulaClassica;
    use RefreshDatabase;

    private User $amministratore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->amministratore = User::factory()->amministratore()->create();
    }

    // --- Costruzione degli scenari ---------------------------------------------------------------------

    /** Due gironi da tre coppie: tre partite per girone. */
    private function torneoConDueGironi(): Torneo
    {
        return $this->torneoConPartite(6, 6, ['squadre_per_girone' => 3]);
    }

    /**
     * @param  array<string, mixed>  $formula
     */
    private function torneoConPartite(int $prima, int $seconda, array $formula = []): Torneo
    {
        $torneo = Torneo::factory()->create($formula);

        $torneo->giocatori()->attach(Giocatore::factory()->fascia(1)->count($prima)->create(['lato' => null]));
        $torneo->giocatori()->attach(Giocatore::factory()->fascia(2)->count($seconda)->create(['lato' => null]));

        $torneo = $torneo->fresh();
        $sorteggio = app(TorneoSocialeService::class);

        $sorteggio->generaCoppie($torneo);
        $sorteggio->generaGironi($torneo);
        $sorteggio->generaPartite($torneo);

        return $torneo;
    }

    /**
     * Le partite di un girone nell'ordine del calendario.
     *
     * @return Collection<int, Partita>
     */
    private function partiteDel(Torneo $torneo, int $posizione): Collection
    {
        $girone = $torneo->gironi()->where('posizione', $posizione)->sole();

        return $girone->partite()->orderBy('giornata')->orderBy('id')->get();
    }

    private function girone(Torneo $torneo, int $posizione): Girone
    {
        return $torneo->gironi()->where('posizione', $posizione)->sole();
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function set(string $punteggio): array
    {
        return array_map(function (string $set): array {
            [$a, $b] = explode('-', $set);

            return ['a' => (int) $a, 'b' => (int) $b, 'tb_a' => '', 'tb_b' => ''];
        }, explode(' ', $punteggio));
    }

    /** Registra un risultato senza passare dal form (per preparare gli scenari). */
    private function gioca(Partita $partita, string $punteggio = '6-3 6-3'): void
    {
        $partita = $partita->fresh();

        app(RisultatiService::class)->registra(
            $partita,
            (new ValutatorePunteggio)->valuta($partita->torneo->formato($partita->fase), $this->set($punteggio)),
        );
    }

    /** Il salvataggio come lo fa torneo.js: in sottofondo, aspettando JSON. */
    private function salva(Torneo $torneo, Partita $partita, string $punteggio = '6-4 6-4'): TestResponse
    {
        return $this->actingAs($this->amministratore)->putJson(
            route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]),
            ['partita_id' => $partita->id, 'set' => $this->set($punteggio)],
        );
    }

    private function successiva(Torneo $torneo, Partita $partita): TestResponse
    {
        return $this->actingAs($this->amministratore)
            ->getJson(route('amministrazione.tornei.partite.successiva', [$torneo, $partita]));
    }

    // --- Dopo un salvataggio ---------------------------------------------------------------------------

    public function test_il_salvataggio_in_sottofondo_risponde_con_la_prossima_partita_del_girone(): void
    {
        $torneo = $this->torneoConDueGironi();
        [$prima, $seconda] = $this->partiteDel($torneo, 0)->all();
        $seconda->load(['coppiaA.primaFascia', 'coppiaA.secondaFascia', 'coppiaB.primaFascia', 'coppiaB.secondaFascia']);

        $risposta = $this->salva($torneo, $prima, '6-4 6-2')->assertOk();

        $this->assertSame('6-4 6-2', $prima->fresh()->punteggio());
        $this->assertStringStartsWith('Risultato registrato: ', $risposta->json('esito'));
        $this->assertStringEndsWith(' 6-4 6-2.', $risposta->json('esito'));
        // Stesso girone: nessun avviso di cambio, e la partita arriva vuota con tutto ciò che serve alla finestra.
        $this->assertNull($risposta->json('cambio'));
        $risposta->assertJsonPath('successiva', [
            'partita' => $seconda->id,
            'azione' => route('amministrazione.tornei.partite.risultato.update', [$torneo, $seconda]),
            'cancella' => '',
            'successiva' => route('amministrazione.tornei.partite.successiva', [$torneo, $seconda]),
            'coppiaA' => $seconda->coppiaA->nome(),
            'coppiaB' => $seconda->coppiaB->nome(),
            'gruppo' => 'girone-'.$seconda->girone_id,
            'gruppoNome' => 'Girone A',
            'dettaglio' => $seconda->giornata.'ª giornata',
            'setMassimi' => 3,
            'tieBreak' => 1,
            'formato' => 'Al meglio di 3 set a 6 game, tie-break a 7 punti',
            'set' => [],
        ]);
    }

    public function test_finche_il_girone_non_e_completo_si_resta_nel_girone(): void
    {
        $torneo = $this->torneoConDueGironi();
        [$prima, $seconda, $terza] = $this->partiteDel($torneo, 0)->all();

        // Salvata l'ultima del calendario, mancano le due precedenti: si torna alla prima, non si cambia girone.
        $this->salva($torneo, $terza)->assertOk()
            ->assertJsonPath('successiva.partita', $prima->id)
            ->assertJsonPath('cambio', null);

        $this->salva($torneo, $prima)->assertOk()
            ->assertJsonPath('successiva.partita', $seconda->id)
            ->assertJsonPath('cambio', null);
    }

    public function test_completato_il_girone_si_passa_al_successivo_e_la_risposta_lo_dice(): void
    {
        $torneo = $this->torneoConDueGironi();
        [$prima, $seconda, $terza] = $this->partiteDel($torneo, 0)->all();
        $this->gioca($prima);
        $this->gioca($seconda);

        $this->salva($torneo, $terza)->assertOk()
            ->assertJsonPath('successiva.partita', $this->partiteDel($torneo, 1)->first()->id)
            ->assertJsonPath('successiva.gruppo', 'girone-'.$this->girone($torneo, 1)->id)
            ->assertJsonPath('successiva.gruppoNome', 'Girone B')
            ->assertJsonPath('cambio', 'Girone A: tutti i risultati inseriti. Ora sei nel Girone B.');
    }

    public function test_dall_ultimo_girone_si_torna_a_quelli_rimasti_indietro(): void
    {
        $torneo = $this->torneoConDueGironi();
        $delPrimo = $this->partiteDel($torneo, 0);
        $delSecondo = $this->partiteDel($torneo, 1);
        $this->gioca($delPrimo[0]);
        $this->gioca($delSecondo[0]);
        $this->gioca($delSecondo[1]);

        $this->salva($torneo, $delSecondo[2])->assertOk()
            ->assertJsonPath('successiva.partita', $delPrimo[1]->id)
            ->assertJsonPath('cambio', 'Girone B: tutti i risultati inseriti. Ora sei nel Girone A.');
    }

    public function test_correggere_un_risultato_propone_comunque_una_partita_ancora_da_giocare(): void
    {
        $torneo = $this->torneoConDueGironi();
        [$prima, $seconda] = $this->partiteDel($torneo, 0)->all();
        $this->gioca($prima);

        $this->salva($torneo, $prima, '6-0 6-0')->assertOk()->assertJsonPath('successiva.partita', $seconda->id);

        $this->assertSame('6-0 6-0', $prima->fresh()->punteggio());
    }

    public function test_con_l_ultimo_risultato_dei_gironi_la_finestra_si_chiude_sul_passo_successivo(): void
    {
        $torneo = $this->torneoConDueGironi();
        $partite = $torneo->partiteDiGirone()->orderBy('id')->get();
        $partite->slice(1)->each(fn (Partita $partita) => $this->gioca($partita));

        $risposta = $this->salva($torneo, $partite->first())->assertOk()
            ->assertJsonPath('successiva', null)
            ->assertJsonPath('vai', route('amministrazione.tornei.show', $torneo).'#sorteggio');

        // La conferma compare nella pagina a cui si viene mandati.
        $risposta->assertSessionHas('status', fn (string $stato) => str_starts_with($stato, 'Risultato registrato: ')
            && str_ends_with($stato, 'Tutte le partite dei gironi hanno il risultato.'));
    }

    public function test_un_punteggio_respinto_torna_in_json_con_l_errore_sul_set_e_non_salva(): void
    {
        $torneo = $this->torneoConDueGironi();
        $partita = $this->partiteDel($torneo, 0)->first();

        $this->salva($torneo, $partita, '6-4 9-7')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['set.1.a' => 'Con set a 6 game il punteggio 9-7 non è valido'])
            ->assertJsonMissingPath('successiva');

        $this->salva($torneo, $partita, '6-4 4-6')->assertStatus(422)->assertJsonValidationErrors(['set']);

        $this->assertFalse($partita->fresh()->giocata());
    }

    public function test_un_salvataggio_non_consentito_torna_in_json_con_il_motivo(): void
    {
        $torneo = $this->torneoConPartite(8, 8);
        $torneo->partiteDiGirone()->get()->each(fn (Partita $partita) => $this->gioca($partita));
        app(FinaliService::class)->generaTabellone($torneo);
        $partita = $torneo->partiteDiGirone()->first();

        $this->salva($torneo, $partita, '6-0 6-0')
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $motivo) => str_contains($motivo, 'annulla prima le finali'))
            ->assertJsonMissingPath('errors');

        $this->assertSame('6-3 6-3', $partita->fresh()->punteggio());
    }

    public function test_senza_javascript_il_salvataggio_torna_alla_pagina_come_prima(): void
    {
        $torneo = $this->torneoConDueGironi();
        $partita = $this->partiteDel($torneo, 0)->first();

        $this->actingAs($this->amministratore)
            ->put(route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]), ['set' => $this->set('6-4 6-4')])
            ->assertRedirect(route('amministrazione.tornei.show', $torneo).'#girone-'.$partita->girone_id)
            ->assertSessionHas('status');
    }

    // --- «Successiva» -----------------------------------------------------------------------------------

    public function test_successiva_passa_alla_prossima_partita_senza_risultato_senza_salvare(): void
    {
        $torneo = $this->torneoConDueGironi();
        [$prima, $seconda, $terza] = $this->partiteDel($torneo, 0)->all();
        $this->gioca($seconda);

        // La seconda ha già il risultato: dalla prima si arriva alla terza.
        $this->successiva($torneo, $prima)->assertOk()
            ->assertJsonPath('successiva.partita', $terza->id)
            ->assertJsonPath('successiva.set', [])
            ->assertJsonPath('cambio', null)
            ->assertJsonMissingPath('esito');

        $this->assertFalse($prima->fresh()->giocata());
    }

    public function test_successiva_esce_dal_girone_dicendo_quante_partite_restano_indietro(): void
    {
        $torneo = $this->torneoConDueGironi();
        [$prima, $seconda, $terza] = $this->partiteDel($torneo, 0)->all();
        $delSecondo = $this->partiteDel($torneo, 1);

        $this->successiva($torneo, $terza)->assertOk()
            ->assertJsonPath('successiva.partita', $delSecondo[0]->id)
            ->assertJsonPath('cambio', 'Girone A: restano 3 partite senza risultato. Ora sei nel Girone B.');

        $this->gioca($prima);
        $this->gioca($seconda);

        $this->successiva($torneo, $terza)->assertOk()
            ->assertJsonPath('cambio', 'Girone A: resta una partita senza risultato. Ora sei nel Girone B.');
    }

    public function test_successiva_fa_il_giro_di_tutte_le_partite_da_giocare(): void
    {
        $torneo = $this->torneoConDueGironi();
        $tutte = $this->partiteDel($torneo, 0)->concat($this->partiteDel($torneo, 1))->values();

        $giro = [];
        $partita = $tutte->first();
        for ($passo = 0; $passo < $tutte->count(); $passo++) {
            $partita = Partita::findOrFail($this->successiva($torneo, $partita)->json('successiva.partita'));
            $giro[] = $partita->id;
        }

        // Girone A, poi Girone B, poi di nuovo dalla prima.
        $this->assertSame($tutte->slice(1)->pluck('id')->push($tutte->first()->id)->all(), $giro);
    }

    public function test_successiva_avvisa_quando_non_ci_sono_altre_partite_da_giocare(): void
    {
        $torneo = $this->torneoConDueGironi();
        $partite = $torneo->partiteDiGirone()->orderBy('id')->get();
        $partite->slice(1)->each(fn (Partita $partita) => $this->gioca($partita));

        $this->successiva($torneo, $partite->first())->assertOk()
            ->assertJsonPath('successiva', null)
            ->assertJsonPath('fine', 'Non ci sono altre partite senza risultato.')
            ->assertJsonMissingPath('vai');
    }

    public function test_successiva_e_riservata_all_amministratore_e_alle_partite_del_torneo(): void
    {
        $torneo = $this->torneoConDueGironi();
        $altro = $this->torneoConDueGironi();
        $partita = $this->partiteDel($torneo, 0)->first();
        $indirizzo = route('amministrazione.tornei.partite.successiva', [$torneo, $partita]);

        $this->getJson($indirizzo)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($indirizzo)->assertForbidden();
        $this->actingAs($this->amministratore)
            ->getJson(route('amministrazione.tornei.partite.successiva', [$altro, $partita]))
            ->assertNotFound();
    }

    // --- Finali -----------------------------------------------------------------------------------------

    public function test_nelle_finali_si_resta_nel_turno_e_poi_si_passa_alle_coppie_appena_sorteggiate(): void
    {
        $torneo = $this->torneoConPartite(8, 8);
        $torneo->partiteDiGirone()->get()->each(fn (Partita $partita) => $this->gioca($partita));
        app(FinaliService::class)->generaTabellone($torneo);
        [$prima, $seconda] = $torneo->partiteDelTurno(Fase::Semifinali)->get()->all();
        $finale = $torneo->partiteDelTurno(Fase::Finale)->sole();

        $this->salva($torneo, $prima)->assertOk()
            ->assertJsonPath('successiva.partita', $seconda->id)
            ->assertJsonPath('successiva.gruppo', 'fase-semifinali')
            ->assertJsonPath('successiva.gruppoNome', 'Semifinali')
            ->assertJsonPath('successiva.dettaglio', '')
            ->assertJsonPath('cambio', null);

        // La finale non ha ancora le coppie: «Successiva» non la propone.
        $this->successiva($torneo, $seconda)->assertOk()->assertJsonPath('successiva', null);

        $risposta = $this->salva($torneo, $seconda)->assertOk()
            ->assertJsonPath('successiva.partita', $finale->id)
            ->assertJsonPath('successiva.gruppoNome', 'Finale')
            ->assertJsonPath('cambio', 'Semifinali: turno concluso, coppie nuove sorteggiate. Ora si gioca: Finale.');

        // Le coppie sono quelle sorteggiate con questo salvataggio.
        $finale = $finale->fresh(['coppiaA.primaFascia', 'coppiaA.secondaFascia', 'coppiaB.primaFascia', 'coppiaB.secondaFascia']);
        $this->assertTrue($finale->completa());
        $risposta->assertJsonPath('successiva.coppiaA', $finale->coppiaA->nome())
            ->assertJsonPath('successiva.coppiaB', $finale->coppiaB->nome());

        $this->salva($torneo, $finale)->assertOk()
            ->assertJsonPath('successiva', null)
            ->assertJsonPath('vai', route('amministrazione.tornei.tabellone', $torneo))
            ->assertSessionHas('status', fn (string $stato) => str_starts_with($stato, 'Risultato registrato: '));
    }

    public function test_le_coppie_che_passano_il_turno_senza_giocare_non_vengono_proposte(): void
    {
        // Tre gironi da due coppie, tutte qualificate: sei squadre per i quarti, due passano senza giocare.
        $torneo = $this->torneoConPartite(6, 6, ['squadre_per_girone' => 2, 'qualificate_per_girone' => 2]);
        $torneo->partiteDiGirone()->get()->each(fn (Partita $partita) => $this->gioca($partita));
        app(FinaliService::class)->generaTabellone($torneo);

        $quarti = $torneo->partiteDelTurno(Fase::Quarti)->get();
        $this->assertSame(2, $quarti->filter(fn (Partita $partita) => $partita->bye())->count());
        $daGiocare = $quarti->filter(fn (Partita $partita) => $partita->completa() && ! $partita->giocata())->values();
        $this->assertSame(2, $daGiocare->count());

        $coda = app(CodaRisultati::class)->dopo($daGiocare->first());

        $this->assertSame($daGiocare->slice(1)->pluck('id')->all(), $coda->pluck('id')->all());
        $this->assertTrue($coda->every(fn (Partita $partita) => $partita->completa() && ! $partita->giocata()));
    }

    // --- Pagina -----------------------------------------------------------------------------------------

    public function test_il_pulsante_del_risultato_dice_alla_finestra_girone_e_partita_successiva(): void
    {
        $torneo = $this->torneoConDueGironi();
        $partita = $this->partiteDel($torneo, 1)->first();

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/data-partita="'.$partita->id.'"[^>]*data-successiva="'.preg_quote(route('amministrazione.tornei.partite.successiva', [$torneo, $partita]), '/').'"'
            .'[^>]*data-gruppo="girone-'.$partita->girone_id.'" data-gruppo-nome="Girone B" data-dettaglio="1ª giornata"/',
            $html,
        );
        // La finestra ha il pulsante «Successiva», la fascia del girone e gli avvisi che torneo.js riempie.
        foreach (['data-risultato-successiva', 'data-risultato-salva', 'data-risultato-testata', 'data-risultato-gruppo', 'data-risultato-esito', 'data-risultato-cambio', 'data-risultato-errore', 'data-risultato-fine', 'data-punteggio-errore'] as $aggancio) {
            $this->assertStringContainsString($aggancio, $html);
        }
        $this->assertStringContainsString('Successiva', $html);
    }

    public function test_la_pagina_di_gestione_non_fa_una_lettura_per_ogni_partita(): void
    {
        $torneo = $this->torneoConDueGironi();
        $indirizzo = route('amministrazione.tornei.show', $torneo);

        $this->actingAs($this->amministratore)->get($indirizzo)->assertOk();

        $letture = 0;
        DB::listen(function ($query) use (&$letture): void {
            if (str_contains($query->sql, '"gironi"') && str_contains($query->sql, 'limit 1')) {
                $letture++;
            }
        });

        $this->actingAs($this->amministratore)->get($indirizzo)->assertOk();

        // Il nome del girone per il pulsante arriva dal girone già caricato, non da una lettura per partita.
        $this->assertSame(0, $letture);
    }
}
