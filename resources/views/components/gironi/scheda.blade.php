{{--
    Scheda di un girone: le sue coppie e il calendario delle partite, con i risultati.
    Generate le partite, il calendario sta sopra l'elenco delle coppie: è la parte su cui si lavora.
    Props: girone (App\Models\Girone con `coppie` (e i loro giocatori) e `partite` caricate, in ordine di giornata),
    torneo (App\Models\Torneo), bloccato (i risultati non si possono più cambiare: le finali sono state generate),
    highlight (vedi <x-ui.card>).
--}}
@props(['girone', 'torneo', 'bloccato' => false, 'highlight' => false])
@php
    $coppie = $girone->coppie->keyBy('id');
    $giocate = $girone->partite->filter->giocata()->count();
    $riepilogo = $coppie->count().' '.($coppie->count() === 1 ? 'coppia' : 'coppie')
        .($girone->partite->isNotEmpty() ? ' · '.$girone->partite->count().' '.($girone->partite->count() === 1 ? 'partita' : 'partite').', '.$giocate.' con il risultato' : '');
@endphp
<x-ui.card :id="'girone-'.$girone->id" :title="$girone->nome" icon="diagram-3" :subtitle="$riepilogo" flush :highlight="$highlight"
           {{ $attributes->class(['h-100']) }}>
    @if ($girone->partite->isEmpty())
        <x-gironi.coppie :coppie="$coppie" />
    @endif

    <x-ui.table :headers="['Giornata', 'Partita', 'Risultato']" empty="Partite non ancora generate.">
        @foreach ($girone->partite as $partita)
            @php
                // Coppie e girone sono già caricati: si agganciano alla partita senza altre letture.
                $partita->setRelation('coppiaA', $coppie[$partita->coppia_a_id]);
                $partita->setRelation('coppiaB', $coppie[$partita->coppia_b_id]);
                $partita->setRelation('girone', $girone);
            @endphp
            <x-ui.tr>
                <x-ui.td muted class="text-nowrap">
                    {{ $partita->giornata }}ª
                    @if ($partita->ritorno)
                        <x-ui.badge variant="light">ritorno</x-ui.badge>
                    @endif
                </x-ui.td>
                <x-ui.td>
                    <x-ui.span :class="$partita->vincitrice_id === $partita->coppia_a_id ? 'fw-bold' : ''">{{ $partita->coppiaA->nome() }}</x-ui.span>
                    –
                    <x-ui.span :class="$partita->vincitrice_id === $partita->coppia_b_id ? 'fw-bold' : ''">{{ $partita->coppiaB->nome() }}</x-ui.span>
                </x-ui.td>
                <x-ui.td class="text-nowrap">
                    <x-partite.risultato :partita="$partita" :torneo="$torneo" :bloccato="$bloccato" />
                </x-ui.td>
            </x-ui.tr>
        @endforeach
    </x-ui.table>

    @if ($girone->partite->isNotEmpty())
        <x-gironi.coppie :coppie="$coppie" />
    @endif
</x-ui.card>
