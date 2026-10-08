<?php

namespace App\Http\Controllers\Amministrazione;

use App\Enums\Ruolo;
use App\Http\Controllers\Controller;
use App\Models\Giocatore;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const ULTIMI_UTENTI = 10;

    public function __invoke(): View
    {
        $perRuolo = User::query()
            ->selectRaw('ruolo, count(*) as totale')
            ->groupBy('ruolo')
            ->pluck('totale', 'ruolo');

        return view('amministrazione.dashboard', [
            // Elenchi completi: ricerca, ordinamento e paginazione avvengono nel browser (DataTables).
            'tornei' => Torneo::query()->withCount('giocatori')->latest('id')->get(),
            'giocatori' => Giocatore::query()->orderBy('cognome')->orderBy('nome')->get(),
            // Circoli già usati: suggerimenti nel form dei nuovi giocatori.
            'circoli' => Giocatore::circoli(),

            'totaleUtenti' => (int) $perRuolo->sum(),
            'totaleAmministratori' => (int) $perRuolo->get(Ruolo::Amministratore->value, 0),
            'totaleUtentiSemplici' => (int) $perRuolo->get(Ruolo::Utente->value, 0),
            'ultimiUtenti' => User::query()->latest('id')->limit(self::ULTIMI_UTENTI)->get(),
        ]);
    }
}
