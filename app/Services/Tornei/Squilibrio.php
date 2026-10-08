<?php

namespace App\Services\Tornei;

/**
 * Fasce non pari tra gli iscritti a un torneo: i giocatori in più della fascia più numerosa possono
 * giocare solo con un compagno dell'altra fascia preso in prestito.
 */
final readonly class Squilibrio
{
    /**
     * @param  int  $fasciaInEccesso  fascia con più giocatori
     * @param  int  $fasciaMancante  fascia con meno giocatori: è da qui che si prendono i prestiti
     * @param  int  $inEccesso  quanti giocatori della fascia più numerosa restano senza compagno
     *                          (= quanti giocatori servono in prestito)
     * @param  string|null  $impedimento  perché il prestito non si può fare, `null` se si può
     */
    public function __construct(
        public int $fasciaInEccesso,
        public int $fasciaMancante,
        public int $inEccesso,
        public ?string $impedimento = null,
    ) {}

    public function prestitoPossibile(): bool
    {
        return $this->impedimento === null;
    }
}
