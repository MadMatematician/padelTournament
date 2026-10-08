{{--
    Finestra modale. Props: id, title, icon, open (si apre al caricamento della pagina,
    es. dopo un errore di validazione), size (sm, lg, xl: larghezza), fullscreen (es. "xl-down": a tutto
    schermo sotto quella larghezza), scrollable (scorre il contenuto, intestazione e pulsanti restano visibili).
    Slot opzionale: footer (pulsanti).
    Si apre con <x-ui.button data-bs-toggle="modal" data-bs-target="#id">.
--}}
@props(['id', 'title', 'icon' => null, 'open' => false, 'size' => null, 'fullscreen' => null, 'scrollable' => false])
<div id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-titolo" aria-hidden="true"
     @if ($open) data-apri-modale @endif {{ $attributes->class(['modal', 'fade']) }}>
    <div @class(['modal-dialog', 'modal-dialog-centered', 'modal-'.$size => $size, 'modal-fullscreen-'.$fullscreen => $fullscreen, 'modal-dialog-scrollable' => $scrollable])>
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $id }}-titolo">
                    @if ($icon)
                        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                    @endif
                    {{ $title }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="modal-footer">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
