{{-- Marchio dell'applicazione (disco forato + nome). Con `href` diventa un link. --}}
@props(['href' => null])
@php
    $tag = $href ? 'a' : 'span';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['marchio']) }}>
    <span class="marchio-simbolo" aria-hidden="true"></span>
    <span class="marchio-nome">{{ config('app.name') }}</span>
</{{ $tag }}>
