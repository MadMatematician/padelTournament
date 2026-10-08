{{-- Collegamento. Props: href, icon. --}}
@props(['href', 'icon' => null])
<a href="{{ $href }}" {{ $attributes }}>@if ($icon)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i> @endif{{ $slot }}</a>
