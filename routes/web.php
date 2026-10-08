<?php

use App\Http\Controllers\Amministrazione\ClassificaController as ClassificaAmministrazione;
use App\Http\Controllers\Amministrazione\DashboardController as DashboardAmministrazione;
use App\Http\Controllers\Amministrazione\GiocatoreController as GiocatoriAmministrazione;
use App\Http\Controllers\Amministrazione\IscrizioneController as IscrizioniAmministrazione;
use App\Http\Controllers\Amministrazione\MappaController as MappaAmministrazione;
use App\Http\Controllers\Amministrazione\RisultatoController as RisultatiAmministrazione;
use App\Http\Controllers\Amministrazione\SorteggioController as SorteggioAmministrazione;
use App\Http\Controllers\Amministrazione\TabelloneController as TabelloneAmministrazione;
use App\Http\Controllers\Amministrazione\TorneoController as TorneiAmministrazione;
use App\Http\Controllers\AreaUtente\DashboardController as DashboardAreaUtente;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
| Ogni rotta dichiara con ->descrizione('…') che cosa fa la sua destinazione, in una riga:
| è il testo mostrato nella mappa del sito (/amministrazione/mappa), che si costruisce da questi file.
*/

// Radice: login per gli ospiti, pagina iniziale del ruolo per chi è autenticato.
Route::get('/', HomeController::class)
    ->name('home')
    ->descrizione('Radice del sito: manda al login o alla pagina iniziale del proprio ruolo.');

require __DIR__.'/auth.php';

// Rotte di test (solo sviluppo): tornei generati con le factory.
require __DIR__.'/test.php';

