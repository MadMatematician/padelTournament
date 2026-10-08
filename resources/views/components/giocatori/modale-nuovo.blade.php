{{--
    Finestra dei nuovi giocatori: una riga per giocatore; appena una riga viene compilata ne compare
    un'altra vuota, così se ne inseriscono quanti si vuole con un solo invio. Le righe vuote sono ignorate.
    Si riapre da sola, con le righe inviate, se la validazione ne ha respinta qualcuna.
    Props: torneo (App\Models\Torneo, facoltativo). Con un torneo i giocatori vengono creati in anagrafica
    e iscritti a quel torneo (pagina di gestione del torneo); senza, vengono solo creati in anagrafica.
    circoli (elenco dei circoli già usati: suggerimenti per il campo "Circolo").
--}}
@props(['torneo' => null, 'circoli' => []])
@php
    $errori = $errors->getBag('giocatore');
    // Dopo un invio respinto si ripropongono le righe compilate, ciascuna con l'indice con cui è stata inviata
    // (è quello a cui si riferiscono gli errori); altrimenti si parte da una riga vuota.
    $inviate = $errori->any() ? array_filter((array) old('giocatori', []), 'is_array') : [];
    $compilate = array_filter($inviate, fn (array $riga) => collect($riga)->contains(fn ($valore) => filled($valore)));
    $righe = array_values(array_filter(array_keys($compilate), 'is_int'));
    $righe = $righe === [] ? [0] : $righe;
    $azione = $torneo
        ? route('amministrazione.tornei.giocatori.store', $torneo)
        : route('amministrazione.giocatori.store');
@endphp
<x-ui.modal id="modale-giocatore" :title="$torneo ? 'Registra giocatori' : 'Nuovi giocatori'" icon="person-plus"
            size="xl" fullscreen="xl-down" scrollable :open="$errori->any()">
    <x-ui.form id="form-giocatore" :action="$azione" bag="giocatore">
        @if ($errori->has('giocatori'))
            <x-ui.alert variant="danger" icon="exclamation-triangle">{{ $errori->first('giocatori') }}</x-ui.alert>
        @endif

        <x-ui.text muted small>
            Una riga per giocatore: quando ne compili una, sotto ne compare un'altra vuota. Nome, cognome, genere, anno di nascita e fascia sono obbligatori; lato e circolo no.
            @if ($torneo)
                I giocatori vengono salvati nell'anagrafica e iscritti a questo torneo; chi ha già una scheda si iscrive con «Dall'anagrafica».
            @endif
        </x-ui.text>

        <x-ui.repeater :next="max($righe) + 1">
            @foreach ($righe as $indice)
                <x-giocatori.riga-nuovo :indice="$indice" circoli="giocatore-circoli" :fuoco="$loop->first" />
            @endforeach

            <x-slot:template>
                <x-giocatori.riga-nuovo indice="__INDICE__" circoli="giocatore-circoli" />
            </x-slot:template>
        </x-ui.repeater>

        <x-ui.datalist id="giocatore-circoli" :options="$circoli" />
    </x-ui.form>

    <x-slot:footer>
        <x-ui.button variant="outline-secondary" data-bs-dismiss="modal">Annulla</x-ui.button>
        <x-ui.button type="submit" form="form-giocatore" :icon="$torneo ? 'person-plus' : 'plus-lg'">{{ $torneo ? 'Registra giocatori' : 'Crea giocatori' }}</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
