<?php

namespace App\Services\Finali;

/**
 * Come si confrontano coppie di gironi diversi per i ripescaggi e per le teste di serie
 * (config/tornei.php → finali.ripescaggi). I valori sono medie per partita giocata,
 * perché i gironi possono avere un numero diverso di squadre.
 */
enum CriterioRipescaggio: string
{
    /** Media punti, poi differenza set, poi differenza game. */
    case Punti = 'punti';

    /** Differenza game media, poi differenza set, poi punti. */
    case Game = 'game';

    /** I ripescaggi si estraggono a sorte tra le coppie con lo stesso piazzamento. */
    case Sorteggio = 'sorteggio';
}
