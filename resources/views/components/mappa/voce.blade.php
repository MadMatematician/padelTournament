{{--
    Una rotta nella mappa del sito. Props: voce (App\Services\Mappa\VoceMappa).
    Colonne: metodo, indirizzo (con il nome della rotta), destinazione (descrizione e controller), accesso.
    Indirizzi e controller non vanno a capo: su schermi stretti la tabella scorre in orizzontale.
--}}
@props(['voce'])
<x-ui.tr>
    <x-ui.td class="text-nowrap">
        @foreach ($voce->metodi as $metodo)
            <x-ui.badge :variant="$metodo === 'GET' ? 'primary' : 'dark'">{{ $metodo }}</x-ui.badge>
        @endforeach
    </x-ui.td>
    <x-ui.td>
        @if ($voce->link)
            <x-ui.link :href="$voce->link"><x-ui.code class="text-nowrap">{{ $voce->indirizzo }}</x-ui.code></x-ui.link>
        @else
            <x-ui.code class="text-nowrap">{{ $voce->indirizzo }}</x-ui.code>
        @endif
        @if ($voce->nome)
            <x-ui.span class="d-block small text-body-secondary">{{ $voce->nome }}</x-ui.span>
        @endif
    </x-ui.td>
    <x-ui.td class="w-50">
        @if ($voce->descrizione)
            {{ $voce->descrizione }}
        @else
            <x-ui.span class="text-body-secondary">Senza descrizione</x-ui.span>
        @endif
        <x-ui.code muted class="d-block small text-nowrap">{{ $voce->destinazione }}</x-ui.code>
    </x-ui.td>
    <x-ui.td>
        {{ $voce->accesso }}
        @if ($voce->soloSviluppo)
            <x-ui.badge icon="cone-striped">solo sviluppo</x-ui.badge>
        @endif
    </x-ui.td>
</x-ui.tr>
