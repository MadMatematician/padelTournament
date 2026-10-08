<?php

namespace App\Http\Controllers\Amministrazione;

use App\Http\Controllers\Controller;
use App\Http\Requests\Amministrazione\IscriviGiocatoriRequest;
use App\Http\Requests\Amministrazione\SalvaGiocatoriRequest;
use App\Models\Giocatore;
use App\Models\Torneo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Giocatori di un torneo: la registrazione crea la scheda in anagrafica (`giocatori`)
 * e la iscrive al torneo; l'iscrizione dall'anagrafica collega schede che esistono già;
 * la rimozione toglie solo l'iscrizione.
 *
 * Dal sorteggio delle coppie in poi le iscrizioni sono chiuse: per cambiarle va annullato il sorteggio.
 */
class IscrizioneController extends Controller
{
    /** Registra uno o più giocatori nuovi (una riga del form per giocatore) e li iscrive al torneo. */
    public function store(SalvaGiocatoriRequest $request, Torneo $torneo): RedirectResponse
    {
        if ($torneo->sorteggiato()) {
            return $this->iscrizioniChiuse($torneo);
        }

        $giocatori = DB::transaction(function () use ($request, $torneo): array {
            $giocatori = array_map(fn (array $dati) => Giocatore::create($dati), $request->giocatori());
            $torneo->giocatori()->attach(array_map(fn (Giocatore $giocatore) => $giocatore->getKey(), $giocatori));

            return $giocatori;
        });

        $messaggio = count($giocatori) === 1
            ? $giocatori[0]->nomeCompleto().' registrato nel torneo "'.$torneo->nome.'".'
            : count($giocatori).' giocatori registrati nel torneo "'.$torneo->nome.'".';

        return redirect()
            ->route('amministrazione.tornei.show', $torneo)
            ->with('status', $messaggio);
    }

    /** Iscrive al torneo uno o più giocatori già presenti in anagrafica. */
    public function iscrivi(IscriviGiocatoriRequest $request, Torneo $torneo): RedirectResponse
    {
        if ($torneo->sorteggiato()) {
            return $this->iscrizioniChiuse($torneo);
        }

        // Chi è già iscritto viene ignorato: ripetere l'operazione non crea doppioni.
        $esito = $torneo->giocatori()->syncWithoutDetaching($request->validated('giocatori'));
        $nuovi = $esito['attached'];

        $messaggio = match (count($nuovi)) {
            0 => 'I giocatori selezionati erano già iscritti al torneo "'.$torneo->nome.'".',
            1 => Giocatore::findOrFail($nuovi[0])->nomeCompleto().' iscritto al torneo "'.$torneo->nome.'".',
            default => count($nuovi).' giocatori iscritti al torneo "'.$torneo->nome.'".',
        };

        return redirect()
            ->route('amministrazione.tornei.show', $torneo)
            ->with('status', $messaggio);
    }

    public function destroy(Torneo $torneo, Giocatore $giocatore): RedirectResponse
    {
        if ($torneo->sorteggiato()) {
            return $this->iscrizioniChiuse($torneo);
        }

        // Toglie solo l'iscrizione: la scheda resta in anagrafica. Chi non è iscritto a questo torneo dà 404.
        abort_if($torneo->giocatori()->detach($giocatore) === 0, 404);

        return redirect()
            ->route('amministrazione.tornei.show', $torneo)
            ->with('status', $giocatore->nomeCompleto().' rimosso dal torneo "'.$torneo->nome.'".');
    }

    private function iscrizioniChiuse(Torneo $torneo): RedirectResponse
    {
        return redirect()
            ->route('amministrazione.tornei.show', $torneo)
            ->with('errore', 'Le coppie sono già state sorteggiate: per cambiare gli iscritti annulla prima il sorteggio.');
    }
}
