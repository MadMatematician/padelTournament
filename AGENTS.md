# AGENTS.md — Torneo Padel (modulo "Sociale")

Istruzioni per gli agenti AI (Claude Code, Junie/AI Assistant di PhpStorm, Codex…) che lavorano su questo progetto.
Lingua del dominio, del codice applicativo e dei commenti: **italiano**.

## Stack
- PHP 8.5 (vincolo composer `^8.2`), Laravel 12
- PostgreSQL in produzione (`DB_CONNECTION=pgsql`); `APP_LOCALE=it`
- Frontend: Bootstrap 5 + Bootstrap Icons + jQuery, **niente Vite/SPA** — asset statici in `public/css/torneo.css`, `public/js/torneo.js`
- Librerie frontend **in locale** in `public/vendor/{bootstrap,bootstrap-icons,jquery,fonts,datatables}` (nessuna CDN), caricate da `components/layouts/base.blade.php`
- DataTables 3 (senza dipendenza da jQuery, integrazione Bootstrap 5) è caricata solo dalle pagine che usano `<x-ui.datatable>`
  (il componente la aggiunge con `@pushOnce('stili')` / `@pushOnce('script')`); la inizializza `public/js/torneo.js`
- **Release delle risorse**: ogni file servito da `public/` (librerie in `public/vendor`, `torneo.css`, `torneo.js`, carattere)
  si collega con `App\Support\Risorse::url('<dipendenza>', '<percorso>')`, mai con `asset()` da solo: aggiunge `?v=<release>`
  letta da `config/risorse.php`. La release si aggiorna **a mano**: `app` a ogni modifica di `torneo.css` / `torneo.js`,
  le altre quando si sostituiscono i file della libreria. Libreria nuova = nuova chiave in `config/risorse.php`.
- Componenti Blade anonimi (`resources/views/components`)

## Layout della cartella (attenzione)
- La radice contiene i file del modulo (`app/`, `config/tornei.php`, `database/`, `resources/`, `routes/web.php`, `tests/`, `public/css|js`).
- `torneo-padel/` è lo skeleton Laravel 12 pulito (`composer create-project`), con `.env` ancora su SQLite.
- Procedura prevista: copiare il contenuto della radice **dentro** `torneo-padel/` sovrascrivendo `routes/web.php`, poi impostare `.env` su pgsql.
- Finché non è fatto, `artisan`, `composer` e `phpunit` vanno lanciati da `torneo-padel/` e non vedono il codice del modulo. Non modificare `torneo-padel/vendor`.

## Comandi
```bash
php artisan migrate
php artisan db:seed                      # amministratore iniziale
php artisan test                         # tutta la suite
php artisan test --filter=Sorteggio      # algoritmi (tests/Unit/Sorteggio) e sorteggio del torneo
php artisan test --filter=Coppie         # anche le coppie nuove a ogni turno delle finali
php artisan test --filter=RisultatiEFinali   # formato delle partite, risultati, classifica, finali, tabellone
php artisan test --filter=TorneoDiTest       # rotte di test del sito e dell'API, factory con i giocatori
php artisan test --filter=MappaDelSito       # mappa del sito e descrizione di ogni rotta nei file di route
```

## Regole di dominio (tipologia "sociale" — `config/tornei.php`)
Già nel codice:
- Coppia = 1 giocatore di 1ª fascia + 1 di 2ª fascia (`squadra.composizione = [1 => 1, 2 => 1]`): il sorteggio prevede
  esattamente due fasce, un giocatore ciascuna.
- **Il sorteggio delle coppie è casuale** e non dipende dall'ordine o dagli identificativi dei giocatori (lo verifica
  `test_senza_vincoli_di_lato_ogni_abbinamento_e_ugualmente_probabile`).
- Lato destra/sinistra facoltativo. Quanto conta nel sorteggio si sceglie **torneo per torneo** nella Formula
  (`tornei.lati_complementari`, etichette in `tornei.lati_complementari`): `ignora` (default: tutto a caso) |
  `preferito` (il maggior numero possibile di coppie destra + sinistra, a caso tra quelle) | `obbligatorio` (se non è
  possibile, `SorteggioImpossibileException`). Il default dei tornei nuovi è
  `tipologie.sociale.sorteggio.lati_complementari` e deve coincidere con quello della colonna.
- **Fasce non pari → prestito**: i giocatori in più della fascia più numerosa giocano con un giocatore dell'altra fascia
  "in prestito", che tiene la sua coppia e ne completa una seconda **in un altro girone** (gioca in entrambi). Ogni
  giocatore va in prestito al più una volta; chi va in prestito si sorteggia o lo indica l'amministratore.
- Gironi di al più `tornei.squadre_per_girone` squadre (limiti e default in `tornei.gironi.squadre`): il numero di
  gironi è il minimo necessario, le coppie sono distribuite in modo uniforme.
- **Formula predefinita dei tornei nuovi** (decisa il 07/10/2026): gironi da **3** squadre al massimo, le prime **2**
  alle finali, **set unico a 4 game senza tie-break** in tutte le fasi **tranne la finale**, che si gioca al meglio di
  3 set a 6 game con il tie-break a 7 punti. Sta tutta in `config/tornei.php`: `gironi.squadre.predefinito`,
  `finali.qualificate.predefinito`, `formato.predefinito` (vale per ogni fase) e `formato.predefinito_per_fase` (le
  eccezioni: solo le voci che cambiano). `Torneo` copia `gironi.squadre.predefinito` nel torneo alla creazione; il
  default della colonna `tornei.squadre_per_girone` va tenuto uguale con una **migrazione nuova** (lo verifica
  `FormulaPredefinitaTest`). Cambiare i predefiniti del formato cambia anche i tornei esistenti che non hanno mai
  salvato la Formula (`tornei.formati` vuoto).
- Partite di girone **all'italiana**, sola andata o andata e ritorno (`tornei.andata_ritorno`, etichette in
  `tornei.gironi.formule`).
- Iscrizioni chiuse dal sorteggio delle coppie; per riaprirle si annulla il sorteggio.
- **Fasi** (`App\Enums\Fase`, etichette in `tornei.fasi`): `gironi`, poi le finali a eliminazione diretta
  `sedicesimi`, `ottavi`, `quarti`, `semifinali`, `finale`. Niente finale per il terzo posto.
- **Formato delle partite, fase per fase** (`tornei.formati`, JSON; limiti, etichette e predefinito in
  `tornei.formato`): set al meglio di 1, 3 o 5; game per set (3–9); tie-break sul pari `no` | `standard`
  (`formato.punti_tie_break.standard` = 7 punti) | `personalizzato` (3–21 punti). **Senza tie-break i due game di scarto
  sono facoltativi** (`due_game_scarto`, per fase, predefinito `false` in `formato.predefinito`): senza, il set va a
  chi arriva per primo ai game previsti (a 4 game: 4-3); con, si continua finché una coppia ha due game in più. Con il
  tie-break la scelta non conta e viene salvata `false`. Si legge sempre con `Torneo::formato(Fase)`.
