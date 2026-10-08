<x-layouts.app title="Mappa del sito">
    <x-ui.heading title="Mappa del sito" icon="diagram-3"
                  :subtitle="$totale.' rotte in '.$sezioni->count().' sezioni, lette dai file di route.'" />

    @forelse ($sezioni as $sezione)
        <x-mappa.sezione :sezione="$sezione" class="mb-4" />
    @empty
        <x-ui.card>
            <x-ui.empty icon="diagram-3" title="Nessuna rotta">I file di route non definiscono ancora nessuna rotta.</x-ui.empty>
        </x-ui.card>
    @endforelse
</x-layouts.app>
