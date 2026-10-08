{{--
    I passi di un torneo: coppie → gironi → partite → finali. Ogni passo si può rifare: sostituisce il
    risultato precedente e cancella i passi che ne dipendono (con conferma).
    Props: torneo, giocatori (iscritti), coppie, gironi (con `partite` caricate),
    squilibrio (App\Services\Tornei\Squilibrio o null: fasce non pari, le coppie passano dalla finestra del prestito),
    partiteDaGiocare (partite dei gironi senza risultato), haFinali (tabellone generato).
--}}
@props(['torneo', 'giocatori', 'coppie', 'gironi', 'squilibrio' => null, 'partiteDaGiocare' => 0, 'haFinali' => false])
@php
    $fasce = config('tornei.giocatori.fasce');
    $perFascia = $giocatori->countBy('fascia');
    // Servono giocatori di tutte le fasce che compongono una coppia.
    $coppiePossibili = collect(array_keys($torneo->regole()['squadra']['composizione']))->every(fn ($fascia) => $perFascia->get($fascia, 0) > 0);

    // I lati contano solo se la formula del torneo lo prevede.
    $descrizioneLati = match ($torneo->latiNelSorteggio()->value) {
        'preferito' => ' preferendo le coppie destra + sinistra',
        'obbligatorio' => ': solo coppie destra + sinistra',
        default => '',
    };

    $conPrestito = $coppie->whereNotNull('prestito_id')->count();
    $gironiPrevisti = (int) ceil($coppie->count() / max($torneo->squadre_per_girone, 1));
    $numeroPartite = $gironi->sum(fn ($girone) => $girone->partite->count());
    $risultati = $numeroPartite - $partiteDaGiocare;
    $dimensioni = $gironi->map(fn ($girone) => $girone->coppie->count())->sortDesc()->implode(', ');
    $qualificate = $torneo->qualificatePerGirone();

    // Che cosa si perde rifacendo un passo: tutto ciò che viene dopo.
    $elenco = fn (array $voci) => collect($voci)->filter()->join(', ', ' e ');
    $dopoLePartite = [$risultati > 0 ? 'i risultati inseriti' : null, $haFinali ? 'le finali' : null];
    $dopoIGironi = [$numeroPartite > 0 ? 'le partite' : null, ...$dopoLePartite];
    $dopoLeCoppie = [$gironi->isNotEmpty() ? 'i gironi' : null, ...$dopoIGironi];
    $perdita = fn (array $voci) => $elenco($voci) === '' ? '' : ' Si perdono anche '.$elenco($voci).'.';
