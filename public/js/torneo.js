/*
 * Torneo Padel — comportamenti jQuery comuni.
 * Nessun JS inline nelle view: gli elementi si agganciano tramite attributi data-*.
 */
$(function () {
    'use strict';

    // --- Mostra / nascondi password --------------------------------------
    // <button data-mostra-password="#id-del-campo">
    $(document).on('click', '[data-mostra-password]', function () {
        var $pulsante = $(this);
        var $campo = $($pulsante.data('mostraPassword'));
        var visibile = $campo.attr('type') === 'text';

        $campo.attr('type', visibile ? 'password' : 'text').trigger('focus');
        $pulsante
            .attr('aria-pressed', String(!visibile))
            .attr('aria-label', visibile ? 'Mostra la password' : 'Nascondi la password')
            .find('.bi').toggleClass('bi-eye', visibile).toggleClass('bi-eye-slash', !visibile);
    });

    // --- Requisiti della password ----------------------------------------
    // <div data-requisiti-password="#password" data-conferma="#password_confirmation">
    //   <li data-requisito="lunghezza" data-min="10">…
    // Le verifiche ricalcano quelle del server (Illuminate\Validation\Rules\Password);
    // la validazione vera resta comunque quella lato server.
    var verifiche = {
        lunghezza: function (password, $voce) {
            return Array.from(password).length >= Number($voce.data('min'));
        },
        lettere: function (password) {
            return /\p{L}/u.test(password);
        },
        maiuscole_minuscole: function (password) {
            return /\p{Lu}/u.test(password) && /\p{Ll}/u.test(password);
        },
        numeri: function (password) {
            return /\p{N}/u.test(password);
        },
        simboli: function (password) {
            return /[\p{Z}\p{S}\p{P}]/u.test(password);
        },
        conferma: function (password, $voce, conferma) {
            return password !== '' && password === conferma;
        }
    };

    $('[data-requisiti-password]').each(function () {
        var $elenco = $(this);
        var $password = $($elenco.data('requisitiPassword'));
        var $conferma = $($elenco.data('conferma'));

        function aggiorna() {
            var password = $password.val() || '';
            var conferma = $conferma.val() || '';

            $elenco.find('[data-requisito]').each(function () {
                var $voce = $(this);
                var verifica = verifiche[$voce.data('requisito')];
                var ok = verifica ? verifica(password, $voce, conferma) : false;

                $voce.toggleClass('soddisfatto', ok)
                    .find('.bi').toggleClass('bi-check-circle-fill', ok).toggleClass('bi-circle', !ok);
            });
        }

        $password.add($conferma).on('input', aggiorna);
        aggiorna();
    });

    // --- Tabelle con ricerca, ordinamento e paginazione (DataTables) ------
    // <table data-datatable data-vuoto="…" data-ordine="[[0,&quot;asc&quot;]]" data-righe="10"
    //        data-singolare="torneo" data-plurale="tornei">   (componente x-ui.datatable)
    // Colonne da escludere: <th data-orderable="false" data-searchable="false">.
    // La libreria è presente solo nelle pagine che contengono una di queste tabelle.
    var linguaTabelle = {
        info: 'Da _START_ a _END_ di _TOTAL_ _ENTRIES-TOTAL_',
        infoEmpty: 'Nessun elemento da mostrare',
        infoFiltered: '(su _MAX_ in totale)',
        lengthMenu: '_MENU_ _ENTRIES_ per pagina',
        loadingRecords: 'Caricamento…',
        search: 'Cerca:',
        thousands: '.',
        zeroRecords: 'Nessun risultato per questa ricerca.',
        aria: {
            orderable: ': attiva per ordinare',
            orderableReverse: ": attiva per invertire l'ordinamento",
            orderableRemove: ": attiva per rimuovere l'ordinamento",
            paginate: {
                first: 'Prima pagina',
                last: 'Ultima pagina',
                next: 'Pagina successiva',
                previous: 'Pagina precedente'
            }
        }
    };

    if (window.DataTable) {
        $('[data-datatable]').each(function () {
            var $tabella = $(this);
            // Filtri di colonna (slot `filters` di x-ui.datatable): vanno accanto alla casella di ricerca.
            var $filtri = $tabella.prev('[data-datatable-filtri]');

            var opzioni = {
                order: $tabella.data('ordine') || [],
                pageLength: Number($tabella.data('righe')) || 10,
                autoWidth: false,
                language: $.extend(true, {}, linguaTabelle, {
                    emptyTable: $tabella.data('vuoto'),
                    entries: {
                        _: $tabella.data('plurale') || 'elementi',
                        1: $tabella.data('singolare') || 'elemento'
                    }
                })
            };

            if ($filtri.length) {
                opzioni.layout = { topEnd: [$filtri[0], 'search'] };
            }

            var tabella = new window.DataTable(this, opzioni);

            // <select data-filtro-colonna="4">: restano le righe in cui la colonna è uguale alla voce scelta.
            $filtri.on('change', '[data-filtro-colonna]', function () {
                tabella
                    .column(Number($(this).data('filtroColonna')))
                    .search($(this).val(), { exact: true })
                    .draw();
            });
        });
    }

    // --- Filtro delle voci di un elenco (x-ui.list-filter) ------------------
    // <select data-filtro-elenco="#id-elenco">: restano visibili le voci con `data-gruppo` uguale alla voce scelta.
    $(document).on('change', '[data-filtro-elenco]', function () {
        var valore = $(this).val();

        $($(this).data('filtroElenco')).children().each(function () {
            $(this).toggleClass('d-none', valore !== '' && $(this).attr('data-gruppo') !== valore);
        });
    });

    // --- Righe ripetute di un form (x-ui.repeater) --------------------------
    // <div data-ripeti data-ripeti-prossimo="1">
    //   <div data-ripeti-elenco> <div data-riga>…<button data-riga-rimuovi>…</div> </div>
    //   <template data-ripeti-modello> riga vuota con __INDICE__ al posto dell'indice </template>
    // In fondo c'è sempre una riga vuota: appena viene compilata ne compare un'altra.
    $('[data-ripeti]').each(function () {
        var $blocco = $(this);
        var $elenco = $blocco.children('[data-ripeti-elenco]');
        var modello = $blocco.children('template[data-ripeti-modello]').html();
        var prossimo = Number($blocco.data('ripetiProssimo')) || 0;

        function vuota($riga) {
            return $riga.find('input, select, textarea').filter(function () {
                return $.trim($(this).val()) !== '';
            }).length === 0;
        }

        function sistema() {
            var $righe = $elenco.children('[data-riga]');

            if (!$righe.length || !vuota($righe.last())) {
                $elenco.append(modello.replace(/__INDICE__/g, prossimo++));
                $righe = $elenco.children('[data-riga]');
            }

            // La riga in attesa (l'ultima, vuota) non si può togliere.
            $righe.find('[data-riga-rimuovi]').removeClass('invisible').prop('disabled', false);
            $righe.last().find('[data-riga-rimuovi]').addClass('invisible').prop('disabled', true);
        }

        $blocco.on('input change', 'input, select, textarea', sistema);

        $blocco.on('click', '[data-riga-rimuovi]', function () {
            $(this).closest('[data-riga]').remove();
            sistema();
        });

        sistema();
    });

    // --- Formato delle partite: punti del tie-break e due game di scarto (x-tornei.formula) -------
    // <tr data-formato-fase data-formato-standard="7"> con <select data-formato-tie-break>, <input data-formato-punti>
    // e <input type="checkbox" data-formato-scarto>:
    // - i punti si scrivono solo con il tie-break "personalizzato"; negli altri casi il campo è spento, non viene
    //   inviato e mostra ciò che vale (i punti standard, oppure niente se il tie-break non si gioca);
    // - i due game di scarto si scelgono solo senza tie-break: con il tie-break la casella è spenta e senza spunta.
    $('[data-formato-fase]').each(function () {
        var $riga = $(this);
        var $tieBreak = $riga.find('[data-formato-tie-break]');
        var $punti = $riga.find('[data-formato-punti]');
        var $scarto = $riga.find('[data-formato-scarto]');
        var standard = $riga.data('formatoStandard');

        function aggiorna() {
            var senzaTieBreak = $tieBreak.val() === 'no';

            $punti.prop('disabled', $tieBreak.val() !== 'personalizzato');
            $scarto.prop('disabled', !senzaTieBreak);
            if (!senzaTieBreak) {
                $scarto.prop('checked', false);
            }
        }

        $tieBreak.on('change', function () {
            var scelta = $tieBreak.val();

            aggiorna();
            if (scelta === 'no') {
                $punti.val('');
            } else if (scelta === 'standard' || $punti.val() === '') {
                $punti.val(standard);
            }
            if (scelta === 'personalizzato') {
                $punti.trigger('focus').trigger('select');
            }
        });
        aggiorna();
    });

    // --- Risultato di una partita (x-partite.risultato, x-partite.modale-risultato) ---
    // <button data-risultato data-partita="12" data-azione="…/risultato" data-cancella="…/risultato|''"
    //         data-successiva="…/successiva" data-coppia-a="…" data-coppia-b="…"
    //         data-gruppo="girone-3" data-gruppo-nome="Girone A" data-dettaglio="1ª giornata"
    //         data-set-massimi="3" data-tie-break="1" data-formato="Al meglio di 3 set…"
    //         data-set='[{"a":6,"b":4,"tb_a":null,"tb_b":null}]'>
    // La finestra #modale-risultato è una sola per pagina. Inserimento continuo: il salvataggio avviene in
    // sottofondo e la finestra resta aperta sulla prossima partita senza risultato, che arriva dal server con le
    // stesse voci degli attributi qui sopra (App\Support\SchedaRisultato). Alla chiusura la pagina si aggiorna.
    var $finestraRisultato = $('#modale-risultato');
    var $formRisultato = $finestraRisultato.find('#form-risultato');
    var $griglia = $finestraRisultato.find('[data-punteggio]');
    var partitaAperta = null;      // la partita mostrata nella finestra
    var punteggioIniziale = '';    // la griglia com'era quando la partita è stata mostrata
    var risultatiSalvati = 0;      // salvataggi fatti da quando la finestra è aperta
    var richiestaInCorso = false;

    // I campi in uso, in ordine: serve a capire se nella griglia è stato scritto qualcosa.
    function punteggioScritto() {
        return $griglia.find('input:enabled').map(function () {
            return $.trim(this.value);
        }).get().join('|');
    }

    // Mostra o nasconde uno degli avvisi della finestra (`esito`, `cambio`, `errore`, `fine`).
    function avvisoRisultato(nome, testo) {
        $finestraRisultato.find('[data-risultato-' + nome + ']')
            .toggleClass('d-none', !testo)
            .find('[data-testo]').text(testo || '');
    }

    function togliErroriRisultato() {
        avvisoRisultato('errore', '');
        $griglia.find('.is-invalid').removeClass('is-invalid');
        $griglia.find('[data-punteggio-errori]').empty();
    }

    // Riempie la finestra con una partita. `accendi`: il girone (o il turno) è cambiato, la fascia lo segnala.
    function mostraPartita(partita, accendi) {
        var setMassimi = Number(partita.setMassimi) || 1;
        var tieBreak = Number(partita.tieBreak) === 1;
        var set = partita.set || [];
        var $testata = $finestraRisultato.find('[data-risultato-testata]');

        $formRisultato.attr('action', partita.azione);
        $finestraRisultato.find('[data-risultato-partita]').val(partita.partita);
        $finestraRisultato.find('[data-risultato-formato]').text(partita.formato);
        $finestraRisultato.find('[data-risultato-gruppo]').text(partita.gruppoNome || '');
        $finestraRisultato.find('[data-risultato-dettaglio]').text(partita.dettaglio || '');
        $finestraRisultato.find('[data-risultato-cancella]')
            .toggleClass('d-none', !partita.cancella)
            .attr('action', partita.cancella || '#');

        // Togliere e rimettere la classe fa ripartire l'animazione anche a due cambi di fila.
        $testata.removeClass('gruppo-cambiato');
        if (accendi) {
            void $testata[0].offsetWidth;
            $testata.addClass('gruppo-cambiato');
        }

        togliErroriRisultato();
        $griglia.find('[data-punteggio-coppia="a"]').text(partita.coppiaA);
        $griglia.find('[data-punteggio-coppia="b"]').text(partita.coppiaB);
        $griglia.find('[data-punteggio-tie-break]').toggleClass('d-none', !tieBreak);

        // Colonne in uso = set del formato; i campi delle altre (e del tie-break, se non previsto) non vengono inviati.
        $griglia.find('[data-punteggio-set]').each(function () {
            var indice = Number($(this).data('punteggioSet'));
            var inUso = indice < setMassimi;
            var $campo = $(this).find('input');

            $(this).toggleClass('d-none', !inUso);

            if (!$campo.length) {
                return;
            }

            var nome = ($campo.attr('name').match(/\[(\w+)\]$/) || [])[1];
            var diTieBreak = nome === 'tb_a' || nome === 'tb_b';
            var valore = set[indice] ? set[indice][nome] : null;

            $campo
                .prop('disabled', !inUso || (diTieBreak && !tieBreak))
                .val(valore === null || valore === undefined ? '' : valore);
        });

        partitaAperta = partita;
        punteggioIniziale = punteggioScritto();
    }

    // Errori di validazione del salvataggio in sottofondo: {"set.1.a": ["…"], "set": ["…"]}.
    // Quelli di un set finiscono sotto la griglia e segnano la sua colonna, gli altri nell'avviso.
    function mostraErroriRisultato(errori) {
        var generali = [];
        var modello = $griglia.find('template[data-punteggio-errore]')[0];

        $.each(errori || {}, function (chiave, messaggi) {
            var parti = chiave.match(/^set\.(\d+)\./);

            if (!parti || !modello) {
                generali = generali.concat(messaggi);
                return;
            }

            var indice = Number(parti[1]);

            $griglia.find('td[data-punteggio-set="' + indice + '"] input').addClass('is-invalid');
            $.each(messaggi, function (_, messaggio) {
                var riga = modello.content.firstElementChild.cloneNode(true);

                riga.textContent = (indice + 1) + '° set: ' + messaggio;
                $griglia.find('[data-punteggio-errori]').append(riga);
            });
        });

        avvisoRisultato('errore', generali.join(' '));
    }

    function fuocoSulPunteggio() {
        var $campo = $griglia.find('.is-invalid:enabled').first();

        if (!$campo.length) {
            $campo = $griglia.find('[data-fuoco]');
        }
        $campo.trigger('focus').trigger('select');
    }

    function occupaRisultato(occupata) {
        richiestaInCorso = occupata;
        $finestraRisultato.find('[data-risultato-salva], [data-risultato-successiva]').prop('disabled', occupata);
    }

    // Va a un indirizzo; se è la pagina corrente (al più con un'altra àncora) la ricarica, con una sola
    // richiesta: il messaggio di conferma preparato dal server vale per la prima pagina che viene chiesta.
    function vaiAllaPagina(indirizzo) {
        var destinazione = new URL(indirizzo, window.location.href);

        if (destinazione.pathname !== window.location.pathname || destinazione.search !== window.location.search) {
            window.location.href = indirizzo;
            return;
        }

        if (destinazione.hash && destinazione.hash !== window.location.hash) {
            window.location.hash = destinazione.hash;
        }
        window.location.reload();
    }

    // Risposta del server dopo un salvataggio o dopo «Successiva»:
    // { esito, cambio, successiva: {…partita…} | null, fine, vai }.
    function proseguiConRisultati(risposta) {
        avvisoRisultato('esito', risposta.esito);
        avvisoRisultato('cambio', risposta.cambio);
        avvisoRisultato('fine', risposta.fine);

        if (risposta.successiva) {
            mostraPartita(risposta.successiva, risposta.successiva.gruppo !== partitaAperta.gruppo);
            fuocoSulPunteggio();
        } else if (risposta.vai) {
            // Non c'è altro da inserire: la pagina mostra la conferma e il passo successivo.
            risultatiSalvati = 0;
            vaiAllaPagina(risposta.vai);
        }
    }

    function erroreDiRete(xhr) {
        return xhr.status === 419 || xhr.status === 401
            ? 'La sessione è scaduta: ricarica la pagina e accedi di nuovo.'
            : 'Operazione non riuscita: controlla la connessione e riprova.';
    }

    function salvaRisultato() {
        if (richiestaInCorso) {
            return;
        }

        occupaRisultato(true);
        togliErroriRisultato();
        avvisoRisultato('fine', '');

        $.ajax({ url: $formRisultato.attr('action'), method: 'POST', data: $formRisultato.serialize(), dataType: 'json' })
            .done(function (risposta) {
                risultatiSalvati += 1;
                proseguiConRisultati(risposta);
            })
            .fail(function (xhr) {
                var corpo = xhr.responseJSON || {};

                // 422: punteggio respinto dalla validazione (`errors`) oppure salvataggio non consentito (`message`).
                if (xhr.status === 422 && corpo.errors) {
                    mostraErroriRisultato(corpo.errors);
                } else {
                    avvisoRisultato('errore', xhr.status === 422 && corpo.message ? corpo.message : erroreDiRete(xhr));
                }
                fuocoSulPunteggio();
            })
            .always(function () {
                occupaRisultato(false);
            });
    }

    function partitaSuccessiva() {
        if (richiestaInCorso) {
            return;
        }

        occupaRisultato(true);
        togliErroriRisultato();

        $.ajax({ url: partitaAperta.successiva, method: 'GET', dataType: 'json' })
            .done(proseguiConRisultati)
            .fail(function (xhr) {
                avvisoRisultato('errore', erroreDiRete(xhr));
            })
            .always(function () {
                occupaRisultato(false);
            });
    }

    $(document).on('click', '[data-risultato]', function () {
        avvisoRisultato('esito', '');
        avvisoRisultato('cambio', '');
        avvisoRisultato('fine', '');
        mostraPartita($.extend({}, $(this).data()), false);

        bootstrap.Modal.getOrCreateInstance($finestraRisultato[0]).show();
    });

    // Senza una partita mostrata da qui (finestra riaperta dal server dopo un invio senza JavaScript)
    // il form segue la strada normale.
    $formRisultato.on('submit', function (evento) {
        if (!partitaAperta) {
            return;
        }

        evento.preventDefault();
        salvaRisultato();
    });

    // «Successiva»: un punteggio scritto e non ancora salvato viene salvato prima di passare oltre.
    $finestraRisultato.on('click', '[data-risultato-successiva]', function () {
        var scritto = punteggioScritto();

        if (!partitaAperta) {
            return;
        }

        if (scritto !== punteggioIniziale && /[^|]/.test(scritto)) {
            salvaRisultato();
        } else {
            partitaSuccessiva();
        }
    });

    // La pagina sotto la finestra è rimasta a prima dei salvataggi: alla chiusura si aggiorna e si ferma sul
    // girone della partita che si stava guardando (la sua scheda ha per id la chiave del gruppo: `girone-3`;
    // nel tabellone i turni non hanno una scheda e la pagina resta dov'è).
    $finestraRisultato.on('hidden.bs.modal', function () {
        if (risultatiSalvati === 0) {
            return;
        }

        if (partitaAperta && partitaAperta.gruppo && document.getElementById(partitaAperta.gruppo)) {
            window.location.hash = partitaAperta.gruppo;
        }
        window.location.reload();
    });

    // --- Finestre modali ---------------------------------------------------
    // <div class="modal" data-apri-modale>: si apre al caricamento (form respinto dalla validazione).
    $('[data-apri-modale]').first().each(function () {
        bootstrap.Modal.getOrCreateInstance(this).show();
    });

    // All'apertura il cursore va sul primo campo con un errore, altrimenti su quello marcato `data-fuoco`.
    $(document).on('shown.bs.modal', '.modal', function () {
        var $modale = $(this);
        var $campo = $modale.find('.is-invalid').first();

        if (!$campo.length) {
            $campo = $modale.find('[data-fuoco]').first();
        }
        $campo.trigger('focus');
    });

    // --- Conferma prima dell'invio ------------------------------------------
    // <form data-conferma="Eliminare …?" data-conferma-azione="Elimina">
    // La domanda compare nella finestra #modale-conferma (componente x-ui.conferma).
    // Gestione delegata: vale anche per le righe che DataTables mostra nelle pagine successive.
    var $modaleConferma = $('#modale-conferma');
    var formInAttesa = null;

    $(document).on('submit', 'form[data-conferma]', function (evento) {
        if (!$modaleConferma.length) {
            return;
        }

        evento.preventDefault();
        formInAttesa = this;

        $modaleConferma.find('[data-conferma-testo]').text($(this).data('conferma'));
        $modaleConferma.find('[data-conferma-etichetta]').text($(this).data('confermaAzione') || 'Conferma');
        bootstrap.Modal.getOrCreateInstance($modaleConferma[0]).show();
    });

    $modaleConferma.on('click', '[data-conferma-ok]', function () {
        if (formInAttesa) {
            $(this).prop('disabled', true);
            // submit() nativo: non rilancia l'evento `submit`, quindi non richiede un'altra conferma.
            formInAttesa.submit();
        }
    });

    $modaleConferma.on('hidden.bs.modal', function () {
        formInAttesa = null;
    });
});
