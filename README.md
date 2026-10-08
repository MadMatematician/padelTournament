# Torneo Padel — modulo "Sociale"

Laravel 12 · PHP 8.5 · PostgreSQL · Bootstrap 5 + jQuery · Blade components

## Prima installazione

Prerequisiti:

- PHP 8.2+ (sviluppato con 8.5) con le estensioni `pdo_pgsql` e `pgsql` abilitate in `php.ini`
- Composer
- PostgreSQL in esecuzione (XAMPP non lo include: va installato a parte) e `psql` nel PATH
- Git Bash, per lanciare lo script `.sh` su Windows

### 1. Dipendenze e `.env`

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Nel `.env` imposta:

```
APP_NAME="Torneo Padel"
APP_LOCALE=it
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=padelTournament
DB_USERNAME=padelLocal
DB_PASSWORD=...
```

Attenzione: la connessione si chiama `pgsql` (non `postgres`) e la porta di PostgreSQL è `5432` (`3306` è MySQL).
Le righe non devono avere spazi iniziali.

### 2. Creare utente e database PostgreSQL

Utente e database non vengono creati da `php artisan migrate`: si creano una volta sola, partendo dai valori
`DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` del `.env`, con gli script della cartella `scripts/`.

```bash
# genera gli script SQL a partire dal .env
./scripts/create_db_from_env.sh .env

# li esegue come superuser postgres (viene chiesta la password di postgres)
psql -U postgres -h 127.0.0.1 -p 5432 -f scripts/create_db_and_user.generated.sql
```

Il primo comando crea due file in `scripts/`:

| File | Cosa fa |
|---|---|
| `create_db_and_user.generated.sql` | crea utente e database e assegna i privilegi |
| `drop_db_and_user.generated.sql` | **elimina** database e utente (per ripartire da zero) |

Lo script di creazione:

1. crea il ruolo `DB_USERNAME` con login e la password del `.env`, se non esiste;
2. crea il database `DB_DATABASE` (UTF8) con quel ruolo come proprietario, se non esiste;
3. assegna al ruolo tutti i privilegi sul database e sullo schema `public`.

Si può rieseguire senza danni: ciò che esiste già viene lasciato com'è. Se il ruolo esiste già, la sua password
**non** viene modificata.

Verifica che l'utente dell'applicazione riesca a collegarsi:

```bash
psql -U padelLocal -h 127.0.0.1 -p 5432 -d padelTournament -c "select current_user, current_database()"
```

Note:

- i file `*.generated.sql` contengono la password in chiaro: sono esclusi da git (`.gitignore`) e non vanno condivisi;
- i nomi con lettere maiuscole (`padelTournament`, `padelLocal`) in psql e pgAdmin vanno scritti tra virgolette
  doppie, es. `\c "padelTournament"`;
- per eliminare tutto e ricominciare: `psql -U postgres -h 127.0.0.1 -p 5432 -f scripts/drop_db_and_user.generated.sql`.

In alternativa, a mano da `psql -U postgres`:

```sql
CREATE ROLE "padelLocal" WITH LOGIN PASSWORD 'la-password-del-.env';
CREATE DATABASE "padelTournament" OWNER "padelLocal" ENCODING 'UTF8' TEMPLATE template0;
GRANT ALL PRIVILEGES ON DATABASE "padelTournament" TO "padelLocal";
```

### 3. Tabelle e avvio

```bash
php artisan config:clear
php artisan migrate
php artisan db:seed                            # crea l'amministratore iniziale: admin / admin
php artisan serve
```

Poi apri l'applicazione e accedi con **admin / admin** (vedi [Accesso e ruoli](#accesso-e-ruoli)).

I messaggi di validazione in italiano sono in `lang/it` e si attivano con `APP_LOCALE=it` nel `.env`.

### Errori frequenti

| Errore | Causa e rimedio |
|---|---|
| `Database connection [postgres] not configured` | nel `.env` serve `DB_CONNECTION=pgsql` |
| `received invalid response to SSL negotiation` (porta 3306) | sulla 3306 risponde MySQL: imposta `DB_PORT=5432` |
| `could not find driver` | abilita `extension=pdo_pgsql` e `extension=pgsql` in `php.ini` |
| `Connection refused` | PostgreSQL non è in esecuzione o ascolta su un'altra porta |
| `database "..." does not exist` / `password authentication failed` | utente o database non ancora creati: esegui il passo 2 |

