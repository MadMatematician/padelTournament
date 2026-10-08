{{-- Riga della griglia Bootstrap. Props: fields = riga di campi di un form (nessuno spazio verticale aggiuntivo). --}}
@props(['fields' => false])
<div {{ $attributes->class(['row', 'g-3' => ! $fields, 'gx-3' => $fields]) }}>
    {{ $slot }}
</div>
