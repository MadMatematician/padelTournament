{{--
    Elenco dei giocatori. Props: giocatori (collezione di App\Models\Giocatore), torneo (App\Models\Torneo, facoltativo).
    Senza torneo è l'anagrafica: «Elimina» cancella la scheda. Con un torneo sono i suoi iscritti:
    «Rimuovi» toglie solo l'iscrizione, la scheda resta in anagrafica.
    Se almeno un giocatore ha un circolo compare il filtro per circolo (colonna 6, contando da 0).
    Genere e anno di nascita mancano nelle schede create prima che il form li richiedesse: lì compare «—».
    bloccato: l'elenco è di sola lettura (iscrizioni chiuse dopo il sorteggio), senza la colonna delle azioni.
--}}
@props(['giocatori', 'torneo' => null, 'bloccato' => false])
@php
    $circoli = $giocatori->pluck('circolo')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    $vuoto = match (true) {
        $bloccato => 'Nessun giocatore iscritto.',
        $torneo !== null => 'Nessun giocatore iscritto: aggiungine con «Registra giocatori» o «Dall\'anagrafica».',
        default => 'Nessun giocatore: creane con «Nuovi giocatori».',
    };
@endphp
<x-ui.datatable :headers="['Cognome', 'Nome', 'Genere', 'Anno di nascita', 'Fascia', 'Lato preferito', 'Circolo']" :actions="! $bloccato" :order="[[0, 'asc'], [1, 'asc']]"
                :entries="['giocatore', 'giocatori']"
                :empty="$vuoto">
    @if ($circoli->isNotEmpty())
        <x-slot:filters>
            <x-ui.datatable-filter column="6" label="Circolo" :options="$circoli" all="Tutti i circoli" />
        </x-slot:filters>
    @endif

    @foreach ($giocatori as $giocatore)
        <x-ui.tr>
            <x-ui.td class="fw-semibold">{{ $giocatore->cognome }}</x-ui.td>
            <x-ui.td>{{ $giocatore->nome }}</x-ui.td>
            <x-ui.td :muted="$giocatore->genere === null">{{ $giocatore->etichettaGenere() ?? '—' }}</x-ui.td>
            <x-ui.td :muted="$giocatore->anno_nascita === null" :data-order="$giocatore->anno_nascita ?? 0">{{ $giocatore->anno_nascita ?? '—' }}</x-ui.td>
            <x-ui.td :data-order="$giocatore->fascia"><x-giocatori.badge-fascia :giocatore="$giocatore" /></x-ui.td>
            <x-ui.td muted>{{ $giocatore->etichettaLato() ?? '—' }}</x-ui.td>
            <x-ui.td :muted="$giocatore->circolo === null">{{ $giocatore->circolo ?? '—' }}</x-ui.td>
            @unless ($bloccato)
            <x-ui.td class="text-end">
                @if ($torneo)
                    <x-ui.form :action="route('amministrazione.tornei.giocatori.destroy', [$torneo, $giocatore])" method="DELETE"
                               :data-conferma="'Rimuovere '.$giocatore->nomeCompleto().' dal torneo «'.$torneo->nome.'»? La sua scheda resta nell\'anagrafica dei giocatori.'"
                               data-conferma-azione="Rimuovi">
                        <x-ui.button type="submit" variant="outline-danger" icon="person-dash" class="btn-sm text-nowrap"
                                     :aria-label="'Rimuovi '.$giocatore->nomeCompleto().' dal torneo'">Rimuovi</x-ui.button>
                    </x-ui.form>
                @else
                    <x-ui.form :action="route('amministrazione.giocatori.destroy', $giocatore)" method="DELETE"
                               :data-conferma="'Eliminare il giocatore '.$giocatore->nomeCompleto().'? Viene tolto anche dai tornei a cui è iscritto. L\'operazione non si può annullare.'"
                               data-conferma-azione="Elimina">
                        <x-ui.button type="submit" variant="outline-danger" icon="trash" class="btn-sm text-nowrap"
                                     :aria-label="'Elimina il giocatore '.$giocatore->nomeCompleto()">Elimina</x-ui.button>
                    </x-ui.form>
                @endif
            </x-ui.td>
            @endunless
        </x-ui.tr>
    @endforeach
</x-ui.datatable>