## Accesso e ruoli

Si accede con **nome utente e password** (non con l'email). Senza login non si vede nulla: la radice `/` manda al
login chi non è autenticato e alla pagina iniziale del proprio ruolo chi lo è.

| Ruolo | Valore in `users.ruolo` | Pagina iniziale | Come si ottiene |
|---|---|---|---|
| Amministratore | `amministratore` | `/amministrazione` | `php artisan db:seed` crea `admin` / `admin` |
| Utente | `utente` | `/area-utente` | registrazione da `/registrazione` |

- **Amministratore iniziale**: `admin` / `admin` è una credenziale provvisoria per lo sviluppo. `AdminSeeder` non la
  crea in produzione e non tocca un utente `admin` già esistente. Va cambiata prima di esporre l'applicazione.
- **Registrazione** (`/registrazione`): nome, nome utente, email, password. Chi si registra è sempre `utente`;
  il ruolo non arriva mai dal form.
- **Password sicura**: i requisiti stanno in `config/sicurezza.php` (default: almeno 10 caratteri, maiuscole e
  minuscole, un numero, un simbolo) e valgono sia per la validazione sul server sia per l'elenco mostrato nel form.
  `PASSWORD_VERIFICA_VIOLAZIONI=true` nel `.env` rifiuta anche le password comparse in violazioni note (richiede rete).
- **Nome utente**: 3–30 caratteri tra lettere, numeri, punto, trattino e trattino basso; salvato in minuscolo.
- **Tentativi di accesso**: dopo 5 errori per nome utente + IP l'accesso resta bloccato per 60 secondi.
- **Proteggere una rotta**: `->middleware(['auth', 'ruolo:amministratore'])` (più ruoli: `ruolo:amministratore,utente`).
  Un ruolo non ammesso riceve la pagina 403 "Accesso negato".

Per aggiungere un ruolo: nuovo `case` in `App\Enums\Ruolo` (etichetta, icona, rotta iniziale) e relativo gruppo di
rotte in `routes/web.php`.

## Amministrazione: tornei e giocatori

Dalla pagina `/amministrazione` l'amministratore crea ed elimina tornei e giocatori. I due elenchi sono tabelle
DataTables: ricerca, ordinamento per colonna e paginazione (10, 25, 50 o 100 righe).

| | Campi | Note |
|---|---|---|
| **Torneo** | nome, tipologia, data di inizio (facoltativa), numero di turni | tipologie e limiti dei turni in `config/tornei.php` |
| **Giocatore** | nome, cognome, genere, anno di nascita, fascia, lato preferito (facoltativo), circolo (facoltativo) | anagrafica autonoma: non è un account; generi, età ammessa, fasce e lati in `config/tornei.php` |

- «Nuovo torneo» / «Nuovi giocatori» aprono una finestra con il form; se i dati non sono validi la finestra si riapre
  con gli errori accanto ai campi.
- **I giocatori si inseriscono a righe**, uno per riga: appena una riga viene compilata ne compare sotto un'altra
  vuota, e con un solo invio si salvano tutti (fino a 50, `tornei.giocatori.massimo_per_inserimento`). Le righe vuote
  vengono ignorate; se una riga ha un errore non viene salvato nessuno e la finestra si riapre con tutte le righe.
- **Genere e anno di nascita** sono obbligatori per ogni nuovo giocatore. L'anno deve corrispondere a un'età tra 5 e
  100 anni (`tornei.giocatori.eta`): così un errore di battitura come `85` o `1895` viene respinto. Le schede create
  prima di questa regola non li hanno e in elenco mostrano «—».
- **Circolo di appartenenza**: testo libero e facoltativo; mentre si scrive vengono suggeriti i circoli già usati e
  lo stesso circolo scritto con maiuscole o spazi diversi prende la grafia già presente. Gli elenchi dei giocatori si
  **filtrano per circolo** con il menu accanto alla casella di ricerca.
- «Elimina» chiede sempre conferma; l'eliminazione è definitiva.
- Le tabelle `tornei`, `giocatori` e `iscrizioni` si creano con `php artisan migrate`.

### Gestione di un torneo

Nell'elenco dei tornei, il nome del torneo e il pulsante «Gestisci» aprono la pagina di gestione
(`/amministrazione/tornei/{id}`): riepilogo degli iscritti (totale e per fascia) ed elenco dei giocatori iscritti.

**La pagina mette in alto la parte su cui stai lavorando.** In cima ci sono sempre i passi del sorteggio; subito
sotto, con un filo ambra, la parte attiva, e poi le altre dalla più recente alla più vecchia:

| A che punto sei | Ordine della pagina |
|---|---|
| Iscrizioni aperte | passi · **giocatori iscritti** · formula |
| Coppie generate | passi · **coppie** · formula · giocatori iscritti |
| Gironi o partite generati | passi · **gironi** (calendario e risultati) · coppie · formula · giocatori iscritti |
| Finali generate | passi · **finali** (stato e collegamento al tabellone) · gironi · coppie · formula · giocatori iscritti |

La tabella delle coppie si ordina facendo clic sulle intestazioni e parte **ordinata per girone**. Nella scheda di un
girone, quando le partite ci sono, il calendario sta sopra l'elenco delle coppie.

- «Registra giocatori» è lo stesso inserimento a righe dell'anagrafica: per ogni giocatore nome, cognome, genere,
  anno di nascita, **fascia** (prima o seconda), lato preferito e circolo (facoltativi). I giocatori vengono salvati nell'anagrafica (tabella
  `giocatori`) e iscritti a quel torneo. Li si ritrova quindi anche nell'elenco «Giocatori» della pagina di amministrazione.
