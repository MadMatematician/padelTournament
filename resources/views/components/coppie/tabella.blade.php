{{--
    Elenco delle coppie sorteggiate, ordinabile per colonna (DataTables). Props: coppie (collezione di
    App\Models\Coppia con primaFascia, secondaFascia e girone caricati), torneo (App\Models\Torneo: dà i nomi
    delle due fasce). Ordine iniziale: per girone (nell'ordine dei gironi: A, B, …) e poi per numero di coppia;
    finché i gironi non ci sono, per numero di coppia.
--}}
@props(['coppie', 'torneo'])
@php
    $fasce = config('tornei.giocatori.fasce');
    [$prima, $seconda] = array_keys($torneo->regole()['squadra']['composizione']);
    $conGironi = $coppie->contains(fn ($coppia) => $coppia->girone !== null);
@endphp
<x-ui.datatable :headers="['N.', $fasce[$prima], $fasce[$seconda], 'Girone']" :order="$conGironi ? [[3, 'asc'], [0, 'asc']] : [[0, 'asc']]"
                :page-length="50" :entries="['coppia', 'coppie']" empty="Coppie non ancora generate.">
    @foreach ($coppie as $coppia)
        <x-ui.tr>
            <x-ui.td muted>{{ $loop->iteration }}</x-ui.td>
            <x-ui.td><x-coppie.giocatore :giocatore="$coppia->primaFascia" :prestito="$coppia->inPrestito($coppia->primaFascia)" /></x-ui.td>
            <x-ui.td><x-coppie.giocatore :giocatore="$coppia->secondaFascia" :prestito="$coppia->inPrestito($coppia->secondaFascia)" /></x-ui.td>
            {{-- Si ordina sulla posizione del girone, non sul nome; le coppie senza girone vanno in fondo. --}}
            <x-ui.td :muted="$coppia->girone === null" :data-order="$coppia->girone?->posizione ?? PHP_INT_MAX">{{ $coppia->girone?->nome ?? '—' }}</x-ui.td>
        </x-ui.tr>
    @endforeach
</x-ui.datatable>
