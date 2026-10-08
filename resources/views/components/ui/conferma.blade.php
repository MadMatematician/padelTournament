{{--
    Finestra di conferma condivisa (una per pagina, inserita da x-layouts.app).
    La usa torneo.js per i form con `data-conferma="Testo della domanda"`;
    `data-conferma-azione` cambia l'etichetta del pulsante (predefinita: "Conferma").
--}}
<x-ui.modal id="modale-conferma" title="Conferma" icon="exclamation-triangle">
    <p class="mb-0" data-conferma-testo></p>

    <x-slot:footer>
        <x-ui.button variant="outline-secondary" data-bs-dismiss="modal">Annulla</x-ui.button>
        <x-ui.button variant="danger" data-conferma-ok>
            <span data-conferma-etichetta>Conferma</span>
        </x-ui.button>
    </x-slot:footer>
</x-ui.modal>
