<x-layouts.guest title="Registrati" wide>
    <x-ui.card title="Crea il tuo account" icon="person-plus">
        <x-ui.form :action="route('registrazione.store')">
            <x-ui.row fields>
                <x-ui.col md="6">
                    <x-ui.input name="name" label="Nome e cognome" icon="person-vcard" autocomplete="name" required autofocus />
                </x-ui.col>
                <x-ui.col md="6">
                    <x-ui.input name="username" label="Nome utente" icon="person" autocomplete="username" autocapitalize="none" spellcheck="false"
                                :minlength="config('sicurezza.username.lunghezza_minima')" :maxlength="config('sicurezza.username.lunghezza_massima')"
                                help="Lo userai per accedere. Lettere, numeri, punto e trattino; maiuscole e minuscole non contano." required />
                </x-ui.col>
            </x-ui.row>

            <x-ui.input name="email" type="email" label="Email" icon="envelope" autocomplete="email" required />

            <x-ui.row fields>
                <x-ui.col md="6">
                    <x-ui.input name="password" type="password" label="Password" icon="lock" autocomplete="new-password" required />
                </x-ui.col>
                <x-ui.col md="6">
                    <x-ui.input name="password_confirmation" type="password" label="Conferma password" icon="lock-fill" autocomplete="new-password" required />
                </x-ui.col>
            </x-ui.row>

            <x-auth.requisiti-password />

            <x-ui.button type="submit" icon="person-plus" block>Registrati</x-ui.button>
        </x-ui.form>

        <x-slot:footer>
            Hai già un account? <x-ui.link :href="route('login')">Accedi</x-ui.link>
        </x-slot:footer>
    </x-ui.card>
</x-layouts.guest>
