<?php

namespace App\Services\Classifica;

/**
 * Il rendimento di un giocatore nel torneo: fin dove è arrivato nelle finali e come sono andate le partite
 * che ha giocato, nei gironi e nelle finali, con qualunque compagno.
 *
 * I confronti tra giocatori usano le medie per partita: non tutti giocano lo stesso numero di partite
 * (gironi di dimensioni diverse, giocatori in prestito in due coppie, chi va avanti nelle finali).
 */
final readonly class RendimentoGiocatore
{
    /** Valore di `turno` per chi non è entrato nelle finali. */
    public const FUORI_DALLE_FINALI = -1;

    /**
     * @param  int  $turno  ordine dell'ultimo turno delle finali raggiunto (0 = sedicesimi … 4 = finale, 5 = ha vinto
     *                      la finale); `FUORI_DALLE_FINALI` se non vi è entrato
     */
    public function __construct(
        public int $giocatore,
        public int $posizione,
        public int $turno,
        public int $giocate,
        public int $vinte,
        public int $setVinti,
        public int $setPersi,
        public int $gameVinti,
        public int $gamePersi,
    ) {}

    public function perse(): int
    {
        return $this->giocate - $this->vinte;
    }

    public function differenzaSet(): int
    {
        return $this->setVinti - $this->setPersi;
    }

    public function differenzaGame(): int
    {
        return $this->gameVinti - $this->gamePersi;
    }

    /** Quota di partite vinte, da 0 a 1 (0 se non ne ha giocate). */
    public function quotaVittorie(): float
    {
        return $this->perPartita($this->vinte);
    }

    /** Ha qualcosa che lo distingue: una partita giocata o un posto nelle finali. */
    public function inClassifica(): bool
    {
        return $this->giocate > 0 || $this->turno !== self::FUORI_DALLE_FINALI;
    }

    /**
     * Ciò che decide la posizione, dal criterio più importante al meno: turno raggiunto nelle finali, quota di
     * vittorie, differenza set, differenza game e game vinti per partita. Più alto è meglio.
     *
     * @return array{int, float, float, float, float}
     */
    public function criteri(): array
    {
        return [
            $this->turno,
            $this->quotaVittorie(),
            $this->perPartita($this->differenzaSet()),
            $this->perPartita($this->differenzaGame()),
            $this->perPartita($this->gameVinti),
        ];
    }

    public function conPosizione(int $posizione): self
    {
        return new self(
            $this->giocatore, $posizione, $this->turno, $this->giocate, $this->vinte,
            $this->setVinti, $this->setPersi, $this->gameVinti, $this->gamePersi,
        );
    }

    private function perPartita(int $totale): float
    {
        return $this->giocate === 0 ? 0.0 : round($totale / $this->giocate, 6);
    }
}
