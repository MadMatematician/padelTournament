{{-- Etichetta del ruolo di un utente. Props: ruolo (App\Enums\Ruolo). --}}
@props(['ruolo'])
<x-ui.badge :variant="$ruolo === \App\Enums\Ruolo::Amministratore ? 'primary' : 'secondary'" :icon="$ruolo->icona()">
    {{ $ruolo->etichetta() }}
</x-ui.badge>