- «Dall'anagrafica» iscrive giocatori che hanno già una scheda (per esempio perché hanno giocato un altro torneo):
  la finestra elenca chi non è ancora iscritto a questo torneo, si può filtrare per circolo e se ne può spuntare più
  di uno. Non crea schede nuove.
- «Rimuovi» toglie il giocatore dal torneo ma non dall'anagrafica.
- Eliminare un torneo non elimina i suoi giocatori; eliminare un giocatore dall'anagrafica lo toglie da tutti i tornei.
- Le iscrizioni stanno nella tabella `iscrizioni` (torneo + giocatore): lo stesso giocatore può partecipare a più tornei.

### Formula e sorteggio

La pagina del torneo ha la **Formula** e i passi del **Sorteggio**.

- **Formula**: numero massimo di squadre per girone (da 2 a 16, predefinito 3) e partite del girone di sola andata o di
  andata e ritorno. Il numero di gironi non si sceglie: dipende da quante coppie ci sono.
- **Lati nel sorteggio delle coppie** (sempre nella Formula): «Non contano» (predefinito: le coppie escono del tutto a
  caso), «Preferisci destra + sinistra» (il maggior numero possibile di coppie con lati diversi, a caso tra quelle) o
  «Solo destra + sinistra» (se non è possibile il sorteggio viene rifiutato). Con i lati preferiti e pochi iscritti le
  coppie possono risultare quasi obbligate.
- **1 · Genera coppie**: un giocatore di prima fascia con uno di seconda, estratti a sorte fra tutti gli iscritti.
- **2 · Genera gironi**: le coppie vengono divise a sorte nel minor numero di gironi che rispetta il massimo, il più
  possibile uguali (10 coppie, massimo 4 → gironi da 4, 3 e 3).
- **3 · Genera partite**: calendario all'italiana di ogni girone, diviso in giornate; con andata e ritorno ogni
  incontro si ripete a campi invertiti.
- **4 · Genera finali**: quando tutte le partite dei gironi hanno un risultato (vedi «Risultati, classifica e finali»).
- Ogni passo si può rifare («Rigenera …»): chiede conferma, sostituisce il risultato e cancella i passi successivi,
  risultati compresi.
- **Fasce non pari**: «Genera coppie» apre una finestra. I giocatori in più giocano con un giocatore dell'altra fascia
  **in prestito**, che tiene la sua coppia e ne completa una seconda in un altro girone: si può sorteggiare chi va in
  prestito oppure indicarlo. Il prestito non è possibile se ne uscirebbe un solo girone o se i giocatori in più sono
  più di quelli dell'altra fascia: la finestra spiega come rimediare.
- **Iscrizioni chiuse** dal sorteggio delle coppie: per aggiungere o togliere giocatori si usa «Annulla sorteggio»,
  che elimina coppie, gironi e partite. Un giocatore in una coppia non si può eliminare dall'anagrafica.
