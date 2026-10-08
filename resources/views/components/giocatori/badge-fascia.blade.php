{{-- Etichetta della fascia di un giocatore. Props: giocatore (App\Models\Giocatore). --}}
@props(['giocatore'])
@php
    // La prima fascia configurata è quella in evidenza.
    $prima = array_key_first(config('tornei.giocatori.fasce'));
@endphp
<x-ui.badge :variant="$giocatore->fascia === $prima ? 'primary' : 'secondary'">{{ $giocatore->etichettaFascia() }}</x-ui.badge>
