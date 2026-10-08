<?php

namespace Tests\Feature\Amministrazione;

use App\Enums\Fase;
use App\Models\Coppia;
use App\Models\Giocatore;
use App\Models\Partita;
use App\Models\Torneo;
use App\Models\User;
use App\Services\Finali\CriterioRipescaggio;
use App\Services\Punteggio\ValutatorePunteggio;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\RisultatiService;
use App\Services\Tornei\TorneoSocialeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FormulaClassica;
use Tests\TestCase;

/**
 * Formato delle partite, risultati, classifica dei gironi, accesso alle finali e tabellone.
 */
class RisultatiEFinaliTest extends TestCase
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

    /**
     * Un torneo con coppie, gironi e partite già generati.
     *
     * @param  array<string, mixed>  $formula
     */
    private function torneoConPartite(int $prima, int $seconda, array $formula = [], ?string $prestito = null): Torneo
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
     * Le righe del form per un punteggio scritto a mano: "6-4 7-6(7-5)".
     *
     * @return list<array<string, int|string>>
     */
    private function set(string $punteggio): array
    {
        return array_map(function (string $set): array {
            preg_match('/^(\d+)-(\d+)(?:\((\d+)-(\d+)\))?$/', $set, $parti);

            return ['a' => (int) $parti[1], 'b' => (int) $parti[2], 'tb_a' => $parti[3] ?? '', 'tb_b' => $parti[4] ?? ''];
        }, explode(' ', $punteggio));
    }

    /** Registra un risultato senza passare dal form (per preparare gli scenari). */
    private function gioca(Partita $partita, string $punteggio): void
    {
        $partita = $partita->fresh();

        app(RisultatiService::class)->registra(
            $partita,
            (new ValutatorePunteggio)->valuta($partita->torneo->formato($partita->fase), $this->set($punteggio)),
        );
    }

    /**
     * Gioca tutte le partite dei gironi: vince sempre la coppia con l'identificativo più basso
     * (oppure quella indicata da `$favorita`), 6-3 6-3.
     */
    private function giocaIGironi(Torneo $torneo, ?callable $favorita = null): void
    {
        foreach ($torneo->partiteDiGirone()->get() as $partita) {
            $vinceA = $favorita !== null && ($favorita($partita->coppia_a_id) || $favorita($partita->coppia_b_id))
                ? $favorita($partita->coppia_a_id)
                : $partita->coppia_a_id < $partita->coppia_b_id;

            $this->gioca($partita, $vinceA ? '6-3 6-3' : '3-6 3-6');
        }
    }

    private function invia(Torneo $torneo, Partita $partita, string $punteggio)
    {
        return $this->actingAs($this->amministratore)->put(
            route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]),
            ['partita_id' => $partita->id, 'set' => $this->set($punteggio)],
        );
    }

    /** @return Collection<int, Partita> */
    private function turno(Torneo $torneo, Fase $fase): Collection
    {
        return $torneo->partite()->where('fase', $fase->value)->orderBy('posizione')->get();
    }

    /**
     * Le coppie già viste nel torneo, come "prima-seconda", escluse quelle di un turno delle finali.
     *
     * @return list<string>
     */
    private function giaInsieme(Torneo $torneo, ?Fase $tranne = null): array
    {
        return $torneo->tutteLeCoppie()
            ->when($tranne, fn ($coppie) => $coppie->where('fase', '!=', $tranne->value))
            ->get()
            ->map(fn (Coppia $coppia) => $coppia->prima_fascia_id.'-'.$coppia->seconda_fascia_id)
            ->all();
    }

    /**
     * I giocatori delle coppie indicate.
     *
     * @param  iterable<int>  $coppie  identificativi
     * @return list<int>
     */
    private function giocatoriDi(iterable $coppie): array
    {
        return Coppia::query()->whereKey(collect($coppie)->all())->get()
            ->flatMap(fn (Coppia $coppia) => $coppia->giocatori())
            ->sort()->values()->all();
    }

    /**
     * Verifica le coppie sorteggiate per un turno: formate dai giocatori attesi, ognuno una volta sola, un giocatore
     * per fascia, e mai due che avevano già fatto coppia (a meno che la coppia sia segnata come ripetuta).
     *
     * @param  list<int>  $giocatori
     */
    private function assertTurnoSorteggiato(Torneo $torneo, Fase $fase, array $giocatori, bool $senzaRipetizioni = true): void
    {
        $coppie = $torneo->coppieDelleFinali()->where('fase', $fase->value)->with(['primaFascia', 'secondaFascia'])->get();
        $prima = $this->giaInsieme($torneo, $fase);

        $this->assertCount(intdiv(count($giocatori), 2), $coppie, $fase->value);
        $this->assertSame($giocatori, $this->giocatoriDi($coppie->modelKeys()), $fase->value);

        foreach ($coppie as $coppia) {
            $this->assertSame(1, $coppia->primaFascia->fascia);
            $this->assertSame(2, $coppia->secondaFascia->fascia);
            $this->assertNull($coppia->girone_id);
            $this->assertNull($coppia->finali);
            $this->assertSame(in_array($coppia->prima_fascia_id.'-'.$coppia->seconda_fascia_id, $prima, true), $coppia->ripetuta);

            if ($senzaRipetizioni) {
                $this->assertFalse($coppia->ripetuta, $fase->value.': coppia ripetuta');
            }
        }

        // Ogni coppia ha il suo posto in una partita del turno, e nessuna partita ne ha una di un altro turno.
        $nellePartite = $this->turno($torneo, $fase)
            ->reject->bye()
            ->flatMap(fn (Partita $partita) => [$partita->coppia_a_id, $partita->coppia_b_id]);

        $this->assertEqualsCanonicalizing($coppie->modelKeys(), $nellePartite->all(), $fase->value);
    }

    /**
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

    // --- Formula: formato per fase, accesso alle finali -----------------------------------------------

    public function test_la_formula_propone_un_formato_per_ogni_fase(): void
    {
        $torneo = Torneo::factory()->create();

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Formato delle partite')
            ->assertSee('Squadre che accedono alle finali, per girone')
            ->assertSee('Ripescaggi')
            ->getContent();

        foreach (array_keys(config('tornei.fasi')) as $fase) {
            foreach (['set', 'game', 'tie_break', 'punti_tie_break', 'due_game_scarto'] as $campo) {
                $this->assertStringContainsString('name="formati['.$fase.']['.$campo.']"', $html);
            }
        }

        // Formula classica (trait): al meglio di 3 set a 6 game, tie-break standard a 7 punti; 2 qualificate per girone.
        $formato = $torneo->fresh()->formato(Fase::Quarti);
        $this->assertSame([3, 6, true, 7], [$formato->set, $formato->game, $formato->tieBreak, $formato->puntiTieBreak]);
        $this->assertMatchesRegularExpression('/id="formula-qualificate"\s+value="2"/', $html);
        $this->assertMatchesRegularExpression('/id="formato-gironi-game"\s+value="6"/', $html);
        $this->assertMatchesRegularExpression('/<select name="formati\[gironi\]\[tie_break\]"[^>]*>\s*<option value="no" >[^<]*<\/option>\s*<option value="standard" selected>/', $html);
        // I due game di scarto si scelgono solo senza tie-break: con il tie-break la casella è spenta e senza spunta.
        $this->assertFalse($formato->dueGameDiScarto);
        $this->assertMatchesRegularExpression('/<input type="checkbox" name="formati\[gironi\]\[due_game_scarto\]"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="checkbox" name="formati\[gironi\]\[due_game_scarto\]"[^>]*checked/', $html);
    }

    public function test_l_amministratore_salva_formati_diversi_per_fase(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore)
            ->patch(route('amministrazione.tornei.formula.update', $torneo), $this->formula([
                'qualificate_per_girone' => 1,
                'criterio_ripescaggio' => 'game',
                'formati' => [
                    // Senza tie-break e senza spunta: vince chi arriva per primo ai game previsti.
                    'gironi' => ['set' => '1', 'game' => 9, 'tie_break' => 'no'],
                    // Senza tie-break, con la spunta: servono due game di scarto.
                    'quarti' => ['set' => '1', 'game' => 4, 'tie_break' => 'no', 'due_game_scarto' => '1'],
                    // Con il tie-break la spunta non conta.
                    'semifinali' => ['tie_break' => 'personalizzato', 'punti_tie_break' => 10, 'due_game_scarto' => '1'],
                    // Con il tie-break standard i punti scritti nel campo non contano.
                    'finale' => ['set' => '5', 'tie_break' => 'standard', 'punti_tie_break' => 15],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Formula salvata: fino a 4 squadre per girone, sola andata, una squadra per girone alle finali.');

        $torneo->refresh();

        $this->assertSame(1, $torneo->qualificate_per_girone);
        $this->assertSame(CriterioRipescaggio::Game, $torneo->criterio_ripescaggio);

        $gironi = $torneo->formato(Fase::Gironi);
        $this->assertSame([1, 9, false], [$gironi->set, $gironi->game, $gironi->tieBreak]);
        $this->assertFalse($gironi->dueGameDiScarto);
        $this->assertSame('Set unico a 9 game, senza tie-break (vince chi arriva per primo a 9 game)', $gironi->descrizione());

        $quarti = $torneo->formato(Fase::Quarti);
        $this->assertSame([false, true], [$quarti->tieBreak, $quarti->dueGameDiScarto]);
        $this->assertSame('Set unico a 4 game, senza tie-break (due game di scarto)', $quarti->descrizione());
        $this->assertFalse($torneo->formato(Fase::Semifinali)->dueGameDiScarto);
        $this->assertSame([true, false], [$torneo->formati['quarti']['due_game_scarto'], $torneo->formati['semifinali']['due_game_scarto']]);

        $semifinali = $torneo->formato(Fase::Semifinali);
        $this->assertSame([3, 6, true, 10], [$semifinali->set, $semifinali->game, $semifinali->tieBreak, $semifinali->puntiTieBreak]);

        $finale = $torneo->formato(Fase::Finale);
        $this->assertSame([5, true, 7], [$finale->set, $finale->tieBreak, $finale->puntiTieBreak]);
        $this->assertSame('Al meglio di 5 set a 6 game, tie-break a 7 punti', $finale->descrizione());

        // La pagina ripropone ciò che è stato salvato.
        $html = $this->actingAs($this->amministratore)->get(route('amministrazione.tornei.show', $torneo))->getContent();

        $this->assertMatchesRegularExpression('/id="formato-gironi-game"\s+value="9"/', $html);
        $this->assertMatchesRegularExpression('/<select name="formati\[semifinali\]\[tie_break\]"[^>]*>.*?<option value="personalizzato" selected>/s', $html);
        $this->assertMatchesRegularExpression('/id="formato-semifinali-punti"\s+value="10"/', $html);
        $this->assertMatchesRegularExpression('/id="formato-quarti-scarto"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="formato-quarti-scarto"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="formato-gironi-scarto"[^>]*(checked|disabled)/', $html);
        $this->assertMatchesRegularExpression('/id="formato-semifinali-scarto"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/<option value="game" selected>/', $html);
    }

    /**
     * @param  array<string, mixed>  $modifiche
     */
    #[DataProvider('formuleNonValide')]
    public function test_i_valori_non_validi_della_formula_vengono_rifiutati(array $modifiche, string $campo): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore)
            ->from(route('amministrazione.tornei.show', $torneo))
            ->patch(route('amministrazione.tornei.formula.update', $torneo), $this->formula($modifiche))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHasErrorsIn('formula', [$campo]);

        $this->assertNull($torneo->fresh()->formati);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function formuleNonValide(): array
    {
        return [
            'nessuna qualificata' => [['qualificate_per_girone' => 0], 'qualificate_per_girone'],
            'troppe qualificate' => [['qualificate_per_girone' => 99], 'qualificate_per_girone'],
            'criterio sconosciuto' => [['criterio_ripescaggio' => 'simpatia'], 'criterio_ripescaggio'],
            'set pari' => [['formati' => ['gironi' => ['set' => '2']]], 'formati.gironi.set'],
            'troppi game' => [['formati' => ['quarti' => ['game' => 40]]], 'formati.quarti.game'],
            'pochi game' => [['formati' => ['quarti' => ['game' => 1]]], 'formati.quarti.game'],
            'tie-break sconosciuto' => [['formati' => ['finale' => ['tie_break' => 'forse']]], 'formati.finale.tie_break'],
            'due game di scarto non booleano' => [['formati' => ['finale' => ['tie_break' => 'no', 'due_game_scarto' => 'forse']]], 'formati.finale.due_game_scarto'],
            'tie-break personalizzato senza punti' => [['formati' => ['finale' => ['tie_break' => 'personalizzato', 'punti_tie_break' => '']]], 'formati.finale.punti_tie_break'],
            'tie-break personalizzato con troppi punti' => [['formati' => ['finale' => ['tie_break' => 'personalizzato', 'punti_tie_break' => 99]]], 'formati.finale.punti_tie_break'],
        ];
    }

    public function test_senza_il_formato_di_una_fase_la_formula_viene_rifiutata(): void
    {
        $torneo = Torneo::factory()->create();
        $formula = $this->formula();
        unset($formula['formati']['ottavi']);

        $this->actingAs($this->amministratore)
            ->patch(route('amministrazione.tornei.formula.update', $torneo), $formula)
            ->assertSessionHasErrorsIn('formula', ['formati.ottavi']);
    }

    // --- Risultati dei gironi --------------------------------------------------------------------------

    public function test_l_amministratore_inserisce_il_risultato_di_una_partita(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $partita = $torneo->partiteDiGirone()->first();

        $this->invia($torneo, $partita, '6-4 6-7(5-7) 7-5')
            ->assertRedirect(route('amministrazione.tornei.show', $torneo).'#girone-'.$partita->girone_id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $partita->refresh();

        $this->assertTrue($partita->giocata());
        $this->assertSame($partita->coppia_a_id, $partita->vincitrice_id);
        $this->assertSame([2, 1, 19, 16], [$partita->set_a, $partita->set_b, $partita->game_a, $partita->game_b]);
        $this->assertSame('6-4 6-7(5-7) 7-5', $partita->punteggio());
        $this->assertSame(['a' => 6, 'b' => 7, 'tb_a' => 5, 'tb_b' => 7], $partita->risultato[1]);

        // Le altre partite non sono toccate.
        $this->assertSame(1, $torneo->partite()->whereNotNull('risultato')->count());
    }

    public function test_la_pagina_mostra_il_risultato_e_il_pulsante_per_inserirlo(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        [$giocata, $daGiocare] = $torneo->partiteDiGirone()->get()->all();
        $this->gioca($giocata, '6-2 6-1');

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('6-2 6-1')
            ->assertSee('6 partite in calendario, 1 con il risultato.')
            ->assertSee('Mancano i risultati di 5 partite dei gironi.')
            ->assertSee('id="modale-risultato"', false)
            ->getContent();

        // Ogni partita ha il suo pulsante, con ciò che serve alla finestra del risultato.
        $this->assertStringContainsString('data-azione="'.route('amministrazione.tornei.partite.risultato.update', [$torneo, $daGiocare]).'"', $html);
        $this->assertStringContainsString('data-cancella="'.route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $giocata]).'"', $html);
        $this->assertMatchesRegularExpression('/data-partita="'.$daGiocare->id.'"[^>]*data-cancella=""/', $html);
        $this->assertStringContainsString('data-set-massimi="3"', $html);
        $this->assertStringContainsString('data-formato="Al meglio di 3 set a 6 game, tie-break a 7 punti"', $html);
        $this->assertStringContainsString('&quot;a&quot;:6,&quot;b&quot;:2', $html);
        // La finestra è chiusa.
        $this->assertDoesNotMatchRegularExpression('/id="modale-risultato"[^>]*data-apri-modale/', $html);
    }

    /** @return array<string, array{string, string}> punteggio, campo con l'errore */
    public static function punteggiNonValidi(): array
    {
        return [
            'set in parità' => ['6-6 6-0', 'set.0.a'],
            'secondo set impossibile' => ['6-4 8-6', 'set.1.a'],
            'partita non finita' => ['6-4 4-6', 'set'],
            'un set di troppo' => ['6-4 6-4 6-4', 'set.2.a'],
            'tie-break sbagliato' => ['7-6(7-6) 6-0', 'set.0.a'],
        ];
    }

    #[DataProvider('punteggiNonValidi')]
    public function test_un_punteggio_non_valido_viene_rifiutato(string $punteggio, string $campo): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $partita = $torneo->partiteDiGirone()->first();

        $this->from(route('amministrazione.tornei.show', $torneo))
            ->invia($torneo, $partita, $punteggio)
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHasErrorsIn('risultato', [$campo]);

        $this->assertFalse($partita->fresh()->giocata());
    }

    public function test_senza_punteggio_il_risultato_viene_rifiutato(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $partita = $torneo->partiteDiGirone()->first();
        $vuota = ['a' => '', 'b' => '', 'tb_a' => '', 'tb_b' => ''];

        foreach ([[], ['set' => [$vuota, $vuota]], ['set' => 'sei a quattro'], ['set' => [['a' => 'sei', 'b' => 4]]], ['set' => [['a' => -1, 'b' => 6]]]] as $dati) {
            $this->actingAs($this->amministratore)
                ->put(route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]), $dati)
                ->assertSessionHasErrorsIn('risultato');
        }

        $this->assertFalse($partita->fresh()->giocata());
    }

    public function test_dopo_un_punteggio_respinto_la_finestra_si_riapre_per_quella_partita(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $partita = $torneo->partiteDiGirone()->with(['coppiaA.primaFascia', 'coppiaA.secondaFascia'])->get()->get(2);

        $risposta = $this->actingAs($this->amministratore)
            ->followingRedirects()
            ->from(route('amministrazione.tornei.show', $torneo))
            ->put(route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]), [
                'partita_id' => $partita->id,
                'set' => $this->set('6-4 9-7'),
            ]);

        $risposta->assertOk()->assertSee('2° set: Con set a 6 game il punteggio 9-7 non è valido');

        $html = $risposta->getContent();

        $this->assertMatchesRegularExpression('/id="modale-risultato"[^>]*data-apri-modale/', $html);
        // Il form punta alla partita respinta e ripropone i valori inseriti.
        $this->assertStringContainsString('action="'.route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]).'" method="POST" novalidate="novalidate" id="form-risultato"', $html);
        $this->assertMatchesRegularExpression('/name="partita_id" value="'.$partita->id.'"/', $html);
        $this->assertMatchesRegularExpression('/name="set\[0\]\[a\]"\s+id="set-0-a"\s+value="6"/', $html);
        $this->assertMatchesRegularExpression('/name="set\[1\]\[a\]"\s+id="set-1-a"\s+value="9"/', $html);
        // L'errore è sul secondo set, non sul primo.
        $this->assertMatchesRegularExpression('/id="set-1-a"[^>]*class="[^"]*is-invalid/s', $html);
        $this->assertDoesNotMatchRegularExpression('/id="set-0-a"[^>]*class="[^"]*is-invalid/s', $html);
        // Formato al meglio di 3: le colonne del quarto e quinto set sono spente.
        $this->assertMatchesRegularExpression('/id="set-3-a"[^>]*disabled/s', $html);
        $this->assertDoesNotMatchRegularExpression('/id="set-2-a"[^>]*disabled/s', $html);
        $this->assertStringContainsString(e($partita->coppiaA->nome()), $html);
    }

    public function test_il_punteggio_segue_il_formato_della_fase(): void
    {
        $torneo = $this->torneoConPartite(4, 4, ['formati' => ['gironi' => [
            'set' => 1, 'game' => 9, 'tie_break' => false, 'punti_tie_break' => 7, 'due_game_scarto' => true,
        ]]]);
        [$prima, $seconda] = $torneo->partiteDiGirone()->get()->all();

        // Set unico a 9 game senza tie-break, con i due game di scarto: 9-7 e 11-9 vanno bene…
        $this->invia($torneo, $prima, '9-7')->assertSessionHasNoErrors();
        $this->invia($torneo, $seconda, '9-11')->assertSessionHasNoErrors();

        $this->assertSame([1, 0], [$prima->fresh()->set_a, $prima->fresh()->set_b]);
        $this->assertSame($seconda->coppia_b_id, $seconda->fresh()->vincitrice_id);

        // …mentre il classico 6-4 6-4 no, e nemmeno il 10-9 con tie-break.
        $terza = $torneo->partiteDiGirone()->get()->get(2);

        $this->invia($torneo, $terza, '6-4 6-4')->assertSessionHasErrorsIn('risultato');
        $this->invia($torneo, $terza, '10-9(7-3)')->assertSessionHasErrorsIn('risultato', ['set.0.a']);

        $this->assertFalse($terza->fresh()->giocata());
    }

    public function test_senza_tie_break_i_due_game_di_scarto_servono_solo_se_richiesti(): void
    {
        // Set unico a 4 game senza tie-break, come salvato dalla formula senza la spunta «Due game di scarto».
        $senzaScarto = ['set' => 1, 'game' => 4, 'tie_break' => false, 'punti_tie_break' => 7, 'due_game_scarto' => false];
        $torneo = $this->torneoConPartite(4, 4, ['formati' => ['gironi' => $senzaScarto]]);
        [$prima, $seconda, $terza] = $torneo->partiteDiGirone()->get()->all();

        // Vince chi arriva per primo a 4 game: il 4-3 è valido…
        $this->invia($torneo, $prima, '4-3')->assertSessionHasNoErrors();
        $this->invia($torneo, $seconda, '3-4')->assertSessionHasNoErrors();

        $this->assertSame($prima->coppia_a_id, $prima->fresh()->vincitrice_id);
        $this->assertSame($seconda->coppia_b_id, $seconda->fresh()->vincitrice_id);

        // …e il set non va oltre i 4 game.
        $this->invia($torneo, $terza, '5-3')->assertSessionHasErrorsIn('risultato', ['set.0.a']);
        $this->assertFalse($terza->fresh()->giocata());

        // Un formato salvato prima che esistesse la scelta non ha la chiave: vale lo stesso «senza due game di scarto».
        $torneo->update(['formati' => ['gironi' => ['set' => 1, 'game' => 4, 'tie_break' => false, 'punti_tie_break' => 7]]]);
        $this->invia($torneo, $terza, '4-3')->assertSessionHasNoErrors();

        // Con la spunta torna la regola dei due game: 4-3 respinto, 5-3 accettato.
        $torneo->update(['formati' => ['gironi' => ['due_game_scarto' => true] + $senzaScarto]]);
        $quarta = $torneo->partiteDiGirone()->get()->get(3);

        $this->invia($torneo, $quarta, '4-3')->assertSessionHasErrorsIn('risultato', ['set.0.a']);
        $this->assertSame(
            'Con set a 4 game il punteggio 4-3 non è valido (senza tie-break si vince con due game di scarto).',
            session('errors')->getBag('risultato')->first('set.0.a'),
        );
        $this->invia($torneo, $quarta, '5-3')->assertSessionHasNoErrors();
    }

    public function test_un_risultato_si_puo_correggere_e_cancellare(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $partita = $torneo->partiteDiGirone()->first();

        $this->invia($torneo, $partita, '6-4 6-4');
        $this->invia($torneo, $partita, '4-6 4-6')->assertSessionHasNoErrors();

        $partita->refresh();
        $this->assertSame($partita->coppia_b_id, $partita->vincitrice_id);
        $this->assertSame([0, 2, 8, 12], [$partita->set_a, $partita->set_b, $partita->game_a, $partita->game_b]);

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $partita]))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo).'#girone-'.$partita->girone_id)
            ->assertSessionHas('status', 'Risultato cancellato.');

        $partita->refresh();
        $this->assertFalse($partita->giocata());
        $this->assertNull($partita->vincitrice_id);
        $this->assertNull($partita->set_a);
    }

    public function test_la_partita_deve_essere_del_torneo_indicato(): void
    {
        $torneo = $this->torneoConPartite(2, 2);
        $altroTorneo = $this->torneoConPartite(2, 2);
        $partita = $altroTorneo->partiteDiGirone()->first();

        $this->invia($torneo, $partita, '6-4 6-4')->assertNotFound();

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $partita]))
            ->assertNotFound();

        $this->assertFalse($partita->fresh()->giocata());
    }

    public function test_rifare_le_partite_cancella_i_risultati(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $this->giocaIGironi($torneo);

        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.partite.store', $torneo));

        $this->assertSame(6, $torneo->partite()->count());
        $this->assertSame(0, $torneo->partite()->whereNotNull('risultato')->count());
    }

    // --- Classifica -----------------------------------------------------------------------------------

    public function test_la_classifica_del_girone_segue_i_risultati(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $coppie = $torneo->coppie()->orderBy('id')->get();
        $this->giocaIGironi($torneo);

        $classifica = app(FinaliService::class)->classifiche($torneo)->first();

        // Vince sempre la coppia con l'identificativo più basso: la classifica è nell'ordine delle coppie.
        $this->assertSame($coppie->modelKeys(), array_map(fn ($riga) => $riga->coppia, $classifica));
        $this->assertSame([6, 4, 2, 0], array_map(fn ($riga) => $riga->punti, $classifica));
        $this->assertSame([3, 3, 3, 3], array_map(fn ($riga) => $riga->giocate, $classifica));
        $this->assertSame([6, 0], [$classifica[0]->setVinti, $classifica[0]->setPersi]);
        $this->assertSame(18, $classifica[0]->differenzaGame());

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Girone A')
            ->assertSee('Gironi conclusi: tutte le 6 partite hanno un risultato.')
            ->assertSee('Tutte le squadre')
            ->assertSee('Accesso alle finali')
            ->assertSee('Le prime 2 di ogni girone: 2 squadre.')
            ->assertSee('Finale (2 squadre).')
            ->assertSeeInOrder($coppie->map->nome()->all())
            ->assertViewHas('stati', fn (array $stati) => $stati === [$coppie[0]->id => 'diretta', $coppie[1]->id => 'diretta']);
    }

    public function test_la_classifica_e_provvisoria_finche_mancano_risultati(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $this->gioca($torneo->partiteDiGirone()->first(), '6-0 6-0');

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Classifica provvisoria: mancano i risultati di 5 partite su 6.')
            ->assertSee('Situazione provvisoria');
    }

    public function test_senza_gironi_la_classifica_non_e_disponibile(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Classifica non ancora disponibile');

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertSee('Tabellone non ancora generato')
            ->assertDontSee('Genera finali');
    }

    public function test_le_tre_pagine_del_torneo_sono_collegate(): void
    {
        $torneo = Torneo::factory()->create();

        foreach (['amministrazione.tornei.show', 'amministrazione.tornei.classifica', 'amministrazione.tornei.tabellone'] as $rotta) {
            $this->actingAs($this->amministratore)
                ->get(route($rotta, $torneo))
                ->assertOk()
                ->assertSee('href="'.route('amministrazione.tornei.show', $torneo).'"', false)
                ->assertSee('href="'.route('amministrazione.tornei.classifica', $torneo).'"', false)
                ->assertSee('href="'.route('amministrazione.tornei.tabellone', $torneo).'"', false);
        }
    }

    // --- Finali: generazione --------------------------------------------------------------------------

    public function test_le_finali_non_si_generano_finche_mancano_risultati(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $partite = $torneo->partiteDiGirone()->get();

        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.tabellone.store', $torneo))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo).'#sorteggio')
            ->assertSessionHas('errore', 'Mancano i risultati di 6 partite dei gironi.');

        foreach ($partite->skip(1) as $partita) {
            $this->gioca($partita, '6-3 6-3');
        }

        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.tabellone.store', $torneo))
            ->assertSessionHas('errore', 'Manca il risultato di una partita dei gironi.');

        $this->assertFalse($torneo->haFinali());

        // Senza partite generate.
        $vuoto = Torneo::factory()->create();

        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.tabellone.store', $vuoto))
            ->assertSessionHas('errore');
    }

    public function test_due_gironi_con_due_qualificate_danno_semifinali_e_finale(): void
    {
        $torneo = $this->torneoConPartite(8, 8);
        $this->giocaIGironi($torneo);

        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.tabellone.store', $torneo))
            ->assertRedirect(route('amministrazione.tornei.tabellone', $torneo))
            ->assertSessionHas('status', 'Tabellone delle finali generato: si parte da semifinali.');

        $semifinali = $this->turno($torneo, Fase::Semifinali);
        $finale = $this->turno($torneo, Fase::Finale);

        $this->assertCount(2, $semifinali);
        $this->assertCount(1, $finale);
        $this->assertSame(3, $torneo->partiteDelleFinali()->count());

        // Quattro qualificate, tutte dirette, con le teste di serie da 1 a 4: le prime dei gironi sono la 1 e la 2.
        $qualificate = $torneo->coppie()->whereNotNull('finali')->orderBy('testa_di_serie')->get();
        $this->assertSame([1, 2, 3, 4], $qualificate->pluck('testa_di_serie')->all());
        $this->assertSame(['diretta'], $qualificate->pluck('finali')->unique()->values()->all());

        $prime = app(FinaliService::class)->classifiche($torneo)->map(fn ($righe) => $righe[0]->coppia)->values()->all();
        $this->assertEqualsCanonicalizing($prime, $qualificate->take(2)->modelKeys());

        // Le semifinali non le giocano le coppie dei gironi: sono state sorteggiate a due a due e si sono
        // scambiate i compagni. Quattro coppie nuove, nessuna uguale a una coppia dei gironi.
        $this->assertTurnoSorteggiato($torneo, Fase::Semifinali, $this->giocatoriDi($qualificate->modelKeys()));
        $this->assertTrue($semifinali->every->completa());

        // La finale si sorteggia dopo le semifinali.
        $this->assertNull($finale[0]->coppia_a_id);
        $this->assertNull($finale[0]->coppia_b_id);
        $this->assertSame(0, $torneo->coppieDelleFinali()->where('fase', 'finale')->count());

        // Le coppie del torneo restano quelle dei gironi.
        $this->assertSame(8, $torneo->coppie()->count());
        $this->assertSame(12, $torneo->tutteLeCoppie()->count());

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('8 coppie.')
            ->assertViewHas('coppie', fn ($coppie) => $coppie->count() === 8);
    }

    public function test_tre_gironi_con_una_qualificata_ripescano_la_migliore_seconda(): void
    {
        // 6 coppie in 3 gironi da 2: tre prime, tabellone da 4, una ripescata tra le seconde.
        $torneo = $this->torneoConPartite(6, 6, ['squadre_per_girone' => 2, 'qualificate_per_girone' => 1]);
        $partite = $torneo->partiteDiGirone()->get();

        // In ogni girone vince la coppia A; la sconfitta meno pesante è quella del secondo girone.
        $this->gioca($partite[0], '6-0 6-0');
        $this->gioca($partite[1], '7-6 7-6');
        $this->gioca($partite[2], '6-2 6-2');

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('La prima di ogni girone: 3 squadre.')
            ->assertSee('Semifinali (4 squadre).')
            ->assertSee('Un posto da completare')
            ->assertViewHas('stati', fn (array $stati) => ($stati[$partite[1]->coppia_b_id] ?? null) === 'ripescata'
                && count(array_keys($stati, 'diretta', true)) === 3
                && count($stati) === 4);

        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.tabellone.store', $torneo))->assertSessionHas('status');

        $ripescata = $torneo->coppie()->where('finali', 'ripescata')->sole();

        $this->assertSame($partite[1]->coppia_b_id, $ripescata->id);
        $this->assertSame(4, $ripescata->testa_di_serie);
        $this->assertSame(3, $torneo->coppie()->where('finali', 'diretta')->count());
        $this->assertCount(2, $this->turno($torneo, Fase::Semifinali));

        // I giocatori della ripescata entrano nel sorteggio delle semifinali come tutti gli altri.
        $this->assertTurnoSorteggiato($torneo, Fase::Semifinali, $this->giocatoriDi($torneo->coppie()->whereNotNull('finali')->pluck('id')));
        $this->assertContains($ripescata->prima_fascia_id, $this->giocatoriDi($torneo->coppieDelleFinali()->pluck('id')));
    }

    public function test_il_criterio_della_differenza_game_sceglie_un_altra_ripescata(): void
    {
        $torneo = $this->torneoConPartite(6, 6, ['squadre_per_girone' => 2, 'qualificate_per_girone' => 1, 'criterio_ripescaggio' => 'game']);
        $partite = $torneo->partiteDiGirone()->get();

        // Le seconde: -12 game, -7 game (ma ha vinto un set: con i punti e i set sarebbe lei) e -4 game.
        $this->gioca($partite[0], '6-0 6-0');
        $this->gioca($partite[1], '6-7 6-0 6-0');
        $this->gioca($partite[2], '6-4 6-4');

        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.tabellone.store', $torneo));

        $this->assertSame($partite[2]->coppia_b_id, $torneo->coppie()->where('finali', 'ripescata')->sole()->id);

        // Con il criterio dei punti (a parità: differenza set) passa quella che ha vinto un set.
        $torneo->update(['criterio_ripescaggio' => 'punti']);
        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.tabellone.store', $torneo));

        $this->assertSame($partite[1]->coppia_b_id, $torneo->coppie()->where('finali', 'ripescata')->sole()->id);
    }

    public function test_se_le_squadre_non_bastano_le_prime_teste_di_serie_passano_il_turno(): void
    {
        // 6 coppie in 3 gironi da 2, tutte qualificate: tabellone da 8 con due posti vuoti.
        $torneo = $this->torneoConPartite(6, 6, ['squadre_per_girone' => 2, 'qualificate_per_girone' => 2]);
        $this->giocaIGironi($torneo);

        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.tabellone.store', $torneo))
            ->assertSessionHas('status', 'Tabellone delle finali generato: si parte da quarti di finale.');

        $quarti = $this->turno($torneo, Fase::Quarti);

        $this->assertCount(4, $quarti);
        $this->assertSame(7, $torneo->partiteDelleFinali()->count());
        $this->assertSame(0, $torneo->coppie()->where('finali', 'ripescata')->count());

        // Le due migliori qualificate passano il turno senza giocare, con la coppia dei gironi.
        $passano = $quarti->filter->bye()->values();
        $this->assertCount(2, $passano);

        foreach ($passano as $partita) {
            $coppia = Coppia::find($partita->coppia_a_id);

            $this->assertNull($partita->coppia_b_id);
            $this->assertFalse($partita->giocata());
            $this->assertSame(Fase::Gironi, $coppia->fase);
            $this->assertContains($coppia->testa_di_serie, [1, 2]);
        }

        // Le altre quattro coppie vengono risorteggiate e giocano i due quarti.
        $altre = $torneo->coppie()->where('testa_di_serie', '>', 2)->pluck('id');
        $this->assertCount(4, $altre);
        $this->assertTurnoSorteggiato($torneo, Fase::Quarti, $this->giocatoriDi($altre));
        $this->assertSame(0, $torneo->coppieDelleFinali()->where('fase', 'semifinali')->count());

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertSee('Passa il turno')
            ->assertSee('Quarti di finale')
            ->assertSee('Semifinali')
            ->assertSee('Da sorteggiare')
            ->assertSee('Quarti di finale: mancano 2 risultati.');

        // Il risultato di un posto vuoto non si inserisce.
        $this->invia($torneo, $passano->first(), '6-0 6-0')->assertSessionHas('errore');

        // Giocati i due quarti, in semifinale vanno i giocatori delle vincitrici insieme a quelli di chi
        // ha passato il turno: otto giocatori, quattro coppie nuove.
        [$primo, $secondo] = $quarti->reject->bye()->values()->all();

        $this->invia($torneo, $primo, '6-4 6-4')->assertSessionHasNoErrors();
        $this->assertSame(0, $torneo->coppieDelleFinali()->where('fase', 'semifinali')->count());

        $this->invia($torneo, $secondo, '4-6 4-6')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $stato) => str_ends_with($stato, 'Turno concluso: sorteggiate le nuove coppie per «Semifinali».'));

        $inGara = $this->giocatoriDi([$primo->coppia_a_id, $secondo->coppia_b_id, ...$passano->pluck('coppia_a_id')]);
        $this->assertCount(8, $inGara);
        $this->assertTurnoSorteggiato($torneo, Fase::Semifinali, $inGara);
        $this->assertTrue($this->turno($torneo, Fase::Semifinali)->every->completa());
    }

    public function test_le_coppie_con_un_giocatore_in_prestito_non_accedono_alle_finali(): void
    {
        // 5 + 4 giocatori: cinque coppie, una completata da un prestito, in due gironi (3 e 2).
        $torneo = Torneo::factory()->create(['squadre_per_girone' => 3, 'qualificate_per_girone' => 1]);
        $torneo->giocatori()->attach(Giocatore::factory()->fascia(1)->count(5)->create(['lato' => null]));
        $torneo->giocatori()->attach(Giocatore::factory()->fascia(2)->count(4)->create(['lato' => null]));
        $torneo = $torneo->fresh();

        $sorteggio = app(TorneoSocialeService::class);
        $sorteggio->generaCoppie($torneo);
        $sorteggio->generaGironi($torneo);
        $sorteggio->generaPartite($torneo);

        $inPrestito = $torneo->coppie()->whereNotNull('prestito_id')->sole();

        // La coppia con il prestito vince tutte le sue partite: è prima nel suo girone.
        $this->giocaIGironi($torneo, fn (int $coppia) => $coppia === $inPrestito->id);

        $classifica = app(FinaliService::class)->classifiche($torneo)[$inPrestito->girone_id];
        $this->assertSame($inPrestito->id, $classifica[0]->coppia);

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('prestito: non accede')
            ->assertViewHas('stati', fn (array $stati) => $stati[$inPrestito->id] === 'esclusa'
                && ($stati[$classifica[1]->coppia] ?? null) === 'diretta');

        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.tabellone.store', $torneo))->assertSessionHas('status');

        // Al suo posto accede la seconda del girone.
        $this->assertNull($inPrestito->fresh()->finali);
        $this->assertSame('diretta', Coppia::find($classifica[1]->coppia)->finali);
        $this->assertSame(0, $torneo->partiteDelleFinali()
            ->where(fn ($partite) => $partite->where('coppia_a_id', $inPrestito->id)->orWhere('coppia_b_id', $inPrestito->id))
            ->count());
    }

    // --- Finali: risultati e avanzamento --------------------------------------------------------------

    /** Un torneo con le semifinali appena generate (due gironi da quattro, due qualificate). */
    private function torneoConSemifinali(): Torneo
    {
        $torneo = $this->torneoConPartite(8, 8);
        $this->giocaIGironi($torneo);
        app(FinaliService::class)->generaTabellone($torneo);

        return $torneo;
    }

    public function test_con_l_ultimo_risultato_del_turno_si_sorteggiano_le_coppie_del_turno_successivo(): void
    {
        $torneo = $this->torneoConSemifinali();
        [$prima, $seconda] = $this->turno($torneo, Fase::Semifinali)->all();

        $this->invia($torneo, $prima, '6-4 6-4')
            ->assertRedirect(route('amministrazione.tornei.tabellone', $torneo))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $stato) => ! str_contains($stato, 'sorteggiate'));

        // Manca una semifinale: la finale non ha ancora le sue coppie e non si può giocare.
        $finale = $this->turno($torneo, Fase::Finale)->sole();
        $this->assertFalse($finale->completa());
        $this->assertSame(0, $torneo->coppieDelleFinali()->where('fase', 'finale')->count());
        $this->invia($torneo, $finale, '6-0 6-0')->assertSessionHas('errore', 'Le coppie di questa partita non sono ancora note.');

        $this->invia($torneo, $seconda, '3-6 6-3 2-6')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $stato) => str_ends_with($stato, 'Turno concluso: sorteggiate le nuove coppie per «Finale».'));

        // In finale ci sono i quattro giocatori delle due coppie vincitrici, rimescolati.
        $finale->refresh();
        $this->assertTrue($finale->completa());
        $this->assertTurnoSorteggiato(
            $torneo,
            Fase::Finale,
            $this->giocatoriDi([$prima->coppia_a_id, $seconda->coppia_b_id]),
        );

        // Le due coppie vincitrici si scambiano i compagni: per come sono nate le semifinali (anch'esse da uno
        // scambio) le coppie che ne escono sono nuove, chiunque abbia vinto.
        $vincitrici = Coppia::query()->findMany([$prima->coppia_a_id, $seconda->coppia_b_id]);
        $finaliste = $torneo->coppieDelleFinali()->where('fase', 'finale')->get();

        $this->assertEqualsCanonicalizing(
            [
                [$vincitrici[0]->prima_fascia_id, $vincitrici[1]->seconda_fascia_id],
                [$vincitrici[1]->prima_fascia_id, $vincitrici[0]->seconda_fascia_id],
            ],
            $finaliste->map(fn (Coppia $coppia) => $coppia->giocatori())->all(),
        );
        $this->assertSame(0, $finaliste->where('ripetuta', true)->count());
    }

    public function test_finche_la_finale_non_e_giocata_la_semifinale_si_puo_correggere(): void
    {
        $torneo = $this->torneoConSemifinali();
        [$prima, $seconda] = $this->turno($torneo, Fase::Semifinali)->all();

        $this->invia($torneo, $prima, '6-4 6-4');
        $this->invia($torneo, $seconda, '6-4 6-4');

        $finaliste = $torneo->coppieDelleFinali()->where('fase', 'finale')->pluck('id')->all();
        $this->assertCount(2, $finaliste);

        // Stessa vincitrice, altro punteggio: il sorteggio della finale resta quello.
        $this->invia($torneo, $prima, '7-5 6-0')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $stato) => ! str_contains($stato, 'sorteggiate'));

        $this->assertSame($finaliste, $torneo->coppieDelleFinali()->where('fase', 'finale')->pluck('id')->all());

        // Cambia la vincitrice: la finale viene sorteggiata di nuovo tra i giocatori che ora la raggiungono.
        $this->invia($torneo, $prima, '4-6 4-6')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $stato) => str_contains($stato, 'sorteggiate le nuove coppie per «Finale»'));

        $this->assertSame([], array_intersect($finaliste, $torneo->coppieDelleFinali()->pluck('id')->all()));
        $this->assertTurnoSorteggiato(
            $torneo,
            Fase::Finale,
            $this->giocatoriDi([$prima->coppia_b_id, $seconda->coppia_a_id]),
        );

        // Cancellato un risultato delle semifinali, la finale torna da sorteggiare (la partita resta).
        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $prima]))
            ->assertRedirect(route('amministrazione.tornei.tabellone', $torneo))
            ->assertSessionHas('status');

        $finale = $this->turno($torneo, Fase::Finale)->sole();
        $this->assertNull($finale->coppia_a_id);
        $this->assertNull($finale->coppia_b_id);
        $this->assertSame(0, $torneo->coppieDelleFinali()->where('fase', 'finale')->count());
        $this->assertFalse($prima->fresh()->giocata());
        $this->assertTrue($seconda->fresh()->giocata());
        $this->assertSame(3, $torneo->partiteDelleFinali()->count());
    }

    public function test_giocata_la_finale_le_semifinali_non_cambiano_vincitrice(): void
    {
        $torneo = $this->torneoConSemifinali();
        [$prima, $seconda] = $this->turno($torneo, Fase::Semifinali)->all();

        $this->gioca($prima, '6-4 6-4');
        $this->gioca($seconda, '6-4 6-4');
        $finale = $this->turno($torneo, Fase::Finale)->sole();
        $this->gioca($finale, '7-5 6-7(3-7) 6-2');
        $finaliste = [$finale->coppia_a_id, $finale->coppia_b_id];

        // Cambiare la vincitrice o cancellare il risultato della semifinale non è più possibile…
        $this->invia($torneo, $prima, '0-6 0-6')->assertSessionHas('errore');
        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $prima]))
            ->assertSessionHas('errore');

        $this->assertSame($prima->coppia_a_id, $prima->fresh()->vincitrice_id);
        $this->assertSame('6-4 6-4', $prima->fresh()->punteggio());

        // …ma correggere il punteggio lasciando la stessa vincitrice sì, e la finale resta com'è.
        $this->invia($torneo, $prima, '6-0 6-0')->assertSessionHasNoErrors();
        $this->assertSame('6-0 6-0', $prima->fresh()->punteggio());

        $finale->refresh();
        $this->assertSame($finaliste, [$finale->coppia_a_id, $finale->coppia_b_id]);
        $this->assertSame('7-5 6-7(3-7) 6-2', $finale->punteggio());
    }

    public function test_il_tabellone_mostra_incontri_risultati_e_vincitrice_del_torneo(): void
    {
        $torneo = $this->torneoConSemifinali();
        [$prima, $seconda] = $this->turno($torneo, Fase::Semifinali)->all();

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertSee('Semifinali')
            ->assertSee('Finale')
            ->assertSee('Da sorteggiare')
            ->assertSee('Semifinali: mancano 2 risultati.')
            ->assertSee('si sorteggiano le coppie nuove per «Finale»')
            ->assertSee('le due coppie si scambiano i compagni')
            ->assertSee('Annulla finali')
            ->assertSee(Coppia::find($prima->coppia_a_id)->nome())
            ->assertDontSee('Torneo concluso')
            ->assertViewHas('turni', fn ($turni) => $turni->keys()->all() === ['semifinali', 'finale'])
            ->getContent();

        // Le semifinali hanno il pulsante del risultato, la finale (ancora senza coppie) no.
        $this->assertSame(2, substr_count($html, ' data-risultato="'));
        $this->assertStringContainsString('data-azione="'.route('amministrazione.tornei.partite.risultato.update', [$torneo, $prima]).'"', $html);

        $this->gioca($prima, '6-4 6-4');
        $this->gioca($seconda, '6-4 6-4');
        $finale = $this->turno($torneo, Fase::Finale)->sole();

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertSee('Finale: manca un risultato.')
            ->assertDontSee('Da sorteggiare')
            ->assertSee(Coppia::find($finale->coppia_a_id)->nome());

        $this->gioca($finale, '6-1 6-1');

        $vincitrice = Coppia::with(['primaFascia', 'secondaFascia'])->find($finale->coppia_a_id);
        $sconfitta = Coppia::find($finale->coppia_b_id);

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertSee('Torneo concluso')
            ->assertDontSee('mancano')
            ->assertSee($vincitrice->primaFascia->nomeCompleto().' / '.$vincitrice->secondaFascia->nomeCompleto());

        // La classifica racconta il cammino di ogni giocatore: nelle finali passano il turno i giocatori.
        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Giocatori nelle finali')
            ->assertSee('Ha vinto il torneo')
            ->assertSee('Finalista')
            ->assertSee('Qualificata')
            ->assertViewHas('cammini', function (array $cammini) use ($vincitrice, $sconfitta) {
                $turni = collect($cammini)->mapWithKeys(fn (array $cammino) => [$cammino['giocatore']->id => $cammino['turno']]);
                $diUno = collect($cammini)->firstWhere('giocatore.id', $vincitrice->prima_fascia_id);

                return count($cammini) === 8
                    && $turni->only($vincitrice->giocatori())->unique()->values()->all() === ['Ha vinto il torneo']
                    && $turni->only($sconfitta->giocatori())->unique()->values()->all() === ['Finalista']
                    && $turni->filter(fn (string $turno) => $turno === 'Semifinali')->count() === 4
                    && count($diUno['compagni']) === 2
                    && $diUno['compagni'][1] === 'Finale: con '.$vincitrice->secondaFascia->nomeBreve()
                    && $diUno['qualificata']->fase === Fase::Gironi;
            });
    }

    public function test_la_finale_puo_avere_un_formato_diverso_dalle_semifinali(): void
    {
        $torneo = $this->torneoConSemifinali();
        $torneo->update(['formati' => ['finale' => ['set' => 5, 'game' => 6, 'tie_break' => true, 'punti_tie_break' => 10]]]);

        foreach ($this->turno($torneo, Fase::Semifinali) as $semifinale) {
            // In semifinale tre set a zero non esistono: la partita finisce dopo due.
            $this->invia($torneo, $semifinale, '6-4 6-4 6-4')->assertSessionHasErrorsIn('risultato', ['set.2.a']);
            $this->invia($torneo, $semifinale, '6-4 6-4')->assertSessionHasNoErrors();
        }

        $finale = $this->turno($torneo, Fase::Finale)->sole();

        // In finale servono tre set, e il tie-break è a 10 punti.
        $this->invia($torneo, $finale, '6-4 6-4')->assertSessionHasErrorsIn('risultato', ['set']);
        $this->invia($torneo, $finale, '6-4 7-6(7-5) 6-4')->assertSessionHasErrorsIn('risultato', ['set.1.a']);
        $this->invia($torneo, $finale, '6-4 7-6(10-8) 6-4')->assertSessionHasNoErrors();

        $this->assertSame([3, 0], [$finale->fresh()->set_a, $finale->fresh()->set_b]);

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertSee('data-formato="Al meglio di 5 set a 6 game, tie-break a 10 punti"', false)
            ->assertSee('data-set-massimi="5"', false);
    }

    /**
     * Semifinali preparate a mano per sapere chi ha già giocato con chi: le quattro coppie qualificate
     * (prima i, seconda i) diventano c1 = 1+2, c2 = 2+3, c3 = 3+4, c4 = 4+1, con c1–c2 e c3–c4 di fronte.
     *
     * @return array{Torneo, Collection<int, Partita>, Collection<int, Coppia>} torneo, semifinali, coppie dei gironi
     */
    private function semifinaliNote(): array
    {
        $torneo = $this->torneoConSemifinali();
        $gironi = $torneo->coppie()->whereNotNull('finali')->orderBy('testa_di_serie')->get();
        $semifinali = $this->turno($torneo, Fase::Semifinali);

        // In due passi, per non avere a metà due coppie uguali nello stesso turno.
        foreach ([[0, 1], [1, 2], [2, 3], [3, 0]] as $indice => [$prima, $seconda]) {
            $partita = $semifinali[intdiv($indice, 2)];

            Coppia::query()->whereKey($indice % 2 === 0 ? $partita->coppia_a_id : $partita->coppia_b_id)->update([
                'fase' => 'provvisoria',
                'prima_fascia_id' => $gironi[$prima]->prima_fascia_id,
                'seconda_fascia_id' => $gironi[$seconda]->seconda_fascia_id,
            ]);
        }

        Coppia::query()->where('fase', 'provvisoria')->update(['fase' => Fase::Semifinali->value]);

        return [$torneo, $semifinali, $gironi];
    }

    public function test_la_finale_non_rimette_insieme_chi_ha_gia_giocato_in_coppia(): void
    {
        [$torneo, $semifinali, $gironi] = $this->semifinaliNote();

        // Vincono c1 (1+2) e c3 (3+4): restare insieme è vietato, quindi la finale è per forza 1+4 contro 3+2.
        $this->invia($torneo, $semifinali[0], '6-4 6-4');
        $this->invia($torneo, $semifinali[1], '6-4 6-4');

        $finaliste = $torneo->coppieDelleFinali()->where('fase', 'finale')->get();

        $this->assertEqualsCanonicalizing(
            [
                [$gironi[0]->prima_fascia_id, $gironi[3]->seconda_fascia_id],
                [$gironi[2]->prima_fascia_id, $gironi[1]->seconda_fascia_id],
            ],
            $finaliste->map(fn (Coppia $coppia) => $coppia->giocatori())->all(),
        );
        $this->assertSame(0, $finaliste->where('ripetuta', true)->count());

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertDontSee('Coppia già vista nel torneo');
    }

    public function test_una_coppia_ripetuta_inevitabile_viene_segnalata(): void
    {
        [$torneo, $semifinali, $gironi] = $this->semifinaliNote();

        // Vincono c1 (1+2) e c4 (4+1): scambiandosi i compagni escono 1+1, che era la coppia del girone, e 4+2.
        // È comunque meglio che riproporre le due coppie delle semifinali.
        $this->invia($torneo, $semifinali[0], '6-4 6-4');
        $this->invia($torneo, $semifinali[1], '4-6 4-6')->assertSessionHasNoErrors()->assertSessionHas('status');

        $finaliste = $torneo->coppieDelleFinali()->where('fase', 'finale')->get();
        $ripetuta = $finaliste->firstWhere('ripetuta', true);

        $this->assertCount(2, $finaliste);
        $this->assertSame(1, $finaliste->where('ripetuta', true)->count());
        $this->assertSame($gironi[0]->giocatori(), $ripetuta->giocatori());
        $this->assertSame(
            [$gironi[3]->prima_fascia_id, $gironi[1]->seconda_fascia_id],
            $finaliste->firstWhere('ripetuta', false)->giocatori(),
        );

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertSee('Coppia già vista nel torneo');
    }

    public function test_se_la_tipologia_non_ammette_ripetizioni_il_risultato_che_le_impone_viene_rifiutato(): void
    {
        config(['tornei.tipologie.sociale.sorteggio.consenti_ripetizione_se_impossibile' => false]);

        [$torneo, $semifinali] = $this->semifinaliNote();

        $this->invia($torneo, $semifinali[0], '6-4 6-4')->assertSessionHasNoErrors();
        $this->invia($torneo, $semifinali[1], '4-6 4-6')
            ->assertRedirect(route('amministrazione.tornei.tabellone', $torneo))
            ->assertSessionHas('errore', fn (string $errore) => str_contains($errore, 'una coppia dovrebbe ripetersi'));

        // Niente a metà: il risultato non è stato salvato e la finale non è stata sorteggiata.
        $this->assertFalse($semifinali[1]->fresh()->giocata());
        $this->assertSame(0, $torneo->coppieDelleFinali()->where('fase', 'finale')->count());

        // Con l'altra vincitrice le coppie nuove ci sono.
        $this->invia($torneo, $semifinali[1], '6-4 6-4')->assertSessionHasNoErrors();
        $this->assertSame(2, $torneo->coppieDelleFinali()->where('fase', 'finale')->where('ripetuta', false)->count());
    }

    public function test_se_la_tipologia_non_vieta_le_coppie_ripetute_il_sorteggio_e_libero(): void
    {
        config(['tornei.tipologie.sociale.sorteggio.vieta_coppie_ripetute' => false]);

        [$torneo, $semifinali] = $this->semifinaliNote();

        $this->invia($torneo, $semifinali[0], '6-4 6-4');
        $this->invia($torneo, $semifinali[1], '4-6 4-6')->assertSessionHasNoErrors();

        $finaliste = $torneo->coppieDelleFinali()->where('fase', 'finale')->get();

        $this->assertCount(2, $finaliste);
        $this->assertSame(0, $finaliste->where('ripetuta', true)->count());
    }

    public function test_in_un_torneo_intero_nessuno_gioca_due_volte_con_lo_stesso_compagno(): void
    {
        // 16 coppie in 4 gironi, le prime due ai quarti: tre turni, tre sorteggi.
        $torneo = $this->torneoConPartite(16, 16);
        $this->giocaIGironi($torneo);
        app(FinaliService::class)->generaTabellone($torneo);

        $inGara = $torneo->coppie()->whereNotNull('finali')->get();
        $this->assertCount(8, $inGara);

        foreach ([Fase::Quarti, Fase::Semifinali, Fase::Finale] as $fase) {
            $this->assertTurnoSorteggiato($torneo, $fase, $this->giocatoriDi($inGara->modelKeys()));

            // Ogni incontro è tra due coppie del turno prima che si sono scambiate i compagni: si gioca contro
            // il compagno di prima.
            $prima = $inGara->map(fn (Coppia $coppia) => $coppia->giocatori())->all();

            foreach ($this->turno($torneo, $fase) as $indice => $partita) {
                $partita->load(['coppiaA', 'coppiaB']);

                $this->assertContains([$partita->coppiaA->prima_fascia_id, $partita->coppiaB->seconda_fascia_id], $prima);
                $this->assertContains([$partita->coppiaB->prima_fascia_id, $partita->coppiaA->seconda_fascia_id], $prima);

                $this->invia($torneo, $partita, $indice % 2 === 0 ? '6-3 7-5' : '2-6 6-7')->assertSessionHasNoErrors();
            }

            $inGara = Coppia::query()->findMany($this->turno($torneo, $fase)->pluck('vincitrice_id'));
        }

        // Nessuna coppia due volte in tutto il torneo, finale compresa.
        $coppie = $torneo->tutteLeCoppie()->get();
        $this->assertSame(16 + 8 + 4 + 2, $coppie->count());
        $this->assertSame(0, $coppie->where('ripetuta', true)->count());
        $this->assertSame($coppie->count(), $coppie->unique(fn (Coppia $coppia) => $coppia->prima_fascia_id.'-'.$coppia->seconda_fascia_id)->count());
        $this->assertCount(1, $inGara);
    }

    public function test_con_il_sorteggio_libero_i_giocatori_rimasti_si_rimescolano_tutti(): void
    {
        config(['tornei.tipologie.sociale.sorteggio.finali' => 'libero']);

        $torneo = $this->torneoConPartite(16, 16);
        $this->giocaIGironi($torneo);
        app(FinaliService::class)->generaTabellone($torneo);

        $inGara = $this->giocatoriDi($torneo->coppie()->whereNotNull('finali')->pluck('id'));

        foreach ([Fase::Quarti, Fase::Semifinali, Fase::Finale] as $fase) {
            // Con almeno quattro coppie in gara un sorteggio senza ripetizioni esiste sempre; in finale, dove le
            // coppie possibili sono due sole, può non esserci: allora la coppia è segnata come ripetuta.
            $this->assertTurnoSorteggiato($torneo, $fase, $inGara, senzaRipetizioni: $fase !== Fase::Finale);

            foreach ($this->turno($torneo, $fase) as $indice => $partita) {
                $this->invia($torneo, $partita, $indice % 2 === 0 ? '6-3 7-5' : '2-6 6-7')->assertSessionHasNoErrors();
            }

            $inGara = $this->giocatoriDi($this->turno($torneo, $fase)->pluck('vincitrice_id'));
        }

        $this->assertCount(2, $inGara);
        $this->assertSame(16 + 8 + 4 + 2, $torneo->tutteLeCoppie()->count());
    }

    public function test_un_tabellone_nato_con_le_coppie_dei_gironi_prosegue_con_le_coppie_nuove(): void
    {
        // Prima di questa regola le finali si giocavano con le coppie dei gironi: un tabellone rimasto così
        // (semifinali già abbinate, nessuna coppia delle finali) continua a funzionare, e dal turno dopo scambia.
        $torneo = $this->torneoConPartite(8, 8);
        $this->giocaIGironi($torneo);

        $qualificate = $torneo->coppie()->orderBy('id')->limit(4)->get();
        $qualificate->each->update(['finali' => 'diretta']);

        foreach ([[0, 1], [2, 3]] as $posizione => [$a, $b]) {
            $torneo->partite()->create([
                'fase' => Fase::Semifinali, 'posizione' => $posizione,
                'coppia_a_id' => $qualificate[$a]->id, 'coppia_b_id' => $qualificate[$b]->id,
            ]);
        }
        $torneo->partite()->create(['fase' => Fase::Finale, 'posizione' => 0]);

        [$prima, $seconda] = $this->turno($torneo, Fase::Semifinali)->all();

        $this->actingAs($this->amministratore)->get(route('amministrazione.tornei.tabellone', $torneo))->assertOk();
        $this->actingAs($this->amministratore)->get(route('amministrazione.tornei.classifica', $torneo))->assertOk();

        $this->invia($torneo, $prima, '6-4 6-4')->assertSessionHasNoErrors();
        $this->invia($torneo, $seconda, '6-4 6-4')->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertEqualsCanonicalizing(
            [
                [$qualificate[0]->prima_fascia_id, $qualificate[2]->seconda_fascia_id],
                [$qualificate[2]->prima_fascia_id, $qualificate[0]->seconda_fascia_id],
            ],
            $torneo->coppieDelleFinali()->where('fase', 'finale')->get()->map(fn (Coppia $coppia) => $coppia->giocatori())->all(),
        );
        $this->assertSame(8, $torneo->coppie()->count());
        $this->assertTrue($this->turno($torneo, Fase::Finale)->sole()->completa());
    }

    // --- Finali e gironi ------------------------------------------------------------------------------

    public function test_generate_le_finali_i_risultati_dei_gironi_sono_bloccati(): void
    {
        $torneo = $this->torneoConSemifinali();
        $partita = $torneo->partiteDiGirone()->first();
        $punteggio = $partita->punteggio();

        $this->invia($torneo, $partita, '0-6 0-6')
            ->assertRedirect(route('amministrazione.tornei.show', $torneo).'#girone-'.$partita->girone_id)
            ->assertSessionHas('errore');

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $partita]))
            ->assertSessionHas('errore');

        $this->assertSame($punteggio, $partita->fresh()->punteggio());

        // La pagina di gestione non offre più il pulsante del risultato.
        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Tabellone generato: i risultati dei gironi sono bloccati.')
            ->assertSee('Rigenera finali')
            ->assertSee($punteggio)
            ->assertDontSee(' data-risultato="', false);
    }

    public function test_annullate_le_finali_i_gironi_tornano_modificabili(): void
    {
        $torneo = $this->torneoConSemifinali();
        $this->gioca($this->turno($torneo, Fase::Semifinali)->first(), '6-4 6-4');
        $partita = $torneo->partiteDiGirone()->first();

        $this->actingAs($this->amministratore)
            ->delete(route('amministrazione.tornei.tabellone.destroy', $torneo))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo).'#sorteggio')
            ->assertSessionHas('status');

        $this->assertFalse($torneo->haFinali());
        $this->assertSame(0, $torneo->coppieDelleFinali()->count());
        $this->assertSame(8, $torneo->tutteLeCoppie()->count());
        $this->assertSame(0, $torneo->coppie()->whereNotNull('finali')->count());
        $this->assertSame(0, $torneo->coppie()->whereNotNull('testa_di_serie')->count());
        // I gironi e i loro risultati restano.
        $this->assertSame(12, $torneo->partiteDiGirone()->whereNotNull('risultato')->count());

        $this->invia($torneo, $partita, '0-6 0-6')->assertSessionHasNoErrors();
        $this->assertSame($partita->coppia_b_id, $partita->fresh()->vincitrice_id);
    }

    public function test_rifare_le_finali_sostituisce_il_tabellone(): void
    {
        $torneo = $this->torneoConSemifinali();
        $this->gioca($this->turno($torneo, Fase::Semifinali)->first(), '6-4 6-4');

        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.tabellone.store', $torneo))->assertSessionHas('status');

        $this->assertSame(3, $torneo->partiteDelleFinali()->count());
        $this->assertSame(0, $torneo->partiteDelleFinali()->whereNotNull('risultato')->count());
        $this->assertNull($this->turno($torneo, Fase::Finale)->sole()->coppia_a_id);
        // Le coppie del tabellone precedente non restano in giro: solo le quattro del nuovo sorteggio.
        $this->assertSame(4, $torneo->coppieDelleFinali()->count());
        $this->assertTurnoSorteggiato($torneo, Fase::Semifinali, $this->giocatoriDi($torneo->coppie()->whereNotNull('finali')->pluck('id')));
    }

    public function test_rifare_un_passo_precedente_cancella_le_finali(): void
    {
        foreach (['amministrazione.tornei.partite.store', 'amministrazione.tornei.gironi.store', 'amministrazione.tornei.coppie.store'] as $rotta) {
            $torneo = $this->torneoConSemifinali();

            $this->actingAs($this->amministratore)->post(route($rotta, $torneo))->assertSessionHas('status');

            $this->assertFalse($torneo->haFinali(), $rotta);
            $this->assertSame(0, $torneo->coppieDelleFinali()->count(), $rotta);
            $this->assertSame(8, $torneo->tutteLeCoppie()->count(), $rotta);
            $this->assertSame(0, $torneo->partite()->whereNotNull('risultato')->count(), $rotta);
            $this->assertSame(0, $torneo->coppie()->whereNotNull('finali')->count(), $rotta);
        }

        $torneo = $this->torneoConSemifinali();

        $this->actingAs($this->amministratore)->delete(route('amministrazione.tornei.sorteggio.destroy', $torneo));

        $this->assertSame(0, $torneo->partite()->count());
        $this->assertSame(0, $torneo->tutteLeCoppie()->count());

        // Eliminare il torneo elimina anche le partite delle finali.
        $torneo = $this->torneoConSemifinali();
        $this->actingAs($this->amministratore)->delete(route('amministrazione.tornei.destroy', $torneo));

        $this->assertSame(0, Partita::where('torneo_id', $torneo->id)->count());
        $this->assertSame(0, Coppia::where('torneo_id', $torneo->id)->count());
    }

    public function test_dal_tabellone_vuoto_si_possono_generare_le_finali_a_gironi_conclusi(): void
    {
        $torneo = $this->torneoConPartite(4, 4);

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertSee('Mancano i risultati di 6 partite dei gironi su 6')
            ->assertDontSee('Genera finali');

        $this->giocaIGironi($torneo);

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.tabellone', $torneo))
            ->assertOk()
            ->assertSee('I gironi sono conclusi')
            ->assertSee('Genera finali')
            ->assertSee(route('amministrazione.tornei.tabellone.store', $torneo));

        // Un solo girone con due qualificate: si gioca direttamente la finale.
        $this->actingAs($this->amministratore)
            ->post(route('amministrazione.tornei.tabellone.store', $torneo))
            ->assertSessionHas('status', 'Tabellone delle finali generato: si parte da finale.');

        $this->assertSame(1, $torneo->partiteDelleFinali()->count());
    }

    // --- Permessi ---------------------------------------------------------------------------------------

    public function test_un_utente_non_puo_toccare_risultati_e_finali(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $partita = $torneo->partiteDiGirone()->first();
        $utente = User::factory()->create();

        $this->actingAs($utente)->put(route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]), ['set' => $this->set('6-0 6-0')])->assertForbidden();
        $this->actingAs($utente)->delete(route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $partita]))->assertForbidden();
        $this->actingAs($utente)->get(route('amministrazione.tornei.classifica', $torneo))->assertForbidden();
        $this->actingAs($utente)->get(route('amministrazione.tornei.tabellone', $torneo))->assertForbidden();
        $this->actingAs($utente)->post(route('amministrazione.tornei.tabellone.store', $torneo))->assertForbidden();
        $this->actingAs($utente)->delete(route('amministrazione.tornei.tabellone.destroy', $torneo))->assertForbidden();

        $this->assertFalse($partita->fresh()->giocata());
    }

    public function test_gli_ospiti_vengono_mandati_al_login(): void
    {
        $torneo = $this->torneoConPartite(4, 4);
        $partita = $torneo->partiteDiGirone()->first();

        $this->put(route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]), ['set' => $this->set('6-0 6-0')])->assertRedirect(route('login'));
        $this->delete(route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $partita]))->assertRedirect(route('login'));
        $this->get(route('amministrazione.tornei.classifica', $torneo))->assertRedirect(route('login'));
        $this->get(route('amministrazione.tornei.tabellone', $torneo))->assertRedirect(route('login'));
        $this->post(route('amministrazione.tornei.tabellone.store', $torneo))->assertRedirect(route('login'));
        $this->delete(route('amministrazione.tornei.tabellone.destroy', $torneo))->assertRedirect(route('login'));

        $this->assertFalse($partita->fresh()->giocata());
    }

    public function test_un_torneo_o_una_partita_inesistenti_danno_404(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore)->get(route('amministrazione.tornei.classifica', 999))->assertNotFound();
        $this->actingAs($this->amministratore)->get(route('amministrazione.tornei.tabellone', 999))->assertNotFound();
        $this->actingAs($this->amministratore)->post(route('amministrazione.tornei.tabellone.store', 999))->assertNotFound();
        $this->actingAs($this->amministratore)
            ->put(route('amministrazione.tornei.partite.risultato.update', [$torneo, 999]), ['set' => $this->set('6-0 6-0')])
            ->assertNotFound();
    }
}
