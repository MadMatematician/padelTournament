<x-layouts.guest title="Accedi">
    @if (session('status'))
        <x-ui.alert variant="success" icon="check-circle">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.card title="Accedi" icon="box-arrow-in-right">
        <x-ui.form :action="route('login.store')">
            <x-ui.input name="username" label="Nome utente" icon="person" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus />
            <x-ui.input name="password" type="password" label="Password" icon="lock" autocomplete="current-password" required />
            <x-ui.checkbox name="ricordami" label="Ricordami su questo dispositivo" />
            <x-ui.button type="submit" icon="box-arrow-in-right" block>Accedi</x-ui.button>
        </x-ui.form>

        <x-slot:footer>
            Non hai un account? <x-ui.link :href="route('registrazione')">Registrati</x-ui.link>
        </x-slot:footer>
    </x-ui.card>
</x-layouts.guest>
