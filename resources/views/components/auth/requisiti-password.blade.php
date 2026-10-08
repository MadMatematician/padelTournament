{{--
    Requisiti della password letti da config/sicurezza.php (gli stessi validati dal server).
    Props: campo e conferma = selettori degli input controllati da public/js/torneo.js.
--}}
@props(['campo' => '#password', 'conferma' => '#password_confirmation'])
@php
    $requisiti = config('sicurezza.password');
@endphp
<x-ui.checklist title="La password deve avere:" data-requisiti-password="{{ $campo }}" data-conferma="{{ $conferma }}" aria-live="polite">
    <x-ui.checklist-item data-requisito="lunghezza" data-min="{{ $requisiti['lunghezza_minima'] }}">
        almeno {{ $requisiti['lunghezza_minima'] }} caratteri
    </x-ui.checklist-item>
    @if ($requisiti['maiuscole_minuscole'])
        <x-ui.checklist-item data-requisito="maiuscole_minuscole">una lettera maiuscola e una minuscola</x-ui.checklist-item>
    @else
        <x-ui.checklist-item data-requisito="lettere">almeno una lettera</x-ui.checklist-item>
    @endif
    @if ($requisiti['numeri'])
        <x-ui.checklist-item data-requisito="numeri">almeno un numero</x-ui.checklist-item>
    @endif
    @if ($requisiti['simboli'])
        <x-ui.checklist-item data-requisito="simboli">almeno un simbolo (es. ! ? @ # -)</x-ui.checklist-item>
    @endif
    <x-ui.checklist-item data-requisito="conferma">le due password coincidono</x-ui.checklist-item>
</x-ui.checklist>
