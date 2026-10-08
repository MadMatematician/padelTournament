<?php

namespace App\Services\Sorteggio;

/**
 * Un giocatore come lo vede il sorteggio: identificativo e lato preferito (`null` = indifferente).
 */
final readonly class GiocatoreSorteggio
{
    public function __construct(
        public int $id,
        public ?string $lato = null,
    ) {}

    /** Due giocatori sono complementari se non hanno dichiarato lo stesso lato. */
    public function complementare(self $altro): bool
    {
        return $this->lato === null || $altro->lato === null || $this->lato !== $altro->lato;
    }
}
