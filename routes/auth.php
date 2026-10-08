<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrazioneController;
use Illuminate\Support\Facades\Route;

/*
| Accesso e registrazione. Incluso da routes/web.php.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])
        ->name('login')
        ->descrizione('Pagina di accesso con nome utente e password.');
    Route::post('login', [LoginController::class, 'store'])
        ->name('login.store')
        ->descrizione('Verifica le credenziali e apre la sessione.');

    Route::get('registrazione', [RegistrazioneController::class, 'create'])
        ->name('registrazione')
        ->descrizione('Pagina di creazione di un account.');
    Route::post('registrazione', [RegistrazioneController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('registrazione.store')
        ->descrizione('Crea l\'account, sempre con ruolo utente, e fa accedere.');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout')
    ->descrizione('Chiude la sessione e torna al login.');