Route::middleware('auth')->group(function () {
    // Pagine riservate agli amministratori
    Route::middleware('ruolo:amministratore')
        ->prefix('amministrazione')
        ->name('amministrazione.')
        ->group(function () {
            Route::get('/', DashboardAmministrazione::class)
                ->name('dashboard')
                ->descrizione('Pagina iniziale dell\'amministratore: elenchi di tornei e giocatori, ultimi account registrati.');

            // Mappa del sito, costruita dai file di route
            Route::get('mappa', MappaAmministrazione::class)
                ->name('mappa')
                ->descrizione('Mappa del sito: tutte le rotte lette dai file di route, con la loro descrizione.');

            // Gestione di tornei e giocatori (elenchi e form sono nella pagina iniziale)
            Route::post('tornei', [TorneiAmministrazione::class, 'store'])
                ->name('tornei.store')
                ->descrizione('Crea un torneo.');
            Route::delete('tornei/{torneo}', [TorneiAmministrazione::class, 'destroy'])
                ->name('tornei.destroy')
                ->descrizione('Elimina un torneo con le sue iscrizioni, coppie, gironi e partite.');

            // Gestione di un torneo: registrazione di giocatori nuovi e rimozione degli iscritti
            Route::get('tornei/{torneo}', [TorneiAmministrazione::class, 'show'])
                ->name('tornei.show')
                ->descrizione('Gestione di un torneo: iscritti, formula, sorteggio e risultati dei gironi.');
            Route::post('tornei/{torneo}/giocatori', [IscrizioniAmministrazione::class, 'store'])
                ->name('tornei.giocatori.store')
                ->descrizione('Registra giocatori nuovi in anagrafica e li iscrive al torneo.');
            Route::delete('tornei/{torneo}/giocatori/{giocatore}', [IscrizioniAmministrazione::class, 'destroy'])
                ->name('tornei.giocatori.destroy')
                ->descrizione('Toglie un giocatore dal torneo: la scheda resta in anagrafica.');
            // Iscrizione di giocatori già presenti in anagrafica
            Route::post('tornei/{torneo}/iscrizioni', [IscrizioniAmministrazione::class, 'iscrivi'])
                ->name('tornei.iscrizioni.store')
                ->descrizione('Iscrive al torneo giocatori già presenti in anagrafica.');

            // Formula del torneo (squadre per girone, sola andata o andata e ritorno)
            Route::patch('tornei/{torneo}/formula', [TorneiAmministrazione::class, 'aggiornaFormula'])
                ->name('tornei.formula.update')
                ->descrizione('Salva la formula: lati, squadre per girone, andata e ritorno, accesso alle finali, formato delle partite.');

            // Sorteggio: coppie → gironi → partite; l'annullamento riapre le iscrizioni
            Route::post('tornei/{torneo}/coppie', [SorteggioAmministrazione::class, 'coppie'])
                ->name('tornei.coppie.store')
                ->descrizione('Sorteggia le coppie, un giocatore di prima fascia con uno di seconda, e chiude le iscrizioni.');
            Route::post('tornei/{torneo}/gironi', [SorteggioAmministrazione::class, 'gironi'])
                ->name('tornei.gironi.store')
                ->descrizione('Divide a sorte le coppie nei gironi.');
            Route::post('tornei/{torneo}/partite', [SorteggioAmministrazione::class, 'partite'])
                ->name('tornei.partite.store')
                ->descrizione('Genera il calendario all\'italiana di ogni girone.');
            Route::delete('tornei/{torneo}/sorteggio', [SorteggioAmministrazione::class, 'annulla'])
                ->name('tornei.sorteggio.destroy')
                ->descrizione('Annulla il sorteggio: cancella coppie, gironi e partite e riapre le iscrizioni.');

            // Risultati delle partite (gironi e finali)
            Route::put('tornei/{torneo}/partite/{partita}/risultato', [RisultatiAmministrazione::class, 'update'])
                ->name('tornei.partite.risultato.update')
                ->descrizione('Registra o corregge il risultato di una partita.');
            Route::delete('tornei/{torneo}/partite/{partita}/risultato', [RisultatiAmministrazione::class, 'destroy'])
                ->name('tornei.partite.risultato.destroy')
                ->descrizione('Cancella il risultato di una partita.');

            Route::get('tornei/{torneo}/partite/{partita}/successiva', [RisultatiAmministrazione::class, 'successiva'])
                ->name('tornei.partite.successiva')
                ->descrizione('La prossima partita senza risultato dopo quella indicata (JSON, per la finestra del risultato).');

            // Classifica dei gironi e finali a eliminazione diretta (tabellone)
            Route::get('tornei/{torneo}/classifica', ClassificaAmministrazione::class)
                ->name('tornei.classifica')
                ->descrizione('Classifica dei gironi, accesso alle finali e migliori giocatori.');
            Route::get('tornei/{torneo}/tabellone', TabelloneAmministrazione::class)
                ->name('tornei.tabellone')
                ->descrizione('Tabellone grafico delle finali a eliminazione diretta, con i risultati.');
            Route::post('tornei/{torneo}/tabellone', [SorteggioAmministrazione::class, 'tabellone'])
                ->name('tornei.tabellone.store')
                ->descrizione('Genera le finali: crea il tabellone e sorteggia le coppie del primo turno.');
            Route::delete('tornei/{torneo}/tabellone', [SorteggioAmministrazione::class, 'annullaTabellone'])
                ->name('tornei.tabellone.destroy')
                ->descrizione('Annulla le finali: elimina partite e coppie del tabellone.');

            Route::post('giocatori', [GiocatoriAmministrazione::class, 'store'])
                ->name('giocatori.store')
                ->descrizione('Crea uno o più giocatori in anagrafica.');
            Route::delete('giocatori/{giocatore}', [GiocatoriAmministrazione::class, 'destroy'])
                ->name('giocatori.destroy')
                ->descrizione('Elimina un giocatore dall\'anagrafica.');
        });

    // Pagine riservate agli utenti
    Route::middleware('ruolo:utente')
        ->prefix('area-utente')
        ->name('area-utente.')
        ->group(function () {
            Route::get('/', DashboardAreaUtente::class)
                ->name('dashboard')
                ->descrizione('Pagina iniziale dell\'utente: profilo e tornei.');
        });
});
