{{--
    Riepilogo dell'accesso alle finali: quante squadre passano, da quale turno si parte, ripescaggi e posti vuoti.
    Props: torneo, accesso (App\Services\Finali\AccessoFinali o null), impedimento (perché non si può calcolare),
    coppie (collezione di App\Models\Coppia per identificativo), provvisorio (mancano risultati dei gironi),
    haFinali (tabellone già generato).
--}}
@props(['torneo', 'accesso' => null, 'impedimento' => null, 'coppie', 'provvisorio' => false, 'haFinali' => false])
@php
    $perGirone = $torneo->qualificatePerGirone();
    $criterio = $torneo->criterioDiRipescaggio();
    $punti = config('tornei.classifica.punti');
    $aSorte = $criterio === \App\Services\Finali\CriterioRipescaggio::Sorteggio;
    $scambio = $torneo->coppieNelleFinali() === \App\Services\Finali\CoppieNelleFinali::Scambio;
    // A tabellone generato le ripescate sono quelle scelte allora; prima, quelle della classifica di adesso.
    $ripescate = $haFinali
        ? $coppie->where('finali', \App\Models\Coppia::RIPESCATA)->sortBy('testa_di_serie')->map->nome()->values()
        : collect($accesso?->ripescate ?? [])->map(fn ($candidata) => $coppie[$candidata->coppia]->nome());
@endphp
<x-ui.card title="Accesso alle finali" icon="trophy" {{ $attributes }}>
    @if ($impedimento)
        <x-ui.alert variant="warning" icon="exclamation-triangle" class="mb-0">{{ $impedimento }}</x-ui.alert>
    @elseif ($accesso)
        <x-ui.dl>
            <x-ui.dl-item label="Dai gironi">
                {{ $perGirone === 1 ? 'La prima' : 'Le prime '.$perGirone }} di ogni girone: {{ count($accesso->dirette) }} squadre.
            </x-ui.dl-item>
            <x-ui.dl-item label="Primo turno">
                {{ \App\Enums\Fase::perSquadre($accesso->dimensione)->etichetta() }} ({{ $accesso->dimensione }} squadre).
            </x-ui.dl-item>
            <x-ui.dl-item label="Ripescaggi">
                @if ($accesso->ripescaggiNecessari() === 0)
                    Non servono.
                @else
                    {{ $accesso->ripescaggiNecessari() === 1 ? 'Un posto' : $accesso->ripescaggiNecessari().' posti' }} da completare
                    ({{ mb_strtolower(config('tornei.finali.ripescaggi.'.$criterio->value)) }}){{ $ripescate->isEmpty() ? '.' : ':' }}
                    @if ($aSorte && ! $haFinali)
                        le ripescate si estraggono quando si genera il tabellone.
                    @else
                        {{-- I nomi delle coppie finiscono già con il punto dell'iniziale ("Rossi M. / Bianchi L."). --}}
                        {{ $ripescate->join(', ', ' e ') }}
                    @endif
                @endif
            </x-ui.dl-item>
            @if ($accesso->bye() > 0)
                <x-ui.dl-item label="Posti vuoti">
                    {{ $accesso->bye() === 1 ? 'Un posto resta vuoto: la migliore qualificata passa' : $accesso->bye().' posti restano vuoti: le '.$accesso->bye().' migliori qualificate passano' }} il primo turno senza giocare.
                </x-ui.dl-item>
            @endif
            <x-ui.dl-item label="Coppie nelle finali">
                @if ($scambio)
                    Cambiano a ogni turno: si sorteggia quali coppie si incontrano e le due coppie si scambiano i compagni,
                    così nessuno gioca due volte con lo stesso compagno.
                @else
                    Cambiano a ogni turno: i giocatori ancora in gara vengono risorteggiati, uno di prima fascia con uno di
                    seconda, senza rimettere insieme chi ha già fatto coppia finché è possibile.
                @endif
                Il primo turno si sorteggia generando le finali, i successivi appena il turno precedente ha tutti i risultati.
            </x-ui.dl-item>
            <x-ui.dl-item label="Classifica" class="mb-0">
                {{ $punti['vittoria'] }} punti per la vittoria, {{ $punti['sconfitta'] }} per la sconfitta. A pari punti: scontri diretti, differenza set, differenza game, game vinti.
            </x-ui.dl-item>
        </x-ui.dl>
        @if ($provvisorio && ! $haFinali)
            <x-ui.text muted small class="mb-0 mt-2">Situazione provvisoria: può cambiare con i risultati che mancano.</x-ui.text>
        @endif
    @endif
</x-ui.card>
