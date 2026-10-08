<?php

namespace App\Services\Finali;

/**
 * Come si formano le coppie a ogni turno delle finali (config/tornei.php → tipologie.*.sorteggio.finali).
 */
enum CoppieNelleFinali: string
{
    /**
     * Si sorteggia quali coppie si incontrano e le due coppie si scambiano i compagni: nessuno gioca mai due
     * volte con lo stesso compagno, finale compresa.
     */
    case Scambio = 'scambio';

    /**
     * Sorteggio libero tra tutti i giocatori rimasti, evitando le coppie già viste finché è possibile:
     * negli ultimi turni, e in finale spesso, una coppia può doversi ripetere.
     */
    case Libero = 'libero';
}
