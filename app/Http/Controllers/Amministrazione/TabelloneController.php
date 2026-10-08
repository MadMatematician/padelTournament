<?php

namespace App\Http\Controllers\Amministrazione;

use App\Enums\Fase;
use App\Http\Controllers\Controller;
use App\Models\Partita;
use App\Models\Torneo;
use App\Services\Finali\CoppieNelleFinali;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\StatoFinali;
use Illuminate\View\View;

/**
 * Tabellone grafico delle finali, con l'inserimento dei risultati. Le coppie di un turno compaiono quando
 * vengono sorteggiate: il primo turno alla generazione, gli altri appena il precedente è concluso.
 */
class TabelloneController extends Controller
{
    public function __invoke(Torneo $torneo, FinaliService $finali): View
    {
        $partite = $torneo->partiteDelleFinali()
            ->with(['coppiaA.primaFascia', 'coppiaA.secondaFascia', 'coppiaB.primaFascia', 'coppiaB.secondaFascia'])
            ->orderBy('posizione')
            ->get();

        // Un elenco di partite per turno, dal primo alla finale.
        $turni = collect(Fase::finali())
            ->mapWithKeys(fn (Fase $fase) => [$fase->value => $partite->filter(fn (Partita $partita) => $partita->fase === $fase)->values()])
            ->filter(fn ($delTurno) => $delTurno->isNotEmpty());

        // Il turno che si sta giocando e, a torneo concluso, chi ha vinto.
        $stato = StatoFinali::daPartite($partite);

        return view('amministrazione.tabellone', [
            'torneo' => $torneo,
            'turni' => $turni,
            'partite' => $partite,
            // Chi ha vinto la finale, cioè il torneo (null finché non è giocata).
            'vincitrice' => $stato->vincitrice,
            'risultati' => $partite->filter->giocata()->count(),
            // Come nascono le coppie di ogni turno: con lo scambio dei compagni oppure con il sorteggio libero.
            'scambio' => $torneo->coppieNelleFinali() === CoppieNelleFinali::Scambio,
            'inCorso' => $stato->inCorso,
            'daGiocare' => $stato->daGiocare,
            'partiteDiGirone' => $torneo->partiteDiGirone()->count(),
            'partiteDaGiocare' => $finali->partiteDaGiocare($torneo),
        ]);
    }
}
