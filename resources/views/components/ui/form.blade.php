{{--
    Form con token CSRF e method spoofing. Props: action, method, bag.
    `bag` è il contenitore degli errori di validazione del form (quello del Form Request):
    serve quando nella stessa pagina ci sono più form; i campi <x-ui.input> e <x-ui.select> lo ereditano.
--}}
@props(['action', 'method' => 'POST', 'bag' => 'default'])
@php
    $metodo = strtoupper($method);
@endphp
<form action="{{ $action }}" method="{{ $metodo === 'GET' ? 'GET' : 'POST' }}" {{ $attributes->merge(['novalidate' => true]) }}>
    @if ($metodo !== 'GET')
        @csrf
    @endif
    @if (! in_array($metodo, ['GET', 'POST'], true))
        @method($metodo)
    @endif

    {{ $slot }}
</form>
