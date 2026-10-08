<?php

namespace App\Providers;

use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->aggiungiDescrizioneAlleRotte();
    }

    public function boot(): void
    {
        $this->configuraPasswordSicura();
    }

    /**
     * `->descrizione('…')` nei file di route: una riga su cosa fa la destinazione, mostrata nella mappa del sito
     * (App\Services\Mappa\MappaDelSito). Sta nell'azione della rotta, quindi resta anche con `route:cache`.
     * Registrata in `register()` perché deve esistere prima che i file di route vengano letti.
     */
    private function aggiungiDescrizioneAlleRotte(): void
    {
        Route::macro('descrizione', function (string $testo): Route {
            /** @var Route $this */
            $this->action['descrizione'] = $testo;

            return $this;
        });
    }

    /**
     * Regola "password sicura" usata ovunque con Password::defaults(),
     * costruita dai requisiti di config/sicurezza.php.
     */
    private function configuraPasswordSicura(): void
    {
        Password::defaults(function (): Password {
            $requisiti = config('sicurezza.password');

            $regola = Password::min($requisiti['lunghezza_minima'])->letters();

            if ($requisiti['maiuscole_minuscole']) {
                $regola->mixedCase();
            }
            if ($requisiti['numeri']) {
                $regola->numbers();
            }
            if ($requisiti['simboli']) {
                $regola->symbols();
            }
            if ($requisiti['verifica_violazioni']) {
                $regola->uncompromised();
            }

            return $regola;
        });
    }
}
