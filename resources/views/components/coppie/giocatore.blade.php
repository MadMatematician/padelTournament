{{--
    Un giocatore dentro una coppia: nome, lato preferito e, se in quella coppia gioca in prestito, l'etichetta.
    Props: giocatore (App\Models\Giocatore), prestito (bool).
--}}
@props(['giocatore', 'prestito' => false])
{{ $giocatore->cognome }} {{ $giocatore->nome }}
@if ($giocatore->etichettaLato())
    <x-ui.badge variant="light">{{ $giocatore->etichettaLato() }}</x-ui.badge>
@endif
@if ($prestito)
    <x-ui.badge variant="dark" icon="arrow-left-right">in prestito</x-ui.badge>
@endif
