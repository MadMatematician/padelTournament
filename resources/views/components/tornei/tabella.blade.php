{{--
    Elenco dei tornei: collegamento alla pagina di gestione ed eliminazione.
    Props: tornei (collezione di App\Models\Torneo, con `giocatori_count` = numero di iscritti).
--}}
@props(['tornei'])
<x-ui.datatable :headers="['Nome', 'Tipologia', 'Data di inizio', 'Turni', 'Iscritti']" actions :entries="['torneo', 'tornei']"
                empty="Nessun torneo: creane uno con «Nuovo torneo».">
    @foreach ($tornei as $torneo)
        <x-ui.tr>
            <x-ui.td class="fw-semibold">
                <x-ui.link :href="route('amministrazione.tornei.show', $torneo)">{{ $torneo->nome }}</x-ui.link>
            </x-ui.td>
            <x-ui.td><x-ui.badge variant="primary">{{ $torneo->etichettaTipologia() }}</x-ui.badge></x-ui.td>
            <x-ui.td muted :data-order="$torneo->data_inizio?->format('Y-m-d') ?? ''">{{ $torneo->data_inizio?->format('d/m/Y') ?? '—' }}</x-ui.td>
            <x-ui.td>{{ $torneo->numero_turni }}</x-ui.td>
            <x-ui.td>{{ $torneo->giocatori_count }}</x-ui.td>
            <x-ui.td class="text-end text-nowrap">
                <x-ui.button :href="route('amministrazione.tornei.show', $torneo)" variant="outline-primary" icon="gear" class="btn-sm"
                             :aria-label="'Gestisci il torneo '.$torneo->nome">Gestisci</x-ui.button>
                <x-ui.form :action="route('amministrazione.tornei.destroy', $torneo)" method="DELETE" class="d-inline-block"
                           :data-conferma="'Eliminare il torneo «'.$torneo->nome.'»? L\'operazione non si può annullare.'"
                           data-conferma-azione="Elimina">
                    <x-ui.button type="submit" variant="outline-danger" icon="trash" class="btn-sm text-nowrap"
                                 :aria-label="'Elimina il torneo '.$torneo->nome">Elimina</x-ui.button>
                </x-ui.form>
            </x-ui.td>
        </x-ui.tr>
    @endforeach
</x-ui.datatable>