- Le tabelle `coppie`, `gironi` e `partite` si creano con `php artisan migrate`.

## Risultati, classifica e finali

Ogni torneo ha tre pagine, collegate dalle linguette in alto: **Gestione**, **Classifica**, **Tabellone**.

- **Formula di partenza**: un torneo nuovo nasce con gironi da 3 squadre al massimo, le prime 2 di ogni girone alle
  finali e partite a **set unico a 4 game senza tie-break** (vince chi arriva per primo a 4: il 4-3 è valido) in
  tutte le fasi tranne la **finale**, che si gioca al meglio di 3 set a 6 game con il tie-break a 7 punti. Tutto si
  può cambiare torneo per torneo nella scheda «Formula»; i valori di partenza stanno in `config/tornei.php`.
- **Formato delle partite** (scheda «Formula»): per i gironi e per ogni turno delle finali (sedicesimi, ottavi, quarti,
  semifinali, finale) si scelgono i set (set unico, al meglio di 3 o di 5), i game per set e il tie-break: nessuno,
  standard a 7 punti oppure personalizzato con i punti che vuoi. **Senza tie-break** il set va a chi arriva per primo
  ai game previsti, anche con un solo game in più (con set a 4 game il 4-3 è valido e il set non va oltre); se spunti
  **«Due game di scarto»** si continua finché una coppia ha due game di vantaggio (5-3, 6-4…). La spunta si sceglie
  fase per fase, parte spenta e con il tie-break non conta.
- **Risultati**: accanto a ogni partita il pulsante «Risultato» apre una finestra con una colonna per set. Il punteggio
  viene controllato con il formato della fase (un 9-7 con set a 6 game e tie-break viene rifiutato, e l'errore indica
  il set sbagliato). I punti del tie-break sono facoltativi. Un risultato si può correggere o cancellare.
- **Inserimento continuo**: «Salva risultato» (o Invio) salva senza chiudere la finestra, che mostra subito la
  prossima partita senza risultato, vuota e con il cursore sul primo campo. Si resta nello stesso girone finché ha
  partite da giocare, poi si passa al girone successivo: la finestra lo dice («Girone A: tutti i risultati inseriti.
  Ora sei nel Girone B.») e la fascia con il nome del girone si accende. **«Successiva»** passa alla partita dopo
  senza aspettare un risultato, girone dopo girone e poi da capo; se hai già scritto un punteggio lo salva prima.
  «Chiudi» non salva ciò che è scritto e aggiorna la pagina. Con l'ultimo risultato dei gironi la finestra si chiude
  da sola sul passo «Genera finali». Nel tabellone funziona allo stesso modo, turno per turno: con l'ultimo
  risultato di un turno si passa alle coppie appena sorteggiate del turno successivo.
- **Classifica**: 2 punti per la vittoria. A pari punti contano, nell'ordine, gli scontri diretti, la differenza set,
  la differenza game e i game vinti. La pagina mostra la classifica di ogni girone, chi accede alle finali e una
  tabella con i punteggi di tutte le squadre.
- **Accesso alle finali** (scheda «Formula»): quante squadre passano da ogni girone e il criterio dei **ripescaggi**.
  Il primo turno è il più piccolo che contiene le qualificate (4, 8, 16 o 32 squadre): 3 gironi con 2 qualificate
  fanno 6 squadre, quindi quarti di finale con 2 ripescate. Le ripescate sono le migliori escluse: prima le meglio
  piazzate (le terze prima delle quarte) e, a parità di piazzamento, quelle con più punti, poi differenza set e game
  (oppure con la miglior differenza game, oppure a sorte), sempre in media per partita perché i gironi possono avere
  un numero diverso di squadre. Se le squadre non bastano nemmeno così, le prime teste di serie passano il primo
  turno senza giocare.
- **Coppie con un giocatore in prestito**: giocano il girone ma non accedono alle finali, perché quel giocatore
  entrerebbe due volte nelle finali; passa la coppia successiva del girone.