- **Punteggio valido** (`ValutatorePunteggio`): con il tie-break, a G game il set finisce G-x con x ≤ G-2, oppure
  (G+1)-(G-1), oppure (G+1)-G al tie-break; senza tie-break e con i due game di scarto, G-x con x ≤ G-2 oppure
  qualunque punteggio oltre G con due game in più (8-6); senza tie-break e senza i due game di scarto, G-x con
  x ≤ G-1 e mai oltre G; la partita finisce appena una coppia ha la maggioranza dei set (niente set in più). I punti
  del tie-break sono facoltativi; se scritti devono essere coerenti (chi vince il set arriva ai punti previsti con due
  di scarto).
- **Classifica del girone**: `classifica.punti` (2 vittoria, 0 sconfitta). A pari punti, nell'ordine: punti negli
  scontri diretti tra le coppie a pari merito, differenza set, differenza game, game vinti.
- **Accesso alle finali**: passano le prime `tornei.qualificate_per_girone` di ogni girone (limiti in
  `finali.qualificate`). Il primo turno è la più piccola potenza di due che le contiene (massimo
  `finali.squadre_massime`). I posti che restano si riempiono con i **ripescaggi**: le migliori escluse, prima le meglio
  piazzate e, a parità di piazzamento, secondo `tornei.criterio_ripescaggio` (`punti` | `game` | `sorteggio`, etichette
  in `finali.ripescaggi`); il confronto tra gironi usa le **medie per partita**, perché i gironi possono avere un numero
  diverso di squadre. Se le squadre non bastano nemmeno così, i posti vuoti vanno alle migliori qualificate
  (`coppie.testa_di_serie`: prime dei gironi, poi le seconde…, infine le ripescate; a parità di piazzamento il criterio
  dei ripescaggi, con `sorteggio` vale `punti`), che passano il primo turno senza giocare.
- **Le coppie con un giocatore in prestito giocano il girone ma non accedono alle finali** (il giocatore entrerebbe
  due volte nelle finali): il loro posto passa alla coppia successiva del girone.
- **Nelle finali le coppie cambiano a ogni turno** e **due giocatori che nel torneo hanno già fatto coppia non
  giocano più insieme** (`tipologie.sociale.sorteggio`: `coppie_ogni_turno`, `vieta_coppie_ripetute`). Il primo turno
  si sorteggia generando le finali, ogni turno successivo appena il precedente ha tutti i risultati. Come nascono le
  coppie lo dice `sorteggio.finali` (enum `CoppieNelleFinali`, `Torneo::coppieNelleFinali()`):
  - `scambio` (**predefinito**): si sorteggia quali coppie in gara si incontrano e le due coppie sorteggiate insieme
    **si scambiano i compagni** (da 1+2 e 3+4 nascono 1+4 e 3+2, che giocano l'una contro l'altra). Nessuna coppia si
    ripete **mai**, finale compresa: dopo l'incontro, di due ex compagni ne resta in gara al più uno.
  - `libero`: sorteggio libero tra tutti i giocatori rimasti, evitando le coppie già viste; in finale (quattro
    giocatori, due sole coppie possibili) una coppia già vista è spesso inevitabile (circa 4 tornei su 10 con 8 coppie
    ai quarti, di più con tabelloni più grandi) e viene segnata `coppie.ripetuta`.
  Chi passa il primo turno senza giocare resta con la coppia dei gironi fino al sorteggio del secondo turno.
  Con `consenti_ripetizione_se_impossibile = false` il risultato che renderebbe inevitabile una coppia ripetuta
  viene rifiutato. I lati preferiti (se la Formula non dice `ignora`) contano solo come preferenza, mai a costo di
  ripetere una coppia.
- Non c'è un tabellone a percorsi fissi né teste di serie negli incontri: chi incontra chi si decide a sorte a ogni turno.
- I parametri delle regole stanno in config: **non hardcodare** fasce, lati, limiti, punti ecc. nel codice.

Prevista ma **non ancora nel codice**: `tornei.numero_turni` (chiesto alla creazione del torneo, non usato).

## Architettura
- `app/Services/Sorteggio/` — algoritmi puri, **senza dipendenze da Laravel** (niente facade, Eloquent, helper
  `config()`); il caso arriva da un `Random\Randomizer` passato al costruttore (nei test: `Mt19937` con seme fisso):
  - `SorteggioCoppie`: abbina prima × seconda fascia. Con i lati `preferito`/`obbligatorio` cerca l'abbinamento massimo
    di coppie complementari (cammini aumentanti su grafo bipartito, ordine di visita casuale); gestisce il prestito.
  - `SorteggioGironi`: numero minimo di gironi, coppie distribuite a caso e in modo uniforme; due coppie con un
    giocatore in comune (il prestito) mai nello stesso girone.
  - `CalendarioGirone`: all'italiana con il metodo del cerchio, sola andata o andata e ritorno; deterministico.
  - `ScambioCoppie`: coppie nuove a ogni turno delle finali con lo scambio dei compagni tra le coppie sorteggiate
    insieme; se un incontro rimetterebbe insieme due ex compagni (dati non nati da questo sorteggio) o i lati non
    sono complementari, sceglie a caso tra gli accoppiamenti migliori (tutti fino a 8 coppie, un campione oltre).
  - `RisorteggioCoppie`: l'alternativa `libero`: abbinamento a caso tra tutti i giocatori rimasti senza coppie già
    viste (estrazioni ripetute, poi assegnazione di costo minimo con il metodo ungherese); nelle semifinali sceglie
    tra i sorteggi che lasciano possibile una finale senza ripetizioni.
  - Entrambe restituiscono le coppie **in ordine di tabellone** (la prima incontra la seconda, la terza la quarta…).
  - DTO: `GiocatoreSorteggio`, `CoppiaSorteggiata` (`ripetuta` = coppia già vista, inevitabile); enum `LatiComplementari`.
- Altre regole pure, **senza dipendenze da Laravel** e ognuna con i suoi test in `tests/Unit`:
  - `app/Services/Punteggio/`: `FormatoPartita` (set, game, tie-break, `dueGameDiScarto`; `setDaVincere()`, `descrizione()`),
    `ValutatorePunteggio::valuta(formato, righe)` → `EsitoPartita` (set, vincitrice `a`|`b`, totali) oppure
    `PunteggioNonValidoException` (messaggio per l'utente + indice del set sbagliato).
  - `app/Services/Classifica/`: `ClassificaGirone::calcola(coppie, partite, punti)` → elenco ordinato di `RigaClassifica`;
    `ClassificaGiocatori::calcola(giocatori, partite, turni)` → classifica individuale (`RendimentoGiocatore`) e
    `migliori(classifica, categorie, quanti)` → i primi di ogni categoria.
  - `app/Services/Finali/`: `Qualificazione::calcola(classifiche, perGirone, criterio, escluse, massimo)` →
    `AccessoFinali` (`dirette`, `ripescate`, `dimensione`, `teste()`, `bye()`, `passano()` = chi salta il primo turno,
    `giocano()` = le altre); enum `CriterioRipescaggio`, `CoppieNelleFinali`; DTO `Candidata`.
- `app/Services/Tornei/TorneoSocialeService.php` — legge gli iscritti, chiama gli algoritmi e salva in transazione:
  `squilibrio()`, `generaCoppie()`, `generaGironi()`, `generaPartite()`, `annulla()`. `Squilibrio` descrive le fasce
  non pari e l'eventuale impedimento al prestito.
- `app/Services/Tornei/RisultatiService.php` — `registra(Partita, EsitoPartita)` e `cancella(Partita)`: salva il
  risultato e, nelle finali, con l'ultimo risultato di un turno fa sorteggiare le coppie del successivo (`registra`
  restituisce il turno sorteggiato).
