<?php

namespace Tests\Feature\Amministrazione;

use App\Models\Giocatore;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pagina di gestione di un torneo: registrazione di giocatori nuovi, iscrizione dall'anagrafica, rimozione.
 */
class GestioneTorneoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Una riga del form «Registra giocatori».
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

    /**
     * Il form inviato con una sola riga compilata.
     *
     * @return array<string, mixed>
     */
    private function datiValidi(array $modifiche = []): array
    {
        return ['giocatori' => [$this->riga($modifiche)]];
    }

    private function amministratore(): User
    {
        return User::factory()->amministratore()->create();
    }

    public function test_l_elenco_dei_tornei_porta_alla_pagina_di_gestione(): void
    {
        $torneo = Torneo::factory()->create(['nome' => 'Coppa del circolo']);
        $torneo->giocatori()->attach(Giocatore::factory()->count(3)->create());

        $risposta = $this->actingAs($this->amministratore())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertSee('Gestisci')
            ->assertSee('Iscritti');

        // Sia il nome sia il pulsante «Gestisci» portano alla pagina del torneo.
        $this->assertSame(2, substr_count(
            $risposta->getContent(),
            'href="'.route('amministrazione.tornei.show', $torneo).'"',
        ));
    }

    public function test_la_pagina_di_gestione_mostra_il_torneo_e_i_suoi_iscritti(): void
    {
        $torneo = Torneo::factory()->create(['nome' => 'Coppa del circolo', 'data_inizio' => '2027-05-02', 'numero_turni' => 4]);
        $altroTorneo = Torneo::factory()->create();

        $iscritto = Giocatore::factory()->fascia(2)->create([
            'nome' => 'Lucia', 'cognome' => 'Bianchi', 'lato' => 'sinistra', 'circolo' => 'Circolo Tennis Lungomare',
        ]);
        $altrove = Giocatore::factory()->create(['nome' => 'Piero', 'cognome' => 'Altrove']);
        $soloAnagrafica = Giocatore::factory()->create(['nome' => 'Sara', 'cognome' => 'Nonpartecipa']);

        $torneo->giocatori()->attach($iscritto);
        $altroTorneo->giocatori()->attach($altrove);

        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Coppa del circolo')
            ->assertSee('inizio 02/05/2027')
            ->assertSee('4 turni')
            ->assertSee('Registra giocatori')
            ->assertSee('Bianchi')
            ->assertSee(config('tornei.giocatori.lati.sinistra'))
            ->assertSee('Circolo Tennis Lungomare')
            ->assertSee('data-filtro-colonna="6"', false)
            ->assertSee(route('amministrazione.tornei.giocatori.store', $torneo))
            ->assertSee(route('amministrazione.tornei.giocatori.destroy', [$torneo, $iscritto]))
            ->assertSee(route('amministrazione.dashboard'))
            // Chi non è iscritto non è nell'elenco: compare solo tra i giocatori da iscrivere.
            ->assertDontSee(route('amministrazione.tornei.giocatori.destroy', [$torneo, $altrove]))
            ->assertDontSee(route('amministrazione.tornei.giocatori.destroy', [$torneo, $soloAnagrafica]))
            ->assertViewHas('giocatori', fn ($giocatori) => $giocatori->pluck('id')->all() === [$iscritto->id])
            ->assertViewHas('iscrittiPerFascia', fn ($conteggi) => $conteggi->all() === [2 => 1]);
    }

    public function test_un_torneo_senza_iscritti_mostra_l_elenco_vuoto(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Nessun giocatore iscritto');
    }

    public function test_un_torneo_inesistente_da_404(): void
    {
        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.tornei.show', 999))
            ->assertNotFound();
    }

    public function test_l_amministratore_registra_un_giocatore_nel_torneo(): void
    {
        $torneo = Torneo::factory()->create();
        $altroTorneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), $this->datiValidi(['fascia' => 2]))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHas('status', 'Mario Rossi registrato nel torneo "'.$torneo->nome.'".');

        // Salvato in anagrafica (tabella `giocatori`) con la sua fascia e il suo circolo…
        $giocatore = Giocatore::sole();

        $this->assertSame('Mario Rossi', $giocatore->nomeCompleto());
        $this->assertSame(2, $giocatore->fascia);
        $this->assertSame('destra', $giocatore->lato);
        $this->assertSame('Padel Club Centro', $giocatore->circolo);
        $this->assertSame('m', $giocatore->genere);
        $this->assertSame(1985, $giocatore->anno_nascita);

        // …e iscritto a questo torneo soltanto.
        $this->assertTrue($torneo->giocatori()->whereKey($giocatore)->exists());
        $this->assertSame(0, $altroTorneo->giocatori()->count());
        $this->assertDatabaseCount('iscrizioni', 1);
    }

    public function test_l_amministratore_registra_piu_giocatori_con_un_solo_invio(): void
    {
        $torneo = Torneo::factory()->create();
        $altroTorneo = Torneo::factory()->create();
        $vuota = ['nome' => '', 'cognome' => '', 'genere' => '', 'anno_nascita' => '', 'fascia' => '', 'lato' => '', 'circolo' => ''];

        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), ['giocatori' => [
                $this->riga(),
                $this->riga(['nome' => 'Lucia', 'cognome' => 'Bianchi', 'fascia' => 2, 'circolo' => 'Sporting Nord']),
                $this->riga(['nome' => 'Paolo', 'cognome' => 'Verdi', 'lato' => '', 'circolo' => '']),
                // La riga vuota che il form tiene sempre in fondo.
                $vuota,
            ]])
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHas('status', '3 giocatori registrati nel torneo "'.$torneo->nome.'".');

        // Tutti in anagrafica e tutti iscritti a questo torneo soltanto.
        $this->assertSame(3, Giocatore::count());
        $this->assertSame(
            ['Bianchi', 'Rossi', 'Verdi'],
            $torneo->giocatori()->orderBy('cognome')->pluck('cognome')->all(),
        );
        $this->assertSame('Sporting Nord', Giocatore::query()->where('cognome', 'Bianchi')->sole()->circolo);
        $this->assertSame(0, $altroTorneo->giocatori()->count());
    }

    public function test_se_una_riga_e_errata_non_viene_registrato_nessuno(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore())
            ->from(route('amministrazione.tornei.show', $torneo))
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), ['giocatori' => [
                $this->riga(),
                $this->riga(['cognome' => 'Bianchi', 'fascia' => '']),
            ]])
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHasErrorsIn('giocatore', ['giocatori.1.fascia']);

        $this->assertSame(0, Giocatore::count());
        $this->assertDatabaseCount('iscrizioni', 0);
    }

    public function test_lato_e_circolo_sono_facoltativi(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), $this->datiValidi(['lato' => '', 'circolo' => '']))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo));

        $giocatore = $torneo->giocatori()->sole();

        $this->assertNull($giocatore->lato);
        $this->assertNull($giocatore->circolo);
    }

    public function test_i_giocatori_registrati_sono_contati_per_fascia(): void
    {
        $torneo = Torneo::factory()->create();
        $amministratore = $this->amministratore();

        $this->actingAs($amministratore)->post(route('amministrazione.tornei.giocatori.store', $torneo), ['giocatori' => [
            $this->riga(['nome' => 'Primo']),
            $this->riga(['nome' => 'Secondo']),
            $this->riga(['nome' => 'Terzo', 'fascia' => 2]),
        ]]);

        $this->actingAs($amministratore)
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertViewHas('giocatori', fn ($giocatori) => $giocatori->count() === 3)
            ->assertViewHas('iscrittiPerFascia', fn ($conteggi) => $conteggi->all() === [1 => 2, 2 => 1]);
    }

    /**
     * @param  array<string, mixed>  $modifiche
     */
    #[DataProvider('datiNonValidi')]
    public function test_i_dati_non_validi_vengono_rifiutati(array $modifiche, string $campo): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs($this->amministratore())
            ->from(route('amministrazione.tornei.show', $torneo))
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), $this->datiValidi($modifiche))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHasErrorsIn('giocatore', ['giocatori.0.'.$campo]);

        $this->assertSame(0, Giocatore::count());
        $this->assertDatabaseCount('iscrizioni', 0);
    }

    public function test_un_invio_senza_giocatori_viene_rifiutato(): void
    {
        $torneo = Torneo::factory()->create();
        $vuota = ['nome' => '', 'cognome' => '', 'genere' => '', 'anno_nascita' => '', 'fascia' => '', 'lato' => '', 'circolo' => ''];

        $risposta = $this->actingAs($this->amministratore())
            ->followingRedirects()
            ->from(route('amministrazione.tornei.show', $torneo))
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), ['giocatori' => [$vuota]]);

        $risposta->assertOk()->assertSee('Inserisci almeno un giocatore.');

        $this->assertMatchesRegularExpression('/id="modale-giocatore"[^>]*data-apri-modale/', $risposta->getContent());
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
            'senza genere' => [['genere' => ''], 'genere'],
            'senza anno di nascita' => [['anno_nascita' => ''], 'anno_nascita'],
            'anno di nascita impossibile' => [['anno_nascita' => 1850], 'anno_nascita'],
            'senza fascia' => [['fascia' => ''], 'fascia'],
            'fascia sconosciuta' => [['fascia' => 3], 'fascia'],
            'lato sconosciuto' => [['lato' => 'centro'], 'lato'],
            'circolo troppo lungo' => [['circolo' => str_repeat('a', 81)], 'circolo'],
        ];
    }

    public function test_dopo_un_errore_la_finestra_si_riapre_nella_pagina_del_torneo(): void
    {
        $torneo = Torneo::factory()->create();

        $risposta = $this->actingAs($this->amministratore())
            ->followingRedirects()
            ->from(route('amministrazione.tornei.show', $torneo))
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), $this->datiValidi(['fascia' => '']));

        $risposta->assertOk()->assertSee('Il campo fascia è obbligatorio.');

        $html = $risposta->getContent();

        $this->assertMatchesRegularExpression('/id="modale-giocatore"[^>]*data-apri-modale/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="modale-iscrizione"[^>]*data-apri-modale/', $html);
        $this->assertMatchesRegularExpression('/id="giocatore-0-nome"\s+value="Mario"/', $html);
        $this->assertMatchesRegularExpression('/id="giocatore-0-circolo"\s+value="Padel Club Centro"/', $html);
        $this->assertStringContainsString('action="'.route('amministrazione.tornei.giocatori.store', $torneo).'"', $html);
    }

    public function test_la_pagina_propone_i_giocatori_dell_anagrafica_non_ancora_iscritti(): void
    {
        $torneo = Torneo::factory()->create();
        $altroTorneo = Torneo::factory()->create();

        $iscritto = Giocatore::factory()->create(['nome' => 'Lucia', 'cognome' => 'Bianchi']);
        $altrove = Giocatore::factory()->fascia(1)->create(['nome' => 'Piero', 'cognome' => 'Zanetti', 'circolo' => 'Sporting Nord']);
        $soloAnagrafica = Giocatore::factory()->fascia(2)->create(['nome' => 'Sara', 'cognome' => 'Costa', 'circolo' => null]);

        $torneo->giocatori()->attach($iscritto);
        $altroTorneo->giocatori()->attach($altrove);

        $risposta = $this->actingAs($this->amministratore())
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee("Dall'anagrafica")
            ->assertSee(route('amministrazione.tornei.iscrizioni.store', $torneo))
            ->assertSee('Costa Sara')
            ->assertSee('Zanetti Piero')
            // In ordine di cognome, senza chi è già iscritto a questo torneo.
            ->assertViewHas('disponibili', fn ($disponibili) => $disponibili->pluck('id')->all() === [$soloAnagrafica->id, $altrove->id]);

        $html = $risposta->getContent();

        $this->assertStringContainsString('name="giocatori[]" id="iscrizione-giocatore-'.$soloAnagrafica->id.'" value="'.$soloAnagrafica->id.'"', $html);
        $this->assertStringNotContainsString('id="iscrizione-giocatore-'.$iscritto->id.'"', $html);

        // L'elenco si filtra per circolo: ogni voce porta il suo, il filtro elenca quelli presenti.
        $this->assertMatchesRegularExpression('/<select data-filtro-elenco="#iscrizione-giocatori"[^>]*>\s*<option value="">Tutti i circoli<\/option>\s*<option value="Sporting Nord">Sporting Nord<\/option>\s*<\/select>/', $html);
        $this->assertMatchesRegularExpression('/<label for="iscrizione-giocatore-'.$altrove->id.'"[^>]*data-gruppo="Sporting Nord"/', $html);
        $this->assertMatchesRegularExpression('/<label for="iscrizione-giocatore-'.$soloAnagrafica->id.'"[^>]*data-gruppo=""/', $html);
    }

    public function test_senza_giocatori_da_iscrivere_la_finestra_lo_dice(): void
    {
        $torneo = Torneo::factory()->create();
        $torneo->giocatori()->attach(Giocatore::factory()->create());

        $this->actingAs($this->amministratore())
            ->get(route('amministrazione.tornei.show', $torneo))
            ->assertOk()
            ->assertSee('Nessun giocatore da iscrivere')
            ->assertDontSee('form-iscrizione');
    }

    public function test_l_amministratore_iscrive_giocatori_gia_in_anagrafica(): void
    {
        $torneo = Torneo::factory()->create();
        $altroTorneo = Torneo::factory()->create();
        [$primo, $secondo, $terzo] = Giocatore::factory()->count(3)->create()->all();

        // Chi partecipa a un altro torneo si può iscrivere anche a questo.
        $altroTorneo->giocatori()->attach($primo);

        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), ['giocatori' => [$primo->id, $secondo->id]])
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHas('status', '2 giocatori iscritti al torneo "'.$torneo->nome.'".');

        $this->assertEqualsCanonicalizing([$primo->id, $secondo->id], $torneo->giocatori()->pluck('giocatori.id')->all());
        $this->assertSame(1, $altroTorneo->giocatori()->count());
        // Nessuna scheda nuova: si collegano quelle esistenti.
        $this->assertSame(3, Giocatore::count());
        $this->assertFalse($torneo->giocatori()->whereKey($terzo)->exists());
    }

    public function test_iscriverne_uno_solo_lo_chiama_per_nome(): void
    {
        $torneo = Torneo::factory()->create(['nome' => 'Coppa del circolo']);
        $giocatore = Giocatore::factory()->create(['nome' => 'Lucia', 'cognome' => 'Bianchi']);

        $this->actingAs($this->amministratore())
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), ['giocatori' => [$giocatore->id]])
            ->assertSessionHas('status', 'Lucia Bianchi iscritto al torneo "Coppa del circolo".');
    }

    public function test_chi_e_gia_iscritto_non_viene_iscritto_due_volte(): void
    {
        $torneo = Torneo::factory()->create();
        [$iscritto, $nuovo] = Giocatore::factory()->count(2)->create()->all();
        $torneo->giocatori()->attach($iscritto);

        $amministratore = $this->amministratore();

        $this->actingAs($amministratore)
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), ['giocatori' => [$iscritto->id, $nuovo->id]])
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('iscrizioni', 2);

        // Ripetere la stessa richiesta (doppio clic, pagina rimasta aperta) non cambia nulla.
        $this->actingAs($amministratore)
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), ['giocatori' => [$iscritto->id, $nuovo->id]])
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('iscrizioni', 2);
    }

    /**
     * @param  array<string, mixed>  $dati
     */
    #[DataProvider('iscrizioniNonValide')]
    public function test_le_iscrizioni_non_valide_vengono_rifiutate(array $dati, string $campo): void
    {
        $torneo = Torneo::factory()->create();
        Giocatore::factory()->create();

        $this->actingAs($this->amministratore())
            ->from(route('amministrazione.tornei.show', $torneo))
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), $dati)
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHasErrorsIn('iscrizione', [$campo]);

        $this->assertDatabaseCount('iscrizioni', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function iscrizioniNonValide(): array
    {
        return [
            'nessuna selezione' => [[], 'giocatori'],
            'elenco vuoto' => [['giocatori' => []], 'giocatori'],
            'non un elenco' => [['giocatori' => '1'], 'giocatori'],
            'giocatore inesistente' => [['giocatori' => [999]], 'giocatori.0'],
            'valore non numerico' => [['giocatori' => ['abc']], 'giocatori.0'],
        ];
    }

    public function test_dopo_un_iscrizione_respinta_si_riapre_solo_la_sua_finestra(): void
    {
        $torneo = Torneo::factory()->create();
        Giocatore::factory()->create();

        $risposta = $this->actingAs($this->amministratore())
            ->followingRedirects()
            ->from(route('amministrazione.tornei.show', $torneo))
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), []);

        $risposta->assertOk()->assertSee('Seleziona almeno un giocatore.');

        $html = $risposta->getContent();

        $this->assertMatchesRegularExpression('/id="modale-iscrizione"[^>]*data-apri-modale/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="modale-giocatore"[^>]*data-apri-modale/', $html);
    }

    public function test_dopo_un_errore_restano_spuntati_i_giocatori_scelti(): void
    {
        $torneo = Torneo::factory()->create();
        [$scelto, $nonScelto] = Giocatore::factory()->count(2)->create()->all();

        $html = $this->actingAs($this->amministratore())
            ->followingRedirects()
            ->from(route('amministrazione.tornei.show', $torneo))
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), ['giocatori' => [$scelto->id, 999]])
            ->assertOk()
            ->assertSee('non è più in anagrafica')
            ->getContent();

        $this->assertMatchesRegularExpression('/id="iscrizione-giocatore-'.$scelto->id.'"[^>]*\schecked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="iscrizione-giocatore-'.$nonScelto->id.'"[^>]*\schecked/', $html);
        $this->assertDatabaseCount('iscrizioni', 0);
    }

    public function test_l_amministratore_rimuove_un_giocatore_dal_torneo(): void
    {
        $torneo = Torneo::factory()->create();
        $altroTorneo = Torneo::factory()->create();
        $giocatore = Giocatore::factory()->create();
        $compagno = Giocatore::factory()->create();

        $torneo->giocatori()->attach([$giocatore->id, $compagno->id]);
        $altroTorneo->giocatori()->attach($giocatore);

        $this->actingAs($this->amministratore())
            ->delete(route('amministrazione.tornei.giocatori.destroy', [$torneo, $giocatore]))
            ->assertRedirect(route('amministrazione.tornei.show', $torneo))
            ->assertSessionHas('status');

        // Sparisce solo l'iscrizione a questo torneo: la scheda e le altre iscrizioni restano.
        $this->assertModelExists($giocatore);
        $this->assertSame([$compagno->id], $torneo->giocatori()->pluck('giocatori.id')->all());
        $this->assertSame(1, $altroTorneo->giocatori()->count());
    }

    public function test_non_si_rimuove_un_giocatore_che_non_e_iscritto_al_torneo(): void
    {
        $torneo = Torneo::factory()->create();
        $altroTorneo = Torneo::factory()->create();
        $giocatore = Giocatore::factory()->create();

        $altroTorneo->giocatori()->attach($giocatore);

        $this->actingAs($this->amministratore())
            ->delete(route('amministrazione.tornei.giocatori.destroy', [$torneo, $giocatore]))
            ->assertNotFound();

        $this->assertSame(1, $altroTorneo->giocatori()->count());
    }

    public function test_eliminare_il_torneo_lascia_i_giocatori_in_anagrafica(): void
    {
        $torneo = Torneo::factory()->create();
        $giocatore = Giocatore::factory()->create();
        $torneo->giocatori()->attach($giocatore);

        $this->actingAs($this->amministratore())
            ->delete(route('amministrazione.tornei.destroy', $torneo))
            ->assertRedirect(route('amministrazione.dashboard'));

        $this->assertModelMissing($torneo);
        $this->assertModelExists($giocatore);
        $this->assertDatabaseCount('iscrizioni', 0);
    }

    public function test_eliminare_un_giocatore_dall_anagrafica_lo_toglie_dai_tornei(): void
    {
        $torneo = Torneo::factory()->create();
        $giocatore = Giocatore::factory()->create();
        $torneo->giocatori()->attach($giocatore);

        $this->actingAs($this->amministratore())
            ->delete(route('amministrazione.giocatori.destroy', $giocatore))
            ->assertRedirect(route('amministrazione.dashboard'));

        $this->assertModelExists($torneo);
        $this->assertDatabaseCount('iscrizioni', 0);
    }

    public function test_un_utente_non_puo_gestire_un_torneo(): void
    {
        $torneo = Torneo::factory()->create();
        $giocatore = Giocatore::factory()->create();
        $torneo->giocatori()->attach($giocatore);
        $utente = User::factory()->create();

        $this->actingAs($utente)->get(route('amministrazione.tornei.show', $torneo))->assertForbidden();

        $this->actingAs($utente)
            ->post(route('amministrazione.tornei.giocatori.store', $torneo), $this->datiValidi())
            ->assertForbidden();

        $this->actingAs($utente)
            ->delete(route('amministrazione.tornei.giocatori.destroy', [$torneo, $giocatore]))
            ->assertForbidden();

        $this->actingAs($utente)
            ->post(route('amministrazione.tornei.iscrizioni.store', $torneo), ['giocatori' => [Giocatore::factory()->create()->id]])
            ->assertForbidden();

        $this->assertSame(2, Giocatore::count());
        $this->assertSame(1, $torneo->giocatori()->count());
    }

    public function test_gli_ospiti_vengono_mandati_al_login(): void
    {
        $torneo = Torneo::factory()->create();
        $giocatore = Giocatore::factory()->create();
        $torneo->giocatori()->attach($giocatore);

        $this->get(route('amministrazione.tornei.show', $torneo))->assertRedirect(route('login'));
        $this->post(route('amministrazione.tornei.giocatori.store', $torneo), $this->datiValidi())->assertRedirect(route('login'));
        $this->delete(route('amministrazione.tornei.giocatori.destroy', [$torneo, $giocatore]))->assertRedirect(route('login'));
        $this->post(route('amministrazione.tornei.iscrizioni.store', $torneo), ['giocatori' => [Giocatore::factory()->create()->id]])
            ->assertRedirect(route('login'));

        $this->assertSame(1, $torneo->giocatori()->count());
    }
}
