<?php

namespace App\Services\Tornei;

use App\Enums\Fase;
use App\Exceptions\SorteggioImpossibileException;
use App\Models\Coppia;
use App\Models\Giocatore;
use App\Models\Girone;
use App\Models\Torneo;
use App\Services\Sorteggio\CalendarioGirone;
use App\Services\Sorteggio\GiocatoreSorteggio;
use App\Services\Sorteggio\SorteggioCoppie;
use App\Services\Sorteggio\SorteggioGironi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * Sorteggio di un torneo "sociale", in tre passi che dipendono l'uno dall'altro:
 * coppie (un giocatore per fascia) → gironi (gruppi di coppie) → partite (calendario all'italiana di ogni girone).
 * Rifare un passo cancella quelli che ne dipendono, risultati e finali compresi (le finali: App\Services\Tornei\FinaliService).
 *
 * Qui stanno la lettura dei dati e il salvataggio; gli algoritmi sono in App\Services\Sorteggio.
 */
class TorneoSocialeService
{
    public function __construct(
        private readonly SorteggioCoppie $sorteggioCoppie = new SorteggioCoppie,
        private readonly SorteggioGironi $sorteggioGironi = new SorteggioGironi,
        private readonly CalendarioGirone $calendario = new CalendarioGirone,
        private readonly Randomizer $caso = new Randomizer,
    ) {}

    /**
     * Le due fasce che formano una coppia, nell'ordine di config (prima, seconda).
     *
     * @return array{int, int}
     */
    public function fasce(Torneo $torneo): array
    {
        $fasce = array_keys($torneo->regole()['squadra']['composizione']);

        return [(int) $fasce[0], (int) $fasce[1]];
    }

    /**
     * Fasce non pari tra gli iscritti: `null` se sono pari (o se manca del tutto una fascia:
     * in quel caso non c'è nessuna coppia da completare).
     */
    public function squilibrio(Torneo $torneo): ?Squilibrio
    {
        [$prima, $seconda] = $this->fasce($torneo);

        $perFascia = $torneo->giocatori()->get(['giocatori.id', 'fascia'])->countBy('fascia');
        $numeroPrima = $perFascia->get($prima, 0);
        $numeroSeconda = $perFascia->get($seconda, 0);

        if ($numeroPrima === $numeroSeconda || $numeroPrima === 0 || $numeroSeconda === 0) {
            return null;
        }

        $maggiore = max($numeroPrima, $numeroSeconda);
        $minore = min($numeroPrima, $numeroSeconda);
        $etichette = config('tornei.giocatori.fasce');
        $fasciaMancante = $numeroPrima > $numeroSeconda ? $seconda : $prima;

        $impedimento = match (true) {
            $maggiore - $minore > $minore => 'Le fasce sono troppo sbilanciate: ogni giocatore può andare in prestito una sola volta, '
                .'quindi i giocatori in più non possono superare quelli di '.mb_strtolower($etichette[$fasciaMancante]).'.',
            // Le coppie saranno tante quanti i giocatori della fascia più numerosa.
            $maggiore <= $torneo->squadre_per_girone => 'Con '.$maggiore.' coppie e fino a '.$torneo->squadre_per_girone
                .' squadre per girone ci sarebbe un solo girone, e chi è in prestito deve giocare in due gironi diversi. '
                .'Pareggia le fasce oppure riduci il numero massimo di squadre per girone.',
            default => null,
        };

        return new Squilibrio(
            fasciaInEccesso: $numeroPrima > $numeroSeconda ? $prima : $seconda,
            fasciaMancante: $fasciaMancante,
            inEccesso: $maggiore - $minore,
            impedimento: $impedimento,
        );
    }

    /**
     * Sorteggia le coppie tra tutti gli iscritti. Sostituisce l'eventuale sorteggio precedente
     * (coppie, gironi e partite).
     *
     * @param  list<int>|null  $prestiti  con fasce non pari: i giocatori indicati per il prestito,
     *                                    oppure `null` per sorteggiarli
     * @return Collection<int, Coppia>
     *
     * @throws SorteggioImpossibileException
     */
    public function generaCoppie(Torneo $torneo, ?array $prestiti = null): Collection
    {
        [$prima, $seconda] = $this->fasce($torneo);
        $squilibrio = $this->squilibrio($torneo);

        if ($squilibrio !== null && ! $squilibrio->prestitoPossibile()) {
            throw new SorteggioImpossibileException($squilibrio->impedimento);
        }

        $iscritti = $torneo->giocatori()->get()->groupBy('fascia');
        $perSorteggio = fn (int $fascia): array => $iscritti->get($fascia, collect())
            ->map(fn (Giocatore $giocatore) => new GiocatoreSorteggio($giocatore->id, $giocatore->lato))
            ->values()
            ->all();

        $sorteggiate = $this->sorteggioCoppie->sorteggia(
            $perSorteggio($prima),
            $perSorteggio($seconda),
            $torneo->latiNelSorteggio(),
            $prestiti,
        );

        return DB::transaction(function () use ($torneo, $sorteggiate): Collection {
            $this->annulla($torneo);

            return collect($sorteggiate)->map(fn ($coppia) => $torneo->coppie()->create([
                'prima_fascia_id' => $coppia->prima,
                'seconda_fascia_id' => $coppia->seconda,
                'prestito_id' => $coppia->prestito,
            ]));
        });
    }

    /**
     * Divide le coppie in gironi di al più `squadre_per_girone` squadre. Sostituisce i gironi
     * precedenti e ne cancella le partite.
     *
     * @return Collection<int, Girone>
     *
     * @throws SorteggioImpossibileException
     */
    public function generaGironi(Torneo $torneo): Collection
    {
        $coppie = $torneo->coppie()->get();

        if ($coppie->isEmpty()) {
            throw new SorteggioImpossibileException('Prima dei gironi vanno generate le coppie.');
        }

        $divisione = $this->sorteggioGironi->dividi(
            $coppie->mapWithKeys(fn (Coppia $coppia) => [$coppia->id => [$coppia->prima_fascia_id, $coppia->seconda_fascia_id]])->all(),
            $torneo->squadre_per_girone,
        );

        return DB::transaction(function () use ($torneo, $divisione): Collection {
            // Cancella le partite (anche quelle delle finali, che non hanno girone) e libera le coppie.
            $this->cancellaPartite($torneo);
            $torneo->gironi()->reorder()->delete();

            return collect($divisione)->map(function (array $coppieDelGirone, int $posizione) use ($torneo): Girone {
                $girone = $torneo->gironi()->create([
                    'nome' => Girone::nomePerPosizione($posizione),
                    'posizione' => $posizione,
                ]);

                $torneo->coppie()->whereKey($coppieDelGirone)->update(['girone_id' => $girone->id]);

                return $girone;
            });
        });
    }

    /**
     * Genera il calendario all'italiana di ogni girone (sola andata o andata e ritorno, secondo la formula
     * del torneo). Sostituisce le partite precedenti.
     *
     * @return int numero di partite create
     *
     * @throws SorteggioImpossibileException
     */
    public function generaPartite(Torneo $torneo): int
    {
        $gironi = $torneo->gironi()->with('coppie:id,girone_id')->get();

        if ($gironi->isEmpty()) {
            throw new SorteggioImpossibileException('Prima delle partite vanno generati i gironi.');
        }

        return DB::transaction(function () use ($torneo, $gironi): int {
            $this->cancellaPartite($torneo);

            $create = 0;

            foreach ($gironi as $girone) {
                $coppie = $girone->coppie->modelKeys();

                // L'ordine delle coppie decide chi incontra chi a ogni giornata: lo si estrae a caso.
                $partite = $this->calendario->genera(
                    $coppie === [] ? [] : $this->caso->shuffleArray($coppie),
                    $torneo->andata_ritorno,
                );

                foreach ($partite as $partita) {
                    $girone->partite()->create([
                        'torneo_id' => $torneo->id,
                        'fase' => Fase::Gironi,
                        'giornata' => $partita['giornata'],
                        'ritorno' => $partita['ritorno'],
                        'coppia_a_id' => $partita['a'],
                        'coppia_b_id' => $partita['b'],
                    ]);
                    $create++;
                }
            }

            return $create;
        });
    }

    /** Cancella tutto il sorteggio del torneo (partite, gironi e coppie): le iscrizioni si riaprono. */
    public function annulla(Torneo $torneo): void
    {
        DB::transaction(function () use ($torneo): void {
            $this->cancellaPartite($torneo);
            $torneo->gironi()->reorder()->delete();
            $torneo->coppie()->delete();
        });
    }

    /**
     * Cancella tutte le partite del torneo, con i risultati, le coppie risorteggiate per le finali e
     * l'accesso alle finali delle coppie dei gironi.
     */
    private function cancellaPartite(Torneo $torneo): void
    {
        $torneo->partite()->delete();
        $torneo->coppieDelleFinali()->delete();
        $torneo->coppie()->update(['finali' => null, 'testa_di_serie' => null]);
    }
}
