<x-layouts.app :title="'Tabellone · '.$torneo->nome">
    <x-tornei.intestazione :torneo="$torneo">
        <x-ui.button :href="route('amministrazione.dashboard').'#tornei'" variant="outline-primary" icon="arrow-left">Tutti i tornei</x-ui.button>
    </x-tornei.intestazione>

    <x-tornei.navigazione :torneo="$torneo" attiva="tabellone" />

    @if ($turni->isEmpty())
        <x-ui.card>
            <x-ui.empty icon="diagram-2" title="Tabellone non ancora generato">
                @if ($partiteDiGirone === 0)
                    Le finali si generano dopo i gironi: servono coppie, gironi, partite e i loro risultati (pagina «Gestione»).
                @elseif ($partiteDaGiocare > 0)
                    {{ $partiteDaGiocare === 1 ? 'Manca il risultato di una partita' : 'Mancano i risultati di '.$partiteDaGiocare.' partite' }} dei gironi su {{ $partiteDiGirone }}: si inseriscono dalla pagina «Gestione».
                @else
                    I gironi sono conclusi: puoi generare il tabellone delle finali.
                @endif
            </x-ui.empty>
            @if ($partiteDiGirone > 0 && $partiteDaGiocare === 0)
                <x-slot:footer>
                    <x-ui.form :action="route('amministrazione.tornei.tabellone.store', $torneo)">
                        <x-ui.button type="submit" icon="diagram-2">Genera finali</x-ui.button>
                    </x-ui.form>
                </x-slot:footer>
            @endif
        </x-ui.card>
    @else
        @if ($vincitrice)
            <x-ui.alert variant="success" icon="trophy-fill">
                Torneo concluso: vince la coppia
                <x-ui.span class="fw-bold">{{ $vincitrice->primaFascia->nomeCompleto() }} / {{ $vincitrice->secondaFascia->nomeCompleto() }}</x-ui.span>
            </x-ui.alert>
        @elseif ($inCorso)
            <x-ui.alert variant="info" icon="shuffle">
                {{ $inCorso->etichetta() }}: {{ $daGiocare === 1 ? 'manca un risultato' : 'mancano '.$daGiocare.' risultati' }}.
                @if ($inCorso->successiva())
                    Con l'ultimo risultato del turno si sorteggiano le coppie nuove per «{{ $inCorso->successiva()->etichetta() }}».
                @endif
            </x-ui.alert>
        @endif

        <x-ui.card id="tabellone" title="Finali" icon="diagram-2"
                   :subtitle="$scambio
                       ? 'A ogni turno si sorteggia quali coppie si incontrano: le due coppie si scambiano i compagni e giocano l\'una contro l\'altra. Così nessuno gioca due volte con lo stesso compagno.'
                       : 'A ogni turno i giocatori ancora in gara vengono risorteggiati, uno di prima fascia con uno di seconda: chi ha già giocato insieme non torna in coppia, finché è possibile.'">
            <x-slot:actions>
                <x-ui.form :action="route('amministrazione.tornei.tabellone.destroy', $torneo)" method="DELETE"
                           :data-conferma="'Annullare le finali? Il tabellone verrà eliminato'.($risultati > 0 ? ' con i suoi '.$risultati.' risultati' : '').'; i gironi restano e i loro risultati tornano modificabili.'"
                           data-conferma-azione="Annulla le finali">
                    <x-ui.button type="submit" variant="outline-danger" icon="arrow-counterclockwise" class="btn-sm text-nowrap">Annulla finali</x-ui.button>
                </x-ui.form>
            </x-slot:actions>

            <x-ui.bracket>
                @foreach ($turni as $fase => $delTurno)
                    <x-ui.bracket-round :title="\App\Enums\Fase::from($fase)->etichetta()"
                                        :note="match (true) {
                                            $scambio && $loop->first => 'Qualificate a sorte, compagni scambiati',
                                            $scambio => 'Vincitrici a sorte, compagni scambiati',
                                            $loop->first => 'Giocatori qualificati, coppie a sorte',
                                            default => 'Giocatori rimasti, coppie a sorte',
                                        }">
                        <x-ui.bracket-node :merge="! $loop->last">
                            @foreach ($delTurno as $partita)
                                <x-partite.incontro :partita="$partita" :torneo="$torneo" />
                            @endforeach
                        </x-ui.bracket-node>
                    </x-ui.bracket-round>
                @endforeach
            </x-ui.bracket>
        </x-ui.card>

        <x-partite.modale-risultato :torneo="$torneo" :partite="$partite" />
    @endif
</x-layouts.app>
