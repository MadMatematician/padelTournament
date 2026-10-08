{{--
    Le finali viste dalla pagina di gestione: a che punto sono e il collegamento al tabellone, dove si
    inseriscono i risultati. Compare, in cima, quando il tabellone è stato generato.
    Props: torneo (App\Models\Torneo), stato (App\Services\Tornei\StatoFinali), highlight (vedi <x-ui.card>).
--}}
@props(['torneo', 'stato', 'highlight' => false])
<x-ui.card id="finali" title="Finali" icon="diagram-2" :highlight="$highlight" {{ $attributes }}>
    <x-slot:actions>
        <x-ui.button :href="route('amministrazione.tornei.tabellone', $torneo)" icon="diagram-2" class="btn-sm text-nowrap">Apri il tabellone</x-ui.button>
    </x-slot:actions>

    <x-ui.text class="mb-0">
        @if ($stato->vincitrice)
            Torneo concluso: vince la coppia
            <x-ui.span class="fw-bold">{{ $stato->vincitrice->primaFascia->nomeCompleto() }} / {{ $stato->vincitrice->secondaFascia->nomeCompleto() }}</x-ui.span>.
        @elseif ($stato->inCorso)
            {{ $stato->inCorso->etichetta() }}: {{ $stato->daGiocare === 1 ? 'manca un risultato' : 'mancano '.$stato->daGiocare.' risultati' }}.
            I risultati delle finali si inseriscono nel tabellone; quelli dei gironi sono bloccati.
        @else
            Tabellone generato: i risultati delle finali si inseriscono nel tabellone.
        @endif
    </x-ui.text>
</x-ui.card>
