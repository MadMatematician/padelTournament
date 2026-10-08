{{--
    Scelta multipla: elenco di caselle di spunta inviate come `name[]`, scorrevole se è lungo.
    Props: name, label, help. Slot: <x-ui.checkbox-list-item>.
    Per filtrarne le voci: <x-ui.list-filter target="id dell'elenco"> e `data-gruppo="…"` sulle voci.
    Gli errori (del campo o di una sua voce) si leggono dal contenitore (`bag`) del form: vedi <x-ui.form>.
--}}
@aware(['bag' => 'default'])
@props(['name', 'label' => null, 'help' => null])
@php
    $id = $attributes->get('id', $name);
    $errori = $errors->getBag($bag);
    $errore = $errori->first($name) ?: $errori->first($name.'.*');
    $descrizioni = collect([$help ? $id.'-aiuto' : null, $errore ? $id.'-errore' : null])->filter()->implode(' ');
@endphp
<fieldset class="mb-3" @if ($descrizioni) aria-describedby="{{ $descrizioni }}" @endif>
    @if ($label)
        <legend class="form-label fs-6">{{ $label }}</legend>
    @endif

    <div id="{{ $id }}" {{ $attributes->except('id')->class(['list-group', 'elenco-scelte', 'border', 'border-danger' => (bool) $errore]) }}>
        {{ $slot }}
    </div>

    @if ($errore)
        <div class="invalid-feedback d-block" id="{{ $id }}-errore">{{ $errore }}</div>
    @endif

    @if ($help)
        <div class="form-text" id="{{ $id }}-aiuto">{{ $help }}</div>
    @endif
</fieldset>
