<?php

namespace App\Services\Sorteggio;

/**
 * Una coppia uscita dal sorteggio: un giocatore per fascia.
 * `prestito` è l'identificativo del giocatore che in questa coppia gioca in prestito
 * (ha già la sua coppia e ne completa una seconda), `null` se la coppia è ordinaria.
 * `ripetuta` segnala, nei nuovi sorteggi delle finali, due giocatori che avevano già fatto coppia nel torneo.
 */
final readonly class CoppiaSorteggiata
{
    public function __construct(
        public int $prima,
        public int $seconda,
        public ?int $prestito = null,
        public bool $ripetuta = false,
    ) {}
}
