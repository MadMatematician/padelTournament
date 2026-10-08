{{--
    Gli iscritti di un torneo: riepilogo (totale e per fascia) ed elenco dei giocatori, con i comandi per
    registrarne di nuovi o iscriverli dall'anagrafica finché il sorteggio non c'è.
    Props: torneo (App\Models\Torneo), giocatori (iscritti), iscrittiPerFascia (fascia => quanti),
    sorteggiato (iscrizioni chiuse), highlight (vedi <x-ui.card>).
--}}
@props(['torneo', 'giocatori', 'iscrittiPerFascia', 'sorteggiato' => false, 'highlight' => false])
<x-ui.row class="mb-4">
    <x-ui.col sm="4">
        <x-ui.stat label="Giocatori iscritti" :value="$giocatori->count()" icon="people" />
    </x-ui.col>
    @foreach (config('tornei.giocatori.fasce') as $fascia => $etichetta)
        <x-ui.col sm="4">
            <x-ui.stat :label="$etichetta" :value="$iscrittiPerFascia->get($fascia, 0)" icon="person" />
        </x-ui.col>
    @endforeach
</x-ui.row>

<x-ui.card id="giocatori" title="Giocatori iscritti" icon="person-lines-fill" flush :highlight="$highlight"
           :subtitle="$sorteggiato ? 'Iscrizioni chiuse: per cambiare gli iscritti annulla il sorteggio.' : null" {{ $attributes }}>
    @unless ($sorteggiato)
        <x-slot:actions>
            <x-ui.button variant="outline-primary" icon="person-check" class="btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#modale-iscrizione">Dall'anagrafica</x-ui.button>
            <x-ui.button icon="person-plus" class="btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#modale-giocatore">Registra giocatori</x-ui.button>
        </x-slot:actions>
    @endunless
    <x-giocatori.tabella :giocatori="$giocatori" :torneo="$torneo" :bloccato="$sorteggiato" />
</x-ui.card>
