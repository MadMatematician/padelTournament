@php
    $sorteggiato = $coppie->isNotEmpty();
    // La parte della pagina su cui si sta lavorando: sta subito sotto i passi del sorteggio ed è messa in evidenza.
    // Le altre seguono dalla più recente alla più vecchia: finali, gironi e partite, coppie, formula, iscritti.
    $attiva = match (true) {
        $haFinali => 'finali',
        $gironi->isNotEmpty() => 'gironi',
        $sorteggiato => 'coppie',
        default => 'iscritti',
    };
@endphp
<x-layouts.app :title="$torneo->nome">
    <x-tornei.intestazione :torneo="$torneo">
        <x-ui.button :href="route('amministrazione.dashboard').'#tornei'" variant="outline-primary" icon="arrow-left">Tutti i tornei</x-ui.button>
    </x-tornei.intestazione>

    <x-tornei.navigazione :torneo="$torneo" attiva="gestione" />

    <x-tornei.sorteggio :torneo="$torneo" :giocatori="$giocatori" :coppie="$coppie" :gironi="$gironi" :squilibrio="$squilibrio"
                        :partite-da-giocare="$partiteDaGiocare" :ha-finali="$haFinali" class="mb-4" />

    @if ($haFinali)
        <x-tornei.finali :torneo="$torneo" :stato="$statoFinali" :highlight="$attiva === 'finali'" class="mb-4" />
    @endif

    @if ($gironi->isNotEmpty())
        <x-ui.row id="gironi" class="mb-4">
            @foreach ($gironi as $girone)
                <x-ui.col lg="6">
                    <x-gironi.scheda :girone="$girone" :torneo="$torneo" :bloccato="$haFinali" :highlight="$attiva === 'gironi'" />
                </x-ui.col>
            @endforeach
        </x-ui.row>
    @endif

    @if ($sorteggiato)
        <x-ui.card id="coppie" title="Coppie" icon="people" class="mb-4" flush :highlight="$attiva === 'coppie'"
                   :subtitle="$gironi->isNotEmpty() ? 'In ordine di girone. Le colonne si possono ordinare.' : 'Le colonne si possono ordinare.'">
            <x-coppie.tabella :coppie="$coppie" :torneo="$torneo" />
        </x-ui.card>
    @endif

    {{-- Finché il sorteggio non c'è si lavora sugli iscritti, che vengono prima della formula; poi passano in fondo. --}}
    @if ($sorteggiato)
        <x-tornei.formula :torneo="$torneo" class="mb-4" />
        <x-tornei.iscritti :torneo="$torneo" :giocatori="$giocatori" :iscritti-per-fascia="$iscrittiPerFascia" sorteggiato />
    @else
        <x-tornei.iscritti :torneo="$torneo" :giocatori="$giocatori" :iscritti-per-fascia="$iscrittiPerFascia" highlight class="mb-4" />
        <x-tornei.formula :torneo="$torneo" />
    @endif

    @unless ($sorteggiato)
        <x-giocatori.modale-nuovo :torneo="$torneo" :circoli="$circoli" />
        <x-tornei.modale-iscrizione :torneo="$torneo" :disponibili="$disponibili" />
    @endunless
    @if ($gironi->isNotEmpty())
        <x-partite.modale-risultato :torneo="$torneo" :partite="$gironi->flatMap->partite" />
    @endif
    @if ($squilibrio)
        <x-tornei.modale-prestito :torneo="$torneo" :squilibrio="$squilibrio" :rigenera="$sorteggiato"
                                  :candidati="$giocatori->where('fascia', $squilibrio->fasciaMancante)->values()" />
    @endif
</x-layouts.app>
