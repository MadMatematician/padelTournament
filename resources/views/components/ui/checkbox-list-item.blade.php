{{--
    Voce di <x-ui.checkbox-list>: tutta la riga è cliccabile. Props: value, checked, note (testo secondario
    accanto all'etichetta).
    Slot: etichetta; slot opzionale `aside`: contenuto allineato a destra (es. un'etichetta colorata).
    Dopo un form respinto le voci che erano state spuntate tornano spuntate.
--}}
@aware(['name', 'bag' => 'default'])
@props(['value', 'checked' => false, 'note' => null])
@php
    $id = $attributes->get('id', $name.'-'.$value);
    $inviati = ($bag === 'default' || $errors->getBag($bag)->any()) ? array_map('strval', (array) old($name, [])) : [];
    $spuntata = $checked || in_array((string) $value, $inviati, true);
@endphp
<label for="{{ $id }}" {{ $attributes->except('id')->class(['list-group-item', 'd-flex', 'align-items-center', 'gap-2']) }}>
    <input type="checkbox" name="{{ $name }}[]" id="{{ $id }}" value="{{ $value }}" class="form-check-input flex-shrink-0 m-0" @checked($spuntata)>
    <span>
        {{ $slot }}
        @if ($note)
            <span class="text-body-secondary small">· {{ $note }}</span>
        @endif
    </span>
    @isset($aside)
        <span class="ms-auto text-nowrap">{{ $aside }}</span>
    @endisset
</label>
