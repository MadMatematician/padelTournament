<?php

namespace App\Services\Classifica;

/**
 * Una coppia nella classifica del suo girone.
 */
final readonly class RigaClassifica
{
    public function __construct(
        public int $coppia,
        public int $posizione,
        public int $giocate,
        public int $vinte,
        public int $perse,
        public int $punti,
        public int $setVinti,
        public int $setPersi,
        public int $gameVinti,
        public int $gamePersi,
    ) {}

    public function differenzaSet(): int
    {
        return $this->setVinti - $this->setPersi;
    }

    public function differenzaGame(): int
    {
        return $this->gameVinti - $this->gamePersi;
    }
}
