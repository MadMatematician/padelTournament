<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Http\Requests\Test\GeneraTorneoDiTestRequest;
use App\Models\Torneo;
use Illuminate\Http\RedirectResponse;

/**
 * Rotta di test del sito: `/test/{giocatori?}` crea con la factory un torneo che ha già tutti i giocatori
 * iscritti e apre la sua pagina di gestione, pronta per il sorteggio.
 */
class TorneoDiTestController extends Controller
{
    public function __invoke(GeneraTorneoDiTestRequest $request): RedirectResponse
    {
        $torneo = Torneo::factory()->conGiocatori($request->numeroGiocatori())->create();

        return redirect()
            ->route('amministrazione.tornei.show', $torneo)
            ->with('status', 'Torneo di test "'.$torneo->nome.'" creato con '.$request->numeroGiocatori().' giocatori iscritti.');
    }
}
