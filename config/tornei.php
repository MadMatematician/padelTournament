<?php

/*
|--------------------------------------------------------------------------
| Tornei e giocatori
|--------------------------------------------------------------------------
|
| Parametri di dominio: tipologie di torneo con le loro regole, fasce e lati
| dei giocatori. Form, validazione ed etichette leggono da qui: fasce, lati,
| numero di turni ecc. non vanno scritti nel codice.
|
*/

return [

    // Tipologia proposta alla creazione di un torneo.
    'tipologia_predefinita' => 'sociale',

    'tipologie' => [

        'sociale' => [
            'etichetta' => 'Sociale',

            // Una coppia = un giocatore di 1ª fascia + uno di 2ª (fascia => quanti).
            // Il sorteggio delle coppie prevede esattamente due fasce, un giocatore ciascuna, in quest'ordine.
            'squadra' => [
                'composizione' => [1 => 1, 2 => 1],
            ],

            'sorteggio' => [
                // Nelle finali le coppie si rifanno a ogni turno tra chi è ancora in gara (il codice prevede solo
                // questo caso). Come: caso di App\Services\Finali\CoppieNelleFinali.
                //   'scambio' = si sorteggia quali coppie si incontrano e le due si scambiano i compagni:
                //               nessuna coppia si ripete mai, finale compresa;
                //   'libero'  = sorteggio libero tra tutti i giocatori rimasti: più vario, ma in finale (quattro
                //               giocatori, due sole coppie possibili) una coppia già vista è spesso inevitabile.
                'coppie_ogni_turno' => true,
                'finali' => 'scambio',
                // Chi ha già giocato insieme nel torneo non viene riaccoppiato…
                'vieta_coppie_ripetute' => true,
                // …a meno che sia inevitabile: la coppia viene segnalata "ripetuta". Con `false` il risultato che
                // la renderebbe necessaria viene rifiutato.
                'consenti_ripetizione_se_impossibile' => true,
                // Quanto contano i lati preferiti nel sorteggio delle coppie (chiave di `lati_complementari`, sotto):
                // è il valore dei tornei nuovi, poi si sceglie torneo per torneo nella scheda "Formula".
                // Deve coincidere con il default della colonna `tornei.lati_complementari`.
                'lati_complementari' => 'ignora',
            ],

            'avanzamento' => 'eliminazione_diretta',
            'bye' => true,
        ],

    ],

    // Numero di turni ammesso alla creazione di un torneo.
    'turni' => [
        'minimo' => 1,
        'massimo' => 10,
        'predefinito' => 3,
    ],

    // Lati preferiti (destra / sinistra) nel sorteggio delle coppie: valore salvato in `tornei.lati_complementari`
    // => etichetta. Le chiavi sono i casi di App\Services\Sorteggio\LatiComplementari.
    'lati_complementari' => [
        'ignora' => 'Non contano (tutto a caso)',
        'preferito' => 'Preferisci destra + sinistra',
        'obbligatorio' => 'Solo destra + sinistra',
    ],

    // Gironi: i valori scelti per ogni torneo si impostano nella sua pagina di gestione ("Formula").
    'gironi' => [
        // Numero massimo di squadre (coppie) in un girone: il numero di gironi dipende da quante coppie ci sono.
        // `predefinito` deve coincidere con il default della colonna `tornei.squadre_per_girone`.
        'squadre' => [
            'minimo' => 2,
            'massimo' => 16,
            'predefinito' => 3,
        ],

        // Valore salvato in `tornei.andata_ritorno` => etichetta. Le partite del girone sono sempre all'italiana.
        'formule' => [
            0 => 'Sola andata',
            1 => 'Andata e ritorno',
        ],
    ],

    // Fasi di un torneo: valore salvato in `partite.fase` (App\Enums\Fase) => etichetta.
    // Dopo i gironi, i turni a eliminazione diretta: si parte da quello che contiene le squadre qualificate.
    'fasi' => [
        'gironi' => 'Gironi',
        'sedicesimi' => 'Sedicesimi di finale',
        'ottavi' => 'Ottavi di finale',
        'quarti' => 'Quarti di finale',
        'semifinali' => 'Semifinali',
        'finale' => 'Finale',
    ],

    // Formato delle partite: si sceglie fase per fase nella scheda "Formula" (colonna `tornei.formati`).
    'formato' => [
        // Set di una partita, "al meglio di": vince chi ne conquista più della metà.
        'set' => [
            1 => 'Set unico',
            3 => 'Al meglio di 3',
            5 => 'Al meglio di 5',
        ],

        // Game per vincere un set.
        'game' => [
            'minimo' => 3,
            'massimo' => 9,
        ],

        // Tie-break sul pari: valore del form => etichetta. "Standard" vale `punti_tie_break.standard` punti.
        'tie_break' => [
            'no' => 'No',
            'standard' => 'Standard',
            'personalizzato' => 'Personalizzato',
        ],
        'punti_tie_break' => [
            'standard' => 7,
            'minimo' => 3,
            'massimo' => 21,
        ],

        // Formato di una fase finché non viene impostato nel torneo: set unico a 4 game, senza tie-break.
        'predefinito' => [
            'set' => 1,
            'game' => 4,
            'tie_break' => false,
            // Punti del tie-break quando una fase lo prevede.
            'punti_tie_break' => 7,
            // Vale solo senza tie-break. `false`: il set va a chi arriva per primo ai game previsti (a 4 game: 4-3).
            // `true`: sul pari si continua finché una coppia ha due game di scarto (5-3, 6-4…).
            'due_game_scarto' => false,
        ],

        // Fasi che fanno eccezione al predefinito (chiave di `fasi` => solo le voci che cambiano).
        // La finale è l'unica con il tie-break: al meglio di 3 set a 6 game.
        'predefinito_per_fase' => [
            'finale' => [
                'set' => 3,
                'game' => 6,
                'tie_break' => true,
            ],
        ],
    ],

    // Classifica dei gironi.
    'classifica' => [
        'punti' => [
            'vittoria' => 2,
            'sconfitta' => 0,
        ],

        // Migliori giocatori per categoria (pulsanti nella pagina "Classifica").
        // Ogni categoria è un dato dell'anagrafica dei giocatori:
        //   `attributo` = come si legge da App\Models\Giocatore (colonna, cast o accessor);
        //   `valori`    = chiave di configurazione con le etichette "valore => etichetta" (decide anche l'ordine);
        //   `nome`, `icona` = testo e icona del pulsante.
        // Se cambia il nome di una colonna in anagrafica, si corregge qui e basta.
        'migliori' => [
            // Posizioni mostrate per ogni categoria (i pari merito sull'ultima ci sono tutti).
            'quanti' => 3,
            'categorie' => [
                'fascia_eta' => [
                    'nome' => 'fascia d\'età',
                    'icona' => 'cake2',
                    'attributo' => 'fascia_eta',
                    'valori' => 'tornei.giocatori.fasce_eta',
                ],
                'genere' => [
                    'nome' => 'genere',
                    'icona' => 'gender-ambiguous',
                    'attributo' => 'genere',
                    'valori' => 'tornei.giocatori.generi',
                ],
            ],
        ],
    ],

    // Accesso alle finali: i valori scelti per ogni torneo si impostano nella scheda "Formula".
    'finali' => [
        // Squadre che accedono da ogni girone. `predefinito` coincide con il default di `tornei.qualificate_per_girone`.
        'qualificate' => [
            'minimo' => 1,
            'massimo' => 8,
            'predefinito' => 2,
        ],

        // Se le qualificate non bastano a riempire il primo turno, i posti che mancano vanno alle migliori escluse.
        // Valore salvato in `tornei.criterio_ripescaggio` (App\Services\Finali\CriterioRipescaggio) => etichetta.
        // I confronti tra gironi diversi usano le medie per partita giocata.
        'ripescaggi' => [
            'punti' => 'Punti, poi differenza set e game',
            'game' => 'Differenza game, poi set e punti',
            'sorteggio' => 'A sorte',
        ],
        'ripescaggio_predefinito' => 'punti',

        // Numero massimo di squadre del primo turno (32 = sedicesimi).
        'squadre_massime' => 32,
    ],

    'giocatori' => [
        // Valore salvato in `giocatori.fascia` => etichetta.
        'fasce' => [
            1 => 'Prima fascia',
            2 => 'Seconda fascia',
        ],

        // Valore salvato in `giocatori.lato` => etichetta. Il lato è facoltativo.
        'lati' => [
            'destra' => 'Destra',
            'sinistra' => 'Sinistra',
        ],

        // Valore salvato in `giocatori.genere` => etichetta. Obbligatorio per i nuovi giocatori.
        'generi' => [
            'm' => 'Uomo',
            'f' => 'Donna',
        ],

        // Età ammessa (anni compiuti nell'anno in corso): ne derivano i limiti dell'anno di nascita,
        // obbligatorio per i nuovi giocatori. Serve a intercettare gli errori di battitura (1895, 2085, 85…).
        'eta' => [
            'minima' => 5,
            'massima' => 100,
        ],

        // Quanti giocatori si possono inserire con un solo invio del form a righe.
        'massimo_per_inserimento' => 50,
    ],

    // Rotte di test (/test/{giocatori?} e /api/test/{giocatori?}): creano tornei finti con le factory,
    // già con tutti i giocatori iscritti. Servono a provare sorteggio, gironi e finali senza inserire dati a mano.
    'test' => [
        // Ambienti (APP_ENV) in cui le rotte rispondono: in tutti gli altri danno 404. Mai `production`.
        'ambienti' => ['local', 'testing'],

        // Giocatori iscritti al torneo generato: il numero si indica nel link, altrimenti vale `predefinito`.
        'giocatori' => [
            'minimo' => 2,
            'massimo' => 128,
            'predefinito' => 16,
        ],
    ],

];
