<?php

namespace App\Services\Punteggio;

/**
 * Formato delle partite di una fase: quanti set (al meglio di), quanti game per set,
 * se sul pari si gioca il tie-break e a quanti punti e, quando il tie-break non c'è,
 * se per vincere il set servono due game di scarto.
 */
final readonly class FormatoPartita
{
    /**
     * @param  bool  $dueGameDiScarto  conta solo senza tie-break: `false` = il set va a chi arriva per primo ai game
     *                                 previsti (4-3 a 4 game); `true` = si continua finché una coppia ha due game in più
     */
    public function __construct(
        public int $set,
        public int $game,
        public bool $tieBreak,
        public int $puntiTieBreak,
        public bool $dueGameDiScarto = false,
    ) {}

    /** Il formato a parole: "Al meglio di 3 set a 6 game, tie-break a 7 punti". */
    public function descrizione(): string
    {
        return ($this->set === 1 ? 'Set unico' : 'Al meglio di '.$this->set.' set')
            .' a '.$this->game.' game, '
            .match (true) {
                $this->tieBreak => 'tie-break a '.$this->puntiTieBreak.' punti',
                $this->dueGameDiScarto => 'senza tie-break (due game di scarto)',
                default => 'senza tie-break (vince chi arriva per primo a '.$this->game.' game)',
            };
    }

    /** Set che servono per vincere la partita. */
    public function setDaVincere(): int
    {
        return intdiv($this->set, 2) + 1;
    }
}
