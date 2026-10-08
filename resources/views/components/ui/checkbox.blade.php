{{--
    Casella di spunta. Props: name, label (senza etichetta resta la sola casella: serve un `aria-label`), checked.
    Nomi con le parentesi come in <x-ui.input>. Dentro un <x-ui.form bag="…"> eredita il contenitore degli errori:
    dopo un form respinto vale ciò che è stato inviato (una casella non spuntata non viene inviata affatto).
--}}
@aware(['bag' => 'default'])
@props(['name', 'label' => null, 'checked' => false])
@php
    $id = $attributes->get('id', $name);
    $chiave = str_replace(['][', '[', ']'], ['.', '.', ''], $name);
    $spuntata = match (true) {
        $bag === 'default' => (bool) old($chiave, $checked),
        $errors->getBag($bag)->any() => (bool) old($chiave),
        default => (bool) $checked,
    };
@endphp
<div class="form-check mb-3">
    <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1" @checked($spuntata)
           {{ $attributes->except('id')->class(['form-check-input']) }}>
    @if ($label)
        <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
    @endif
</div>
