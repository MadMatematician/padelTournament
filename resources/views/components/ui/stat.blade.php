{{-- Riquadro con un numero in evidenza. Props: label, value, icon. --}}
@props(['label', 'value', 'icon' => null])
<div {{ $attributes->class(['card', 'shadow-sm', 'riquadro-numero', 'h-100']) }}>
    <div class="card-body d-flex align-items-center gap-3">
        @if ($icon)
            <span class="riquadro-numero-icona"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
        @endif
        <div>
            <div class="riquadro-numero-valore">{{ $value }}</div>
            <div class="text-body-secondary small">{{ $label }}</div>
        </div>
    </div>
</div>
