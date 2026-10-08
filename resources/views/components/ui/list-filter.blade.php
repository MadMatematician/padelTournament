{{--
    Filtro delle voci di un elenco (es. <x-ui.checkbox-list>): restano visibili solo le voci con
    `data-gruppo` uguale alla voce scelta. Non ha `name`: non viene inviato con il form.
    Props: target (id dell'elenco), label, options (elenco di valori), all (voce "nessun filtro").
--}}
@props(['target', 'label', 'options' => [], 'all' => 'Tutti'])
<label class="d-flex align-items-center gap-2 mb-3">
    <span class="text-nowrap small text-body-secondary">{{ $label }}:</span>
    <select data-filtro-elenco="#{{ $target }}" {{ $attributes->class(['form-select', 'form-select-sm']) }}>
        <option value="">{{ $all }}</option>
        @foreach ($options as $opzione)
            <option value="{{ $opzione }}">{{ $opzione }}</option>
        @endforeach
    </select>
</label>
