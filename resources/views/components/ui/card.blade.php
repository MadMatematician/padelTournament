{{--
    Scheda Bootstrap. Props: title, icon, subtitle, flush (contenuto a filo, es. una tabella),
    highlight (la scheda su cui si sta lavorando in questo momento: filo ambra in alto, per trovarla subito).
    Slot opzionali: actions (pulsanti a destra del titolo), footer.
--}}
@props(['title' => null, 'icon' => null, 'subtitle' => null, 'flush' => false, 'highlight' => false])
<div {{ $attributes->class(['card', 'shadow-sm', 'scheda-attiva' => $highlight]) }}>
    @if ($title)
        <div @class(['card-header', 'd-flex flex-wrap justify-content-between align-items-center gap-2' => isset($actions)])>
            <div>
                <h2 class="card-title h5 mb-0">
                    @if ($icon)
                        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                    @endif
                    {{ $title }}
                </h2>
                @if ($subtitle)
                    <p class="text-body-secondary small mb-0 mt-1">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['card-body' => ! $flush])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="card-footer text-center">
            {{ $footer }}
        </div>
    @endisset
</div>
