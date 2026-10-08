<?php

namespace App\Services\Mappa;

use App\Enums\Ruolo;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;

/**
 * Mappa del sito costruita dalle rotte registrate, cioè da ciò che è scritto nei file di `routes/`:
 * niente elenco tenuto a mano. Ogni rotta porta la descrizione della sua destinazione, dichiarata nel file di
 * route con `->descrizione('…')`; se manca si usa il commento del metodo (o della classe) del controller.
 *
 * Entrano nella mappa le rotte dell'applicazione (controller in `App\`) e quelle che hanno una descrizione;
 * restano fuori quelle aggiunte dal framework (`/up`, `/storage/…`).
 */
class MappaDelSito
{
    private const CONTROLLER = 'App\\Http\\Controllers\\';

    /** Alias dei middleware (bootstrap/app.php e framework) da cui si ricava chi può usare una rotta. */
    private const OSPITI = 'guest';

    private const AUTENTICATI = 'auth';

    private const RUOLO = 'ruolo:';

    private const SOLO_SVILUPPO = 'ambiente-test';

    public function __construct(
        private readonly Router $router,
        private readonly UrlGenerator $url,
    ) {}

    /**
     * Le sezioni della mappa: una per prefisso, la radice per prima e poi in ordine alfabetico.
     *
     * @return Collection<int, SezioneMappa>
     */
    public function sezioni(): Collection
    {
        return collect($this->router->getRoutes()->getRoutes())
            ->filter(fn (Route $rotta) => $this->daMostrare($rotta))
            ->groupBy(fn (Route $rotta) => trim((string) $rotta->getPrefix(), '/'))
            ->sortKeys()
            ->map(fn (Collection $rotte, string $prefisso) => new SezioneMappa(
                $prefisso,
                $rotte->map(fn (Route $rotta) => $this->voce($rotta))->values()->all(),
            ))
            ->values();
    }

    private function daMostrare(Route $rotta): bool
    {
        // Le rotte di test fuori dai loro ambienti rispondono 404: nella mappa non compaiono.
        if ($this->soloSviluppo($rotta) && ! app()->environment(config('tornei.test.ambienti', []))) {
            return false;
        }

        return $rotta->getAction('descrizione') !== null
            || str_starts_with($rotta->getActionName(), 'App\\');
    }

    private function voce(Route $rotta): VoceMappa
    {
        return new VoceMappa(
            metodi: array_values(array_diff($rotta->methods(), ['HEAD'])),
            indirizzo: '/'.ltrim($rotta->uri(), '/'),
            nome: $rotta->getName(),
            descrizione: $rotta->getAction('descrizione') ?? $this->commentoDellaDestinazione($rotta),
            destinazione: Str::after($rotta->getActionName(), self::CONTROLLER),
            accesso: $this->accesso($rotta),
            soloSviluppo: $this->soloSviluppo($rotta),
            link: $this->link($rotta),
        );
    }

    /**
     * Chi può usare la rotta, dai suoi middleware.
     */
    private function accesso(Route $rotta): string
    {
        $middleware = $rotta->gatherMiddleware();

        $ruoli = collect($middleware)
            ->filter(fn (mixed $nome) => is_string($nome) && str_starts_with($nome, self::RUOLO))
            ->flatMap(fn (string $nome) => explode(',', Str::after($nome, self::RUOLO)))
            ->map(fn (string $ruolo) => Ruolo::tryFrom($ruolo)?->etichetta() ?? $ruolo)
            ->unique();

        return match (true) {
            $ruoli->isNotEmpty() => $ruoli->implode(', '),
            in_array(self::AUTENTICATI, $middleware, true) => 'Utenti autenticati',
            in_array(self::OSPITI, $middleware, true) => 'Solo ospiti',
            default => 'Tutti',
        };
    }

    private function soloSviluppo(Route $rotta): bool
    {
        return in_array(self::SOLO_SVILUPPO, $rotta->gatherMiddleware(), true);
    }

    /**
     * Indirizzo da aprire con un clic: solo per le pagine (GET) che non hanno parametri obbligatori.
     */
    private function link(Route $rotta): ?string
    {
        if (! in_array('GET', $rotta->methods(), true)) {
            return null;
        }

        try {
            return $this->url->toRoute($rotta, [], true);
        } catch (UrlGenerationException) {
            return null;
        }
    }

    /**
     * Prima frase del commento del metodo che risponde alla rotta; se il metodo non ne ha, quello della classe.
     */
    private function commentoDellaDestinazione(Route $rotta): ?string
    {
        $azione = $rotta->getActionName();

        if (! str_contains($azione, '\\')) {
            return null;
        }

        [$classe, $metodo] = array_pad(explode('@', $azione, 2), 2, '__invoke');

        try {
            $riflessione = new ReflectionClass($classe);

            return $this->sommario($riflessione->getMethod($metodo)->getDocComment())
                ?? $this->sommario($riflessione->getDocComment());
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * Il primo paragrafo di un commento di documentazione, su una riga; `null` se non c'è testo (solo `@param`, `@return`…).
     */
    private function sommario(string|false $commento): ?string
    {
        if ($commento === false) {
            return null;
        }

        $righe = [];

        foreach (preg_split('/\R/', $commento) ?: [] as $riga) {
            $riga = trim(preg_replace('#^\s*/?\*+/?|\*/\s*$#', '', $riga) ?? '');

            if ($riga === '' || str_starts_with($riga, '@')) {
                if ($righe !== []) {
                    break;
                }

                continue;
            }

            $righe[] = $riga;
        }

        return $righe === [] ? null : implode(' ', $righe);
    }
}
