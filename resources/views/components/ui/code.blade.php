{{-- Testo a larghezza fissa in linea: indirizzi, nomi di rotte, classi. Props: muted. --}}
@props(['muted' => false])
<code {{ $attributes->class(['text-body' => ! $muted, 'text-body-secondary' => $muted]) }}>{{ $slot }}</code>
