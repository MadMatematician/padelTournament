<?php

/*
|--------------------------------------------------------------------------
| Regole di sicurezza degli account
|--------------------------------------------------------------------------
|
| I requisiti della password valgono sia per la validazione lato server
| (Password::defaults() in AppServiceProvider) sia per l'elenco mostrato
| nella pagina di registrazione: si cambiano solo qui.
|
*/

return [

    'password' => [
        'lunghezza_minima' => 10,
        'maiuscole_minuscole' => true,
        'numeri' => true,
        'simboli' => true,

        // Rifiuta le password comparse in violazioni note (servizio esterno
        // haveibeenpwned.com: richiede la rete). Consigliato in produzione.
        'verifica_violazioni' => (bool) env('PASSWORD_VERIFICA_VIOLAZIONI', false),
    ],

    'login' => [
        // Tentativi falliti consentiti per coppia nome utente + IP,
        // poi l'accesso resta bloccato per `blocco_secondi`.
        'tentativi_massimi' => 5,
        'blocco_secondi' => 60,
    ],

    'username' => [
        'lunghezza_minima' => 3,
        'lunghezza_massima' => 30,
    ],

];
