{{--
    Finestra "Fasce non pari": compare al posto del sorteggio diretto delle coppie quando una fascia ha più
    giocatori dell'altra. I giocatori in più giocano con un compagno dell'altra fascia preso in prestito
    (ha già la sua coppia, ne completa una seconda e gioca in due gironi diversi): chi va in prestito
    si può sorteggiare oppure indicare.
    Props: torneo, squilibrio (App\Services\Tornei\Squilibrio), candidati (iscritti della fascia con meno
    giocatori), rigenera (esiste già un sorteggio, che verrà sostituito).
    Si riapre da sola se la scelta è stata respinta.
--}}
@props(['torneo', 'squilibrio', 'candidati', 'rigenera' => false])
@php
    $fasce = config('tornei.giocatori.fasce');
    $errori = $errors->getBag('sorteggio');
    $inPiu = mb_strtolower($fasce[$squilibrio->fasciaInEccesso]);
    $mancante = mb_strtolower($fasce[$squilibrio->fasciaMancante]);
    $uno = $squilibrio->inEccesso === 1;
@endphp
<x-ui.modal id="modale-prestito" title="Fasce non pari" icon="people" size="lg" :open="$errori->any()">
    <x-ui.text>
        {{ $uno ? 'Un giocatore di '.$inPiu.' resta' : $squilibrio->inEccesso.' giocatori di '.$inPiu.' restano' }} senza compagno.
        Per completare le coppie {{ $uno ? 'serve un giocatore' : 'servono '.$squilibrio->inEccesso.' giocatori' }} di {{ $mancante }} in prestito:
        chi va in prestito tiene la sua coppia, ne completa una seconda e gioca in due gironi diversi.
    </x-ui.text>

    @if (! $squilibrio->prestitoPossibile())
        <x-ui.alert variant="warning" icon="exclamation-triangle" class="mb-0">{{ $squilibrio->impedimento }}</x-ui.alert>
    @else
        @if ($errori->has('prestito'))
            <x-ui.alert variant="danger" icon="exclamation-triangle">{{ $errori->first('prestito') }}</x-ui.alert>
        @endif

        <x-ui.form id="form-prestito" :action="route('amministrazione.tornei.coppie.store', $torneo)" bag="sorteggio">
            <x-ui.checkbox-list id="prestito-giocatori" name="giocatori_prestito"
                                :label="'Chi va in prestito ('.$mancante.')'"
                                :help="'Puoi lasciarlo al sorteggio, oppure spuntare '.($uno ? 'il giocatore' : 'i '.$squilibrio->inEccesso.' giocatori').' e usare «Usa i giocatori indicati».'">
                @foreach ($candidati as $giocatore)
                    <x-ui.checkbox-list-item :id="'prestito-giocatore-'.$giocatore->id" :value="$giocatore->id" :note="$giocatore->circolo">
                        {{ $giocatore->cognome }} {{ $giocatore->nome }}
                        @if ($giocatore->etichettaLato())
                            <x-slot:aside>
                                <x-ui.badge variant="light">{{ $giocatore->etichettaLato() }}</x-ui.badge>
                            </x-slot:aside>
                        @endif
                    </x-ui.checkbox-list-item>
                @endforeach
            </x-ui.checkbox-list>

            @if ($rigenera)
                <x-ui.text muted small class="mb-0">Le coppie attuali, con i loro gironi e le loro partite, verranno sostituite.</x-ui.text>
            @endif
        </x-ui.form>
    @endif

    <x-slot:footer>
        <x-ui.button variant="outline-secondary" data-bs-dismiss="modal">{{ $squilibrio->prestitoPossibile() ? 'Annulla' : 'Chiudi' }}</x-ui.button>
        @if ($squilibrio->prestitoPossibile())
            <x-ui.button type="submit" form="form-prestito" name="prestito" value="indicati" variant="outline-primary" icon="hand-index">Usa i giocatori indicati</x-ui.button>
            <x-ui.button type="submit" form="form-prestito" name="prestito" value="sorteggia" icon="shuffle">Sorteggia il prestito</x-ui.button>
        @endif
    </x-slot:footer>
</x-ui.modal>
