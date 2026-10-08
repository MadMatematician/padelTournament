{{-- Una sezione della mappa del sito: le rotte con lo stesso prefisso. Props: sezione (App\Services\Mappa\SezioneMappa). --}}
@props(['sezione'])
@php
    $quante = count($sezione->voci);
@endphp
<x-ui.card :title="$sezione->titolo()" icon="signpost-split" :subtitle="$quante === 1 ? 'Una rotta' : $quante.' rotte'" flush {{ $attributes }}>
    <x-ui.table :headers="['Metodo', 'Indirizzo', 'Destinazione', 'Accesso']">
        @foreach ($sezione->voci as $voce)
            <x-mappa.voce :voce="$voce" />
        @endforeach
    </x-ui.table>
</x-ui.card>
