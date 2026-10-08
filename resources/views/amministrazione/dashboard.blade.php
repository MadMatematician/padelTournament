<x-layouts.app title="Amministrazione">
    <x-ui.heading title="Amministrazione" icon="shield-lock" subtitle="Tornei, giocatori e account registrati." />

    <x-ui.row class="mb-4">
        <x-ui.col sm="4">
            <x-ui.stat label="Tornei" :value="$tornei->count()" icon="trophy" />
        </x-ui.col>
        <x-ui.col sm="4">
            <x-ui.stat label="Giocatori" :value="$giocatori->count()" icon="person-lines-fill" />
        </x-ui.col>
        <x-ui.col sm="4">
            <x-ui.stat label="Account registrati" :value="$totaleUtenti" icon="people" />
        </x-ui.col>
    </x-ui.row>

    <x-ui.card id="tornei" title="Tornei" icon="trophy" class="mb-4" flush>
        <x-slot:actions>
            <x-ui.button icon="plus-lg" class="btn-sm" data-bs-toggle="modal" data-bs-target="#modale-torneo">Nuovo torneo</x-ui.button>
        </x-slot:actions>
        <x-tornei.tabella :tornei="$tornei" />
    </x-ui.card>

    <x-ui.card id="giocatori" title="Giocatori" icon="person-lines-fill" class="mb-4" flush>
        <x-slot:actions>
            <x-ui.button icon="plus-lg" class="btn-sm" data-bs-toggle="modal" data-bs-target="#modale-giocatore">Nuovi giocatori</x-ui.button>
        </x-slot:actions>
        <x-giocatori.tabella :giocatori="$giocatori" />
    </x-ui.card>

    <x-ui.card title="Ultimi account registrati" icon="clock-history" flush
               :subtitle="'Amministratori: '.$totaleAmministratori.' · Utenti: '.$totaleUtentiSemplici">
        <x-utenti.tabella :utenti="$ultimiUtenti" />
    </x-ui.card>

    <x-tornei.modale-nuovo />
    <x-giocatori.modale-nuovo :circoli="$circoli" />
</x-layouts.app>
