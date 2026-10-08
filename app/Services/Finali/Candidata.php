<?php

namespace App\Services\Finali;

use App\Services\Classifica\RigaClassifica;

/**
 * Una coppia che accede (o potrebbe accedere) alle finali.
 * `livello` è il piazzamento utile nel suo girone: 1 per la prima tra le coppie che possono accedere, 2 per la seconda…
 * (coincide con la posizione in classifica quando davanti non ci sono coppie escluse).
 */
final readonly class Candidata
{
    public function __construct(
        public int $coppia,
        public int $girone,
        public int $livello,
        public RigaClassifica $riga,
    ) {}
}
