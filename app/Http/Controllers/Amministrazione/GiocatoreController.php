<?php

namespace App\Http\Controllers\Amministrazione;

use App\Http\Controllers\Controller;
use App\Http\Requests\Amministrazione\SalvaGiocatoriRequest;
use App\Models\Giocatore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class GiocatoreController extends Controller
{
    /** Crea in anagrafica uno o più giocatori (una riga del form per giocatore). */
    public function store(SalvaGiocatoriRequest $request): RedirectResponse
    {
        $giocatori = DB::transaction(
            fn () => array_map(fn (array $dati) => Giocatore::create($dati), $request->giocatori())
        );

        $messaggio = count($giocatori) === 1
            ? 'Giocatore '.$giocatori[0]->nomeCompleto().' creato.'
            : count($giocatori).' giocatori creati.';

        return redirect()
            ->route('amministrazione.dashboard')
            ->with('status', $messaggio);
    }

    public function destroy(Giocatore $giocatore): RedirectResponse
    {
        // Le coppie sorteggiate fanno riferimento alla scheda: prima va annullato il sorteggio di quel torneo.
        if ($giocatore->inCoppia()) {
            return redirect()
                ->route('amministrazione.dashboard')
                ->with('errore', $giocatore->nomeCompleto().' fa parte delle coppie sorteggiate di un torneo: annulla prima quel sorteggio.');
        }

        $giocatore->delete();

        return redirect()
            ->route('amministrazione.dashboard')
            ->with('status', 'Giocatore '.$giocatore->nomeCompleto().' eliminato.');
    }
}
