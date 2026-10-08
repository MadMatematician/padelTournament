<?php

namespace App\Exceptions;

use DomainException;

/**
 * Il risultato di una partita non si può registrare o cancellare in questo momento
 * (coppie non ancora note, tabellone già generato, turno successivo già giocato…).
 * Il messaggio è scritto per l'amministratore e viene mostrato così com'è.
 */
class RisultatoNonConsentitoException extends DomainException {}
