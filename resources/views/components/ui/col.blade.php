{{--
    Colonna della griglia. Props: sm, md, lg (numero di colonne su 12 da quel breakpoint in su),
    grow (da 1200px in su la colonna esce dalla griglia a 12 e prende una parte della riga in proporzione
    a questo numero: per le righe con tanti campi che in dodicesimi non starebbero su una riga sola;
    va messo su tutte le colonne della riga).
--}}
@props(['sm' => null, 'md' => null, 'lg' => null, 'grow' => null])
<div {{ $attributes->class(['col-12', 'col-sm-'.$sm => $sm, 'col-md-'.$md => $md, 'col-lg-'.$lg => $lg, 'col-xl-proporzionale' => $grow]) }}
     @if ($grow) style="--proporzione: {{ (float) $grow }}" @endif>
    {{ $slot }}
</div>
