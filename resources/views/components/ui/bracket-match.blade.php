{{--
    Un incontro di <x-ui.bracket>: due righe, una per squadra, con nome e game set per set.
    Props: teamA, teamB (nomi; null = posto non ancora assegnato), seedA, seedB (teste di serie, facoltative),
    scoresA, scoresB (game per set), winner ('a', 'b' o null), emptyA, emptyB (testo dei posti vuoti),
    flagA, flagB (avviso sulla squadra: icona accanto al nome, il testo si legge passandoci sopra).
    Slot: azione sotto l'incontro (es. il pulsante del risultato).
--}}
@props([
    'teamA' => null, 'teamB' => null, 'seedA' => null, 'seedB' => null,
    'scoresA' => [], 'scoresB' => [], 'winner' => null, 'emptyA' => 'Da definire', 'emptyB' => 'Da definire',
    'flagA' => null, 'flagB' => null,
])
@php
    $squadre = [
        ['lato' => 'a', 'nome' => $teamA, 'testa' => $seedA, 'game' => $scoresA, 'vuoto' => $emptyA, 'avviso' => $flagA],
        ['lato' => 'b', 'nome' => $teamB, 'testa' => $seedB, 'game' => $scoresB, 'vuoto' => $emptyB, 'avviso' => $flagB],
    ];
    $conTeste = $seedA !== null || $seedB !== null;
@endphp
<div class="tabellone-posto">
    <div {{ $attributes->class(['tabellone-partita']) }}>
        @foreach ($squadre as $squadra)
            <div @class(['tabellone-squadra', 'vincente' => $winner === $squadra['lato'], 'vuota' => $squadra['nome'] === null])>
                @if ($conTeste)
                    <span class="tabellone-testa" @if ($squadra['testa']) title="Testa di serie n. {{ $squadra['testa'] }}" @endif>{{ $squadra['testa'] }}</span>
                @endif
                <span class="tabellone-nome">{{ $squadra['nome'] ?? $squadra['vuoto'] }}</span>
                @if ($squadra['avviso'])
                    <span class="tabellone-avviso" title="{{ $squadra['avviso'] }}"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span class="visually-hidden">{{ $squadra['avviso'] }}</span></span>
                @endif
                @if ($winner === $squadra['lato'])
                    <span class="visually-hidden">(vincitrice)</span>
                @endif
                @foreach ($squadra['game'] as $game)
                    <span class="tabellone-game">{{ $game }}</span>
                @endforeach
            </div>
        @endforeach
        @if ($slot->hasActualContent())
            <div class="tabellone-azione">{{ $slot }}</div>
        @endif
    </div>
</div>
