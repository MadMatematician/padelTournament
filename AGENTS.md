# AGENTS.md — Torneo Padel (modulo "Sociale")

Istruzioni per gli agenti AI (Claude Code, Junie/AI Assistant di PhpStorm, Codex…) che lavorano su questo progetto.
Lingua del dominio, del codice applicativo e dei commenti: **italiano**.

## Stack
- PHP 8.5 (vincolo composer `^8.2`), Laravel 12
- PostgreSQL in produzione (`DB_CONNECTION=pgsql`); `APP_LOCALE=it`
- Frontend: Bootstrap 5 + Bootstrap Icons + jQuery, **niente Vite/SPA** — asset statici in `public/css/torneo.css`, `public/js/torneo.js`
- Componenti Blade anonimi (`resources/views/components`)

## Layout della cartella (attenzione)
- La radice contiene i file del modulo (`app/`, `config/tornei.php`, `database/`, `resources/`, `routes/web.php`, `tests/`, `public/css|js`).
- `torneo-padel/` è lo skeleton Laravel 12 pulito (`composer create-project`), con `.env` ancora su SQLite.
- Procedura prevista: copiare il contenuto della radice **dentro** `torneo-padel/` sovrascrivendo `routes/web.php`, poi impostare `.env` su pgsql.
- Finché non è fatto, `artisan`, `composer` e `phpunit` vanno lanciati da `torneo-padel/` e non vedono il codice del modulo. Non modificare `torneo-padel/vendor`.

## Comandi
```bash
php artisan migrate
php artisan db:seed --class=TorneoDemoSeeder   # torneo di prova 8+8
php artisan serve
php artisan test --filter=SorteggioCoppieTest
```

## Regole di dominio (tipologia "sociale" — `config/tornei.php`)
- Coppia = 1 giocatore di 1ª fascia + 1 di 2ª fascia (`squadra.composizione = [1 => 1, 2 => 1]`).
- A **ogni turno** le coppie vengono risorteggiate tra i giocatori ancora in gara (`coppie_ogni_turno`).
- Due giocatori che hanno già giocato insieme non vengono riaccoppiati (`vieta_coppie_ripetute`); se impossibile
  (turni finali) si accetta il minimo di ripetizioni e la coppia è marcata `ripetuta`
  (`consenti_ripetizione_se_impossibile`, `false` = `SorteggioImpossibileException`).
- Lato destra/sinistra facoltativo: `lati_complementari` = `ignora` | `preferito` (default) | `obbligatorio`.
- Eliminazione diretta: passano i giocatori delle coppie vincenti. Coppie dispari → bye sorteggiato.
- Risultato valido solo se il vincitore arriva a `risultato.set_da_vincere` set (default 2).
- Iscrizioni chiuse dal primo sorteggio; si può annullare solo l'**ultimo** turno.
- I parametri delle regole stanno in config: **non hardcodare** fasce, lati, set ecc. nel codice.

## Architettura
- `app/Contracts/GestoreTorneo.php` — interfaccia delle regole di una tipologia. Ogni tipologia in `config/tornei.php`
  dichiara il suo `handler`. Nuova tipologia = nuovo blocco in config + (se le regole cambiano) nuovo handler che
  implementa `GestoreTorneo`. I controller dialogano solo con l'interfaccia, mai con un handler concreto.
- `app/Services/Tornei/TorneoSocialeService.php` — sorteggio turno, risultati, annullamento, fine torneo (transazioni DB).
- `app/Services/Sorteggio/` — algoritmo puro, **senza dipendenze da Laravel** (niente facade, Eloquent, helper `config()`):
  - `SorteggioCoppie`: assegnamento a costo minimo (algoritmo ungherese) prima×seconda fascia; penalità per coppie
    ripetute e lati uguali, rumore casuale sempre inferiore alla penalità minima → casualità solo tra abbinamenti ammessi.
  - `SorteggioAvversari`: estrazione incontri + bye.
  - DTO: `GiocatoreSorteggio`, `CoppiaSorteggiata`, `OpzioniSorteggio`.
- `app/Models`: Torneo, Giocatore, Turno, Coppia, Partita. Eccezioni di dominio in `app/Exceptions`.
- Validazione sempre in Form Request (`app/Http/Requests`), controller sottili.
- Rotte in `routes/web.php`: `tornei` (resource, parametro `{torneo}`), annidate `tornei/{torneo}/giocatori|turni|partite`.

## Convenzioni per le view (vincolanti)
- I file **pagina** (`resources/views/{tornei,giocatori,turni}/*.blade.php`) contengono **solo** componenti `<x-…>` e
  direttive Blade: **nessun tag HTML**.
- L'HTML (markup Bootstrap) sta **solo** in `components/ui/*` (libreria UI generica) e `components/layouts/*`.
- I componenti di dominio (`components/{tornei,giocatori,turni,partite,coppie}`) usano solo `<x-ui.*>` e direttive;
  logica minima in `@props`/`@php`.
- Se serve un elemento HTML nuovo, si crea/estende un componente `x-ui.*` riutilizzabile (props + `$attributes->class()`),
  non si scrive HTML nella pagina.
- Icone: Bootstrap Icons tramite prop `icon` (`bi bi-{icon}`).
- JS: jQuery in `public/js/torneo.js` (conferme, filtro tabelle, salvataggio risultati via AJAX); nessun JS inline.
- Testi UI in italiano.

## Test
- `tests/Unit/SorteggioCoppieTest.php` usa `PHPUnit\Framework\TestCase` puro: ogni modifica al sorteggio deve
  mantenere verdi questi test e aggiungerne per i nuovi casi.
- Proprietà da preservare: ogni giocatore in una sola coppia; nessuna ripetizione se evitabile; ripetizione minima
  o eccezione quando inevitabile; preferenza lati complementari; fasce sbilanciate rifiutate; bye con coppie dispari.

## Stile
- PSR-12 / Laravel Pint; tipizzazione stretta (tipi di ritorno, property promotion, `readonly` sui DTO).
- Nomi di classi, metodi e variabili di dominio in italiano (`sorteggiaTurno`, `registraRisultato`, `turniNecessari`).
- Migrazioni nuove: non modificare quelle già eseguite, aggiungerne di nuove.
