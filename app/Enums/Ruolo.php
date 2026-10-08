<?php

namespace App\Enums;

/**
 * Ruoli applicativi. Il valore è quello salvato in `users.ruolo`
 * e usato come parametro del middleware `ruolo:` nelle rotte.
 */
enum Ruolo: string
{
    case Amministratore = 'amministratore';
    case Utente = 'utente';

    public function etichetta(): string
    {
        return match ($this) {
            self::Amministratore => 'Amministratore',
            self::Utente => 'Utente',
        };
    }

    /** Nome della rotta a cui si atterra dopo l'accesso. */
    public function rottaIniziale(): string
    {
        return match ($this) {
            self::Amministratore => 'amministrazione.dashboard',
            self::Utente => 'area-utente.dashboard',
        };
    }

    /** Voce di menu della pagina iniziale. */
    public function etichettaRottaIniziale(): string
    {
        return match ($this) {
            self::Amministratore => 'Amministrazione',
            self::Utente => 'Area utente',
        };
    }

    /** Nome Bootstrap Icons (senza prefisso `bi-`). */
    public function icona(): string
    {
        return match ($this) {
            self::Amministratore => 'shield-lock',
            self::Utente => 'person',
        };
    }
}
