{{-- Riga di <x-ui.repeater>: i campi nello slot e, a destra, il pulsante per toglierla. --}}
<div data-riga {{ $attributes->class(['riga-ripetuta', 'd-flex', 'align-items-start', 'gap-2']) }}>
    <div class="flex-grow-1">{{ $slot }}</div>
    <button type="button" class="btn btn-outline-secondary" data-riga-rimuovi aria-label="Togli questa riga" title="Togli questa riga">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
</div>
