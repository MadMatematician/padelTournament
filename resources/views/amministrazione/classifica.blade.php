<x-layouts.app :title="'Classifica · '.$torneo->nome">
    <x-tornei.intestazione :torneo="$torneo">
        <x-ui.button :href="route('amministrazione.dashboard').'#tornei'" variant="outline-primary" icon="arrow-left">Tutti i tornei</x-ui.button>
    </x-tornei.intestazione>

    <x-tornei.navigazione :torneo="$torneo" attiva="classifica" />

    @if ($gironi->isEmpty())
        <x-ui.card>
            <x-ui.empty icon="list-ol" title="Classifica non ancora disponibile">
                Servono coppie, gironi e partite: si generano dalla pagina «Gestione».
            </x-ui.empty>
        </x-ui.card>
    @else
        @if ($partiteDiGirone === 0)
            <x-ui.alert variant="info" icon="info-circle">Le partite dei gironi non sono ancora state generate.</x-ui.alert>
        @elseif ($partiteDaGiocare > 0)
            <x-ui.alert variant="info" icon="hourglass-split">
                Classifica provvisoria: {{ $partiteDaGiocare === 1 ? 'manca il risultato di una partita' : 'mancano i risultati di '.$partiteDaGiocare.' partite' }} su {{ $partiteDiGirone }}.
            </x-ui.alert>
        @else
            <x-ui.alert variant="success" icon="check-circle">
                Gironi conclusi: tutte le {{ $partiteDiGirone }} partite hanno un risultato.
                {{ $haFinali ? 'Le finali sono nel «Tabellone».' : 'Il tabellone delle finali si genera dalla pagina «Gestione».' }}
            </x-ui.alert>
        @endif

        @if ($migliori !== [])
            <x-giocatori.migliori :categorie="$migliori" :provvisorio="! $concluso" class="mb-4" />
        @endif

        <x-ui.row class="mb-4">
            @foreach ($gironi as $girone)
                <x-ui.col lg="6">
                    <x-gironi.classifica :girone="$girone" :righe="$classifiche[$girone->id]" :coppie="$coppie" :stati="$stati" />
                </x-ui.col>
            @endforeach
        </x-ui.row>

        <x-tornei.accesso-finali :torneo="$torneo" :accesso="$accesso" :impedimento="$impedimento" :coppie="$coppie"
                                 :provvisorio="$partiteDaGiocare > 0 || $partiteDiGirone === 0" :ha-finali="$haFinali" class="mb-4" />

        <x-ui.card id="squadre" title="Tutte le squadre" icon="people" flush
                   subtitle="Punteggi dei gironi e accesso alle finali. Le colonne si possono ordinare.">
            <x-ui.datatable :headers="['Coppia', 'Girone', 'Pos.', 'G', 'V', 'P', 'Set', 'Diff. set', 'Game', 'Diff. game', 'Punti', 'Finali']"
                            :order="[[10, 'desc'], [7, 'desc'], [9, 'desc']]" :page-length="25" :entries="['squadra', 'squadre']"
                            empty="Nessuna squadra.">
                @foreach ($gironi as $girone)
                    @foreach ($classifiche[$girone->id] as $riga)
                        <x-ui.tr>
                            <x-ui.td class="fw-semibold text-nowrap">{{ $coppie[$riga->coppia]->nome() }}</x-ui.td>
                            <x-ui.td class="text-nowrap" :data-order="$girone->posizione">{{ $girone->nome }}</x-ui.td>
                            <x-ui.td>{{ $riga->posizione }}</x-ui.td>
                            <x-ui.td>{{ $riga->giocate }}</x-ui.td>
                            <x-ui.td>{{ $riga->vinte }}</x-ui.td>
                            <x-ui.td>{{ $riga->perse }}</x-ui.td>
                            <x-ui.td class="text-nowrap" :data-order="$riga->setVinti">{{ $riga->setVinti }}-{{ $riga->setPersi }}</x-ui.td>
                            <x-ui.td>{{ $riga->differenzaSet() }}</x-ui.td>
                            <x-ui.td class="text-nowrap" :data-order="$riga->gameVinti">{{ $riga->gameVinti }}-{{ $riga->gamePersi }}</x-ui.td>
                            <x-ui.td>{{ $riga->differenzaGame() }}</x-ui.td>
                            <x-ui.td class="fw-bold">{{ $riga->punti }}</x-ui.td>
                            <x-ui.td class="text-nowrap" :muted="! isset($stati[$riga->coppia])">{{ ['diretta' => 'Qualificata', 'ripescata' => 'Ripescata', 'esclusa' => 'Non accede (prestito)'][$stati[$riga->coppia] ?? ''] ?? '—' }}</x-ui.td>
                        </x-ui.tr>
                    @endforeach
                @endforeach
            </x-ui.datatable>
        </x-ui.card>

        @if ($haFinali)
            <x-ui.card id="giocatori-finali" title="Giocatori nelle finali" icon="person-lines-fill" flush class="mt-4"
                       subtitle="Nelle finali le coppie cambiano a ogni turno: a passare il turno sono i giocatori. Per ognuno, fin dove è arrivato e con chi ha giocato.">
                <x-ui.datatable :headers="['Giocatore', 'Fascia', 'Qualificato con', 'Turno raggiunto', 'Nelle finali']"
                                :order="[[3, 'desc'], [0, 'asc']]" :page-length="25" :entries="['giocatore', 'giocatori']"
                                empty="Nessun giocatore.">
                    @foreach ($cammini as $cammino)
                        <x-ui.tr>
                            <x-ui.td class="fw-semibold text-nowrap" :data-order="$cammino['giocatore']->cognome.' '.$cammino['giocatore']->nome">{{ $cammino['giocatore']->nomeCompleto() }}</x-ui.td>
                            <x-ui.td :data-order="$cammino['giocatore']->fascia"><x-giocatori.badge-fascia :giocatore="$cammino['giocatore']" /></x-ui.td>
                            <x-ui.td class="text-nowrap">{{ $cammino['qualificata']?->nome() ?? '—' }}</x-ui.td>
                            <x-ui.td class="text-nowrap fw-semibold" :data-order="$cammino['ordine']">{{ $cammino['turno'] }}</x-ui.td>
                            <x-ui.td class="small">{{ implode(' · ', $cammino['compagni']) }}</x-ui.td>
                        </x-ui.tr>
                    @endforeach
                </x-ui.datatable>
            </x-ui.card>
        @endif
    @endif
</x-layouts.app>
