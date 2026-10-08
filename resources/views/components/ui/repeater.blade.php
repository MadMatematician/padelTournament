{{--
    Righe ripetute di un form (es. più giocatori in un solo invio). Appena l'ultima riga viene compilata
    ne compare sotto una vuota; ogni riga tranne quella in attesa si può togliere (torneo.js).
    Props: next (indice della prossima riga da creare = numero di righe già presenti).
    Slot: le righe già presenti, ciascuna in un <x-ui.repeater-row>.
    Slot `template`: una riga vuota in cui l'indice è il segnaposto __INDICE__ (in nomi e id dei campi):
    è il modello da cui nascono le righe nuove.
--}}
@props(['next' => 0])
<div data-ripeti data-ripeti-prossimo="{{ $next }}" {{ $attributes }}>
    <div data-ripeti-elenco>{{ $slot }}</div>
    <template data-ripeti-modello>{{ $template }}</template>
</div>
