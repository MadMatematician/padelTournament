{{--
    Campo di testo con etichetta, icona, aiuto ed errore di validazione.
    Props: name, label, type, value, icon, help, compact (meno spazio sotto: campi in righe ripetute).
    Per i campi di un elenco il nome si scrive con le parentesi (`giocatori[0][nome]`): errori e valori
    riproposti si cercano con la chiave puntata corrispondente (`giocatori.0.nome`).
    Con type="password" il valore non viene mai riproposto e compare il pulsante "mostra".
    Gli errori si leggono dal contenitore (`bag`) del form che contiene il campo: vedi <x-ui.form>.
--}}
@aware(['bag' => 'default'])
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'icon' => null, 'help' => null, 'compact' => false])
@php
    $id = $attributes->get('id', $name);
    $segreto = $type === 'password';
    $errori = $errors->getBag($bag);
    $chiave = str_replace(['][', '[', ']'], ['.', '.', ''], $name);
    $errato = $errori->has($chiave);
    // In una pagina con più form il valore inviato si ripropone solo nel form respinto.
    $valore = ($bag === 'default' || $errori->any()) ? old($chiave, $value) : $value;
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

        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"
               @unless ($segreto) value="{{ $valore }}" @endunless
               @if ($descrizioni) aria-describedby="{{ $descrizioni }}" @endif
               @if ($errato) aria-invalid="true" @endif
               {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errato]) }}>

        @if ($segreto)
            <button type="button" class="btn btn-outline-secondary" data-mostra-password="#{{ $id }}"
                    aria-label="Mostra la password" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        @endif

        @if ($errato)
            <div class="invalid-feedback" id="{{ $id }}-errore">{{ $errori->first($chiave) }}</div>
        @endif
    </div>

    @if ($help)
        <div class="form-text" id="{{ $id }}-aiuto">{{ $help }}</div>
    @endif
</div>
