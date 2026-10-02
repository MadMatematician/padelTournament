# Torneo Padel — modulo "Sociale"

Laravel 12 · PHP 8.5 · PostgreSQL · Bootstrap 5 + jQuery · Blade components

## Installazione (su un progetto Laravel 12 nuovo)

```bash
composer create-project laravel/laravel:^12.0 torneo-padel
# copia sopra al progetto il contenuto di questo archivio (sovrascrivi routes/web.php)
```

`.env`:

```
APP_NAME="Torneo Padel"
APP_LOCALE=it
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=torneo_padel
DB_USERNAME=...
DB_PASSWORD=...
```

```bash
php artisan migrate
php artisan db:seed --class=TorneoDemoSeeder   # facoltativo: torneo di prova 8+8
php artisan serve
```

Per i messaggi di validazione in italiano: `php artisan lang:publish` e traduci `lang/it/validation.php`
(oppure il pacchetto `laravel-lang/lang`).

## Regole (config/tornei.php → tipologie.sociale)

| Chiave | Default | Significato |
|---|---|---|
| `squadra.composizione` | `[1 => 1, 2 => 1]` | un giocatore di 1ª e uno di 2ª fascia |
| `sorteggio.vieta_coppie_ripetute` | `true` | chi ha già giocato insieme non viene riaccoppiato |
| `sorteggio.consenti_ripetizione_se_impossibile` | `true` | nei turni finali, se nessun abbinamento evita ripetizioni, si accetta il minimo e la coppia è segnalata "ripetuta"; `false` = errore |
| `sorteggio.lati_complementari` | `preferito` | `ignora` / `preferito` (massimizza coppie destra+sinistra) / `obbligatorio` |
| `avanzamento` | `eliminazione_diretta` | passano al turno successivo i giocatori delle coppie vincenti |
| `bye` | `true` | con coppie dispari una coppia sorteggiata passa il turno |
| `risultato.set_da_vincere` | `2` | risultato valido solo se il vincitore arriva a N set |

## Come funziona il sorteggio

`App\Services\Sorteggio\SorteggioCoppie` risolve un assegnamento a costo minimo (algoritmo ungherese)
tra giocatori di 1ª e 2ª fascia ancora in gara: le coppie già formate e i lati uguali sono penalizzati
(o vietati), e un rumore casuale, sempre inferiore alla penalità minima, rende l'estrazione casuale tra
gli abbinamenti ammessi. Ne segue che: se esiste un abbinamento senza ripetizioni viene sempre trovato,
e tra questi si sceglie quello col maggior numero di coppie destra/sinistra.
`SorteggioAvversari` estrae poi gli incontri.

Le classi di sorteggio non dipendono da Laravel (test in `tests/Unit/SorteggioCoppieTest.php`).

## Struttura

- `app/Contracts/GestoreTorneo.php` — interfaccia delle regole di una tipologia; `handler` in config
- `app/Services/Tornei/TorneoSocialeService.php` — sorteggio turno, risultati, annullamento, fine torneo
- `app/Models` — Torneo, Giocatore, Turno, Coppia, Partita
- `resources/views/components/ui` e `components/layouts` — **unici file con HTML** (libreria Bootstrap)
- `resources/views/components/{tornei,giocatori,turni,partite,coppie}` — componenti di dominio, solo `<x-ui.*>`
- `resources/views/{tornei,giocatori,turni}` — pagine, solo componenti e direttive Blade
- `public/js/torneo.js` — jQuery: conferme, filtro tabella, salvataggio risultati via AJAX

## Pagine

- `/tornei` — elenco e creazione (nome, tipologia, numero di turni)
- `/tornei/{id}/giocatori` — iscrizione (fascia obbligatoria; lato, sesso, fascia d'età facoltativi),
  riepilogo per fascia, turni necessari, modifica numero turni. Iscrizioni chiuse dal primo sorteggio.
- `/tornei/{id}/turni` — sorteggio coppie e avversari, inserimento risultati, annullamento ultimo sorteggio,
  coppia vincitrice
