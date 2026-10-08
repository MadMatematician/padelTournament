{{-- Suggerimenti per un campo di testo: <x-ui.input list="id">. Props: id, options (elenco di valori). --}}
@props(['id', 'options' => []])
<datalist id="{{ $id }}">
    @foreach ($options as $opzione)
        <option value="{{ $opzione }}"></option>
    @endforeach
</datalist>
