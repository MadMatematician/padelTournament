{{-- Paragrafo. Props: muted, small. --}}
@props(['muted' => false, 'small' => false])
<p {{ $attributes->class(['text-body-secondary' => $muted, 'small' => $small]) }}>{{ $slot }}</p>
