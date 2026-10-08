<?php

namespace App\Support;

use App\Enums\Fase;
use App\Models\Partita;
use App\Models\Torneo;

/**
 * I dati con cui la finestra del risultato (<x-partite.modale-risultato>) mostra una partita: coppie, girone o
 * turno, formato della fase, set già inseriti e indirizzi per salvare, cancellare e passare alla successiva.
 *
 * Hanno una sola forma per le due strade da cui arrivano a torneo.js: gli attributi `data-*` del pulsante
 * «Risultato» (<x-partite.risultato>) e la risposta JSON del salvataggio. Le chiavi sono quelle che jQuery
 * ricava dagli attributi (`data-coppia-a` → `coppiaA`): chi ne aggiunge una la aggiunge anche al pulsante.
 */
final class SchedaRisultato
{
    /**
     * @param  Partita  $partita  con coppiaA e coppiaB (e i loro giocatori) e, nei gironi, il girone caricati
     * @return array<string, mixed>
     */
    public static function di(Torneo $torneo, Partita $partita): array
    {
        $formato = $torneo->formato($partita->fase);

        return [
            'partita' => $partita->id,
            'azione' => route('amministrazione.tornei.partite.risultato.update', [$torneo, $partita]),
            'cancella' => $partita->giocata() ? route('amministrazione.tornei.partite.risultato.destroy', [$torneo, $partita]) : '',
            'successiva' => route('amministrazione.tornei.partite.successiva', [$torneo, $partita]),
            'coppiaA' => (string) $partita->coppiaA?->nome(),
            'coppiaB' => (string) $partita->coppiaB?->nome(),
            'gruppo' => $partita->chiaveGruppo(),
            'gruppoNome' => $partita->nomeGruppo(),
            'dettaglio' => self::dettaglio($partita),
            'setMassimi' => $formato->set,
            'tieBreak' => $formato->tieBreak ? 1 : 0,
            'formato' => $formato->descrizione(),
            'set' => $partita->risultato ?? [],
        ];
    }

    /** Dove sta la partita dentro il suo gruppo: la giornata del girone (nelle finali basta il turno). */
    private static function dettaglio(Partita $partita): string
    {
        if ($partita->fase !== Fase::Gironi) {
            return '';
        }

        return $partita->giornata.'ª giornata'.($partita->ritorno ? ' · ritorno' : '');
    }
}
