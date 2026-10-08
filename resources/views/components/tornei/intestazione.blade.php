{{--
    Intestazione della pagina di gestione di un torneo: nome, tipologia, data di inizio e numero di turni.
    Props: torneo (App\Models\Torneo). Slot: azioni a destra.
--}}
@props(['torneo'])
@php
    $dettagli = collect([
        'Gestione del torneo',
        $torneo->etichettaTipologia(),
        $torneo->data_inizio ? 'inizio '.$torneo->data_inizio->format('d/m/Y') : null,
        $torneo->numero_turni.' '.($torneo->numero_turni === 1 ? 'turno' : 'turni'),
    ])->filter()->implode(' · ');
@endphp
<x-ui.heading :title="$torneo->nome" icon="trophy" :subtitle="$dettagli">{{ $slot }}</x-ui.heading>
