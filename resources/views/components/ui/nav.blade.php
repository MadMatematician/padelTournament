{{-- Barra di navigazione a schede (pillole) tra le pagine di uno stesso oggetto. Slot: <x-ui.nav-item>. --}}
<nav {{ $attributes->class(['mb-4']) }}>
    <ul class="nav nav-pills navigazione-schede gap-1">
        {{ $slot }}
    </ul>
</nav>