@endphp
<x-ui.card id="sorteggio" title="Sorteggio e fasi" icon="shuffle" {{ $attributes }}>
    @if ($coppie->isNotEmpty())
        <x-slot:actions>
            <x-ui.form :action="route('amministrazione.tornei.sorteggio.destroy', $torneo)" method="DELETE"
                       :data-conferma="'Annullare il sorteggio? Le coppie verranno eliminate e le iscrizioni si riaprono.'.$perdita($dopoLeCoppie)"
                       data-conferma-azione="Annulla il sorteggio">
                <x-ui.button type="submit" variant="outline-danger" icon="arrow-counterclockwise" class="btn-sm text-nowrap">Annulla sorteggio</x-ui.button>
            </x-ui.form>
        </x-slot:actions>
    @endif

    <x-ui.row>
        <x-ui.col md="6" lg="3">
            <x-ui.step number="1" title="Coppie" :done="$coppie->isNotEmpty()">
                @if ($coppie->isNotEmpty())
                    {{ $coppie->count() }} {{ $coppie->count() === 1 ? 'coppia' : 'coppie' }}{{ $conPrestito > 0 ? ', '.$conPrestito.' con un giocatore in prestito' : '' }}.
                @elseif (! $coppiePossibili)
                    Servono giocatori di entrambe le fasce.
                @elseif ($squilibrio)
                    Le fasce non sono pari: {{ $squilibrio->inEccesso === 1 ? 'un giocatore' : $squilibrio->inEccesso.' giocatori' }} di {{ mb_strtolower($fasce[$squilibrio->fasciaInEccesso]) }} in più.
                @else
                    Un giocatore di prima fascia con uno di seconda, estratti a sorte{{ $descrizioneLati }}.
                @endif

                <x-slot:action>
                    @if ($squilibrio)
                        <x-ui.button :variant="$coppie->isEmpty() ? 'primary' : 'outline-primary'" icon="people" class="text-nowrap"
                                     data-bs-toggle="modal" data-bs-target="#modale-prestito">{{ $coppie->isEmpty() ? 'Genera coppie' : 'Rigenera coppie' }}</x-ui.button>
                    @else
                        <x-ui.form :action="route('amministrazione.tornei.coppie.store', $torneo)"
                                   :data-conferma="$coppie->isEmpty() ? null : 'Rifare il sorteggio delle coppie? Le coppie attuali verranno sostituite.'.$perdita($dopoLeCoppie)"
                                   data-conferma-azione="Rigenera le coppie">
                            <x-ui.button type="submit" :variant="$coppie->isEmpty() ? 'primary' : 'outline-primary'" icon="people" class="text-nowrap"
                                         :disabled="! $coppiePossibili">{{ $coppie->isEmpty() ? 'Genera coppie' : 'Rigenera coppie' }}</x-ui.button>
                        </x-ui.form>
                    @endif
                </x-slot:action>
            </x-ui.step>
        </x-ui.col>

        <x-ui.col md="6" lg="3">
            <x-ui.step number="2" title="Gironi" :done="$gironi->isNotEmpty()">
                @if ($gironi->isNotEmpty())
                    {{ $gironi->count() === 1 ? 'Un girone' : $gironi->count().' gironi' }} da {{ $dimensioni }} coppie.
                @elseif ($coppie->isNotEmpty())
                    Fino a {{ $torneo->squadre_per_girone }} squadre per girone: {{ $gironiPrevisti === 1 ? 'un girone' : $gironiPrevisti.' gironi' }}.
                @else
                    Dopo le coppie: fino a {{ $torneo->squadre_per_girone }} squadre per girone.
                @endif

                <x-slot:action>
                    <x-ui.form :action="route('amministrazione.tornei.gironi.store', $torneo)"
                               :data-conferma="$gironi->isEmpty() ? null : 'Rifare i gironi? I gironi attuali verranno sostituiti.'.$perdita($dopoIGironi)"
                               data-conferma-azione="Rigenera i gironi">
                        <x-ui.button type="submit" :variant="$gironi->isEmpty() ? 'primary' : 'outline-primary'" icon="diagram-3" class="text-nowrap"
                                     :disabled="$coppie->isEmpty()">{{ $gironi->isEmpty() ? 'Genera gironi' : 'Rigenera gironi' }}</x-ui.button>
                    </x-ui.form>
                </x-slot:action>
            </x-ui.step>
        </x-ui.col>

        <x-ui.col md="6" lg="3">
            <x-ui.step number="3" title="Partite" :done="$numeroPartite > 0">
                @if ($numeroPartite > 0)
                    {{ $numeroPartite }} {{ $numeroPartite === 1 ? 'partita' : 'partite' }} in calendario, {{ $risultati }} con il risultato.
                @else
                    {{ $gironi->isEmpty() ? 'Dopo i gironi: all\'italiana' : 'All\'italiana' }}, {{ mb_strtolower($torneo->etichettaFormula()) }}.
                @endif

                <x-slot:action>
                    <x-ui.form :action="route('amministrazione.tornei.partite.store', $torneo)"
                               :data-conferma="$numeroPartite === 0 ? null : 'Rifare il calendario? Le partite attuali verranno sostituite.'.$perdita($dopoLePartite)"
                               data-conferma-azione="Rigenera le partite">
                        <x-ui.button type="submit" :variant="$numeroPartite === 0 ? 'primary' : 'outline-primary'" icon="calendar3" class="text-nowrap"
                                     :disabled="$gironi->isEmpty()">{{ $numeroPartite === 0 ? 'Genera partite' : 'Rigenera partite' }}</x-ui.button>
                    </x-ui.form>
                </x-slot:action>
            </x-ui.step>
        </x-ui.col>

        <x-ui.col md="6" lg="3">
            <x-ui.step number="4" title="Finali" :done="$haFinali">
                @if ($haFinali)
                    Tabellone generato: i risultati dei gironi sono bloccati.
                @elseif ($numeroPartite === 0)
                    Dopo i risultati dei gironi: {{ $qualificate === 1 ? 'la prima' : 'le prime '.$qualificate }} di ogni girone, a eliminazione diretta, con coppie nuove a ogni turno.
                @elseif ($partiteDaGiocare > 0)
                    {{ $partiteDaGiocare === 1 ? 'Manca il risultato di una partita' : 'Mancano i risultati di '.$partiteDaGiocare.' partite' }} dei gironi.
                @else
                    Gironi conclusi: accedono {{ $qualificate === 1 ? 'la prima' : 'le prime '.$qualificate }} di ogni girone; nelle finali le coppie cambiano a ogni turno.
                @endif

                <x-slot:action>
                    <x-ui.form :action="route('amministrazione.tornei.tabellone.store', $torneo)"
                               :data-conferma="$haFinali ? 'Rifare il tabellone delle finali? Quello attuale verrà sostituito, con i suoi risultati, e le coppie saranno sorteggiate di nuovo.' : null"
                               data-conferma-azione="Rigenera le finali">
                        <x-ui.button type="submit" :variant="$haFinali ? 'outline-primary' : 'primary'" icon="diagram-2" class="text-nowrap"
                                     :disabled="$numeroPartite === 0 || $partiteDaGiocare > 0">{{ $haFinali ? 'Rigenera finali' : 'Genera finali' }}</x-ui.button>
                    </x-ui.form>
                </x-slot:action>
            </x-ui.step>
        </x-ui.col>
    </x-ui.row>
</x-ui.card>
