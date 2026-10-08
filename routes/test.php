<?php

use App\Http\Controllers\Test\TorneoDiTestController;
use Illuminate\Support\Facades\Route;

/*
| Rotte di test del sito. Incluso da routes/web.php.
|
| Attive solo negli ambienti di `tornei.test.ambienti` (altrove 404) e riservate all'amministratore,
| perché creano dati e portano alla pagina di gestione del torneo.
*/

Route::middleware(['ambiente-test', 'auth', 'ruolo:amministratore'])
    ->prefix('test')
    ->name('test.')
    ->group(function () {
        // Rotta principale: torneo generato con la factory, già con tutti i giocatori iscritti.
        // /test → `tornei.test.giocatori.predefinito` giocatori; /test/24 → 24 giocatori.
        Route::get('{giocatori?}', TorneoDiTestController::class)
            ->whereNumber('giocatori')
            ->name('torneo')
            ->descrizione('Crea un torneo di test già con i giocatori iscritti (numero nel link) e apre la sua gestione.');
    });
