{{-- Cella di tabella. Props: muted. --}}
@props(['muted' => false])
<td {{ $attributes->class(['text-body-secondary' => $muted]) }}>{{ $slot }}</td>
