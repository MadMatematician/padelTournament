{{--
    Tabella con ricerca, ordinamento e paginazione nel browser (DataTables, avviata da torneo.js).
    Props: headers (elenco intestazioni), actions (aggiunge in coda una colonna "Azioni", né ordinabile né cercabile),
    empty (testo se non ci sono righe), order (ordinamento iniziale, es. [[0, 'asc']]; vuoto = ordine di arrivo),
    pageLength (righe per pagina), entries (nome degli elementi: ['giocatore', 'giocatori']).
    Slot: <x-ui.tr>; slot opzionale `filters`: uno o più <x-ui.datatable-filter>, che torneo.js sposta accanto
    alla casella di ricerca. Per ordinare una cella su un valore diverso da quello mostrato (es. una data):
    <x-ui.td data-order="2026-10-05">.
    La libreria viene caricata solo nelle pagine che usano questo componente.
--}}
@use('App\Support\Risorse')
@props(['headers' => [], 'actions' => false, 'empty' => 'Nessun elemento.', 'order' => [], 'pageLength' => 10, 'entries' => ['elemento', 'elementi']])
@pushOnce('stili')
    <link rel="stylesheet" href="{{ Risorse::url('datatables', 'vendor/datatables/css/dataTables.bootstrap5.min.css') }}">
@endPushOnce
@pushOnce('script')
    <script src="{{ Risorse::url('datatables', 'vendor/datatables/js/dataTables.min.js') }}"></script>
    <script src="{{ Risorse::url('datatables', 'vendor/datatables/js/dataTables.bootstrap5.min.js') }}"></script>
@endPushOnce
@isset($filters)
    <div data-datatable-filtri class="d-flex flex-wrap align-items-center gap-3">{{ $filters }}</div>
@endisset
<table data-datatable data-vuoto="{{ $empty }}" data-ordine="{{ json_encode($order) }}" data-righe="{{ $pageLength }}"
       data-singolare="{{ $entries[0] }}" data-plurale="{{ $entries[1] }}"
       {{ $attributes->class(['table', 'table-hover', 'align-middle', 'mb-0', 'w-100']) }}>
    <thead>
        <tr>
            @foreach ($headers as $intestazione)
                <th scope="col">{{ $intestazione }}</th>
            @endforeach
            @if ($actions)
                <th scope="col" class="text-end" data-orderable="false" data-searchable="false">
                    <span class="visually-hidden">Azioni</span>
                </th>
            @endif
        </tr>
    </thead>
    <tbody>
        {{ $slot }}
    </tbody>
</table>