- **Nelle finali le coppie cambiano a ogni turno** e nessuno gioca due volte con lo stesso compagno. A ogni turno
  si sorteggia quali coppie si incontrano, e le due coppie sorteggiate insieme **si scambiano i compagni**: da
  Rossi/Bianchi e Verdi/Neri nascono Rossi/Neri e Verdi/Bianchi, che giocano l'una contro l'altra. In questo modo la
  regola vale sempre, finale compresa, perché di due ex compagni ne resta in gara al più uno. Il primo turno si
  sorteggia con «Genera finali» (tra le coppie qualificate), ogni turno successivo da solo, appena il turno
  precedente ha tutti i risultati. Chi passa il primo turno senza giocare resta con il compagno dei gironi fino al
  sorteggio del secondo turno.
- **Tabellone**: mostra un turno per colonna; i turni non ancora sorteggiati sono «Da sorteggiare». Se correggi un
  risultato cambiando la vincitrice, il turno successivo viene sorteggiato di nuovo (finché non ha risultati). Dal
  momento in cui generi le finali i risultati dei gironi sono bloccati: per correggerli si usa «Annulla finali».
  Non è prevista la finale per il terzo posto. Nella pagina «Classifica» la tabella «Giocatori nelle finali» dice
  per ogni giocatore fin dove è arrivato e con chi ha giocato.
- **Migliori giocatori**: nella pagina «Classifica» i pulsanti «Migliori per fascia d'età» e «Migliori per genere»
  aprono l'elenco dei primi tre giocatori di ogni fascia d'età e di ogni genere. Conta prima di tutto fin dove il
  giocatore è arrivato nelle finali, poi le partite che ha giocato (gironi e finali, con qualunque compagno):
  percentuale di vittorie, differenza set e differenza game, in media per partita. Fascia d'età e genere si leggono
  dall'anagrafica dei giocatori: chi non li ha indicati non compare.
- Servono le migrazioni nuove: `php artisan migrate`. Un tabellone generato quando le finali si giocavano con le
  coppie dei gironi continua a funzionare, ma le coppie cambiano solo dal turno successivo: per averle nuove già dal
  primo turno usa «Rigenera finali».

## Tornei di test (solo sviluppo)

Per provare sorteggio, gironi e finali senza inserire i giocatori a mano ci sono due rotte che creano, con le factory
di Laravel, un torneo **già con tutti i giocatori iscritti**. Il numero di giocatori si scrive nel link:

| Indirizzo | Cosa fa |
|---|---|
| `http://padel/test` | torneo con 16 giocatori (`tornei.test.giocatori.predefinito`), poi apre la sua pagina di gestione |
| `http://padel/test/24` | lo stesso con 24 giocatori |
| `http://padel/api/test` | lo stesso torneo in JSON, con l'elenco completo dei giocatori (stato 201) |
| `http://padel/api/test/24` | lo stesso con 24 giocatori |

- I giocatori sono schede nuove in anagrafica, metà di prima e metà di seconda fascia. Con un numero dispari quello in
  più è di prima fascia: `/test/17` dà 9 + 8, cioè il caso del **prestito**.
- Limiti: da 2 a 128 giocatori (`tornei.test.giocatori`). Fuori dai limiti il sito torna ad «Amministrazione» con il
  messaggio di errore, l'API risponde 422.
- La rotta del sito chiede l'accesso da **amministratore** (se non sei entrato passa dal login e poi prosegue).
  L'API non chiede credenziali.
- Rispondono **solo** con `APP_ENV=local` o `testing` (`tornei.test.ambienti`): in produzione danno 404.
- Ogni apertura del link crea un torneo nuovo: quelli che non servono più si eliminano da «Amministrazione»
  (le schede dei giocatori restano in anagrafica).
- Nel codice e in `php artisan tinker`: `Torneo::factory()->conGiocatori(24)->create()`.

Test: `php artisan test --filter=TorneoDiTest`.

## Mappa del sito

L'amministratore ha nella barra in alto la voce **«Mappa del sito»** (`/amministrazione/mappa`): l'elenco di tutte le
rotte dell'applicazione, divise per prefisso (`/`, `/amministrazione`, `/api/test`…), con metodo, indirizzo e nome,
una breve descrizione della destinazione e il controller che risponde, e chi può accedere. Le pagine che non chiedono
parametri sono collegamenti.

- La mappa **non è un elenco da aggiornare**: si costruisce dalle rotte scritte in `routes/*.php`.
- La descrizione sta nel file di route, accanto alla rotta:
  `Route::get('mappa', …)->name('mappa')->descrizione('Mappa del sito: …');`.
  Una rotta nuova senza `->descrizione()` fa fallire i test; nella pagina comparirebbe con il commento del
  controller oppure «Senza descrizione».
