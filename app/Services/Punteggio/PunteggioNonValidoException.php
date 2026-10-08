<?php

namespace App\Services\Punteggio;

use DomainException;

/**
 * Il punteggio inserito non rispetta il formato della partita.
 * `set` è l'indice (da 0) del set a cui si riferisce l'errore, `null` se riguarda tutta la partita.
 */
final class PunteggioNonValidoException extends DomainException
{
    public function __construct(string $messaggio, public readonly ?int $set = null)
    {
        parent::__construct($messaggio);
    }
}
