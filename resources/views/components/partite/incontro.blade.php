{{--
    Un incontro delle finali nel tabellone grafico. Le coppie sono quelle sorteggiate per quel turno; finché
    il turno precedente non è concluso i posti restano «Da sorteggiare».
    Props: partita (App\Models\Partita con le coppie caricate), torneo (App\Models\Torneo).
--}}
@props(['partita', 'torneo'])
@php
    $vincitrice = match (true) {
        $partita->vincitrice_id === null => null,
        $partita->vincitrice_id === $partita->coppia_a_id => 'a',
        default => 'b',
    };
    // Nel primo turno un posto può restare vuoto: la coppia di fronte passa senza giocare, così com'era nei gironi.
    $bye = $partita->bye();
    $ripetuta = 'Coppia già vista nel torneo: con i giocatori rimasti non c\'era modo di evitarla.';
@endphp
<x-ui.bracket-match :team-a="$partita->coppiaA?->nome()" :team-b="$partita->coppiaB?->nome()"
                    :scores-a="$partita->gameDi('a')" :scores-b="$partita->gameDi('b')" :winner="$vincitrice"
                    :flag-a="$partita->coppiaA?->ripetuta ? $ripetuta : null" :flag-b="$partita->coppiaB?->ripetuta ? $ripetuta : null"
                    empty-a="Da sorteggiare" :empty-b="$bye ? 'Passa il turno' : 'Da sorteggiare'" :id="'partita-'.$partita->id">
    @if ($partita->completa())
        <x-partite.risultato :partita="$partita" :torneo="$torneo" compatto />
    @endif
</x-ui.bracket-match>
