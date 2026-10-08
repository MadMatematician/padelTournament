{{--
    Classifica di un girone.
    Props: girone (App\Models\Girone), righe (elenco di App\Services\Classifica\RigaClassifica, dalla prima),
    coppie (collezione di App\Models\Coppia per identificativo, con i giocatori caricati),
    stati (identificativo della coppia => "diretta", "ripescata" o "esclusa").
--}}
@props(['girone', 'righe', 'coppie', 'stati' => []])
<x-ui.card :id="'classifica-'.$girone->id" :title="$girone->nome" icon="list-ol" flush {{ $attributes->class(['h-100']) }}>
    <x-ui.table :headers="['#', 'Coppia', 'G', 'V', 'P', 'Set', 'Game', 'Punti']" empty="Nessuna coppia." class="tabella-classifica">
        @foreach ($righe as $riga)
            @php
                $stato = $stati[$riga->coppia] ?? null;
            @endphp
            <x-ui.tr>
                <x-ui.td muted>{{ $riga->posizione }}</x-ui.td>
                <x-ui.td>
                    <x-ui.span class="fw-semibold">{{ $coppie[$riga->coppia]->nome() }}</x-ui.span>
                    @if ($stato === 'diretta')
                        <x-ui.badge variant="primary" icon="check-lg">alle finali</x-ui.badge>
                    @elseif ($stato === 'ripescata')
                        <x-ui.badge variant="dark" icon="life-preserver">ripescata</x-ui.badge>
                    @elseif ($stato === 'esclusa')
                        <x-ui.badge variant="light" icon="arrow-left-right">prestito: non accede</x-ui.badge>
                    @endif
                </x-ui.td>
                <x-ui.td>{{ $riga->giocate }}</x-ui.td>
                <x-ui.td>{{ $riga->vinte }}</x-ui.td>
                <x-ui.td>{{ $riga->perse }}</x-ui.td>
                <x-ui.td class="text-nowrap">{{ $riga->setVinti }}-{{ $riga->setPersi }}</x-ui.td>
                <x-ui.td class="text-nowrap">{{ $riga->gameVinti }}-{{ $riga->gamePersi }}</x-ui.td>
                <x-ui.td class="fw-bold">{{ $riga->punti }}</x-ui.td>
            </x-ui.tr>
        @endforeach
    </x-ui.table>
</x-ui.card>