- Le rotte di test hanno l'etichetta «solo sviluppo» e in produzione non compaiono.

Test: `php artisan test --filter=MappaDelSito`.

## Regole (config/tornei.php)

| Chiave | Default | Significato |
|---|---|---|
| `tipologie.sociale.squadra.composizione` | `[1 => 1, 2 => 1]` | un giocatore di 1ª e uno di 2ª fascia |
| `tipologie.sociale.sorteggio.lati_complementari` | `ignora` | valore dei tornei nuovi; poi si sceglie per torneo nella Formula: `ignora` / `preferito` / `obbligatorio` |
| `lati_complementari` | tre voci | etichette delle tre scelte |
| `gironi.squadre` | minimo 2, massimo 16, predefinito 3 | limiti del numero massimo di squadre per girone e valore dei tornei nuovi |
| `gironi.formule` | sola andata / andata e ritorno | formule delle partite di girone |
| `giocatori.massimo_per_inserimento` | `50` | righe per invio nel form dei giocatori |
| `fasi` | gironi, sedicesimi … finale | nomi delle fasi |
| `formato.set` | 1, 3, 5 | scelte per il numero di set |
| `formato.game` | minimo 3, massimo 9 | limiti dei game per set |
| `formato.punti_tie_break` | standard 7, minimo 3, massimo 21 | punti del tie-break standard e limiti di quello personalizzato |
| `formato.predefinito` | set unico a 4 game, senza tie-break, `due_game_scarto` = `false` | formato di una fase finché non lo imposti nel torneo; `due_game_scarto` vale solo senza tie-break |
| `formato.predefinito_per_fase` | `finale`: 3 set a 6 game, tie-break a 7 | fasi che fanno eccezione al predefinito (solo le voci che cambiano) |
| `classifica.punti` | vittoria 2, sconfitta 0 | punti in classifica |
| `classifica.migliori.quanti` | `3` | posizioni mostrate per ogni fascia d'età e per ogni genere |
| `classifica.migliori.categorie` | `fascia_eta`, `genere` | categorie dei «migliori»: per ognuna il campo dell'anagrafica (`attributo`) e la chiave con le etichette (`valori`) |
| `finali.qualificate` | minimo 1, massimo 8, predefinito 2 | squadre per girone che accedono alle finali |
| `finali.ripescaggi` | punti / game / sorteggio | criteri di ripescaggio |
| `finali.squadre_massime` | `32` | dimensione massima del primo turno (sedicesimi) |
| `test.ambienti` | `local`, `testing` | ambienti (`APP_ENV`) in cui rispondono le rotte di test; negli altri 404 |
| `test.giocatori` | minimo 2, massimo 128, predefinito 16 | giocatori del torneo di test: limiti del numero nel link e valore usato se manca |

| `tipologie.sociale.sorteggio.finali` | `scambio` | come nascono le coppie a ogni turno delle finali: `scambio` (le coppie sorteggiate insieme si scambiano i compagni: nessuna coppia si ripete mai) oppure `libero` (sorteggio tra tutti i giocatori rimasti: in finale una coppia già vista è spesso inevitabile) |
| `tipologie.sociale.sorteggio.vieta_coppie_ripetute` | `true` | chi ha già giocato insieme nel torneo non torna in coppia |
| `tipologie.sociale.sorteggio.consenti_ripetizione_se_impossibile` | `true` | se una coppia già vista è inevitabile viene ammessa e segnalata; con `false` il risultato che la imporrebbe viene rifiutato |

Le chiavi `avanzamento` e `bye` di `tipologie.sociale` descrivono la tipologia ma non sono lette dal codice.

## Come funziona il sorteggio

Gli algoritmi stanno in `app/Services/Sorteggio` e non dipendono da Laravel (test in `tests/Unit/Sorteggio`).

