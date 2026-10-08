{{-- Intestazione di pagina. Props: title, subtitle, icon. Slot: azioni a destra. --}}
@props(['title', 'subtitle' => null, 'icon' => null])
<header {{ $attributes->class(['intestazione-pagina', 'd-flex', 'flex-wrap', 'justify-content-between', 'align-items-end', 'gap-3', 'mb-4']) }}>
    <div>
        <h1 class="h3 mb-1">
            @if ($icon)
                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
            @endif
            {{ $title }}
        </h1>
        @if ($subtitle)
            <p class="text-body-secondary mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->hasActualContent())
        <div class="d-flex gap-2">{{ $slot }}</div>
    @endif
</header>
