<?php

use App\Http\Controllers\Api\Test\TorneoDiTestController;
use Illuminate\Support\Facades\Route;

/*
| API (prefisso /api, risposte in JSON).
|
| Per ora solo le rotte di test: attive negli ambienti di `tornei.test.ambienti` (altrove 404).
| L'API non ha ancora un'autenticazione: non aggiungere qui rotte che servano fuori dallo sviluppo
| senza prima proteggerle.
*/

Route::middleware('ambiente-test')
    ->prefix('test')
    ->name('api.test.')
    ->group(function () {
        // Rotta principale: torneo generato con la factory, già con tutti i giocatori iscritti.
        // /api/test → `tornei.test.giocatori.predefinito` giocatori; /api/test/24 → 24 giocatori.
        Route::get('{giocatori?}', TorneoDiTestController::class)
            ->whereNumber('giocatori')
            ->name('torneo')
            ->descrizione('Crea un torneo di test già con i giocatori iscritti (numero nel link) e lo restituisce in JSON.');
    });
