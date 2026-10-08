{{--
    Filtro su una colonna di <x-ui.datatable> (da mettere nel suo slot `filters`): mostra solo le righe
    in cui la colonna è uguale alla voce scelta.
    Props: column (indice della colonna, da 0), label, options (elenco di valori), all (voce "nessun filtro").
--}}
@props(['column', 'label', 'options' => [], 'all' => 'Tutti'])
<label class="filtro-tabella d-inline-flex align-items-center gap-2 mb-0">
    <span>{{ $label }}:</span>
    <select data-filtro-colonna="{{ $column }}" {{ $attributes->class(['form-select', 'form-select-sm']) }}>
        <option value="">{{ $all }}</option>
        @foreach ($options as $opzione)
            <option value="{{ $opzione }}">{{ $opzione }}</option>
        @endforeach
    </select>
</label>
