<x-layouts.app title="Area utente">
    <x-ui.heading :title="'Ciao, '.$utente->name" icon="person" subtitle="Questa è la tua area personale." />

    <x-ui.row>
        <x-ui.col lg="5">
            <x-ui.card title="Il tuo profilo" icon="person-vcard" class="h-100">
                <x-ui.dl>
                    <x-ui.dl-item label="Nome">{{ $utente->name }}</x-ui.dl-item>
                    <x-ui.dl-item label="Nome utente">{{ $utente->username }}</x-ui.dl-item>
                    <x-ui.dl-item label="Email">{{ $utente->email }}</x-ui.dl-item>
                    <x-ui.dl-item label="Ruolo"><x-utenti.badge-ruolo :ruolo="$utente->ruolo" /></x-ui.dl-item>
                </x-ui.dl>
            </x-ui.card>
        </x-ui.col>
        <x-ui.col lg="7">
            <x-ui.card title="I tuoi tornei" icon="trophy" class="h-100">
                <x-ui.empty icon="trophy" title="Nessun torneo">Quando verrai iscritto a un torneo lo troverai qui.</x-ui.empty>
            </x-ui.card>
        </x-ui.col>
    </x-ui.row>
</x-layouts.app>
