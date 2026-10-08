<?php

namespace App\Http\Controllers\Amministrazione;

use App\Exceptions\SorteggioImpossibileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Amministrazione\GeneraCoppieRequest;
use App\Models\Torneo;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\TorneoSocialeService;
use Illuminate\Http\RedirectResponse;

/**
 * I passi del sorteggio di un torneo: coppie, gironi, partite e, giocati i gironi, il tabellone delle finali.
 * Ogni passo sostituisce il risultato precedente e cancella quelli che ne dipendono.
 */
class SorteggioController extends Controller
{
    public function __construct(
        private readonly TorneoSocialeService $sorteggio,
        private readonly FinaliService $finali,
    ) {}

    public function coppie(GeneraCoppieRequest $request, Torneo $torneo): RedirectResponse
    {
        $squilibrio = $this->sorteggio->squilibrio($torneo);

        // Fasce non pari: come completare le coppie lo decide l'amministratore (finestra del prestito).
        if ($squilibrio?->prestitoPossibile() && ! $request->prestitoDeciso()) {
            return $this->allaPagina($torneo)->withErrors(
                ['prestito' => 'Le fasce non sono pari: scegli come completare le coppie.'],
                'sorteggio',
            );
        }

        try {
            $coppie = $this->sorteggio->generaCoppie($torneo, $squilibrio === null ? null : $request->prestiti());
        } catch (SorteggioImpossibileException $errore) {
            // Un prestito indicato male si corregge nella finestra del prestito; il resto va in cima alla pagina.
            return $squilibrio?->prestitoPossibile()
                ? $this->allaPagina($torneo)->withErrors(['giocatori_prestito' => $errore->getMessage()], 'sorteggio')->withInput()
                : $this->allaPagina($torneo)->with('errore', $errore->getMessage());
        }

        $inPrestito = $coppie->whereNotNull('prestito_id')->count();

        return $this->allaPagina($torneo)->with('status', $coppie->count().' coppie generate'.match ($inPrestito) {
            0 => '.',
            1 => ', una con un giocatore in prestito.',
            default => ', '.$inPrestito.' con un giocatore in prestito.',
        });
    }

    public function gironi(Torneo $torneo): RedirectResponse
    {
        try {
            $gironi = $this->sorteggio->generaGironi($torneo);
        } catch (SorteggioImpossibileException $errore) {
            return $this->allaPagina($torneo)->with('errore', $errore->getMessage());
        }

        return $this->allaPagina($torneo)->with(
            'status',
            $gironi->count() === 1 ? 'Generato un girone.' : 'Generati '.$gironi->count().' gironi.',
        );
    }

    public function partite(Torneo $torneo): RedirectResponse
    {
        try {
            $partite = $this->sorteggio->generaPartite($torneo);
        } catch (SorteggioImpossibileException $errore) {
            return $this->allaPagina($torneo)->with('errore', $errore->getMessage());
        }

        return $this->allaPagina($torneo)->with(
            'status',
            $partite.' partite generate ('.mb_strtolower($torneo->etichettaFormula()).').',
        );
    }

    /** Genera il tabellone delle finali dalle classifiche dei gironi. */
    public function tabellone(Torneo $torneo): RedirectResponse
    {
        try {
            $prima = $this->finali->generaTabellone($torneo);
        } catch (SorteggioImpossibileException $errore) {
            return $this->allaPagina($torneo)->with('errore', $errore->getMessage());
        }

        return redirect()
            ->route('amministrazione.tornei.tabellone', $torneo)
            ->with('status', 'Tabellone delle finali generato: si parte da '.mb_strtolower($prima->etichetta()).'.');
    }

    /** Cancella il tabellone delle finali: i risultati dei gironi tornano modificabili. */
    public function annullaTabellone(Torneo $torneo): RedirectResponse
    {
        $this->finali->annullaTabellone($torneo);

        return $this->allaPagina($torneo)->with('status', 'Finali annullate: i risultati dei gironi si possono di nuovo modificare.');
    }

    /** Cancella coppie, gironi e partite: le iscrizioni si riaprono. */
    public function annulla(Torneo $torneo): RedirectResponse
    {
        $this->sorteggio->annulla($torneo);

        return redirect()
            ->route('amministrazione.tornei.show', $torneo)
            ->with('status', 'Sorteggio annullato: le iscrizioni sono di nuovo aperte.');
    }

    private function allaPagina(Torneo $torneo): RedirectResponse
    {
        return redirect()->to(route('amministrazione.tornei.show', $torneo).'#sorteggio');
    }
}
