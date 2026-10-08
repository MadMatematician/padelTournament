{{--
    Migliori giocatori per categoria (fascia d'età, genere…): un pulsante per categoria, che apre una finestra
    con i primi di ogni valore della categoria.
    Props: categorie (elenco di ciò che restituisce App\Services\Tornei\MiglioriGiocatoriService::migliori():
    chiave, nome, icona, quanti, disponibile, senza, gruppi), provvisorio (il torneo non è concluso).
--}}
@props(['categorie', 'provvisorio' => false])
<x-ui.card id="migliori" title="Migliori giocatori" icon="award" flush {{ $attributes }}
           subtitle="I primi di ogni categoria. Conta fin dove si arriva nelle finali, poi le partite giocate: vittorie, differenza set e differenza game, in media per partita.">
    <x-slot:actions>
        @foreach ($categorie as $categoria)
            <x-ui.button variant="outline-primary" :icon="$categoria['icona']" class="text-nowrap"
                         data-bs-toggle="modal" :data-bs-target="'#migliori-'.str_replace('_', '-', $categoria['chiave'])">Migliori per {{ $categoria['nome'] }}</x-ui.button>
        @endforeach
    </x-slot:actions>
</x-ui.card>

@foreach ($categorie as $categoria)
    <x-ui.modal :id="'migliori-'.str_replace('_', '-', $categoria['chiave'])" :title="'Migliori per '.$categoria['nome']"
                :icon="$categoria['icona']" size="lg" scrollable>
        @if (! $categoria['disponibile'])
            <x-ui.empty :icon="$categoria['icona']" title="Dato non ancora in anagrafica">
                Nell'anagrafica nessun giocatore iscritto ha il dato «{{ $categoria['nome'] }}»: i migliori compariranno qui appena c'è.
            </x-ui.empty>
        @elseif ($categoria['gruppi'] === [])
            <x-ui.empty icon="hourglass-split" title="Nessun risultato ancora">
                I migliori compaiono appena ci sono partite con un risultato.
            </x-ui.empty>
        @else
            @if ($provvisorio)
                <x-ui.alert variant="info" icon="hourglass-split">Situazione provvisoria: il torneo non è concluso.</x-ui.alert>
            @endif

            @foreach ($categoria['gruppi'] as $gruppo)
                <x-ui.text class="fw-bold mb-1">{{ $gruppo['etichetta'] }}</x-ui.text>
                <x-ui.table :headers="['#', 'Giocatore', 'Fascia', 'Finali', 'Partite', 'Set', 'Game']" class="table-sm tabella-migliori mb-4">
                    @foreach ($gruppo['righe'] as $voce)
                        <x-ui.tr>
                            <x-ui.td muted>{{ $voce['riga']->posizione }}</x-ui.td>
                            <x-ui.td class="fw-semibold text-nowrap">{{ $voce['giocatore']->nomeCompleto() }}</x-ui.td>
                            <x-ui.td><x-giocatori.badge-fascia :giocatore="$voce['giocatore']" /></x-ui.td>
                            <x-ui.td class="text-nowrap" :muted="$voce['turno'] === null">{{ $voce['turno'] ?? 'Gironi' }}</x-ui.td>
                            <x-ui.td class="text-nowrap">{{ $voce['riga']->vinte }} vinte su {{ $voce['riga']->giocate }}</x-ui.td>
                            <x-ui.td class="text-nowrap">{{ $voce['riga']->setVinti }}-{{ $voce['riga']->setPersi }}</x-ui.td>
                            <x-ui.td class="text-nowrap">{{ $voce['riga']->gameVinti }}-{{ $voce['riga']->gamePersi }}</x-ui.td>
                        </x-ui.tr>
                    @endforeach
                </x-ui.table>
            @endforeach

            <x-ui.text muted small class="mb-0">
                {{ $categoria['quanti'] === 1 ? 'Il primo' : 'I primi '.$categoria['quanti'] }} di ogni gruppo; i pari merito compaiono tutti.
                @if ($categoria['senza'] > 0)
                    {{ $categoria['senza'] === 1 ? 'Un giocatore iscritto non ha' : $categoria['senza'].' giocatori iscritti non hanno' }} il dato «{{ $categoria['nome'] }}» in anagrafica e non {{ $categoria['senza'] === 1 ? 'compare' : 'compaiono' }}.
                @endif
            </x-ui.text>
        @endif

        <x-slot:footer>
            <x-ui.button variant="outline-secondary" data-bs-dismiss="modal">Chiudi</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endforeach
