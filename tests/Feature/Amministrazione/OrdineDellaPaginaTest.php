<?php

namespace Tests\Feature\Amministrazione;

use App\Enums\Fase;
use App\Models\Giocatore;
use App\Models\Partita;
use App\Models\Torneo;
use App\Models\User;
use App\Services\Punteggio\ValutatorePunteggio;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\RisultatiService;
use App\Services\Tornei\StatoFinali;
use App\Services\Tornei\TorneoSocialeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FormulaClassica;
use Tests\TestCase;

/**
 * Pagina di gestione di un torneo: la parte su cui si sta lavorando sta in alto, subito sotto i passi del
 * sorteggio, ed è messa in evidenza; le altre seguono dalla più recente alla più vecchia. La tabella delle
 * coppie è ordinabile e parte ordinata per girone.
 */
class OrdineDellaPaginaTest extends TestCase
{
    use FormulaClassica;
    use RefreshDatabase;

    private User $amministratore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->amministratore = User::factory()->amministratore()->create();
    }

    /** Un torneo con 8 + 8 iscritti, portato fino al passo indicato: `iscritti`, `coppie`, `gironi`, `partite`, `finali`. */
    private function torneoFinoA(string $passo): Torneo
    {
        $torneo = Torneo::factory()->create();
        $torneo->giocatori()->attach(Giocatore::factory()->fascia(1)->count(8)->create(['lato' => null]));
        $torneo->giocatori()->attach(Giocatore::factory()->fascia(2)->count(8)->create(['lato' => null]));
        $torneo = $torneo->fresh();

        $sorteggio = app(TorneoSocialeService::class);
        $passi = [
            'coppie' => fn () => $sorteggio->generaCoppie($torneo),
            'gironi' => fn () => $sorteggio->generaGironi($torneo),
            'partite' => fn () => $sorteggio->generaPartite($torneo),
            'finali' => function () use ($torneo): void {
                $torneo->partiteDiGirone()->get()->each(fn (Partita $partita) => $this->gioca($partita, '6-3 6-3'));
                app(FinaliService::class)->generaTabellone($torneo);
            },
        ];

        foreach ($passi as $nome => $esegui) {
            if ($passo === 'iscritti') {
                break;
            }
            $esegui();
            if ($nome === $passo) {
                break;
            }
        }

        return $torneo->fresh();
    }

    private function gioca(Partita $partita, string $punteggio): void
    {
        $partita = $partita->fresh();
        $set = array_map(function (string $set): array {
            [$a, $b] = explode('-', $set);

            return ['a' => (int) $a, 'b' => (int) $b, 'tb_a' => '', 'tb_b' => ''];
        }, explode(' ', $punteggio));

        app(RisultatiService::class)->registra($partita, (new ValutatorePunteggio)->valuta($partita->torneo->formato($partita->fase), $set));
    }

    private function pagina(Torneo $torneo): string
    {
        return $this->actingAs($this->amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->getContent();
    }

    /**
     * Le sezioni presenti nella pagina, nell'ordine in cui compaiono, e quelle messe in evidenza.
     *
     * @return array{list<string>, list<string>}
     */
    private function sezioni(string $html): array
    {
        preg_match_all('/<div[^>]*\bid="(sorteggio|finali|gironi|girone-\d+|coppie|formula|giocatori)"[^>]*>/', $html, $trovate, PREG_SET_ORDER);

        $ordine = [];
        $evidenza = [];
        foreach ($trovate as [$tag, $id]) {
            // Le schede dei gironi contano come la sezione «gironi», che le contiene.
            $sezione = str_starts_with($id, 'girone-') ? 'gironi' : $id;
            $ordine[] = $sezione;
            if (str_contains($tag, 'scheda-attiva')) {
                $evidenza[] = $sezione;
            }
        }

        return [array_values(array_unique($ordine)), array_values(array_unique($evidenza))];
    }

    public function test_senza_sorteggio_si_lavora_sugli_iscritti(): void
    {
        [$ordine, $evidenza] = $this->sezioni($this->pagina($this->torneoFinoA('iscritti')));

        $this->assertSame(['sorteggio', 'giocatori', 'formula'], $ordine);
        $this->assertSame(['giocatori'], $evidenza);
    }

    public function test_generate_le_coppie_la_loro_tabella_e_la_prima(): void
    {
        $html = $this->pagina($this->torneoFinoA('coppie'));
        [$ordine, $evidenza] = $this->sezioni($html);

        // Iscritti e formula passano in fondo: le iscrizioni sono chiuse.
        $this->assertSame(['sorteggio', 'coppie', 'formula', 'giocatori'], $ordine);
        $this->assertSame(['coppie'], $evidenza);
        $this->assertStringContainsString('Iscrizioni chiuse', $html);
    }

    public function test_generati_i_gironi_vengono_prima_delle_coppie(): void
    {
        $html = $this->pagina($this->torneoFinoA('gironi'));
        [$ordine, $evidenza] = $this->sezioni($html);

        $this->assertSame(['sorteggio', 'gironi', 'coppie', 'formula', 'giocatori'], $ordine);
        $this->assertSame(['gironi'], $evidenza);

        // Senza partite, nella scheda del girone vengono prima le sue coppie.
        $this->assertLessThan(strpos($html, 'Partite non ancora generate.'), strpos($html, '<th scope="col">Coppie</th>'));
    }

    public function test_generate_le_partite_il_calendario_viene_prima_delle_coppie_del_girone(): void
    {
        $html = $this->pagina($this->torneoFinoA('partite'));
        [$ordine, $evidenza] = $this->sezioni($html);

        $this->assertSame(['sorteggio', 'gironi', 'coppie', 'formula', 'giocatori'], $ordine);
        $this->assertSame(['gironi'], $evidenza);

        // In ogni scheda: calendario, poi coppie del girone.
        $this->assertSame(2, preg_match_all('/<th scope="col">Giornata<\/th>.*?<th scope="col">Coppie<\/th>/s', $html));
        $this->assertLessThan(strpos($html, '<th scope="col">Coppie</th>'), strpos($html, '<th scope="col">Giornata</th>'));
        $this->assertStringNotContainsString('Partite non ancora generate.', $html);
    }

    public function test_generate_le_finali_la_loro_scheda_e_la_prima(): void
    {
        $torneo = $this->torneoFinoA('finali');
        $html = $this->pagina($torneo);
        [$ordine, $evidenza] = $this->sezioni($html);

        $this->assertSame(['sorteggio', 'finali', 'gironi', 'coppie', 'formula', 'giocatori'], $ordine);
        $this->assertSame(['finali'], $evidenza);

        // La scheda dice a che punto sono le finali e porta al tabellone.
        $this->assertStringContainsString('Semifinali: mancano 2 risultati.', $html);
        $this->assertStringContainsString('Apri il tabellone', $html);
        $this->assertStringContainsString('href="'.route('amministrazione.tornei.tabellone', $torneo).'"', $html);
    }

    public function test_la_scheda_delle_finali_segue_il_turno_in_corso_fino_alla_vincitrice(): void
    {
        $torneo = $this->torneoFinoA('finali');
        $finali = app(FinaliService::class);

        $stato = $finali->stato($torneo);
        $this->assertSame([Fase::Semifinali, 2, null], [$stato->inCorso, $stato->daGiocare, $stato->vincitrice]);

        [$prima, $seconda] = $torneo->partiteDelTurno(Fase::Semifinali)->orderBy('posizione')->get()->all();

        $this->gioca($prima, '6-4 6-4');
        $this->assertStringContainsString('Semifinali: manca un risultato.', $this->pagina($torneo));

        $this->gioca($seconda, '6-4 6-4');
        $stato = $finali->stato($torneo);
        $this->assertSame([Fase::Finale, 1], [$stato->inCorso, $stato->daGiocare]);
        $this->assertStringContainsString('Finale: manca un risultato.', $this->pagina($torneo));

        $finale = $torneo->partiteDelTurno(Fase::Finale)->firstOrFail();
        $this->gioca($finale, '6-4 6-4');

        $stato = $finali->stato($torneo);
        $vincitrice = $finale->fresh()->coppiaA;

        $this->assertNull($stato->inCorso);
        $this->assertSame(0, $stato->daGiocare);
        $this->assertTrue($stato->vincitrice->is($vincitrice));

        $html = $this->pagina($torneo);
        $this->assertStringContainsString('Torneo concluso: vince la coppia', $html);
        $this->assertStringContainsString(e($vincitrice->primaFascia->nomeCompleto().' / '.$vincitrice->secondaFascia->nomeCompleto()), $html);
    }

    public function test_senza_partite_delle_finali_non_c_e_un_turno_in_corso(): void
    {
        $stato = StatoFinali::daPartite(collect());

        $this->assertSame([null, 0, null], [$stato->inCorso, $stato->daGiocare, $stato->vincitrice]);
    }

    public function test_la_tabella_delle_coppie_e_ordinabile_e_senza_gironi_parte_dal_numero_di_coppia(): void
    {
        $html = $this->pagina($this->torneoFinoA('coppie'));

        // DataTables, ordinata sulla prima colonna (N.); nessuna coppia ha ancora un girone.
        $this->assertMatchesRegularExpression('/id="coppie".*?<table data-datatable[^>]*data-ordine="\[\[0,&quot;asc&quot;\]\]"[^>]*data-righe="50"[^>]*data-singolare="coppia" data-plurale="coppie"/s', $html);
        $this->assertSame(8, substr_count($html, 'data-order="'.PHP_INT_MAX.'"'));
    }

    public function test_con_i_gironi_la_tabella_delle_coppie_parte_ordinata_per_girone(): void
    {
        $torneo = $this->torneoFinoA('gironi');
        $html = $this->pagina($torneo);

        // Prima per girone (quarta colonna), poi per numero di coppia.
        $this->assertMatchesRegularExpression('/id="coppie".*?<table data-datatable[^>]*data-ordine="\[\[3,&quot;asc&quot;\],\[0,&quot;asc&quot;\]\]"/s', $html);
        $this->assertStringContainsString('In ordine di girone.', $html);

        // Ogni cella «Girone» si ordina sulla posizione del girone (A = 0, B = 1), non sul nome.
        foreach ($torneo->gironi()->withCount('coppie')->get() as $girone) {
            $this->assertSame(
                $girone->coppie_count,
                preg_match_all('/<td[^>]*data-order="'.$girone->posizione.'"[^>]*>'.preg_quote($girone->nome, '/').'<\/td>/', $html),
                $girone->nome,
            );
        }
    }
}
