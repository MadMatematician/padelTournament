<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lascia passare solo negli ambienti elencati in `tornei.test.ambienti` (sviluppo e test automatici):
 * altrove le rotte di test rispondono 404, come se non esistessero.
 * Uso nelle rotte: ->middleware('ambiente-test').
 */
class SoloAmbientiDiTest
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment(config('tornei.test.ambienti', [])), 404);

        return $next($request);
    }
}
