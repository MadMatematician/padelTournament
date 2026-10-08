{{-- Etichetta colorata. Props: variant, icon. --}}
@props(['variant' => 'secondary', 'icon' => null])
<span {{ $attributes->class(['badge', 'text-bg-'.$variant]) }}>@if ($icon)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i> @endif{{ $slot }}</span>
