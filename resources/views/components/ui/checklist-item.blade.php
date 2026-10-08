{{-- Voce di <x-ui.checklist>. L'icona cambia via JS quando la voce riceve la classe "soddisfatto". --}}
<li {{ $attributes->class(['requisito']) }}>
    <i class="bi bi-circle" aria-hidden="true"></i>
    <span>{{ $slot }}</span>
</li>
