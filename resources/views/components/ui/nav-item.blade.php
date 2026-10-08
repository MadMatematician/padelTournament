{{-- Voce di <x-ui.nav>. Props: href, active (pagina corrente), icon. --}}
@props(['href', 'active' => false, 'icon' => null])
<li class="nav-item">
    <a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class(['nav-link', 'active' => $active]) }}>
        @if ($icon)
            <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </a>
</li>
