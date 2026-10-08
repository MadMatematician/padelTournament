{{--
    Finestra "Iscrivi dall'anagrafica": iscrive al torneo giocatori che hanno già una scheda, anche più di uno.
    Props: torneo (App\Models\Torneo), disponibili (collezione di App\Models\Giocatore non ancora iscritti).
    Si riapre da sola se l'iscrizione è stata respinta dalla validazione.
--}}
@props(['torneo', 'disponibili'])
@php
    $circoli = $disponibili->pluck('circolo')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
@endphp
<x-ui.modal id="modale-iscrizione" title="Iscrivi dall'anagrafica" icon="person-check" :open="$errors->getBag('iscrizione')->any()">
    @if ($disponibili->isEmpty())
        <x-ui.empty icon="person-check" title="Nessun giocatore da iscrivere">
            In anagrafica non ci sono giocatori che non siano già iscritti a questo torneo. Per aggiungerne di nuovi usa «Registra giocatori».
        </x-ui.empty>
    @else
        <x-ui.form id="form-iscrizione" :action="route('amministrazione.tornei.iscrizioni.store', $torneo)" bag="iscrizione">
            @if ($circoli->isNotEmpty())
                <x-ui.list-filter target="iscrizione-giocatori" label="Circolo" :options="$circoli" all="Tutti i circoli" />
            @endif
            <x-ui.checkbox-list id="iscrizione-giocatori" name="giocatori" label="Giocatori non ancora iscritti a questo torneo"
                                help="Puoi selezionarne più di uno.">
                @foreach ($disponibili as $giocatore)
                    <x-ui.checkbox-list-item :id="'iscrizione-giocatore-'.$giocatore->id" :value="$giocatore->id"
                                             :data-gruppo="$giocatore->circolo ?? ''" :note="$giocatore->circolo">
                        {{ $giocatore->cognome }} {{ $giocatore->nome }}
                        <x-slot:aside>
                            <x-giocatori.badge-fascia :giocatore="$giocatore" />
                        </x-slot:aside>
                    </x-ui.checkbox-list-item>
                @endforeach
            </x-ui.checkbox-list>
        </x-ui.form>
    @endif

    <x-slot:footer>
        <x-ui.button variant="outline-secondary" data-bs-dismiss="modal">{{ $disponibili->isEmpty() ? 'Chiudi' : 'Annulla' }}</x-ui.button>
        @if ($disponibili->isNotEmpty())
            <x-ui.button type="submit" form="form-iscrizione" icon="person-check">Iscrivi i selezionati</x-ui.button>
        @endif
    </x-slot:footer>
</x-ui.modal>
