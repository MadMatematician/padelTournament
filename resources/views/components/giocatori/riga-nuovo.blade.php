{{--
    Una riga del form dei nuovi giocatori: nome, cognome, genere, anno di nascita, fascia, lato preferito, circolo.
    I campi stanno su una riga sola da 1200px in su (`grow`), su due da 992px, su tre da 576px.
    Nessun campo ha un valore iniziale: la riga in attesa deve restare vuota (vedi x-ui.repeater).
    Props: indice (posizione della riga: i campi si chiamano `giocatori[indice][…]`; nel modello delle righe
    nuove è il segnaposto __INDICE__), circoli (id del <x-ui.datalist> con i circoli già usati), fuoco
    (il cursore parte dal nome di questa riga all'apertura della finestra).
--}}
@props(['indice', 'circoli' => null, 'fuoco' => false])
@php
    $nome = fn (string $campo) => 'giocatori['.$indice.']['.$campo.']';
    $id = fn (string $campo) => 'giocatore-'.$indice.'-'.$campo;
    [$primoAnno, $ultimoAnno] = \App\Models\Giocatore::anniNascitaAmmessi();
@endphp
<x-ui.repeater-row>
    <x-ui.row fields>
        <x-ui.col sm="6" lg="3" grow="1.3">
            <x-ui.input :id="$id('nome')" :name="$nome('nome')" placeholder="Nome" aria-label="Nome" maxlength="60"
                        autocomplete="off" compact :data-fuoco="$fuoco" />
        </x-ui.col>
        <x-ui.col sm="6" lg="3" grow="1.3">
            <x-ui.input :id="$id('cognome')" :name="$nome('cognome')" placeholder="Cognome" aria-label="Cognome" maxlength="60"
                        autocomplete="off" compact />
        </x-ui.col>
        <x-ui.col sm="4" lg="3" grow="1.1">
            <x-ui.select :id="$id('genere')" :name="$nome('genere')" :options="config('tornei.giocatori.generi')"
                         placeholder="Genere…" aria-label="Genere" compact />
        </x-ui.col>
        <x-ui.col sm="4" lg="3" grow="1.1">
            <x-ui.input :id="$id('anno_nascita')" :name="$nome('anno_nascita')" placeholder="Anno nascita"
                        :aria-label="'Anno di nascita (da '.$primoAnno.' a '.$ultimoAnno.')'" :title="'Anno di nascita, da '.$primoAnno.' a '.$ultimoAnno"
                        inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" compact />
        </x-ui.col>
        <x-ui.col sm="4" lg="4" grow="1.4">
            <x-ui.select :id="$id('fascia')" :name="$nome('fascia')" :options="config('tornei.giocatori.fasce')"
                         placeholder="Fascia…" aria-label="Fascia" compact />
        </x-ui.col>
        <x-ui.col sm="4" lg="4" grow="1">
            <x-ui.select :id="$id('lato')" :name="$nome('lato')" :options="config('tornei.giocatori.lati')"
                         placeholder="Lato…" aria-label="Lato preferito (facoltativo)" compact />
        </x-ui.col>
        <x-ui.col sm="8" lg="4" grow="1.3">
            <x-ui.input :id="$id('circolo')" :name="$nome('circolo')" placeholder="Circolo" aria-label="Circolo di appartenenza (facoltativo)"
                        maxlength="80" autocomplete="off" :list="$circoli" compact />
        </x-ui.col>
    </x-ui.row>
</x-ui.repeater-row>
