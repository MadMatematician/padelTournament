<?php

namespace App\Services\Punteggio;

/**
 * Un risultato valido: i set giocati, chi ha vinto, i totali di set e game delle due coppie.
 */
final readonly class EsitoPartita
{
    /**
     * @param  list<array{a: int, b: int, tb_a: int|null, tb_b: int|null}>  $set  i set nell'ordine in cui sono stati giocati
     * @param  'a'|'b'  $vincitore
     */
    public function __construct(
        public array $set,
        public string $vincitore,
        public int $setA,
        public int $setB,
        public int $gameA,
        public int $gameB,
    ) {}
}
