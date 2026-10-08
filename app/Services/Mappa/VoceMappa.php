<?php

namespace App\Services\Mappa;

/**
 * Una rotta nella mappa del sito.
 */
final readonly class VoceMappa
{
    /**
     * @param  list<string>  $metodi  metodi HTTP accettati (senza HEAD)
     * @param  string  $indirizzo  percorso com'è scritto nei file di route, con i parametri: `/amministrazione/tornei/{torneo}`
     * @param  string|null  $nome  nome della rotta
     * @param  string|null  $descrizione  cosa fa la destinazione: `->descrizione()` nel file di route, altrimenti il commento del controller
     * @param  string  $destinazione  controller (e metodo) che risponde, senza `App\Http\Controllers\`
     * @param  string  $accesso  chi può usarla: «Tutti», «Solo ospiti», «Utenti autenticati» o i ruoli ammessi
     * @param  bool  $soloSviluppo  rotta di test: risponde solo negli ambienti di `tornei.test.ambienti`
     * @param  string|null  $link  indirizzo da aprire: solo per le pagine (GET) che non chiedono parametri
     */
    public function __construct(
        public array $metodi,
        public string $indirizzo,
        public ?string $nome,
        public ?string $descrizione,
        public string $destinazione,
        public string $accesso,
        public bool $soloSviluppo,
        public ?string $link,
    ) {}
}
