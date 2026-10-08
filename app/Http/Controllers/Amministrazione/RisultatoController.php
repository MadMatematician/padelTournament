<?php

namespace App\Http\Controllers\Amministrazione;

use App\Enums\Fase;
use App\Exceptions\RisultatoNonConsentitoException;
use App\Exceptions\SorteggioImpossibileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Amministrazione\RegistraRisultatoRequest;
use App\Models\Partita;
use App\Models\Torneo;
use App\Services\Tornei\CodaRisultati;
use App\Services\Tornei\RisultatiService;
use App\Support\SchedaRisultato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

/**
 * Risultati delle partite, dei gironi e delle finali. La partita deve essere del torneo indicato
 * nell'indirizzo, altrimenti 404 (per il salvataggio lo verifica già RegistraRisultatoRequest).
 */
class RisultatoController extends Controller
{
    public function __construct(
        private readonly RisultatiService $risultati,
        private readonly CodaRisultati $coda,
    ) {}

    /**
     * Dal browser il salvataggio arriva in sottofondo (torneo.js) e la risposta è JSON: la finestra del risultato
     * resta aperta e passa alla prossima partita senza risultato. Senza JavaScript vale il giro classico, con il
     * ritorno alla pagina.
     */
    public function update(RegistraRisultatoRequest $request, Torneo $torneo, Partita $partita): RedirectResponse|JsonResponse
    {
        try {
            $sorteggiato = $this->risultati->registra($partita, $request->esito());
        } catch (RisultatoNonConsentitoException|SorteggioImpossibileException $errore) {
            return $request->expectsJson()
                ? response()->json(['message' => $errore->getMessage()], 422)
                : $this->allaPagina($torneo, $partita)->with('errore', $errore->getMessage());
        }

        $partita->load(['coppiaA.primaFascia', 'coppiaA.secondaFascia', 'coppiaB.primaFascia', 'coppiaB.secondaFascia']);

        $esito = 'Risultato registrato: '.$partita->coppiaA->nome().' – '.$partita->coppiaB->nome().' '.$partita->punteggio().'.';
        // Nelle finali, con l'ultimo risultato di un turno si sorteggiano le coppie del successivo.
        $sorteggio = $sorteggiato === null ? '' : ' Turno concluso: sorteggiate le nuove coppie per «'.$sorteggiato->etichetta().'».';

        if (! $request->expectsJson()) {
            return $this->allaPagina($torneo, $partita)->with('status', $esito.$sorteggio);
        }

        $coda = $this->coda->dopo($partita, primaIlGruppo: true);

        if ($coda->isEmpty()) {
            // Non c'è altro da inserire: la finestra si chiude e la pagina mostra il passo successivo.
            $nonGiocate = $partita->fase === Fase::Gironi
                ? ' Tutte le partite dei gironi hanno il risultato.'
                : '';
            session()->flash('status', $esito.$nonGiocate);

            return response()->json([
                'esito' => $esito,
                'successiva' => null,
                'vai' => $partita->fase === Fase::Gironi
                    ? route('amministrazione.tornei.show', $torneo).'#sorteggio'
                    : route('amministrazione.tornei.tabellone', $torneo),
            ]);
        }

        return response()->json([
            'esito' => $esito,
            'cambio' => $this->cambio($partita, $coda, restaDaGiocare: false),
            'successiva' => SchedaRisultato::di($torneo, $coda->first()),
        ]);
    }

    /**
     * La prossima partita senza risultato dopo quella indicata, per il pulsante «Successiva» della finestra
     * del risultato: l'ordine è quello di CodaRisultati e, arrivati in fondo, ricomincia da capo.
     */
    public function successiva(Torneo $torneo, Partita $partita): JsonResponse
    {
        abort_unless($partita->torneo_id === $torneo->id, 404);

        $coda = $this->coda->dopo($partita);

        if ($coda->isEmpty()) {
            return response()->json([
                'successiva' => null,
                'fine' => 'Non ci sono altre partite senza risultato.',
            ]);
        }

        return response()->json([
            'cambio' => $this->cambio($partita, $coda, restaDaGiocare: $partita->completa() && ! $partita->giocata()),
            'successiva' => SchedaRisultato::di($torneo, $coda->first()),
        ]);
    }

    public function destroy(Torneo $torneo, Partita $partita): RedirectResponse
    {
        abort_unless($partita->torneo_id === $torneo->id, 404);

        try {
            $this->risultati->cancella($partita);
        } catch (RisultatoNonConsentitoException $errore) {
            return $this->allaPagina($torneo, $partita)->with('errore', $errore->getMessage());
        }

        return $this->allaPagina($torneo, $partita)->with('status', 'Risultato cancellato.');
    }

    /**
     * L'avviso per quando la prossima partita è di un altro girone (o di un altro turno delle finali): dice
     * dove si è arrivati e in che stato si lascia il gruppo di partenza. `null` se il gruppo non cambia.
     *
     * @param  Collection<int, Partita>  $coda  le partite senza risultato dopo $da: la prima è quella a cui si passa
     * @param  bool  $restaDaGiocare  la partita di partenza è stata saltata senza risultato
     */
    private function cambio(Partita $da, Collection $coda, bool $restaDaGiocare): ?string
    {
        $a = $coda->first();

        if ($a->chiaveGruppo() === $da->chiaveGruppo()) {
            return null;
        }

        $da->loadMissing('girone');
        $restano = $coda->filter(fn (Partita $altra) => $altra->chiaveGruppo() === $da->chiaveGruppo())->count()
            + ($restaDaGiocare ? 1 : 0);
        $ora = $a->fase === Fase::Gironi ? 'Ora sei nel '.$a->nomeGruppo().'.' : 'Ora si gioca: '.$a->nomeGruppo().'.';

        return $da->nomeGruppo().': '.match (true) {
            $restano === 0 && $da->fase === Fase::Gironi => 'tutti i risultati inseriti.',
            $restano === 0 => 'turno concluso, coppie nuove sorteggiate.',
            $restano === 1 => 'resta una partita senza risultato.',
            default => 'restano '.$restano.' partite senza risultato.',
        }.' '.$ora;
    }

    /** I risultati dei gironi si inseriscono dalla pagina di gestione, quelli delle finali dal tabellone. */
    private function allaPagina(Torneo $torneo, Partita $partita): RedirectResponse
    {
        return $partita->fase === Fase::Gironi
            ? redirect()->to(route('amministrazione.tornei.show', $torneo).'#girone-'.$partita->girone_id)
            : redirect()->route('amministrazione.tornei.tabellone', $torneo);
    }
}
