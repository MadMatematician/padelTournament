<?php

namespace Tests\Feature\Amministrazione;

use App\Enums\Fase;
use App\Models\Giocatore;
use App\Models\Partita;
use App\Models\Torneo;
use App\Models\User;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\TorneoSocialeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La formula con cui nasce un torneo (config/tornei.php): gironi da 3 squadre al massimo, le prime 2 alle finali,
 * set unico a 4 game senza tie-break in tutte le fasi tranne la finale, che si gioca al meglio di 3 set a 6 game
 * con il tie-break.
 */
class FormulaPredefinitaTest extends TestCase
{
    use RefreshDatabase;

    private User $amministratore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->amministratore = User::factory()->amministratore()->create();
    }

    private function torneoCreatoDalForm(): Torneo
    {
        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.store'), [
                'nome' => 'Torneo sociale',
                'tipologia' => config('tornei.tipologia_predefinita'),
                'data_inizio' => '',
                'numero_turni' => config('tornei.turni.predefinito'),
            ])
            ->assertSessionHasNoErrors();

        return Torneo::sole();
    }

    private function torneoConPartite(int $prima, int $seconda): Torneo
    {
        $torneo = Torneo::factory()->create();

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
     * @param  list<array{int, int}>  $set
     */
    private function invia(Torneo $torneo, Partita $partita, array $set)
    {
        return $this->actingAs($this->amministratore)->putJson(
            route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]),
            ['set' => array_map(fn (array $game) => ['a' => $game[0], 'b' => $game[1]], $set)],
        );
    }

    public function test_un_torneo_nuovo_ha_gironi_da_tre_e_due_qualificate(): void
    {
        $torneo = $this->torneoCreatoDalForm();

        $this->assertSame(3, config('tornei.gironi.squadre.predefinito'));
        $this->assertSame(2, config('tornei.finali.qualificate.predefinito'));
        $this->assertSame(3, $torneo->fresh()->squadre_per_girone);
        $this->assertSame(2, $torneo->fresh()->qualificatePerGirone());
        $this->assertFalse($torneo->fresh()->andata_ritorno);
    }

    public function test_il_default_della_colonna_coincide_con_la_configurazione(): void
    {
        // Una riga scritta senza passare dal modello prende il default della tabella.
        $id = DB::table('tornei')->insertGetId([
            'nome' => 'Scritto a mano',
            'tipologia' => config('tornei.tipologia_predefinita'),
            'numero_turni' => 1,
        ]);

        $this->assertSame(config('tornei.gironi.squadre.predefinito'), Torneo::findOrFail($id)->squadre_per_girone);
        $this->assertSame(config('tornei.finali.qualificate.predefinito'), Torneo::findOrFail($id)->qualificate_per_girone);
    }

    public function test_tutte_le_fasi_tranne_la_finale_si_giocano_a_set_unico_a_quattro_game_senza_tie_break(): void
    {
        $torneo = $this->torneoCreatoDalForm();

        foreach ([Fase::Gironi, Fase::Sedicesimi, Fase::Ottavi, Fase::Quarti, Fase::Semifinali] as $fase) {
            $formato = $torneo->formato($fase);

            $this->assertSame([1, 4, false, false], [$formato->set, $formato->game, $formato->tieBreak, $formato->dueGameDiScarto], $fase->value);
            $this->assertSame('Set unico a 4 game, senza tie-break (vince chi arriva per primo a 4 game)', $formato->descrizione());
        }
    }

    public function test_solo_la_finale_ha_il_tie_break_e_si_gioca_al_meglio_di_tre_set_a_sei_game(): void
    {
        $formato = $this->torneoCreatoDalForm()->formato(Fase::Finale);

        $this->assertSame([3, 6, true, 7], [$formato->set, $formato->game, $formato->tieBreak, $formato->puntiTieBreak]);
        $this->assertSame('Al meglio di 3 set a 6 game, tie-break a 7 punti', $formato->descrizione());
    }

    public function test_la_scheda_formula_propone_i_valori_predefiniti(): void
    {
        $torneo = $this->torneoCreatoDalForm();

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/id="formula-squadre"\s+value="3"/', $html);
        $this->assertMatchesRegularExpression('/id="formula-qualificate"\s+value="2"/', $html);

        foreach (['gironi', 'sedicesimi', 'ottavi', 'quarti', 'semifinali'] as $fase) {
            $this->assertMatchesRegularExpression('/<select name="formati\['.$fase.'\]\[set\]"[^>]*>\s*<option value="1" selected>/', $html);
            $this->assertMatchesRegularExpression('/id="formato-'.$fase.'-game"\s+value="4"/', $html);
            $this->assertMatchesRegularExpression('/<select name="formati\['.$fase.'\]\[tie_break\]"[^>]*>\s*<option value="no" selected>/', $html);
            // Senza tie-break la casella dei due game di scarto è attiva, e parte senza spunta.
            $this->assertDoesNotMatchRegularExpression('/<input type="checkbox" name="formati\['.$fase.'\]\[due_game_scarto\]"[^>]*(checked|disabled)/', $html);
        }

        $this->assertMatchesRegularExpression('/<select name="formati\[finale\]\[set\]"[^>]*>\s*<option value="1" >[^<]*<\/option>\s*<option value="3" selected>/', $html);
        $this->assertMatchesRegularExpression('/id="formato-finale-game"\s+value="6"/', $html);
        $this->assertMatchesRegularExpression('/<select name="formati\[finale\]\[tie_break\]"[^>]*>\s*<option value="no" >[^<]*<\/option>\s*<option value="standard" selected>/', $html);
    }

    public function test_nei_gironi_vale_il_set_unico_a_quattro_game(): void
    {
        // 6 coppie con gironi da 3: due gironi, tre partite ciascuno.
        $torneo = $this->torneoConPartite(6, 6);
        $this->assertSame([3, 3], $torneo->gironi()->withCount('coppie')->orderBy('posizione')->pluck('coppie_count')->all());
        [$prima, $seconda, $terza] = $torneo->partiteDiGirone()->orderBy('id')->get()->all();

        $this->invia($torneo, $prima, [[4, 3]])->assertOk();
        $this->invia($torneo, $seconda, [[1, 4]])->assertOk();
        $this->assertSame('4-3', $prima->fresh()->punteggio());
        $this->assertSame($seconda->coppia_b_id, $seconda->fresh()->vincitrice_id);

        // Niente tie-break e niente oltre i 4 game; un secondo set non esiste.
        $this->invia($torneo, $terza, [[5, 3]])->assertStatus(422)->assertJsonValidationErrors(['set.0.a']);
        $this->invia($torneo, $terza, [[4, 4]])->assertStatus(422)->assertJsonValidationErrors(['set.0.a']);
        $this->invia($torneo, $terza, [[6, 4]])->assertStatus(422)->assertJsonValidationErrors(['set.0.a']);
        $this->invia($torneo, $terza, [[4, 2], [4, 1]])->assertStatus(422);
        $this->assertFalse($terza->fresh()->giocata());
    }

    public function test_un_torneo_intero_con_la_formula_predefinita_arriva_alla_finale_con_il_tie_break(): void
    {
        // 6 coppie: due gironi da 3, le prime due di ognuno in semifinale.
        $torneo = $this->torneoConPartite(6, 6);

        foreach ($torneo->partiteDiGirone()->get() as $partita) {
            $this->invia($torneo, $partita, $partita->coppia_a_id < $partita->coppia_b_id ? [[4, 2]] : [[2, 4]])->assertOk();
        }

        $this->assertSame(Fase::Semifinali, app(FinaliService::class)->generaTabellone($torneo->fresh()));

        foreach ($torneo->partiteDelTurno(Fase::Semifinali)->get() as $semifinale) {
            // Anche le semifinali sono a set unico a 4 game: un punteggio "da 6 game" viene respinto.
            $this->invia($torneo, $semifinale, [[6, 4], [6, 4]])->assertStatus(422);
            $this->invia($torneo, $semifinale, [[4, 3]])->assertOk();
        }

        $finale = $torneo->partiteDelTurno(Fase::Finale)->sole();
        $this->assertTrue($finale->completa());

        // In finale il set unico a 4 game non basta: tre set a 6 game, con il tie-break sul 6 pari.
        $this->invia($torneo, $finale, [[4, 3]])->assertStatus(422);
        $this->actingAs($this->amministratore)->putJson(
            route('amministrazione.tornei.partite.risultato.update', [$torneo, $finale]),
            ['set' => [['a' => 7, 'b' => 6, 'tb_a' => 7, 'tb_b' => 4], ['a' => 3, 'b' => 6], ['a' => 6, 'b' => 2]]],
        )->assertOk();

        $this->assertSame('7-6(7-4) 3-6 6-2', $finale->fresh()->punteggio());
        $this->assertSame($finale->coppia_a_id, $finale->fresh()->vincitrice_id);
    }

    public function test_la_formula_salvata_nel_torneo_vince_sui_predefiniti(): void
    {
        $torneo = Torneo::factory()->create([
            'squadre_per_girone' => 5,
            'formati' => [
                'gironi' => ['set' => 3, 'game' => 6, 'tie_break' => true, 'punti_tie_break' => 10],
                'finale' => ['set' => 1, 'game' => 9, 'tie_break' => false, 'punti_tie_break' => 7, 'due_game_scarto' => true],
            ],
        ])->fresh();

        $this->assertSame(5, $torneo->squadre_per_girone);
        $this->assertSame('Al meglio di 3 set a 6 game, tie-break a 10 punti', $torneo->formato(Fase::Gironi)->descrizione());
        $this->assertSame('Set unico a 9 game, senza tie-break (due game di scarto)', $torneo->formato(Fase::Finale)->descrizione());
        // Le fasi non impostate restano sul predefinito.
        $this->assertSame('Set unico a 4 game, senza tie-break (vince chi arriva per primo a 4 game)', $torneo->formato(Fase::Quarti)->descrizione());
    }
}
