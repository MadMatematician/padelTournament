{{--
    Il risultato di una partita e il pulsante che apre la finestra per inserirlo o correggerlo
    (<x-partite.modale-risultato>, una sola per pagina: gli attributi `data-*` del pulsante le dicono
    di quale partita si tratta; sono le voci di App\Support\SchedaRisultato, le stesse che la finestra riceve
    in JSON quando passa da sola alla partita successiva).
    Props: partita (App\Models\Partita con coppiaA e coppiaB e, nei gironi, il girone caricati), torneo
    (App\Models\Torneo: dà il formato della fase), bloccato (solo lettura), compatto (pulsante con la sola icona,
    per il tabellone).
--}}
@props(['partita', 'torneo', 'bloccato' => false, 'compatto' => false])
@php
    $scheda = \App\Support\SchedaRisultato::di($torneo, $partita);
@endphp
@if ($partita->giocata() && ! $compatto)
    <x-ui.span class="fw-semibold text-nowrap me-2">{{ $partita->punteggio() }}</x-ui.span>
@endif
@if (! $bloccato && $partita->completa())
    <x-ui.button :variant="$partita->giocata() ? 'outline-secondary' : 'outline-primary'" :icon="$partita->giocata() ? 'pencil' : 'plus-lg'"
                 class="btn-sm text-nowrap" data-risultato
                 :data-partita="$scheda['partita']"
                 :data-azione="$scheda['azione']" :data-cancella="$scheda['cancella']" :data-successiva="$scheda['successiva']"
                 :data-coppia-a="$scheda['coppiaA']" :data-coppia-b="$scheda['coppiaB']"
                 :data-gruppo="$scheda['gruppo']" :data-gruppo-nome="$scheda['gruppoNome']" :data-dettaglio="$scheda['dettaglio']"
                 :data-set-massimi="$scheda['setMassimi']" :data-tie-break="$scheda['tieBreak']"
                 :data-formato="$scheda['formato']"
                 :data-set="json_encode($scheda['set'])"
                 :aria-label="($partita->giocata() ? 'Modifica il risultato di ' : 'Inserisci il risultato di ').$scheda['coppiaA'].' – '.$scheda['coppiaB']">{{ $partita->giocata() ? 'Modifica' : 'Risultato' }}</x-ui.button>
@elseif (! $partita->giocata() && ! $compatto)
    <x-ui.span class="text-body-secondary">—</x-ui.span>
@endif
