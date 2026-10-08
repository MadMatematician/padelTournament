{{-- Pulsante (o link con aspetto di pulsante se c'è `href`). Props: variant, type, icon, href, block. --}}
@props(['variant' => 'primary', 'type' => 'button', 'icon' => null, 'href' => null, 'block' => false])
@php
    $classi = ['btn', 'btn-'.$variant, 'w-100' => $block];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classi) }}>
        @if ($icon)
            <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classi) }}>
        @if ($icon)
            <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </button>
@endif