- `SorteggioCoppie` abbina i giocatori delle due fasce a caso (non conta l'ordine in cui arrivano). Con i lati
  `preferito` cerca il maggior numero possibile di coppie destra + sinistra (abbinamento massimo, in ordine casuale),
  poi completa a caso. Con le fasce non pari forma
  prima le coppie ordinarie e poi quelle completate dai giocatori in prestito.
- `SorteggioGironi` distribuisce le coppie a caso e in modo uniforme; le due coppie di chi è in prestito non finiscono
  mai nello stesso girone.
- `CalendarioGirone` costruisce il calendario all'italiana (metodo del cerchio): con le coppie dispari a ogni giornata
  una riposa.
- `ScambioCoppie` forma le coppie di ogni turno delle finali: estrae quali coppie in gara si incontrano e fa
  scambiare loro i compagni. `RisorteggioCoppie` è l'alternativa a sorteggio libero (`sorteggio.finali = libero`).

`App\Services\Tornei\TorneoSocialeService` legge gli iscritti, chiama gli algoritmi e salva il risultato.

## Struttura

- `app/Services/Sorteggio` — algoritmi puri: coppie, gironi, calendario, coppie nuove a ogni turno delle finali
- `app/Services/Punteggio`, `app/Services/Classifica`, `app/Services/Finali` — regole pure di punteggio, classifica
  e accesso alle finali
- `app/Services/Tornei` — `TorneoSocialeService` (coppie, gironi, partite, annullamento), `RisultatiService`
  (risultati), `CodaRisultati` (in che ordine la finestra del risultato propone le partite), `FinaliService`
  (classifiche, tabellone, sorteggio dei turni delle finali)
- `app/Models` — Torneo, Giocatore, Coppia, Girone, Partita, User
- `resources/views/components/ui` e `components/layouts` — **unici file con HTML** (libreria Bootstrap)
- `resources/views/components/{tornei,giocatori,coppie,gironi,partite,auth,utenti}` — componenti di dominio, solo `<x-ui.*>`
- `resources/views/{amministrazione,area-utente,auth,errors}` — pagine, solo componenti e direttive Blade
- `public/js/torneo.js` — jQuery: tabelle e filtri, righe ripetute dei form, finestre modali, conferme, password
- `public/vendor` — Bootstrap, Bootstrap Icons, jQuery, DataTables e il carattere Archivo in locale (nessuna CDN: funziona anche senza internet)
- `config/risorse.php` — release di ogni risorsa statica, da aggiornare a mano: finisce in coda agli indirizzi (`?v=…`)
  tramite `App\Support\Risorse`, così il browser riscarica i file solo quando la release cambia
- `app/Http/Controllers/Amministrazione`, `app/Http/Requests/Amministrazione` — pagina dell'amministratore, creazione
  ed eliminazione di tornei e giocatori
- `app/Enums/Ruolo.php`, `app/Http/Middleware/VerificaRuolo.php`, `app/Http/Controllers/Auth`,
  `app/Http/Requests/Auth`, `config/sicurezza.php` — accesso, registrazione e ruoli
- `routes/test.php`, `routes/api.php`, `app/Http/Controllers/Test`, `app/Http/Controllers/Api/Test`,
  `app/Http/Requests/Test`, `app/Http/Resources`, `app/Http/Middleware/SoloAmbientiDiTest.php` — rotte di test del
  sito e dell'API (tornei generati con `TorneoFactory::conGiocatori()`)
- `app/Services/Mappa`, `app/Http/Controllers/Amministrazione/MappaController.php`,
  `resources/views/components/mappa` — mappa del sito, costruita dalle rotte e dalle loro `->descrizione()`
- `lang/it` — messaggi di validazione e di accesso in italiano

## Pagine

- `/login`, `/registrazione` — accesso e creazione account (`routes/auth.php`)
- `/amministrazione` — pagina iniziale dell'amministratore: elenco di **tornei** e **giocatori** (tabelle DataTables con
  ricerca, ordinamento e paginazione), creazione ed eliminazione di entrambi, ultimi account registrati
- `/amministrazione/tornei/{id}` — gestione di un torneo: registrazione di giocatori nuovi (con la fascia), iscrizione
  di giocatori già in anagrafica, elenco e rimozione degli iscritti; formula, sorteggio di coppie, gironi e partite
- `/amministrazione/mappa` — mappa del sito: tutte le rotte con la descrizione della loro destinazione
- `/area-utente` — pagina iniziale dell'utente: profilo e tornei
- `/test`, `/test/{giocatori}` — solo sviluppo, amministratore: crea un torneo di test già con i giocatori iscritti e
  apre la sua pagina di gestione
- `/api/test`, `/api/test/{giocatori}` — solo sviluppo: lo stesso torneo in JSON
