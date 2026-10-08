<?php

namespace App\Services\Finali;

/**
 * Chi accede alle finali e con quale tabellone.
 *
 * Nelle finali le coppie si risorteggiano a ogni turno: quelle che accedono portano i loro due giocatori.
 * Fanno eccezione le coppie che passano il primo turno senza giocare, che restano com'erano nei gironi
 * fino al sorteggio del secondo turno.
 */
final readonly class AccessoFinali
{
    /**
     * @param  list<Candidata>  $dirette  qualificate per piazzamento, in ordine di testa di serie
     * @param  list<Candidata>  $ripescate  ripescate per completare il primo turno, in ordine di ripescaggio
     * @param  int  $dimensione  squadre del primo turno (potenza di due: 2, 4, 8, 16, 32)
     */
    public function __construct(
        public array $dirette,
        public array $ripescate,
        public int $dimensione,
    ) {}

    /**
     * Le teste di serie, dalla numero 1 in giù: prima le qualificate, poi le ripescate.
     *
     * @return list<Candidata>
     */
    public function teste(): array
    {
        return [...$this->dirette, ...$this->ripescate];
    }

    /** Posti del primo turno rimasti vuoti: le prime teste di serie passano il turno senza giocare. */
    public function bye(): int
    {
        return $this->dimensione - count($this->dirette) - count($this->ripescate);
    }

    /**
     * Le coppie che passano il primo turno senza giocare: le prime teste di serie, una per posto vuoto.
     *
     * @return list<Candidata>
     */
    public function passano(): array
    {
        return array_slice($this->teste(), 0, $this->bye());
    }

    /**
     * Le coppie i cui giocatori vengono risorteggiati per il primo turno: tutte le altre.
     *
     * @return list<Candidata>
     */
    public function giocano(): array
    {
        return array_slice($this->teste(), $this->bye());
    }

    /** Posti da riempire con i ripescaggi (quelli che servivano, anche se non c'erano abbastanza coppie). */
    public function ripescaggiNecessari(): int
    {
        return $this->dimensione - count($this->dirette);
    }
}
