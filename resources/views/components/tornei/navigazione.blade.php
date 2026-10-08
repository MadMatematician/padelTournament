{{-- Le pagine di un torneo. Props: torneo (App\Models\Torneo), attiva ('gestione', 'classifica' o 'tabellone'). --}}
@props(['torneo', 'attiva'])
<x-ui.nav aria-label="Pagine del torneo">
    <x-ui.nav-item :href="route('amministrazione.tornei.show', $torneo)" :active="$attiva === 'gestione'" icon="gear">Gestione</x-ui.nav-item>
    <x-ui.nav-item :href="route('amministrazione.tornei.classifica', $torneo)" :active="$attiva === 'classifica'" icon="list-ol">Classifica</x-ui.nav-item>
    <x-ui.nav-item :href="route('amministrazione.tornei.tabellone', $torneo)" :active="$attiva === 'tabellone'" icon="diagram-2">Tabellone</x-ui.nav-item>
</x-ui.nav>
