<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consente l'accesso solo agli utenti con uno dei ruoli indicati.
 * Uso nelle rotte: ->middleware('ruolo:amministratore') oppure 'ruolo:amministratore,utente'.
 */
class VerificaRuolo
{
    public function handle(Request $request, Closure $next, string ...$ruoli): Response
    {
        $utente = $request->user();

        abort_if($utente === null, 401);

        abort_unless(
            in_array($utente->ruolo->value, $ruoli, true),
            403,
            'Non hai i permessi per accedere a questa pagina.'
        );

        return $next($request);
    }
}
