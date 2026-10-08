<?php

namespace App\Services\Tornei;

use App\Enums\Fase;
use App\Exceptions\RisultatoNonConsentitoException;
use App\Exceptions\SorteggioImpossibileException;
use App\Models\Partita;
use App\Services\Punteggio\EsitoPartita;
use Illuminate\Support\Facades\DB;

/**
 * Registra e cancella i risultati delle partite.
 *
 * Nelle finali le coppie si risorteggiano a ogni turno: quando un turno è concluso (tutte le partite hanno
 * una vincitrice) i giocatori che lo hanno superato vengono sorteggiati in coppie nuove per il turno
 * successivo. Di conseguenza:
 * - se cambia chi ha vinto una partita, o il suo risultato viene cancellato, il sorteggio del turno successivo
 *   non vale più e viene tolto (e rifatto subito, se il turno è di nuovo concluso);
 * - questo non è più possibile quando nel turno successivo c'è già un risultato;
 * - correggere il punteggio lasciando la stessa vincitrice non tocca il sorteggio.
 *
 * I risultati dei gironi non si toccano più dopo che il tabellone delle finali è stato generato.
 */
class RisultatiService
{
    public function __construct(
        private readonly FinaliService $finali,
    ) {}

    /**
     * @return Fase|null il turno delle finali le cui coppie sono state sorteggiate con questo risultato
     *
     * @throws RisultatoNonConsentitoException
     * @throws SorteggioImpossibileException
     */
    public function registra(Partita $partita, EsitoPartita $esito): ?Fase
    {
        if (! $partita->completa()) {
            throw new RisultatoNonConsentitoException('Le coppie di questa partita non sono ancora note.');
        }

        $this->verificaGironiAperti($partita);

        $torneo = $partita->torneo;
        $vincitrice = $esito->vincitore === 'a' ? $partita->coppia_a_id : $partita->coppia_b_id;
        $cambiaVincitrice = $partita->vincitrice_id !== $vincitrice;
        $successivo = $partita->fase->successiva();

        if ($successivo !== null && $cambiaVincitrice && $this->finali->turnoGiocato($torneo, $successivo)) {
            throw new RisultatoNonConsentitoException(
                'Le coppie del turno successivo sono state sorteggiate con un\'altra vincitrice e hanno già dei risultati: '
                .'per cambiarla cancella prima quelli.'
            );
        }

        return DB::transaction(function () use ($partita, $esito, $torneo, $vincitrice, $cambiaVincitrice, $successivo): ?Fase {
            $partita->update([
                'risultato' => $esito->set,
                'set_a' => $esito->setA,
                'set_b' => $esito->setB,
                'game_a' => $esito->gameA,
                'game_b' => $esito->gameB,
                'vincitrice_id' => $vincitrice,
            ]);

            if ($successivo === null || ! $cambiaVincitrice) {
                return null;
            }

            $this->finali->annullaTurno($torneo, $successivo);

            return $this->finali->turnoConcluso($torneo, $partita->fase)
                ? $this->finali->sorteggiaDopo($torneo, $partita->fase)
                : null;
        });
    }

    /**
     * @throws RisultatoNonConsentitoException
     */
    public function cancella(Partita $partita): void
    {
        if (! $partita->giocata()) {
            return;
        }

        $this->verificaGironiAperti($partita);

        $torneo = $partita->torneo;
        $successivo = $partita->fase->successiva();

        if ($successivo !== null && $this->finali->turnoGiocato($torneo, $successivo)) {
            throw new RisultatoNonConsentitoException(
                'Il turno successivo ha già dei risultati: cancella prima quelli.'
            );
        }

        DB::transaction(function () use ($partita, $torneo, $successivo): void {
            $partita->update([
                'risultato' => null,
                'set_a' => null,
                'set_b' => null,
                'game_a' => null,
                'game_b' => null,
                'vincitrice_id' => null,
            ]);

            if ($successivo !== null) {
                $this->finali->annullaTurno($torneo, $successivo);
            }
        });
    }

    /**
     * @throws RisultatoNonConsentitoException
     */
    private function verificaGironiAperti(Partita $partita): void
    {
        if ($partita->fase === Fase::Gironi && $partita->torneo->haFinali()) {
            throw new RisultatoNonConsentitoException(
                'Il tabellone delle finali è già stato generato dalle classifiche dei gironi: '
                .'per cambiare un risultato dei gironi annulla prima le finali.'
            );
        }
    }
}