- `app/Services/Tornei/CodaRisultati.php` — `dopo(Partita, primaIlGruppo)`: le partite senza risultato nell'ordine
  in cui la finestra del risultato le propone (sezione «Risultati, classifica e finali»).
- `app/Services/Tornei/FinaliService.php` — `classifiche()`, `partiteDaGiocare()`, `accesso()`, `generaTabellone()`,
  `sorteggiaDopo(torneo, turno concluso)`, `annullaTurno()`, `turnoGiocato()`, `turnoConcluso()`, `annullaTabellone()`.
- `app/Services/Tornei/MiglioriGiocatoriService.php` — `classifica(torneo)`, `migliori(torneo, categoria)`: i migliori
  giocatori per fascia d'età, genere… (sezione «Migliori giocatori per categoria»).
- `app/Exceptions/SorteggioImpossibileException`, `RisultatoNonConsentitoException` — il messaggio è per
  l'amministratore e viene mostrato così com'è (`session('errore')`).
- `app/Models`: Torneo, Giocatore, Coppia, Girone, Partita, User.
- `app/Http/Resources`: `TorneoResource`, `GiocatoreResource`: la forma JSON di tornei e giocatori nell'API.
- Validazione sempre in Form Request (`app/Http/Requests`), controller sottili.
- Non esiste (ancora) un'interfaccia comune alle tipologie: quando arriverà una seconda tipologia, il servizio da usare
  andrà dichiarato in `config/tornei.php` e i controller dovranno dipendere dall'interfaccia.

## Amministrazione: tornei e giocatori
- La pagina iniziale dell'amministratore (`/amministrazione`) elenca tornei e giocatori in due tabelle `<x-ui.datatable>`
  (ricerca, ordinamento e paginazione nel browser: il controller passa gli elenchi completi) e li crea/elimina.
- Rotte nel gruppo `auth` + `ruolo:amministratore`, prefisso nome `amministrazione.`:
  `tornei.store`, `tornei.destroy` (`{torneo}`), `giocatori.store`, `giocatori.destroy` (`{giocatore}`), più quelle
  della gestione di un torneo (sezione successiva).
  Controller in `app/Http/Controllers/Amministrazione`, Form Request in `app/Http/Requests/Amministrazione`.
- `App\Models\Torneo` (tabella `tornei`): nome, tipologia (chiave di `config/tornei.php → tipologie`), data_inizio
  facoltativa, numero_turni (limiti in `tornei.turni`).
- `App\Models\Giocatore` (tabella `giocatori`): **anagrafica autonoma**, non è un account e non è legata a `users`:
  nome, cognome, **genere** e **anno di nascita** (obbligatori), fascia (obbligatoria), lato preferito (facoltativo),
  chiavi di `config/tornei.php → giocatori.generi|fasce|lati`, e **circolo** di appartenenza (facoltativo, testo libero).
  Gli omonimi sono ammessi. Partecipa ai tornei tramite le iscrizioni (sotto).
- **Genere e anno di nascita**: colonne `giocatori.genere` (chiave di `giocatori.generi`: `m`, `f`) e
  `giocatori.anno_nascita`. Il form li **richiede sempre**; le colonne sono `nullable` solo per le schede create prima
  (in elenco mostrano «—», `etichettaGenere()` dà `null`): non renderle `NOT NULL` senza prima valorizzarle. L'anno
  ammesso si ricava dall'età in `giocatori.eta` (`minima`, `massima`) con `Giocatore::anniNascitaAmmessi()`: non
  scrivere anni nel codice.
- **Circolo**: colonna `giocatori.circolo`, non c'è una tabella dei circoli. Per non avere lo stesso circolo scritto in
  due modi, al salvataggio (`SalvaGiocatoriRequest::giocatori()`) gli spazi vengono normalizzati e si riusa la grafia
  già presente a meno di maiuscole/minuscole; il campo suggerisce i circoli esistenti (`Giocatore::circoli()`,
  `<x-ui.datalist>`). Le tabelle dei giocatori hanno il filtro per circolo (sotto, "Filtri").
