{{-- Finestra "Nuovo torneo". Si riapre da sola se la creazione è stata respinta dalla validazione. --}}
@php
    $tipologie = collect(config('tornei.tipologie'))->map(fn (array $tipologia) => $tipologia['etichetta'])->all();
    $turni = config('tornei.turni');
@endphp
<x-ui.modal id="modale-torneo" title="Nuovo torneo" icon="trophy" :open="$errors->getBag('torneo')->any()">
    <x-ui.form id="form-torneo" :action="route('amministrazione.tornei.store')" bag="torneo">
        <x-ui.input id="torneo-nome" name="nome" label="Nome" icon="trophy" maxlength="120" autocomplete="off" required data-fuoco />
        <x-ui.select id="torneo-tipologia" name="tipologia" label="Tipologia" icon="diagram-3" :options="$tipologie"
                     :value="config('tornei.tipologia_predefinita')" required />
        <x-ui.row fields>
            <x-ui.col sm="7">
                <x-ui.input id="torneo-data-inizio" name="data_inizio" type="date" label="Data di inizio" icon="calendar-event" help="Facoltativa." />
            </x-ui.col>
            <x-ui.col sm="5">
                <x-ui.input id="torneo-numero-turni" name="numero_turni" type="number" label="Numero di turni" icon="list-ol"
                            :min="$turni['minimo']" :max="$turni['massimo']" :value="$turni['predefinito']"
                            :help="'Da '.$turni['minimo'].' a '.$turni['massimo'].'.'" required />
            </x-ui.col>
        </x-ui.row>
    </x-ui.form>

    <x-slot:footer>
        <x-ui.button variant="outline-secondary" data-bs-dismiss="modal">Annulla</x-ui.button>
        <x-ui.button type="submit" form="form-torneo" icon="plus-lg">Crea torneo</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
