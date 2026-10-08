<?php

namespace App\Enums;

/**
 * Fasi di un torneo: i gironi e i turni a eliminazione diretta. Il valore è quello salvato in
 * `partite.fase` e usato come chiave dei formati (`tornei.formati`); le etichette stanno in
 * config/tornei.php → fasi.
 */
enum Fase: string
{
    case Gironi = 'gironi';
    case Sedicesimi = 'sedicesimi';
    case Ottavi = 'ottavi';
    case Quarti = 'quarti';
    case Semifinali = 'semifinali';
    case Finale = 'finale';

    public function etichetta(): string
    {
        return config('tornei.fasi.'.$this->value, $this->value);
    }

    /** Squadre in gara in questo turno delle finali (`null` per i gironi). */
    public function squadre(): ?int
    {
        return match ($this) {
            self::Gironi => null,
            self::Sedicesimi => 32,
            self::Ottavi => 16,
            self::Quarti => 8,
            self::Semifinali => 4,
            self::Finale => 2,
        };
    }

    public function finale(): bool
    {
        return $this !== self::Gironi;
    }

    /** Il turno delle finali che si gioca con quel numero di squadre. */
    public static function perSquadre(int $squadre): self
    {
        foreach (self::finali() as $fase) {
            if ($fase->squadre() === $squadre) {
                return $fase;
            }
        }

        throw new \InvalidArgumentException('Nessun turno delle finali con '.$squadre.' squadre.');
    }

    /** Il turno che segue nelle finali (`null` dopo la finale e per i gironi). */
    public function successiva(): ?self
    {
        return $this->finale() && $this !== self::Finale
            ? self::perSquadre(intdiv($this->squadre(), 2))
            : null;
    }

    /**
     * I turni delle finali, dal più affollato alla finale.
     *
     * @return list<self>
     */
    public static function finali(): array
    {
        return [self::Sedicesimi, self::Ottavi, self::Quarti, self::Semifinali, self::Finale];
    }
}
