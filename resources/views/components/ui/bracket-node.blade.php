{{--
    Nodo di un turno di <x-ui.bracket>: gli incontri da cui escono, tutte insieme, le squadre del turno
    successivo. Props: merge (disegna la linea che li raccoglie e li porta al turno successivo: da non usare
    per l'ultimo turno).
--}}
@props(['merge' => true])
<div {{ $attributes->class(['tabellone-nodo', 'raccolta' => $merge]) }}>
    {{ $slot }}
</div>
