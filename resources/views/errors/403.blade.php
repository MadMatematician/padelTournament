<x-layouts.app title="Accesso negato">
    <x-ui.card>
        <x-ui.empty icon="shield-x" title="Accesso negato">
            {{ $exception->getMessage() ?: 'Non hai i permessi per accedere a questa pagina.' }}
        </x-ui.empty>
        <x-slot:footer>
            <x-ui.button :href="route('home')" icon="house" variant="outline-primary">Torna alla pagina iniziale</x-ui.button>
        </x-slot:footer>
    </x-ui.card>
</x-layouts.app>
