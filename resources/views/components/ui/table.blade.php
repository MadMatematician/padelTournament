{{-- Tabella responsiva. Props: headers (elenco intestazioni), empty (testo se non ci sono righe). Slot: <x-ui.tr>. --}}
@props(['headers' => [], 'empty' => 'Nessun elemento.'])
<div class="table-responsive">
    <table {{ $attributes->class(['table', 'table-hover', 'align-middle', 'mb-0']) }}>
        <thead>
            <tr>
                @foreach ($headers as $intestazione)
                    <th scope="col">{{ $intestazione }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if (! $slot->hasActualContent())
                <tr>
                    <td colspan="{{ max(count($headers), 1) }}" class="text-center text-body-secondary py-4">{{ $empty }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
