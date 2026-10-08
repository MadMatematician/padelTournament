<?php

namespace App\Services\Sorteggio;

/**
 * Quanto conta, nel formare le coppie, che i due giocatori preferiscano lati diversi
 * (config/tornei.php → tipologie.*.sorteggio.lati_complementari).
 */
enum LatiComplementari: string
{
    /** I lati non contano: abbinamento del tutto casuale. */
    case Ignora = 'ignora';

    /** Il maggior numero possibile di coppie destra + sinistra; le altre si formano comunque. */
    case Preferito = 'preferito';

    /** Solo coppie destra + sinistra (o con un giocatore indifferente): se non è possibile, errore. */
    case Obbligatorio = 'obbligatorio';
}
