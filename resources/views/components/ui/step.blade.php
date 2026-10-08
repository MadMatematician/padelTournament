{{--
    Un passo di una procedura a tappe: numero, titolo, stato e pulsante.
    Props: number, title, done (passo già fatto: al posto del numero compare la spunta).
    Slot: descrizione o stato del passo; slot `action`: il pulsante (o il form) che lo esegue.
--}}
@props(['number', 'title', 'done' => false])
<div {{ $attributes->class(['passo', 'h-100', 'd-flex', 'flex-column', 'fatto' => $done]) }}>
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="passo-numero">
            @if ($done)
                <i class="bi bi-check-lg" aria-hidden="true"></i>
                <span class="visually-hidden">Passo {{ $number }}, fatto:</span>
            @else
                <span aria-hidden="true">{{ $number }}</span>
                <span class="visually-hidden">Passo {{ $number }}:</span>
            @endif
        </span>
        <h3 class="h6 mb-0">{{ $title }}</h3>
    </div>
    <div class="small text-body-secondary flex-grow-1 mb-3">{{ $slot }}</div>
    @isset($action)
        <div>{{ $action }}</div>
    @endisset
</div>
