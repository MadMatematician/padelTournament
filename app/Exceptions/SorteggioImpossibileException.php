<?php

namespace App\Exceptions;

use DomainException;

/**
 * Il sorteggio richiesto non si può fare con i giocatori, le coppie o le regole attuali.
 * Il messaggio è scritto per l'amministratore e viene mostrato così com'è.
 */
class SorteggioImpossibileException extends DomainException {}
