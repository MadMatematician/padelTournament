<?php

namespace App\Http\Controllers\Amministrazione;

use App\Http\Controllers\Controller;
use App\Http\Requests\Amministrazione\AggiornaFormulaRequest;
use App\Http\Requests\Amministrazione\SalvaTorneoRequest;
use App\Models\Giocatore;
use App\Models\Torneo;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\TorneoSocialeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TorneoController extends Controller
{
    public function store(SalvaTorneoRequest $request): RedirectResponse
    {
        $torneo = Torneo::create($request->validated());

        return redirect()
            ->route('amministrazione.dashboard')
            ->with('status', 'Torneo "'.$torneo->nome.'" creato.');
    }

    /**
     * Pagina di gestione del torneo: giocatori iscritti (registrazione di nuovi, iscrizione dall'anagrafica),
     * formula e sorteggio (coppie, gironi, partite).
     */
    public function show(Torneo $torneo, TorneoSocialeService $sorteggio, FinaliService $finali): View
    {
        // Elenco completo: ricerca, ordinamento e paginazione avvengono nel browser (DataTables).
        $giocatori = $torneo->giocatori()->orderBy('cognome')->orderBy('nome')->get();

        // Schede dell'anagrafica non ancora iscritte a questo torneo (finestra "Iscrivi dall'anagrafica").
        $disponibili = Giocatore::query()
            ->whereDoesntHave('tornei', fn (Builder $tornei) => $tornei->whereKey($torneo->getKey()))
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get();

        return view('amministrazione.torneo', [
            'torneo' => $torneo,
            'giocatori' => $giocatori,
            'disponibili' => $disponibili,
            // Circoli già usati in anagrafica: suggerimenti nel form dei nuovi giocatori.
            'circoli' => Giocatore::circoli(),
            'iscrittiPerFascia' => $giocatori->countBy('fascia'),

            // Sorteggio
            'coppie' => $torneo->coppie()->with(['primaFascia', 'secondaFascia', 'girone'])->orderBy('id')->get(),
            'gironi' => $torneo->gironi()
                ->with(['coppie' => fn ($coppie) => $coppie->orderBy('id'), 'coppie.primaFascia', 'coppie.secondaFascia'])
                ->with(['partite' => fn ($partite) => $partite->orderBy('giornata')->orderBy('id')])
                ->get(),
            // Risultati dei gironi che mancano e stato delle finali (passo 4 del sorteggio).
            'partiteDaGiocare' => $finali->partiteDaGiocare($torneo),
            'haFinali' => $haFinali = $torneo->haFinali(),
            // A che punto sono le finali (scheda in cima alla pagina, con il collegamento al tabellone).
            'statoFinali' => $haFinali ? $finali->stato($torneo) : null,
            // Fasce non pari: serve un giocatore in prestito (`null` se sono pari).
            'squilibrio' => $sorteggio->squilibrio($torneo),
        ]);
    }

    /** Salva la formula: vale per i gironi e le partite generati da qui in poi. */
    public function aggiornaFormula(AggiornaFormulaRequest $request, Torneo $torneo): RedirectResponse
    {
        $torneo->update($request->formula());

        return redirect()
            ->to(route('amministrazione.tornei.show', $torneo).'#sorteggio')
            ->with('status', 'Formula salvata: fino a '.$torneo->squadre_per_girone.' squadre per girone, '
                .mb_strtolower($torneo->etichettaFormula()).', '
                .($torneo->qualificate_per_girone === 1 ? 'una squadra per girone' : $torneo->qualificate_per_girone.' squadre per girone')
                .' alle finali.');
    }

    public function destroy(Torneo $torneo): RedirectResponse
    {
        $torneo->delete();

        return redirect()
            ->route('amministrazione.dashboard')
            ->with('status', 'Torneo "'.$torneo->nome.'" eliminato.');
    }
}
