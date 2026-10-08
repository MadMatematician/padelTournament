<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Indirizzi delle risorse statiche servite in locale (public/), con la
 * release dichiarata a mano in config/risorse.php in coda come ?v=<release>.
 */
final class Risorse
{
    /**
     * @param  string  $dipendenza  chiave di config/risorse.php → release (es. "bootstrap", "app")
     * @param  string  $percorso  percorso del file dentro public/ (es. "css/torneo.css")
     */
    public static function url(string $dipendenza, string $percorso): string
    {
        return asset($percorso).'?v='.rawurlencode(self::release($dipendenza));
    }

    public static function release(string $dipendenza): string
    {
        $release = config('risorse.release')[$dipendenza] ?? null;

        if (! is_string($release) || $release === '') {
            throw new InvalidArgumentException(
                "Release non dichiarata per la dipendenza \"{$dipendenza}\": aggiungila in config/risorse.php."
            );
        }

        return $release;
    }
}
