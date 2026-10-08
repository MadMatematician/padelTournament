{{--
    Menu a tendina con etichetta, icona, aiuto ed errore di validazione.
    Props: name, label, options (valore => etichetta), value, placeholder (voce vuota iniziale), icon, help,
    compact (meno spazio sotto: campi in righe ripetute). Nomi con le parentesi come in <x-ui.input>.
--}}
@aware(['bag' => 'default'])
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'icon' => null, 'help' => null, 'compact' => false])
@php
    $id = $attributes->get('id', $name);
    $errori = $errors->getBag($bag);
    $chiave = str_replace(['][', '[', ']'], ['.', '.', ''], $name);
    $errato = $errori->has($chiave);
    // In una pagina con più form il valore inviato si ripropone solo nel form respinto.
    $scelto = (string) (($bag === 'default' || $errori->any()) ? old($chiave, $value) : $value);
    $descrizioni = collect([$help ? $id.'-aiuto' : null, $errato ? $id.'-errore' : null])->filter()->implode(' ');
@endphp
<div class="{{ $compact ? 'mb-2' : 'mb-3' }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    @endif

    <div class="input-group has-validation">
        @if ($icon)
            <span class="input-group-text"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
        @endif

        <select name="{{ $name }}" id="{{ $id }}"
                @if ($descrizioni) aria-describedby="{{ $descrizioni }}" @endif
                @if ($errato) aria-invalid="true" @endif
                {{ $attributes->except('id')->class(['form-select', 'is-invalid' => $errato]) }}>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $valore => $etichetta)
                <option value="{{ $valore }}" @selected($scelto === (string) $valore)>{{ $etichetta }}</option>
            @endforeach
        </select>

        @if ($errato)
            <div class="invalid-feedback" id="{{ $id }}-errore">{{ $errori->first($chiave) }}</div>
        @endif
    </div>

    @if ($help)
        <div class="form-text" id="{{ $id }}-aiuto">{{ $help }}</div>
    @endif
</div>
