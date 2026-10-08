<?php

namespace Tests\Feature\Amministrazione;

use App\Enums\Fase;
use App\Models\Coppia;
use App\Models\Giocatore;
use App\Models\Partita;
use App\Models\Torneo;
use App\Models\User;
use App\Services\Punteggio\ValutatorePunteggio;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\MiglioriGiocatoriService;
use App\Services\Tornei\RisultatiService;
use App\Services\Tornei\TorneoSocialeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FormulaClassica;
use Tests\TestCase;

/**
 * Migliori giocatori per categoria nella pagina Classifica.
 *
 * Una categoria è un dato dell'anagrafica dichiarato in config (`classifica.migliori.categorie`). Per non
 * dipendere da come si chiamano fascia d'età e genere in anagrafica, qui le due categorie vengono puntate su
 * dati che i giocatori hanno di sicuro: la fascia di gioco (numeri, con etichette) e il circolo (testo libero,
 * senza etichette, a volte assente).
 */
class MiglioriGiocatoriTest extends TestCase
{
    use FormulaClassica;
    use RefreshDatabase;

    private User $amministratore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->amministratore = User::factory()->amministratore()->create();

        config([
            'tornei.classifica.migliori.categorie.fascia_eta.attributo' => 'circolo',
            'tornei.classifica.migliori.categorie.fascia_eta.valori' => 'tornei.giocatori.chiave_che_non_esiste',
            'tornei.classifica.migliori.categorie.genere.attributo' => 'fascia',
            'tornei.classifica.migliori.categorie.genere.valori' => 'tornei.giocatori.fasce',
        ]);
    }

    /**
     * Un torneo con un girone da quattro coppie e le sue partite. I circoli: metà «Nord», un quarto «Sud»,
     * gli altri senza.
     */
    private function torneo(int $perFascia = 4): Torneo
    {
        $torneo = Torneo::factory()->create(['squadre_per_girone' => 4, 'qualificate_per_girone' => 2]);
        $circoli = ['Nord', 'Nord', 'Sud', null];

        foreach ([1, 2] as $fascia) {
            foreach (range(0, $perFascia - 1) as $indice) {
                $torneo->giocatori()->attach(
                    Giocatore::factory()->fascia($fascia)->create(['lato' => null, 'circolo' => $circoli[$indice % 4]])
                );
            }
        }

        $torneo = $torneo->fresh();
        $sorteggio = app(TorneoSocialeService::class);
        $sorteggio->generaCoppie($torneo);
        $sorteggio->generaGironi($torneo);
        $sorteggio->generaPartite($torneo);

        return $torneo;
    }

    private function gioca(Partita $partita, bool $vinceA, string $vittoria = '6-3 6-3'): void
    {
        $partita = $partita->fresh();
        $righe = array_map(function (string $set) use ($vinceA): array {
            [$alti, $bassi] = array_map('intval', explode('-', $set));

            return $vinceA ? ['a' => $alti, 'b' => $bassi] : ['a' => $bassi, 'b' => $alti];
        }, explode(' ', $vittoria));

        app(RisultatiService::class)->registra(
            $partita,
            (new ValutatorePunteggio)->valuta($partita->torneo->formato($partita->fase), $righe),
        );
    }

    /** Nei gironi vince sempre la coppia con l'identificativo più basso. */
    private function giocaIGironi(Torneo $torneo): void
    {
        foreach ($torneo->partiteDiGirone()->get() as $partita) {
            $this->gioca($partita, $partita->coppia_a_id < $partita->coppia_b_id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function categoria(Torneo $torneo, string $chiave): array
    {
        return app(MiglioriGiocatoriService::class)->migliori($torneo, $chiave);
    }

    public function test_la_classifica_ha_i_due_pulsanti_con_le_loro_finestre(): void
    {
        $torneo = $this->torneo();

        $html = $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Migliori giocatori')
            ->assertSee('Migliori per fascia d\'età')
            ->assertSee('Migliori per genere')
            ->assertViewHas('migliori', fn (array $migliori) => array_column($migliori, 'chiave') === ['fascia_eta', 'genere'])
            ->getContent();

        foreach (['migliori-fascia-eta', 'migliori-genere'] as $id) {
            $this->assertStringContainsString('data-bs-target="#'.$id.'"', $html);
            $this->assertMatchesRegularExpression('/<div id="'.$id.'"[^>]*class="[^"]*modal/', $html);
        }

        // Nessuna partita giocata: le finestre lo dicono.
        $this->assertSame(2, substr_count($html, 'Nessun risultato ancora'));
    }

    public function test_senza_gironi_i_pulsanti_non_ci_sono(): void
    {
        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', Torneo::factory()->create()))
            ->assertOk()
            ->assertDontSee('Migliori giocatori');
    }

    public function test_senza_categorie_in_configurazione_la_pagina_resta_senza_pulsanti(): void
    {
        config(['tornei.classifica.migliori' => null]);

        $torneo = $this->torneo();
        $this->giocaIGironi($torneo);

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Girone A')
            ->assertDontSee('Migliori giocatori')
            ->assertViewHas('migliori', []);
    }

    public function test_se_l_anagrafica_non_ha_ancora_il_dato_la_finestra_lo_spiega(): void
    {
        config(['tornei.classifica.migliori.categorie.genere.attributo' => 'dato_che_non_esiste']);

        $torneo = $this->torneo();
        $this->giocaIGironi($torneo);

        $genere = $this->categoria($torneo, 'genere');

        $this->assertFalse($genere['disponibile']);
        $this->assertSame([], $genere['gruppi']);
        $this->assertSame(8, $genere['senza']);

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Migliori per genere')
            ->assertSee('Dato non ancora in anagrafica');
    }

    public function test_nei_gironi_i_migliori_sono_quelli_che_hanno_vinto_di_piu(): void
    {
        $torneo = $this->torneo();
        $this->giocaIGironi($torneo);

        $coppie = $torneo->coppie()->orderBy('id')->get();
        $genere = $this->categoria($torneo, 'genere');

        $this->assertTrue($genere['disponibile']);
        $this->assertSame(0, $genere['senza']);
        $this->assertSame(3, $genere['quanti']);
        // Un gruppo per valore, nell'ordine e con le etichette della configurazione.
        $this->assertSame([1, 2], array_column($genere['gruppi'], 'valore'));
        $this->assertSame(['Prima fascia', 'Seconda fascia'], array_column($genere['gruppi'], 'etichetta'));

        // Le coppie hanno vinto 3, 2, 1 e 0 partite: i primi tre di ogni gruppo sono i giocatori delle prime tre.
        foreach ([0 => 'prima_fascia_id', 1 => 'seconda_fascia_id'] as $gruppo => $colonna) {
            $righe = $genere['gruppi'][$gruppo]['righe'];

            $this->assertSame($coppie->take(3)->pluck($colonna)->all(), array_map(fn (array $voce) => $voce['giocatore']->id, $righe));
            $this->assertSame([1, 2, 3], array_map(fn (array $voce) => $voce['riga']->posizione, $righe));
            $this->assertSame([3, 2, 1], array_map(fn (array $voce) => $voce['riga']->vinte, $righe));
            $this->assertSame([3, 3, 3], array_map(fn (array $voce) => $voce['riga']->giocate, $righe));
            $this->assertSame([null, null, null], array_column($righe, 'turno'));
            $this->assertSame([6, 0, 36, 18], [$righe[0]['riga']->setVinti, $righe[0]['riga']->setPersi, $righe[0]['riga']->gameVinti, $righe[0]['riga']->gamePersi]);
        }

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Prima fascia')
            ->assertSee('3 vinte su 3')
            ->assertSee('Situazione provvisoria: il torneo non è concluso.')
            ->assertSee('I primi 3 di ogni gruppo')
            ->assertViewHas('concluso', false);
    }

    public function test_il_numero_di_posizioni_viene_dalla_configurazione(): void
    {
        config(['tornei.classifica.migliori.quanti' => 1]);

        $torneo = $this->torneo();
        $this->giocaIGironi($torneo);

        foreach ($this->categoria($torneo, 'genere')['gruppi'] as $gruppo) {
            $this->assertCount(1, $gruppo['righe']);
        }

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertSee('Il primo di ogni gruppo');
    }

    public function test_chi_non_ha_la_categoria_resta_fuori_e_viene_contato(): void
    {
        $torneo = $this->torneo();
        $this->giocaIGironi($torneo);

        $eta = $this->categoria($torneo, 'fascia_eta');

        // Circoli: 4 «Nord», 2 «Sud», 2 senza. Senza etichette in configurazione si mostra il valore, in ordine alfabetico.
        $this->assertTrue($eta['disponibile']);
        $this->assertSame(2, $eta['senza']);
        $this->assertSame(['Nord', 'Sud'], array_column($eta['gruppi'], 'valore'));
        $this->assertSame(['Nord', 'Sud'], array_column($eta['gruppi'], 'etichetta'));
        $this->assertCount(2, $eta['gruppi'][1]['righe']);

        foreach ($eta['gruppi'] as $gruppo) {
            foreach ($gruppo['righe'] as $voce) {
                $this->assertSame($gruppo['valore'], $voce['giocatore']->circolo);
            }
        }

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('2 giocatori iscritti non hanno il dato «fascia d\'età» in anagrafica e non compaiono.');
    }

    public function test_a_torneo_concluso_davanti_a_tutti_c_e_chi_e_arrivato_piu_avanti_nelle_finali(): void
    {
        // Due gironi da quattro, due qualificate per girone: semifinali e finale.
        $torneo = $this->torneo(8);
        $this->giocaIGironi($torneo);
        app(FinaliService::class)->generaTabellone($torneo);

        foreach ([Fase::Semifinali, Fase::Finale] as $fase) {
            foreach ($torneo->partiteDelTurno($fase)->get() as $partita) {
                // Nelle finali vince sempre la coppia B, con un punteggio risicato: chi vince il torneo ha numeri
                // peggiori di chi nei gironi ha stravinto, ma sta davanti lo stesso.
                $this->gioca($partita, false, '7-6 7-6');
            }
        }

        $finale = $torneo->partiteDelTurno(Fase::Finale)->sole();
        $vincitrice = Coppia::find($finale->coppia_b_id);
        $finalista = Coppia::find($finale->coppia_a_id);

        $gruppi = $this->categoria($torneo, 'genere')['gruppi'];

        foreach ([0 => 'prima_fascia_id', 1 => 'seconda_fascia_id'] as $gruppo => $colonna) {
            $righe = $gruppi[$gruppo]['righe'];

            $this->assertSame($vincitrice->{$colonna}, $righe[0]['giocatore']->id);
            $this->assertSame('Ha vinto il torneo', $righe[0]['turno']);
            $this->assertSame($finalista->{$colonna}, $righe[1]['giocatore']->id);
            $this->assertSame('Finalista', $righe[1]['turno']);
            $this->assertSame('Semifinali', $righe[2]['turno']);
            // Gironi (3 partite) + semifinale + finale.
            $this->assertSame(5, $righe[0]['riga']->giocate);
        }

        $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.classifica', $torneo))
            ->assertOk()
            ->assertSee('Ha vinto il torneo')
            ->assertDontSee('Situazione provvisoria: il torneo non è concluso.')
            ->assertViewHas('concluso', true);
    }

    public function test_un_valore_numerico_senza_etichetta_viene_mostrato_cosi_com_e(): void
    {
        config(['tornei.classifica.migliori.categorie.genere.valori' => 'tornei.giocatori.chiave_che_non_esiste']);

        $torneo = $this->torneo();
        $this->giocaIGironi($torneo);

        $this->assertSame(['1', '2'], array_column($this->categoria($torneo, 'genere')['gruppi'], 'etichetta'));
    }
}
