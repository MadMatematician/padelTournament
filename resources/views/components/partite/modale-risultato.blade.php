{{--
    Finestra del risultato, una per pagina: la apre il pulsante di <x-partite.risultato> e torneo.js la riempie
    con i dati della partita scelta (coppie, girone o turno, formato della fase, set già inseriti).

    Inserimento continuo: «Salva risultato» salva in sottofondo e la finestra resta aperta sulla prossima partita
    senza risultato (dello stesso girone finché ce ne sono, poi degli altri: l'ordine è in
    App\Services\Tornei\CodaRisultati). «Successiva» passa oltre senza aspettare un risultato; se nella griglia
    c'è un punteggio lo salva prima. Quando cambia il girone, o il turno delle finali, la finestra lo dice e la
    fascia con il suo nome si accende. Alla chiusura la pagina si aggiorna.

    Senza JavaScript il form viene inviato normalmente: se il punteggio viene respinto la finestra si riapre da
    sola, già compilata per quella partita (`partita_id` nel form dice quale) con l'errore sul set sbagliato.

    Props: torneo (App\Models\Torneo), partite (collezione delle partite mostrate nella pagina, con le coppie caricate).
--}}
@props(['torneo', 'partite'])
@php
    $errori = $errors->getBag('risultato');
    $respinta = $errori->any() ? $partite->firstWhere('id', (int) old('partita_id')) : null;
    $scheda = $respinta ? \App\Support\SchedaRisultato::di($torneo, $respinta) : null;
    $massimo = max(array_keys(config('tornei.formato.set')));
@endphp
<x-ui.modal id="modale-risultato" title="Risultato" icon="pencil-square" size="lg" :open="$respinta !== null">
    {{-- Che cosa è appena successo: risultato salvato, cambio di girone. Li riempie torneo.js. --}}
    <x-ui.alert variant="success" icon="check-circle-fill" class="d-none py-2" data-risultato-esito>
        <x-ui.span data-testo></x-ui.span>
    </x-ui.alert>
    <x-ui.alert variant="dark" icon="signpost-split-fill" class="d-none py-2" data-risultato-cambio>
        <x-ui.span data-testo></x-ui.span>
    </x-ui.alert>

    <x-ui.text class="gruppo-partita" data-risultato-testata>
        <x-ui.span class="gruppo-partita-nome" data-risultato-gruppo>{{ $scheda['gruppoNome'] ?? '' }}</x-ui.span>
        <x-ui.span data-risultato-dettaglio>{{ $scheda['dettaglio'] ?? '' }}</x-ui.span>
    </x-ui.text>
    <x-ui.text muted small data-risultato-formato>{{ $scheda['formato'] ?? '' }}</x-ui.text>

    <x-ui.alert variant="danger" icon="exclamation-triangle" :class="$errori->has('set') ? 'py-2' : 'd-none py-2'" data-risultato-errore>
        <x-ui.span data-testo>{{ $errori->first('set') }}</x-ui.span>
    </x-ui.alert>

    <x-ui.form id="form-risultato" method="PUT" bag="risultato" :action="$scheda['azione'] ?? '#'">
        <x-ui.hidden name="partita_id" :value="$respinta?->id" data-risultato-partita />
        <x-ui.score-grid name="set" :sets="$massimo" :visible="$scheda['setMassimi'] ?? null" :tie-break="(bool) ($scheda['tieBreak'] ?? true)"
                         :team-a="$scheda['coppiaA'] ?? ''" :team-b="$scheda['coppiaB'] ?? ''" />
        <x-ui.text muted small class="mb-0">I punti del tie-break sono facoltativi: servono solo se vuoi registrarli.</x-ui.text>
    </x-ui.form>

    <x-ui.alert variant="info" icon="info-circle" class="d-none py-2 mt-3 mb-0" data-risultato-fine>
        <x-ui.span data-testo></x-ui.span>
    </x-ui.alert>

    <x-slot:footer>
        <x-ui.form id="form-risultato-cancella" method="DELETE" data-risultato-cancella
                   :action="($scheda['cancella'] ?? '') ?: '#'"
                   :class="'me-auto'.(($scheda['cancella'] ?? '') === '' ? ' d-none' : '')">
            <x-ui.button type="submit" variant="outline-danger" icon="trash">Cancella risultato</x-ui.button>
        </x-ui.form>
        <x-ui.button variant="outline-secondary" data-bs-dismiss="modal">Chiudi</x-ui.button>
        <x-ui.button variant="outline-primary" icon="skip-forward-fill" data-risultato-successiva
                     title="Passa alla prossima partita senza risultato (se hai scritto un punteggio, prima lo salva)">Successiva</x-ui.button>
        <x-ui.button type="submit" form="form-risultato" icon="check-lg" data-risultato-salva>Salva risultato</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
