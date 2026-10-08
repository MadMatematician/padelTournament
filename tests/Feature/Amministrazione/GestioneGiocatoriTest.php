<?php

namespace Tests\Feature\Amministrazione;

use App\Models\Giocatore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Anagrafica dei giocatori: il form invia una riga per giocatore (`giocatori[0][nome]`, …).
 */
class GestioneGiocatoriTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Una riga del form.
     *
     * @return array<string, mixed>
     */
    private function riga(array $modifiche = []): array
    {
        return array_merge([
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'genere' => 'm',
            'anno_nascita' => 1985,
            'fascia' => 1,
            'lato' => 'destra',
            'circolo' => 'Padel Club Centro',
        ], $modifiche);
    }

    /** La riga vuota che il form tiene sempre in fondo, in attesa del prossimo giocatore. */
    private function rigaVuota(): array
    {
        return ['nome' => '', 'cognome' => '', 'genere' => '', 'anno_nascita' => '', 'fascia' => '', 'lato' => '', 'circolo' => ''];
    }

    private function amministratore(): User
    {
        return User::factory()->amministratore()->create();
    }

    public function test_la_pagina_di_amministrazione_elenca_i_giocatori(): void
    {
        $giocatore = Giocatore::factory()->fascia(2)->create([
            'nome' => 'Lucia', 'cognome' => 'Bianchi', 'genere' => 'f', 'anno_nascita' => 1991,
            'lato' => 'sinistra', 'circolo' => 'Circolo Tennis Lungomare',
        ]);

        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertSee('Bianchi')
            ->assertSee('Lucia')
            ->assertSee(config('tornei.giocatori.generi.f'))
            ->assertSee('1991')
            ->assertSee(config('tornei.giocatori.fasce.2'))
            ->assertSee(config('tornei.giocatori.lati.sinistra'))
            ->assertSee('Circolo Tennis Lungomare')
            ->assertSee('Nuovi giocatori')
            ->assertSee(route('amministrazione.giocatori.destroy', $giocatore));
    }

    public function test_il_form_parte_con_una_riga_e_il_modello_per_le_successive(): void
    {
        $html = $this->actingAs($this->amministratore())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->getContent();

        foreach (['nome', 'cognome', 'genere', 'anno_nascita', 'fascia', 'lato', 'circolo'] as $campo) {
            $this->assertStringContainsString('name="giocatori[0]['.$campo.']"', $html);
            // La riga modello, da cui il browser crea le successive.
            $this->assertStringContainsString('name="giocatori[__INDICE__]['.$campo.']"', $html);
        }

        $this->assertStringNotContainsString('name="giocatori[1][nome]"', $html);
        $this->assertMatchesRegularExpression('/data-ripeti-prossimo="1"/', $html);
        $this->assertSame(1, substr_count($html, '<template data-ripeti-modello>'));
    }

    public function test_l_amministratore_crea_un_giocatore(): void
    {
        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [$this->riga()]])
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHas('status', 'Giocatore Mario Rossi creato.');

        $giocatore = Giocatore::sole();

        $this->assertSame('Mario Rossi', $giocatore->nomeCompleto());
        $this->assertSame('m', $giocatore->genere);
        $this->assertSame(config('tornei.giocatori.generi.m'), $giocatore->etichettaGenere());
        $this->assertSame(1985, $giocatore->anno_nascita);
        $this->assertSame(1, $giocatore->fascia);
        $this->assertSame('destra', $giocatore->lato);
        $this->assertSame('Padel Club Centro', $giocatore->circolo);
    }

    public function test_l_amministratore_crea_piu_giocatori_con_un_solo_invio(): void
    {
        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [
                $this->riga(),
                $this->riga(['nome' => 'Lucia', 'cognome' => 'Bianchi', 'fascia' => 2, 'lato' => '']),
                $this->riga(['nome' => 'Paolo', 'cognome' => 'Verdi', 'circolo' => '']),
                $this->rigaVuota(),
            ]])
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHas('status', '3 giocatori creati.');

        $this->assertSame(
            ['Lucia Bianchi', 'Mario Rossi', 'Paolo Verdi'],
            Giocatore::query()->orderBy('cognome')->get()->map->nomeCompleto()->all(),
        );
        $this->assertSame(2, Giocatore::query()->where('cognome', 'Bianchi')->sole()->fascia);
    }

    public function test_le_righe_vuote_vengono_ignorate_ovunque_si_trovino(): void
    {
        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [
                3 => $this->rigaVuota(),
                7 => $this->riga(),
                9 => $this->rigaVuota(),
            ]])
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Giocatore::count());
    }

    public function test_lato_e_circolo_sono_facoltativi(): void
    {
        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [$this->riga(['lato' => '', 'circolo' => ''])]])
            ->assertRedirect(route('amministrazione.dashboard'));

        $giocatore = Giocatore::sole();

        $this->assertNull($giocatore->lato);
        $this->assertNull($giocatore->etichettaLato());
        $this->assertNull($giocatore->circolo);
    }

    public function test_l_anno_di_nascita_deve_rientrare_nell_eta_ammessa(): void
    {
        $this->travelTo('2026-10-06');
        config(['tornei.giocatori.eta' => ['minima' => 5, 'massima' => 100]]);

        $invia = fn (int $anno) => $this->actingAs($this->amministratore())
            ->from(route('amministrazione.dashboard'))
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [$this->riga(['anno_nascita' => $anno])]]);

        // Troppo lontano (più di 100 anni) e troppo vicino (meno di 5): respinti, con i limiti nel messaggio.
        $invia(1925)->assertSessionHasErrorsIn('giocatore', [
            'giocatori.0.anno_nascita' => 'Il campo anno di nascita deve essere compreso tra 1926 e 2021.',
        ]);
        $invia(2022)->assertSessionHasErrorsIn('giocatore', ['giocatori.0.anno_nascita']);
        $this->assertSame(0, Giocatore::count());

        // Gli estremi sono ammessi.
        $invia(1926)->assertSessionHasNoErrors();
        $invia(2021)->assertSessionHasNoErrors();
        $this->assertSame([1926, 2021], Giocatore::query()->orderBy('anno_nascita')->pluck('anno_nascita')->all());
    }

    public function test_le_schede_senza_genere_e_anno_di_nascita_restano_in_elenco(): void
    {
        // Le schede create prima che il form li richiedesse non li hanno.
        $vecchia = Giocatore::factory()->create(['cognome' => 'Storico', 'genere' => null, 'anno_nascita' => null]);

        $this->assertNull($vecchia->etichettaGenere());

        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertSee('Storico')
            ->assertSeeInOrder(['Genere', 'Anno di nascita', 'Fascia']);
    }

    public function test_due_giocatori_possono_avere_lo_stesso_nome(): void
    {
        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [$this->riga(), $this->riga(['fascia' => 2])]]);

        $this->assertSame(2, Giocatore::count());
    }

    public function test_lo_stesso_circolo_scritto_in_modo_diverso_prende_la_grafia_esistente(): void
    {
        Giocatore::factory()->circolo('Padel Club Centro')->create();

        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [
                $this->riga(['cognome' => 'Uno', 'circolo' => 'padel  club CENTRO']),
                // Un circolo nuovo: vale la grafia della prima riga che lo nomina.
                $this->riga(['cognome' => 'Due', 'circolo' => 'Sporting Nord']),
                $this->riga(['cognome' => 'Tre', 'circolo' => 'sporting nord']),
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame('Padel Club Centro', Giocatore::query()->where('cognome', 'Uno')->sole()->circolo);
        $this->assertSame('Sporting Nord', Giocatore::query()->where('cognome', 'Tre')->sole()->circolo);
        $this->assertSame(['Padel Club Centro', 'Sporting Nord'], Giocatore::circoli()->all());
    }

    public function test_i_circoli_gia_usati_vengono_suggeriti_e_filtrano_la_tabella(): void
    {
        Giocatore::factory()->circolo('Sporting Nord')->create();
        Giocatore::factory()->circolo('Padel Club Centro')->count(2)->create();
        Giocatore::factory()->circolo(null)->create();

        $html = $this->actingAs($this->amministratore())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertViewHas('circoli', fn ($circoli) => $circoli->all() === ['Padel Club Centro', 'Sporting Nord'])
            ->getContent();

        // Suggerimenti del campo "Circolo"…
        $this->assertMatchesRegularExpression('/<datalist id="giocatore-circoli">\s*<option value="Padel Club Centro"><\/option>\s*<option value="Sporting Nord"><\/option>\s*<\/datalist>/', $html);
        $this->assertStringContainsString('list="giocatore-circoli"', $html);
        // …e filtro sulla colonna "Circolo" (la settima) dell'elenco.
        $this->assertMatchesRegularExpression('/<select data-filtro-colonna="6"[^>]*>\s*<option value="">Tutti i circoli<\/option>\s*<option value="Padel Club Centro">Padel Club Centro<\/option>\s*<option value="Sporting Nord">Sporting Nord<\/option>\s*<\/select>/', $html);
    }

    public function test_senza_circoli_il_filtro_non_compare(): void
    {
        Giocatore::factory()->circolo(null)->create();

        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertDontSee('data-filtro-colonna', false);
    }

    /**
     * @param  array<string, mixed>  $modifiche
     */
    #[DataProvider('datiNonValidi')]
    public function test_i_dati_non_validi_vengono_rifiutati(array $modifiche, string $campo): void
    {
        $this->actingAs($this->amministratore())
            ->from(route('amministrazione.dashboard'))
            // La riga errata è la seconda: nessun giocatore viene salvato, nemmeno il primo.
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [$this->riga(), $this->riga($modifiche)]])
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHasErrorsIn('giocatore', ['giocatori.1.'.$campo]);

        $this->assertSame(0, Giocatore::count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function datiNonValidi(): array
    {
        return [
            'senza nome' => [['nome' => ''], 'nome'],
            'senza cognome' => [['cognome' => ''], 'cognome'],
            'cognome troppo lungo' => [['cognome' => str_repeat('a', 61)], 'cognome'],
            'senza genere' => [['genere' => ''], 'genere'],
            'genere sconosciuto' => [['genere' => 'x'], 'genere'],
            'senza anno di nascita' => [['anno_nascita' => ''], 'anno_nascita'],
            'anno di nascita non numerico' => [['anno_nascita' => 'ottantacinque'], 'anno_nascita'],
            'anno di nascita a due cifre' => [['anno_nascita' => 85], 'anno_nascita'],
            'anno di nascita con i decimali' => [['anno_nascita' => '1985.5'], 'anno_nascita'],
            'senza fascia' => [['fascia' => ''], 'fascia'],
            'fascia sconosciuta' => [['fascia' => 9], 'fascia'],
            'lato sconosciuto' => [['lato' => 'centro'], 'lato'],
            'circolo troppo lungo' => [['circolo' => str_repeat('a', 81)], 'circolo'],
        ];
    }

    /**
     * @param  array<string, mixed>  $dati
     */
    #[DataProvider('inviiSenzaGiocatori')]
    public function test_un_invio_senza_giocatori_viene_rifiutato(array $dati): void
    {
        $this->actingAs($this->amministratore())
            ->from(route('amministrazione.dashboard'))
            ->post(route('amministrazione.giocatori.store'), $dati)
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHasErrorsIn('giocatore', ['giocatori']);

        $this->assertSame(0, Giocatore::count());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function inviiSenzaGiocatori(): array
    {
        $vuota = ['nome' => '', 'cognome' => '', 'genere' => '', 'anno_nascita' => '', 'fascia' => '', 'lato' => '', 'circolo' => ''];

        return [
            'nessun campo' => [[]],
            'solo righe vuote' => [['giocatori' => [$vuota, $vuota]]],
            'non un elenco' => [['giocatori' => 'Mario Rossi']],
            'il vecchio form a giocatore singolo' => [['nome' => 'Mario', 'cognome' => 'Rossi', 'fascia' => 1]],
        ];
    }

    public function test_non_si_inseriscono_piu_giocatori_del_massimo_consentito(): void
    {
        config(['tornei.giocatori.massimo_per_inserimento' => 2]);

        $this->actingAs($this->amministratore())
            ->from(route('amministrazione.dashboard'))
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [$this->riga(), $this->riga(), $this->riga()]])
            ->assertSessionHasErrorsIn('giocatore', ['giocatori']);

        $this->assertSame(0, Giocatore::count());
    }

    public function test_dopo_un_errore_si_riapre_la_finestra_con_tutte_le_righe_inviate(): void
    {
        $risposta = $this->actingAs($this->amministratore())
            ->followingRedirects()
            ->from(route('amministrazione.dashboard'))
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [
                $this->rigaVuota(),
                $this->riga(),
                $this->riga(['nome' => 'Lucia', 'cognome' => '', 'fascia' => 2, 'circolo' => 'Sporting Nord']),
                $this->rigaVuota(),
            ]]);

        $risposta->assertOk()->assertSee('Il campo cognome è obbligatorio.');

        $html = $risposta->getContent();

        $this->assertMatchesRegularExpression('/id="modale-giocatore"[^>]*data-apri-modale/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="modale-torneo"[^>]*data-apri-modale/', $html);

        // Tornano le sole righe compilate, con i loro valori e l'indice con cui erano state inviate;
        // l'errore sta sulla riga di Lucia e non su quella di Mario.
        $this->assertMatchesRegularExpression('/id="giocatore-1-nome"\s+value="Mario"/', $html);
        $this->assertMatchesRegularExpression('/id="giocatore-1-circolo"\s+value="Padel Club Centro"/', $html);
        $this->assertMatchesRegularExpression('/id="giocatore-2-nome"\s+value="Lucia"/', $html);
        $this->assertMatchesRegularExpression('/id="giocatore-2-circolo"\s+value="Sporting Nord"/', $html);
        $this->assertMatchesRegularExpression('/id="giocatore-1-anno_nascita"\s+value="1985"/', $html);
        $this->assertMatchesRegularExpression('/<select name="giocatori\[1\]\[genere\]"[^>]*>\s*<option value="">[^<]*<\/option>\s*<option value="m" selected>/', $html);
        $this->assertMatchesRegularExpression('/<select name="giocatori\[2\]\[fascia\]"[^>]*>\s*<option value="">[^<]*<\/option>\s*<option value="1" >[^<]*<\/option>\s*<option value="2" selected>/', $html);
        $this->assertMatchesRegularExpression('/id="giocatore-2-cognome"[^>]*is-invalid/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="giocatore-1-cognome"[^>]*is-invalid/', $html);
        $this->assertSame(1, substr_count($html, 'Il campo cognome è obbligatorio.'));
        // Le righe vuote (la prima e l'ultima) non tornano.
        $this->assertStringNotContainsString('id="giocatore-0-nome"', $html);
        $this->assertStringNotContainsString('id="giocatore-3-nome"', $html);
        // La prossima riga creata dal browser non riusa un indice già presente.
        $this->assertMatchesRegularExpression('/data-ripeti-prossimo="3"/', $html);
        // Il campo omonimo dell'altro form non eredita il valore inviato.
        $this->assertMatchesRegularExpression('/id="torneo-nome"\s+value=""/', $html);
        $this->assertSame(0, Giocatore::count());
    }

    public function test_l_amministratore_elimina_un_giocatore(): void
    {
        $giocatore = Giocatore::factory()->create();
        $altro = Giocatore::factory()->create();

        $this->actingAs($this->amministratore())
            ->delete(route('amministrazione.giocatori.destroy', $giocatore))
            ->assertRedirect(route('amministrazione.dashboard'))
            ->assertSessionHas('status');

        $this->assertModelMissing($giocatore);
        $this->assertModelExists($altro);
    }

    public function test_un_utente_non_puo_gestire_i_giocatori(): void
    {
        $giocatore = Giocatore::factory()->create();
        $utente = User::factory()->create();

        $this->actingAs($utente)
            ->post(route('amministrazione.giocatori.store'), ['giocatori' => [$this->riga()]])
            ->assertForbidden();

        $this->actingAs($utente)
            ->delete(route('amministrazione.giocatori.destroy', $giocatore))
            ->assertForbidden();

        $this->assertSame(1, Giocatore::count());
    }

    public function test_gli_ospiti_vengono_mandati_al_login(): void
    {
        $giocatore = Giocatore::factory()->create();

        $this->post(route('amministrazione.giocatori.store'), ['giocatori' => [$this->riga()]])->assertRedirect(route('login'));
        $this->delete(route('amministrazione.giocatori.destroy', $giocatore))->assertRedirect(route('login'));

        $this->assertSame(1, Giocatore::count());
    }
}