- **Inserimento a righe**: i giocatori si inseriscono più d'uno alla volta. La finestra `<x-giocatori.modale-nuovo>`
  contiene un `<x-ui.repeater>` con una `<x-giocatori.riga-nuovo>` per giocatore (campi `giocatori[i][nome|cognome|
  genere|anno_nascita|fascia|lato|circolo]`, nessuno con un valore iniziale: la riga in attesa deve restare vuota); appena l'ultima riga viene compilata `torneo.js` ne aggiunge una vuota. Validazione in
  `SalvaGiocatoriRequest` (contenitore errori `giocatore`): le righe vuote vengono scartate **senza rinumerare** le
  altre (il form respinto viene riproposto con l'input originale, gli errori devono cadere sulle stesse righe);
  se una riga è errata non viene salvato nessuno; massimo `tornei.giocatori.massimo_per_inserimento` righe.
- **Più form nella stessa pagina**: ogni Form Request dichiara il proprio `$errorBag` (`torneo`, `giocatore`,
  `iscrizione`) e il form lo riceve con `<x-ui.form bag="…">`; `<x-ui.input>` e `<x-ui.select>` lo ereditano (`@aware`)
  e ripropongono i valori inviati solo nel form respinto. Con più form i campi hanno bisogno di un `id` esplicito
  (`torneo-nome`, `giocatore-0-nome`).
- **Campi di un elenco**: `<x-ui.input>` e `<x-ui.select>` accettano nomi con le parentesi (`giocatori[0][nome]`) e
  cercano errori e valori riproposti con la chiave puntata (`giocatori.0.nome`); `compact` riduce lo spazio sotto.
- **Righe ripetute**: `<x-ui.repeater :next="…">` con le righe presenti in `<x-ui.repeater-row>` e, nello slot
  `template`, una riga vuota con `__INDICE__` al posto dell'indice. Attributi usati da `torneo.js`: `data-ripeti`,
  `data-ripeti-elenco`, `data-ripeti-modello`, `data-ripeti-prossimo`, `data-riga`, `data-riga-rimuovi`
  (attenzione: `data-righe` è già di `<x-ui.datatable>`, sono le righe per pagina).
- **Filtri**: una tabella si filtra su una colonna con `<x-ui.datatable-filter column="6" label="…" :options="…">`
  (`column` = posizione della colonna contando da 0: va aggiornata se si aggiungono colonne prima di quella filtrata)
  nello slot `filters` di `<x-ui.datatable>` (`torneo.js` lo sposta accanto alla ricerca; confronto esatto);
  un `<x-ui.checkbox-list>` con `<x-ui.list-filter target="id">` e `data-gruppo="…"` sulle voci.
- `<x-ui.modal>`: `size` (`lg`, `xl`), `fullscreen` (`xl-down`…), `scrollable`.
- `<x-ui.col grow="1.3">`: da 1200px in su la colonna esce dalla griglia a 12 e si divide la riga con le altre in
  proporzione al numero (va messo su tutte le colonne della riga). Serve alle righe con tanti campi, come
  `<x-giocatori.riga-nuovo>` (sette campi su una riga); sotto i 1200px valgono `sm` / `md` / `lg`.
- Creazione in finestra modale (`<x-ui.modal>`, si riapre da sola con `:open` se la validazione fallisce); eliminazione
  con `<x-ui.form method="DELETE" data-conferma="…">`, che chiede conferma nella finestra condivisa `<x-ui.conferma>`
  (già inclusa in `x-layouts.app`). Niente `confirm()` del browser.
- Test in `tests/Feature/Amministrazione`: `php artisan test --filter=Amministrazione`.

## Gestione di un torneo: giocatori iscritti
- Dall'elenco dei tornei (nome del torneo o pulsante «Gestisci») si apre `/amministrazione/tornei/{torneo}`
  (`amministrazione.tornei.show`, pagina `resources/views/amministrazione/torneo.blade.php`): riepilogo degli iscritti
  per fascia e tabella dei giocatori iscritti.
- **Iscrizioni** = tabella `iscrizioni` (`torneo_id`, `giocatore_id`, coppia unica), relazioni `Torneo::giocatori()` e
  `Giocatore::tornei()` (`belongsToMany`). Non c'è un modello `Iscrizione`: finché l'iscrizione non ha dati propri basta
  la relazione. La fascia resta sul giocatore, non sull'iscrizione.
- «Registra giocatori» (`amministrazione.tornei.giocatori.store`, `IscrizioneController@store`) **crea le schede in
  `giocatori` e le iscrive al torneo** in un'unica transazione, più giocatori per invio. Stessa validazione
  dell'anagrafica (`SalvaGiocatoriRequest`, contenitore errori `giocatore`) e stessa finestra a righe:
  `<x-giocatori.modale-nuovo :torneo="…" :circoli="…">`.
- «Dall'anagrafica» (`amministrazione.tornei.iscrizioni.store`, `IscrizioneController@iscrivi`) iscrive giocatori che
  hanno **già una scheda**, anche più di uno: finestra `<x-tornei.modale-iscrizione>` con l'elenco di chi non è ancora
  iscritto a quel torneo (`disponibili`, filtrabile per circolo), campo `giocatori[]`, validazione in
  `IscriviGiocatoriRequest` (contenitore errori `iscrizione`). Usa `syncWithoutDetaching`: chi è già iscritto viene ignorato, nessun doppione.
- «Rimuovi» (`amministrazione.tornei.giocatori.destroy`) toglie **solo l'iscrizione**: la scheda resta in anagrafica.
  Un giocatore non iscritto a quel torneo dà 404.
- Eliminare un torneo o un giocatore elimina a cascata le sue iscrizioni (chiavi esterne `cascadeOnDelete`), mai le
  schede dell'altra tabella.
- `<x-giocatori.tabella>` serve entrambe le pagine: senza `torneo` è l'anagrafica («Elimina»), con `:torneo` sono gli
  iscritti («Rimuovi»).
- Scelta multipla nei form: `<x-ui.checkbox-list name="…">` con dentro `<x-ui.checkbox-list-item :value="…">`
  (invia `name[]`, legge gli errori dal `bag` del form, ripropone le voci spuntate dopo un form respinto; slot `aside`
  per un contenuto a destra della voce). Stile in `torneo.css` (`.elenco-scelte`).
- Dal sorteggio delle coppie le iscrizioni sono **chiuse** (sezione successiva): la pagina nasconde i comandi e
  `IscrizioneController` rifiuta le modifiche.
- Test: `tests/Feature/Amministrazione/GestioneTorneoTest.php`.

## Sorteggio di un torneo: formula, coppie, gironi, partite
- **Ordine della pagina di gestione — la parte attiva in alto** (`resources/views/amministrazione/torneo.blade.php`):
  in cima c'è sempre **Sorteggio e fasi** (i passi, con il pulsante del prossimo), subito sotto la parte su cui si
  sta lavorando, poi le altre **dalla più recente alla più vecchia**: `finali` (`<x-tornei.finali>`, a tabellone
  generato) → `gironi` (schede con calendario e risultati) → `coppie` → `formula` → `giocatori` (`<x-tornei.iscritti>`).
  Senza sorteggio la parte attiva sono gli iscritti: `sorteggio` → `giocatori` → `formula`. La scheda attiva ha
  `<x-ui.card highlight>` (filo ambra, classe `.scheda-attiva`). Una sezione nuova va inserita rispettando questa
  regola, non in fondo. Dentro la scheda di un girone, generate le partite, il calendario sta sopra l'elenco delle
  coppie. Test: `tests/Feature/Amministrazione/OrdineDellaPaginaTest.php`.
- Nella pagina di gestione: scheda **Formula** (`<x-tornei.formula>`) e scheda
  **Sorteggio e fasi** (`<x-tornei.sorteggio>`) con quattro passi `<x-ui.step>`: «Genera coppie» → «Genera gironi» →
  «Genera partite» → «Genera finali» (sezione successiva). Un passo è attivo solo se il precedente è fatto; rifarlo
  («Rigenera …», con conferma) sostituisce il risultato e **cancella i passi che ne dipendono**, risultati compresi.
  «Annulla sorteggio» cancella tutto e riapre le iscrizioni.
- Rotte (`amministrazione.*`): `tornei.formula.update` (PATCH, `TorneoController@aggiornaFormula`,
  `AggiornaFormulaRequest`, contenitore errori `formula`), `tornei.coppie.store`, `tornei.gironi.store`,
  `tornei.partite.store`, `tornei.sorteggio.destroy` (`SorteggioController`).
- **Formula**: `tornei.lati_complementari` (cast all'enum `LatiComplementari`, letto con `Torneo::latiNelSorteggio()`),
  `tornei.squadre_per_girone`, `tornei.andata_ritorno`, `tornei.qualificate_per_girone`, `tornei.criterio_ripescaggio`
  e il formato delle partite di ogni fase (`formati[<fase>][set|game|tie_break|punti_tie_break|due_game_scarto]`; i punti si inviano
  solo con il tie-break `personalizzato` e la casella «Due game di scarto» solo senza tie-break: `torneo.js` spegne
  i due campi negli altri casi). Salvarla non tocca ciò che è
  già generato: vale dalla generazione successiva di coppie, gironi, partite e finali e per i risultati inseriti dopo.
- **Tabelle**: `coppie` (`torneo_id`, `fase` = per quale fase è stata sorteggiata, `prima_fascia_id`,
  `seconda_fascia_id`, `prestito_id` = chi in quella coppia è in prestito, `girone_id` riempito dai gironi,
  `finali` = `diretta`|`ripescata` e `testa_di_serie` riempiti dal tabellone sulle coppie dei gironi, `ripetuta`;
  unica per torneo + fase + giocatori), `gironi` (`nome`, `posizione`), `partite` (`torneo_id`, `fase`, `girone_id` solo nei gironi, `giornata`,
  `ritorno`, `posizione` = posto nel turno delle finali, `coppia_a_id`, `coppia_b_id` (vuote finché il turno non
  viene sorteggiato), `risultato` JSON con i set, totali `set_a|b` e `game_a|b`, `vincitrice_id`). Eliminare il torneo elimina
  tutto a cascata; le chiavi di `coppie` verso `giocatori` sono `restrict`: un giocatore in una coppia non si elimina
  dall'anagrafica (`Giocatore::inCoppia()`).
- **Fasce non pari**: `TorneoSocialeService::squilibrio()` restituisce uno `Squilibrio`; «Genera coppie» allora apre la
  finestra `<x-tornei.modale-prestito>` invece di inviare il form. Il form ha due pulsanti di invio con
  `name="prestito"`: `sorteggia` (chi va in prestito viene estratto) oppure `indicati` (quelli spuntati in
  `giocatori_prestito[]`). `GeneraCoppieRequest`, contenitore errori `sorteggio`: se la scelta manca o è sbagliata la
  finestra si riapre. Il prestito è impossibile, e la finestra lo spiega, se i giocatori in più superano quelli
  dell'altra fascia o se ne uscirebbe un solo girone.
- **Messaggi**: `session('status')` (riuscito) e `session('errore')` (non fatto, es. `SorteggioImpossibileException`),
  entrambi mostrati da `x-layouts.app`.
- Componenti: `<x-coppie.tabella>` (un `<x-ui.datatable>`: colonne ordinabili, ordine iniziale **per girone** — sulla
  `posizione` del girone tramite `data-order` — e poi per numero di coppia; senza gironi, per numero di coppia),
  `<x-coppie.giocatore>`, `<x-gironi.scheda>` (calendario, risultati e coppie di un girone; le coppie sono
  `<x-gironi.coppie>`), `<x-tornei.finali>` (a che punto sono le finali + collegamento al tabellone).
- `App\Services\Tornei\StatoFinali::daPartite()` (anche `FinaliService::stato()`): turno in corso, risultati che gli
  mancano, coppia vincitrice. Lo usano la pagina di gestione e il tabellone: non ricalcolarlo altrove.
- **Coppie del torneo e coppie delle finali**: `Torneo::coppie()` sono solo quelle dei gironi (`fase = gironi`), ed è
  ciò che conta per iscrizioni chiuse, gironi, classifiche; `coppieDelleFinali()` quelle sorteggiate turno per turno;
  `tutteLeCoppie()` l'elenco di chi ha già giocato con chi. Attenzione: le chiavi `partite.coppia_*_id` sono
  `cascadeOnDelete`: prima di eliminare coppie delle finali si svuotano le partite (`FinaliService::annullaTurno()`).

## Risultati, classifica e finali
- **Tre pagine per torneo**, collegate da `<x-tornei.navigazione :torneo attiva="…">`: «Gestione»
  (`tornei.show`), «Classifica» (`tornei.classifica`, `ClassificaController`), «Tabellone» (`tornei.tabellone`,
  `TabelloneController`). Tutte nel gruppo `auth` + `ruolo:amministratore`.
- **Risultato di una partita**: `PUT|DELETE tornei/{torneo}/partite/{partita}/risultato`
  (`tornei.partite.risultato.update|destroy`, `RisultatoController`). `RegistraRisultatoRequest` (contenitore errori
  `risultato`): campi `partita_id` e `set[i][a|b|tb_a|tb_b]`; valuta il punteggio con il formato della fase e mette
  l'errore sul set sbagliato (`set.<i>.a`) oppure su `set` (partita incompleta). Una partita di un altro torneo dà 404.
- **Una sola finestra per pagina**: `<x-partite.modale-risultato :torneo :partite>` con la griglia
  `<x-ui.score-grid>`. Ogni partita ha il pulsante `<x-partite.risultato>` con gli attributi `data-risultato`,
  `data-partita`, `data-azione`, `data-cancella`, `data-successiva`, `data-coppia-a|b`, `data-gruppo`,
  `data-gruppo-nome`, `data-dettaglio`, `data-set-massimi`, `data-tie-break`, `data-formato`, `data-set` (JSON dei
  set già inseriti): `torneo.js` riempie la finestra e la apre.
- **Inserimento continuo** (regola: inserire i risultati uno dopo l'altro senza riaprire la finestra):
  - «Salva risultato» invia il form **in sottofondo** (`$.ajax`, risposta JSON) e la finestra **resta aperta** sulla
    prossima partita senza risultato, vuota. `RisultatoController::update()` risponde in JSON quando la richiesta lo
    chiede (`expectsJson()`): `{ esito, cambio, successiva }`; con l'ultimo risultato `{ esito, successiva: null, vai }`
    e la conferma va in `session('status')` per la pagina indicata da `vai` (gestione `#sorteggio` per i gironi,
    tabellone per le finali). Errori: 422 con `errors` (validazione, chiavi `set.<i>.a` o `set`) oppure 422 con il
    solo `message` (`RisultatoNonConsentitoException`). Senza JavaScript resta il giro classico con il redirect, e la
    finestra si riapre dal server già compilata per la partita respinta (`old('partita_id')`).
  - **Ordine** (`CodaRisultati`): dopo un salvataggio vengono prima le partite dello **stesso girone** (anche quelle
    precedenti nel calendario) e si cambia girone solo quando è completo; nelle finali lo stesso vale per il turno, e
    con l'ultimo risultato del turno si passa alle coppie appena sorteggiate. Le partite senza coppie e i passaggi
    del turno senza giocare non vengono mai proposti.
  - «Successiva» (`GET tornei/{torneo}/partite/{partita}/successiva`, `tornei.partite.successiva`, JSON
    `{ cambio, successiva }` oppure `{ successiva: null, fine }`): passa alla prossima partita senza risultato
    nell'ordine del calendario, girone dopo girone, ricominciando da capo. **Se nella griglia c'è un punteggio non
    ancora salvato lo salva prima** (stessa chiamata di «Salva risultato»); chiudere la finestra invece non salva.
  - **Cambio di girone (o di turno) in evidenza**: `Partita::chiaveGruppo()` (`girone-<id>`, `fase-<turno>`) e
    `nomeGruppo()`; la risposta porta il testo `cambio` («Girone A: tutti i risultati inseriti. Ora sei nel
    Girone B.») e `torneo.js` accende la fascia con il nome del girone (`.gruppo-partita.gruppo-cambiato`).
  - I dati di una partita per la finestra hanno **una sola forma**, `App\Support\SchedaRisultato::di()`: la usano
    sia gli attributi `data-*` del pulsante sia la risposta JSON (chiavi come le legge jQuery: `coppiaA`,
    `setMassimi`…). Una voce nuova si aggiunge lì e al pulsante.
  - La pagina sotto la finestra non viene aggiornata a ogni salvataggio: **si ricarica alla chiusura**, fermandosi
    sul girone della partita mostrata. Gli errori del salvataggio in sottofondo usano il `<template
    data-punteggio-errore>` di `<x-ui.score-grid>` (niente HTML scritto in `torneo.js`).
- **Blocchi** (`RisultatiService`, messaggi in `RisultatoNonConsentitoException`): generato il tabellone i risultati
  dei gironi non si toccano più (prima si annullano le finali); nelle finali non si inserisce un risultato se il turno
  non è ancora stato sorteggiato o se un posto è vuoto.
- **Finali**: `POST tornei/{torneo}/tabellone` (`tornei.tabellone.store`) e `DELETE` (`tornei.tabellone.destroy`),
  in `SorteggioController`. Servono tutti i risultati dei gironi. `FinaliService::generaTabellone()` crea **tutte** le
  partite, dal primo turno alla finale, segna `coppie.finali` e `coppie.testa_di_serie`, mette in fondo al primo
  turno le coppie che lo passano senza giocare (`coppia_a_id` = la coppia dei gironi, `vincitrice_id` già impostata:
  `Partita::bye()`) e sorteggia le coppie del primo turno.
- **Da un turno al successivo**: quando tutte le partite di un turno sono decise (`Partita::decisa()`),
  `FinaliService::sorteggiaDopo()` crea le coppie del turno successivo (righe di `coppie` con quella `fase`) tra chi lo
  ha superato e le mette nelle partite, dall'alto in basso. Se cambia la vincitrice di una partita, o il suo risultato
  viene cancellato, il sorteggio del turno successivo viene tolto (`annullaTurno()`) e rifatto appena il turno è di
  nuovo concluso; non si può più fare quando il turno successivo ha già un risultato. Correggere il punteggio
  lasciando la stessa vincitrice non tocca il sorteggio. La `posizione` di una partita è solo l'ordine nel turno:
  non c'è un percorso fisso da una partita alla successiva.
- **Tabellone generato quando le finali si giocavano con le coppie dei gironi**: continua a funzionare (il turno in
  corso resta com'è, dal successivo le coppie cambiano); per avere coppie nuove già dal primo turno si usa
  «Rigenera finali».
- **Classifica** (`resources/views/amministrazione/classifica.blade.php`): una `<x-gironi.classifica>` per girone con
  i distintivi «alle finali», «ripescata», «prestito: non accede»; `<x-tornei.accesso-finali>` spiega quante squadre
  passano, primo turno, ripescaggi, posti vuoti e come cambiano le coppie; «Tutte le squadre» è un `<x-ui.datatable>`
  con i punteggi dei gironi; a finali generate, «Giocatori nelle finali» elenca per ogni giocatore la coppia con cui
  si è qualificato, il turno raggiunto e i compagni avuti (`ClassificaController::cammini()`): nelle finali a passare
  il turno sono i giocatori. Finché mancano risultati la situazione è dichiarata provvisoria.
- **Migliori giocatori per categoria**: nella pagina Classifica la scheda `<x-giocatori.migliori>` ha un pulsante per
  categoria («Migliori per fascia d'età», «Migliori per genere») che apre una finestra con i primi di ogni valore.
  - **Classifica individuale** (`ClassificaGiocatori`, `RendimentoGiocatore::criteri()`): prima il turno raggiunto
    nelle finali (chi non vi è entrato viene dopo), poi quota di partite vinte, differenza set, differenza game e
    game vinti, **in media per partita** su tutte le partite giocate, gironi e finali, con qualunque compagno
    (non tutti giocano lo stesso numero di partite). Pari su tutto = stessa posizione.
  - **Categorie** in `classifica.migliori.categorie`: `attributo` = come si legge il dato da `App\Models\Giocatore`
    (colonna, cast anche a enum, accessor), `valori` = chiave di configurazione con le etichette (decide anche
    l'ordine dei gruppi), `nome` e `icona` del pulsante; `classifica.migliori.quanti` = posizioni per gruppo (3; i
    pari merito sull'ultima ci sono tutti). **È l'unico punto che conosce i nomi dei dati in anagrafica.**
  - Predefinito: `fascia_eta` → `giocatori.fascia_eta` con etichette `tornei.giocatori.fasce_eta`; `genere` →
    `giocatori.genere` con etichette `tornei.giocatori.generi`. Finché l'anagrafica non ha il dato (colonna assente
    o vuota per tutti gli iscritti) la finestra dice «Dato non ancora in anagrafica», senza errori; chi non ha il
    dato resta fuori e viene contato. Senza `classifica.migliori` in config la scheda non compare.
- **Tabellone grafico**: `<x-ui.bracket>` → `<x-ui.bracket-round title note>` → `<x-ui.bracket-node :merge>` (tutti
  gli incontri del turno: con `merge` una linea li raccoglie e li porta al turno successivo, perché le coppie si
  rimescolano) → `<x-ui.bracket-match>` (`flag-a|b` = avviso sulla squadra, usato per la coppia ripetuta); il dominio
  sta in `<x-partite.incontro>`. I turni non ancora sorteggiati mostrano «Da sorteggiare». Le linee sono CSS
  (`.tabellone*` in `torneo.css`); su schermi stretti il tabellone scorre in orizzontale dentro la scheda.

## Accesso e ruoli
- Login con **nome utente** (`users.username`, sempre minuscolo) e password; l'email non è una credenziale.
- Ruoli in `App\Enums\Ruolo` (`amministratore`, `utente`), colonna `users.ruolo`. Ogni ruolo dichiara la propria
  pagina iniziale (`rottaIniziale()`): `/amministrazione` e `/area-utente`. La radice `/` (`HomeController`) smista.
- `ruolo` **non è mass-assignable**: si imposta solo in modo esplicito (`$utente->ruolo = Ruolo::…`). La registrazione
  crea sempre `Ruolo::Utente`.
- Rotte protette: `auth` + `ruolo:<valore>[,<valore>]` (`App\Http\Middleware\VerificaRuolo`, alias in `bootstrap/app.php`).
  Le rotte di accesso stanno in `routes/auth.php`, incluso da `routes/web.php`.
- Requisiti password, limiti di login e lunghezza del nome utente in `config/sicurezza.php`: **non hardcodarli**.
  Per validare una password usare sempre `Password::defaults()`.
- `AdminSeeder` crea `admin` / `admin` solo fuori produzione: credenziale provvisoria, non riusarla nei test come
  esempio di password valida.
- Layout: `x-layouts.guest` (login, registrazione), `x-layouts.app` (pagine autenticate, con menu utente e logout),
  entrambi sopra `x-layouts.base`.
- Test in `tests/Feature/Auth`: `php artisan test --filter=Auth`.

## Rotte di test: tornei generati con le factory
- Servono a provare a mano sorteggio, gironi e finali: creano un torneo **già con tutti i giocatori iscritti**.
  Il numero di giocatori è un parametro facoltativo **nel link** (`{giocatori?}`, solo cifre: altrimenti 404).
  - Sito: `GET /test/{giocatori?}` (`test.torneo`, `routes/test.php` incluso da `routes/web.php`,
    `App\Http\Controllers\Test\TorneoDiTestController`) → crea il torneo e rimanda a `amministrazione.tornei.show`
    con `session('status')`. Middleware: `ambiente-test`, `auth`, `ruolo:amministratore`.
  - API: `GET /api/test/{giocatori?}` (`api.test.torneo`, `routes/api.php`,
    `App\Http\Controllers\Api\Test\TorneoDiTestController`) → 201 con `TorneoResource` (torneo, `gestione` = indirizzo
    della pagina di gestione, `iscritti`, `iscritti_per_fascia`, `giocatori` come `GiocatoreResource`).
    Middleware: `ambiente-test`. **Nessuna autenticazione**.
- **Mai in produzione**: `ambiente-test` (`App\Http\Middleware\SoloAmbientiDiTest`, alias in `bootstrap/app.php`) dà 404
  fuori dagli ambienti di `tornei.test.ambienti` (`local`, `testing`). Ogni rotta di test nuova va in questi gruppi.
- **Factory**: `Torneo::factory()->conGiocatori(?int $numero)` crea le schede in `giocatori` e le iscrive
  (`hasAttached`), in parti uguali tra le fasce di `tornei.giocatori.fasce`; i giocatori in più vanno alle prime fasce
  (17 → 9 di prima e 8 di seconda: il caso del prestito). Senza numero vale `tornei.test.giocatori.predefinito`.
  Le due rotte non fanno altro che chiamarla: i dati finti si cambiano nelle factory, non nei controller.
- **Validazione**: `App\Http\Requests\Test\GeneraTorneoDiTestRequest` prende il numero dalla rotta e lo valida con i
  limiti di `tornei.test.giocatori` (`minimo`, `massimo`): non scriverli nel codice. Numero fuori dai limiti: nel sito
  si torna ad `amministrazione.dashboard` con `session('errore')` (il numero sta nel link, non c'è un form da
  riaprire), nell'API 422 in JSON.
- **API**: è la prima rotta in `routes/api.php` (prefisso `/api`, gruppo `api`, senza sessione). Le risposte di errore
  sotto `/api/*` sono sempre JSON, anche dal browser (`shouldRenderJsonWhen` in `bootstrap/app.php`). L'API non ha
  ancora un'autenticazione: una rotta che debba servire fuori dallo sviluppo va prima protetta.
- Test: `tests/Feature/Test/TorneoDiTestTest.php` (`php artisan test --filter=TorneoDiTest`).

## Mappa del sito: costruita dai file di route
- `/amministrazione/mappa` (`amministrazione.mappa`, `MappaController`, pagina
  `resources/views/amministrazione/mappa.blade.php`, voce «Mappa del sito» nella barra per l'amministratore) elenca
  **tutte le rotte dell'applicazione**: metodo, indirizzo e nome, descrizione e controller della destinazione, chi può
  accedere. Non c'è un elenco tenuto a mano: si legge il router, cioè ciò che è scritto in `routes/*.php`.
- **Ogni rotta nuova va descritta nel file di route** con `->descrizione('…')`: una riga su che cosa fa la
  destinazione, in italiano, con il punto finale. Lo impone
  `test_ogni_rotta_dei_file_di_route_dichiara_la_sua_descrizione`. La macro è registrata in
  `AppServiceProvider::register()` (deve esistere prima che i file di route vengano letti) e salva il testo
  nell'azione della rotta (`$rotta->getAction('descrizione')`), quindi funziona anche con `route:cache`.
- `App\Services\Mappa\MappaDelSito::sezioni()` → elenco di `SezioneMappa` (una per `prefix()` di gruppo: `/`,
  `/amministrazione`, `/api/test`…; la radice per prima, poi in ordine alfabetico) con le `VoceMappa` nell'ordine dei
  file. Per ogni rotta:
  - **descrizione**: quella del file di route; se manca, il primo paragrafo del commento del metodo del controller (o
    della classe); se manca anche quello la pagina scrive «Senza descrizione»;
  - **accesso**: dai middleware: `ruolo:…` → etichette di `App\Enums\Ruolo`, `auth` → «Utenti autenticati»,
    `guest` → «Solo ospiti», altrimenti «Tutti»;
  - **link**: solo per le rotte GET senza parametri obbligatori;
  - **solo sviluppo**: rotte con `ambiente-test`; fuori dagli ambienti di `tornei.test.ambienti` non compaiono.
- Entrano le rotte con un controller in `App\` o con una descrizione; restano fuori quelle del framework (`/up`,
  `/storage/…`).
- Componenti: `<x-mappa.sezione>`, `<x-mappa.voce>`, `<x-ui.code>` (testo a larghezza fissa in linea).
- Test: `tests/Feature/Amministrazione/MappaDelSitoTest.php` (`php artisan test --filter=MappaDelSito`).

## Convenzioni per le view (vincolanti)
- I file **pagina** (`resources/views/{tornei,giocatori,turni,auth,amministrazione,area-utente,errors}/*.blade.php`) contengono **solo** componenti `<x-…>` e
  direttive Blade: **nessun tag HTML**.
- L'HTML (markup Bootstrap) sta **solo** in `components/ui/*` (libreria UI generica) e `components/layouts/*`.
- I componenti di dominio (`components/{tornei,giocatori,coppie,gironi,turni,partite,auth,utenti,mappa}`) usano solo `<x-ui.*>` e direttive;
  logica minima in `@props`/`@php`.
- Se serve un elemento HTML nuovo, si crea/estende un componente `x-ui.*` riutilizzabile (props + `$attributes->class()`),
  non si scrive HTML nella pagina.
- Icone: Bootstrap Icons tramite prop `icon` (`bi bi-{icon}`).
- JS: jQuery in `public/js/torneo.js` (tabelle DataTables e loro filtri, righe ripetute, filtro degli elenchi,
  finestre modali, conferme, formato delle partite, finestra del risultato, mostra password, requisiti password); nessun JS inline: gli elementi si agganciano con attributi `data-*`.
- Elenchi lunghi: `<x-ui.datatable :headers="[…]" actions>` al posto di `<x-ui.table>`; per ordinare una cella su un
  valore diverso da quello mostrato (date, fasce) si usa `<x-ui.td data-order="…">`.
- Testi UI in italiano.

## Stile grafico (`public/css/torneo.css`)
- Colori e carattere vengono dall'identità del circolo, **senza usarne il logo**: nessun file del logo nel progetto.
- Palette in variabili CSS su `:root`: `--ambra` (#f8b006, colore principale = `--bs-primary`), `--ambra-scura`, `--ambra-chiara`,
  `--ambra-testo`, `--sole` (giallo), `--rosso` (#d80c17 = `--bs-danger`), `--nero`, `--pagina`, `--bordo`. **Non hardcodare colori**
  nei componenti: usare le variabili o le classi Bootstrap (`primary`, `danger`…), già ricolorate.
- Contrasto: sopra l'ambra il testo è **sempre nero**, mai bianco. Sul bianco l'ambra non si usa per il testo (`--ambra-testo`).
- Il rosso è decorazione (tratto a pennello `--tratto` sotto marchio e titolo di pagina) e stato di errore: non usarlo per azioni normali.
- Carattere: Archivo variabile in locale (`public/vendor/fonts/archivo`, licenza OFL). Larghezza `--largo` (125%) e maiuscolo solo per
  marchio e titolo di pagina (`x-ui.heading`); `--semilargo` per titoli di scheda, pulsanti e numeri; testo corrente a larghezza normale.
- Pagine per ospiti (`.pagina-ospite`): fondo ambra con i fori della racchetta. Pagine autenticate: barra nera con filo ambra.

## Aggiornamento risorse (skill `aggiorna-risorse`)
Il blocco qui sotto dichiara le risorse statiche del progetto: lo legge la skill `aggiorna-risorse`, che aggiorna le
librerie da npm e scrive la release in tutti i file indicati. Una risorsa è una **libreria** (`npm` + `cartella`)
oppure un insieme di **file propri** (`file`); `release` elenca dove è scritta la versione, con `{release}` al suo posto
nel `modello`. Libreria nuova, file spostato o release scritta anche altrove = aggiornare questo blocco.
`impronta` è gestita dalla skill (serve a capire se i file propri sono cambiati): non modificarla a mano.

```risorse
{
  "risorse": [
    {
      "nome": "app",
      "file": ["public/css/torneo.css", "public/js/torneo.js"],
      "release": [
        { "file": "config/risorse.php", "modello": "'app' => '{release}'" }
      ]
    },
    {
      "nome": "bootstrap",
      "npm": ["bootstrap"],
      "cartella": "public/vendor/bootstrap",
      "release": [
        { "file": "config/risorse.php", "modello": "'bootstrap' => '{release}'" }
      ]
    },
    {
      "nome": "bootstrap-icons",
      "npm": ["bootstrap-icons"],
      "cartella": "public/vendor/bootstrap-icons",
      "release": [
        { "file": "config/risorse.php", "modello": "'bootstrap-icons' => '{release}'" }
      ]
    },
    {
      "nome": "jquery",
      "npm": ["jquery"],
      "cartella": "public/vendor/jquery",
      "release": [
        { "file": "config/risorse.php", "modello": "'jquery' => '{release}'" }
      ]
    },
    {
      "nome": "datatables",
      "npm": ["datatables.net", "datatables.net-bs5"],
      "cartella": "public/vendor/datatables",
      "release": [
        { "file": "config/risorse.php", "modello": "'datatables' => '{release}'" }
      ]
    },
    {
      "nome": "archivo",
      "npm": ["@fontsource-variable/archivo"],
      "cartella": "public/vendor/fonts/archivo",
      "release": [
        { "file": "config/risorse.php", "modello": "'archivo' => '{release}'" }
      ]
    }
  ]
}
```

## Test
- `tests/Unit/Sorteggio/` (`SorteggioCoppieTest`, `SorteggioGironiTest`, `CalendarioGironeTest`) usano
  `PHPUnit\Framework\TestCase` puro: ogni modifica agli algoritmi deve mantenerli verdi e aggiungere i nuovi casi.
- Proprietà da preservare: ogni giocatore in una sola coppia ordinaria; chi è in prestito in due coppie, mai nello
  stesso girone; massimo numero di coppie con lati complementari; gironi entro il massimo e con al più una coppia di
  differenza; nel calendario ogni coppia incontra tutte le altre una volta (due con il ritorno, a campi invertiti) e
  mai due volte nella stessa giornata.
- `tests/Feature/Amministrazione/SorteggioTorneoTest.php`: formula, i tre passi, prestito, chiusura delle iscrizioni.
- `tests/Unit/Punteggio`, `tests/Unit/Classifica`, `tests/Unit/Finali`: regole pure di punteggio, classifica e
  qualificazione (stesse regole dei test del sorteggio: `TestCase` puro, casi nuovi per ogni modifica).
- `tests/Unit/Sorteggio/ScambioCoppieTest.php`, `RisorteggioCoppieTest.php`: i due modi di formare le coppie nelle finali.
- `tests/Unit/Classifica/ClassificaGiocatoriTest.php`, `tests/Feature/Amministrazione/MiglioriGiocatoriTest.php`:
  classifica individuale e migliori per categoria. Il test funzionale punta le due categorie su dati che i giocatori
  hanno di sicuro (fascia di gioco e circolo), così non dipende dai nomi dei campi in anagrafica.
- `tests/Feature/Amministrazione/FormulaPredefinitaTest.php`: i valori con cui nasce un torneo (squadre per girone,
  qualificate, formato fase per fase) e un torneo intero giocato con quelli.
- `tests/Concerns/FormulaClassica.php`: trait per i test i cui scenari contano su gironi da 4 e partite al meglio di
  3 set a 6 game con il tie-break. **I test non devono dipendere dai predefiniti di `config/tornei.php`**: o usano
  il trait o impostano la formula del loro torneo.
- `tests/Feature/Amministrazione/InserimentoContinuoRisultatiTest.php`: salvataggio in sottofondo, ordine delle
  partite proposte, cambio di girone e di turno, «Successiva», risposte JSON degli errori.
- `tests/Feature/Amministrazione/RisultatiEFinaliTest.php`: formato per fase, risultati, classifica, generazione delle
  finali (ripescaggi, posti vuoti, prestito), sorteggio turno per turno, correzioni, blocchi, permessi.
- Proprietà da preservare nelle finali: ogni giocatore in una sola coppia per turno, uno per fascia; con lo scambio
  **nessuna coppia due volte in tutto il torneo**, per qualunque numero di turni e qualunque esito (lo verifica
  `test_in_un_tabellone_da_trentadue_coppie_nessuna_coppia_si_ripete_fino_alla_finale`); i posti vuoti solo per le
  migliori qualificate; un giocatore di una coppia con prestito entra nelle finali al più una volta.
- `tests/Feature/Test/TorneoDiTestTest.php`: factory con i giocatori, rotte di test del sito e dell'API (numero nel
  link, limiti, permessi, 404 in produzione). Per simulare la produzione il test imposta `$this->app['env']`.
- `tests/Feature/Amministrazione/MappaDelSitoTest.php`: mappa del sito (sezioni, descrizioni, accesso, link, rotte
  di test) e obbligo di `->descrizione()` su ogni rotta: una rotta nuova senza descrizione fa fallire la suite.
- Nei test funzionali i nomi dei giocatori sono casuali e possono contenere apostrofi: nel confronto con l'HTML si
  usa `e(...)` oppure `assertSee`.

## Stile
- PSR-12 / Laravel Pint; tipizzazione stretta (tipi di ritorno, property promotion, `readonly` sui DTO).
- Nomi di classi, metodi e variabili di dominio in italiano (`sorteggiaTurno`, `registraRisultato`, `turniNecessari`).
- Migrazioni nuove: non modificare quelle già eseguite, aggiungerne di nuove.
