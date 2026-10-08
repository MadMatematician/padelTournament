{{-- Le coppie di un girone, dentro la sua scheda. Props: coppie (collezione di App\Models\Coppia con i giocatori caricati). --}}
@props(['coppie'])
<x-ui.table :headers="['Coppie']" empty="Nessuna coppia.">
    @foreach ($coppie as $coppia)
        <x-ui.tr>
            <x-ui.td>
                {{ $coppia->nome() }}
                @if ($coppia->conPrestito())
                    <x-ui.badge variant="dark" icon="arrow-left-right">prestito</x-ui.badge>
                @endif
            </x-ui.td>
        </x-ui.tr>
    @endforeach
</x-ui.table>
