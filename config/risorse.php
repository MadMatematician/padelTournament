<?php

/*
|--------------------------------------------------------------------------
| Release delle risorse statiche (public/)
|--------------------------------------------------------------------------
|
| Ogni dipendenza servita in locale ha qui la sua release, da aggiornare
| A MANO. Il valore finisce in coda all'indirizzo del file (?v=<release>)
| tramite App\Support\Risorse::url() in components/layouts/base.blade.php:
| finché la release non cambia il browser riusa la copia che ha già,
| quando cambia la riscarica.
|
| Quando aggiornarla:
|  - `app`: a ogni modifica di public/css/torneo.css o public/js/torneo.js;
|  - le altre: quando si sostituiscono i file in public/vendor/<nome> con
|    una versione nuova (scrivere la versione reale della libreria).
|
| Se la configurazione è in cache (php artisan config:cache) va rigenerata
| dopo ogni modifica a questo file.
|
*/

return [

    'release' => [
        // public/css/torneo.css, public/js/torneo.js
        'app' => '1.11.0',

        // public/vendor/bootstrap
        'bootstrap' => '5.3.8',

        // public/vendor/bootstrap-icons (il suo CSS versiona da sé i propri font)
        'bootstrap-icons' => '1.13.1',

        // public/vendor/jquery
        'jquery' => '3.7.1',

        // public/vendor/datatables (caricata solo da <x-ui.datatable>)
        'datatables' => '3.1.3',

        // public/vendor/fonts/archivo (pacchetto @fontsource-variable/archivo)
        'archivo' => '5.3.0',
    ],

];
