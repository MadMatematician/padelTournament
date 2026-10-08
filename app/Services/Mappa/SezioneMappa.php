<?php

namespace App\Services\Mappa;

/**
 * Le rotte che condividono lo stesso prefisso (il `prefix()` del gruppo nel file di route).
 */
final readonly class SezioneMappa
{
    /**
     * @param  string  $prefisso  prefisso del gruppo senza barre (`amministrazione`, `api/test`); vuoto per le rotte alla radice
     * @param  list<VoceMappa>  $voci  nell'ordine in cui sono scritte nei file di route
     */
    public function __construct(
        public string $prefisso,
        public array $voci,
    ) {}

    /** Il prefisso come indirizzo: `/amministrazione`, `/` per la radice. */
    public function titolo(): string
    {
        return '/'.$this->prefisso;
    }
}
