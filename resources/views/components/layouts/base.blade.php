{{-- Scheletro HTML comune a tutte le pagine: head, asset statici, script.
     Ogni risorsa locale passa da Risorse::url(), che aggiunge ?v=<release> letta da config/risorse.php:
     quando si aggiorna un file in public/ va aggiornata a mano la release corrispondente.
     I componenti che hanno bisogno di una libreria in più la aggiungono con @pushOnce('stili') / @pushOnce('script'):
     viene caricata prima di torneo.css / torneo.js (vedi x-ui.datatable). --}}
@use('App\Support\Risorse')
@props(['title' => null])
@php
    $carattere = Risorse::url('archivo', 'vendor/fonts/archivo/archivo-latin-wdth-normal.woff2');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="preload" href="{{ $carattere }}" as="font" type="font/woff2" crossorigin>
    {{-- Il carattere è dichiarato qui, e non in torneo.css, perché il suo indirizzo porta la release --}}
    <style>
        @font-face {
            font-family: "Archivo";
            font-style: normal;
            font-weight: 100 900;
            font-stretch: 62% 125%;
            font-display: swap;
            src: url("{!! $carattere !!}") format("woff2-variations");
        }
    </style>
    <link rel="stylesheet" href="{{ Risorse::url('bootstrap', 'vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ Risorse::url('bootstrap-icons', 'vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    @stack('stili')
    <link rel="stylesheet" href="{{ Risorse::url('app', 'css/torneo.css') }}">
</head>
<body {{ $attributes }}>
    {{ $slot }}

    <script src="{{ Risorse::url('jquery', 'vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ Risorse::url('bootstrap', 'vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @stack('script')
    <script src="{{ Risorse::url('app', 'js/torneo.js') }}"></script>
</body>
</html>
