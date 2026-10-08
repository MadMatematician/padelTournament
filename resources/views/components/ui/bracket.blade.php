{{--
    Tabellone a eliminazione diretta: una colonna per turno, da sinistra a destra. Le linee raccolgono gli
    incontri di un turno e li portano agli incontri del turno successivo. Su schermi stretti scorre in orizzontale.
    Slot: un <x-ui.bracket-round> per turno, dal primo alla finale.
--}}
<div {{ $attributes->class(['tabellone']) }} tabindex="0" role="group" aria-label="Tabellone delle finali">
    {{ $slot }}
</div>
