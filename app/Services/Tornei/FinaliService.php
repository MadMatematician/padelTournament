<?php

namespace App\Services\Tornei;

use App\Enums\Fase;
use App\Exceptions\SorteggioImpossibileException;
use App\Models\Coppia;
use App\Models\Giocatore;
use App\Models\Girone;
use App\Models\Partita;
use App\Models\Torneo;
use App\Services\Classifica\ClassificaGirone;
use App\Services\Classifica\RigaClassifica;
use App\Services\Finali\AccessoFinali;
use App\Services\Finali\CoppieNelleFinali;
use App\Services\Finali\Qualificazione;
use App\Services\Sorteggio\CoppiaSorteggiata;
use App\Services\Sorteggio\GiocatoreSorteggio;
use App\Services\Sorteggio\LatiComplementari;
use App\Services\Sorteggio\RisorteggioCoppie;
use App\Services\Sorteggio\ScambioCoppie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dai gironi alle finali: classifiche, squadre che accedono (qualificate e ripescate) e tabellone
 * a eliminazione diretta.
 *
 * Nelle finali le coppie non sono quelle dei gironi: a ogni turno i giocatori ancora in gara formano coppie
 * nuove, uno di prima fascia con uno di seconda, senza rimettere insieme chi nel torneo ha già fatto coppia.
 * Il primo turno si sorteggia quando si genera il tabellone, ogni turno successivo appena il precedente è
 * concluso (lo chiede RisultatiService). Come si formano le coppie dipende dalla tipologia
 * (`Torneo::coppieNelleFinali()`): con lo scambio dei compagni tra le coppie sorteggiate insieme (ScambioCoppie,
 * nessuna ripetizione, mai) oppure con un sorteggio libero tra tutti i giocatori rimasti (RisorteggioCoppie).
 *
 * Qui stanno la lettura dei dati e il salvataggio; le regole sono in App\Services\Classifica,
 * App\Services\Finali e App\Services\Sorteggio.
 */
class FinaliService
{
    public function __construct(
        private readonly ClassificaGirone $classifica = new ClassificaGirone,
        private readonly Qualificazione $qualificazione = new Qualificazione,
        private readonly ScambioCoppie $scambio = new ScambioCoppie,
        private readonly RisorteggioCoppie $risorteggio = new RisorteggioCoppie,
    ) {}

    /**
     * La classifica di ogni girone del torneo, calcolata sulle partite che hanno un risultato.
     *
     * @return Collection<int, list<RigaClassifica>> identificativo del girone => righe dalla prima all'ultima
     */
    public function classifiche(Torneo $torneo): Collection
    {
        $punti = config('tornei.classifica.punti');

        return $torneo->gironi()
            ->with(['coppie:id,girone_id', 'partite' => fn ($partite) => $partite->whereNotNull('risultato')])
            ->get()
            ->mapWithKeys(fn (Girone $girone) => [$girone->id => $this->classifica->calcola(
                $girone->coppie->modelKeys(),
                $girone->partite->map(fn (Partita $partita) => [
                    'a' => $partita->coppia_a_id,
                    'b' => $partita->coppia_b_id,
                    'vincitrice' => $partita->vincitrice_id,
                    'set_a' => $partita->set_a,
                    'set_b' => $partita->set_b,
                    'game_a' => $partita->game_a,
                    'game_b' => $partita->game_b,
                ])->all(),
                $punti['vittoria'],
                $punti['sconfitta'],
            )]);
    }

    /** Partite dei gironi che non hanno ancora un risultato. */
    public function partiteDaGiocare(Torneo $torneo): int
    {
        return $torneo->partiteDiGirone()->whereNull('risultato')->count();
    }

    /** A che punto sono le finali: turno in corso, risultati che gli mancano, coppia vincitrice. */
    public function stato(Torneo $torneo): StatoFinali
    {
        return StatoFinali::daPartite(
            $torneo->partiteDelleFinali()->with(['coppiaA.primaFascia', 'coppiaA.secondaFascia', 'coppiaB.primaFascia', 'coppiaB.secondaFascia'])->get(),
        );
    }

    /**
     * Coppie che giocano il girone ma non accedono alle finali: quelle completate da un giocatore in
     * prestito, che ha già la sua coppia e nel sorteggio delle finali non può entrare due volte.
     *
     * @return list<int>
     */
    public function escluse(Torneo $torneo): array
    {
        return $torneo->coppie()->whereNotNull('prestito_id')->pluck('id')->all();
    }

    /**
     * Chi accederebbe alle finali con le classifiche di adesso (provvisorio finché mancano risultati).
     *
     * @throws SorteggioImpossibileException
     */
    public function accesso(Torneo $torneo): AccessoFinali
    {
        return $this->qualificazione->calcola(
            $this->classifiche($torneo)->all(),
            $torneo->qualificatePerGirone(),
            $torneo->criterioDiRipescaggio(),
            $this->escluse($torneo),
            config('tornei.finali.squadre_massime'),
        );
    }

    /**
     * Genera il tabellone delle finali dalle classifiche dei gironi: le partite di tutti i turni, dal primo
     * alla finale, e il sorteggio delle coppie del primo turno tra i giocatori delle coppie che accedono.
     * Se le coppie non riempiono il primo turno, le prime teste di serie lo passano senza giocare, così
     * come sono: i loro giocatori entrano nel sorteggio del secondo turno.
     * Sostituisce il tabellone precedente, con i suoi risultati.
     *
     * @return Fase il primo turno
     *
     * @throws SorteggioImpossibileException
     */
    public function generaTabellone(Torneo $torneo): Fase
    {
        if (! $torneo->partiteDiGirone()->exists()) {
            throw new SorteggioImpossibileException('Prima delle finali vanno generate e giocate le partite dei gironi.');
        }

        $daGiocare = $this->partiteDaGiocare($torneo);

        if ($daGiocare > 0) {
            throw new SorteggioImpossibileException(
                $daGiocare === 1
                    ? 'Manca il risultato di una partita dei gironi.'
                    : 'Mancano i risultati di '.$daGiocare.' partite dei gironi.'
            );
        }

        $accesso = $this->accesso($torneo);
        $prima = Fase::perSquadre($accesso->dimensione);

        DB::transaction(function () use ($torneo, $accesso, $prima): void {
            $this->annullaTabellone($torneo);

            foreach ($accesso->teste() as $indice => $candidata) {
                Coppia::query()->whereKey($candidata->coppia)->update([
                    'finali' => $indice < count($accesso->dirette) ? Coppia::DIRETTA : Coppia::RIPESCATA,
                    'testa_di_serie' => $indice + 1,
                ]);
            }

            for ($fase = $prima; $fase !== null; $fase = $fase->successiva()) {
                foreach (range(0, intdiv($fase->squadre(), 2) - 1) as $posizione) {
                    $torneo->partite()->create(['fase' => $fase, 'posizione' => $posizione]);
                }
            }

            // Primo turno: in fondo le coppie che passano senza giocare, sopra gli incontri da sorteggiare.
            $incontri = intdiv(count($accesso->giocano()), 2);
            $partite = $torneo->partiteDelTurno($prima)->get();

            foreach ($accesso->passano() as $indice => $candidata) {
                $partite[$incontri + $indice]->update([
                    'coppia_a_id' => $candidata->coppia,
                    'vincitrice_id' => $candidata->coppia,
                ]);
            }

            $this->sorteggiaTurno(
                $torneo,
                $prima,
                Coppia::query()->whereKey(array_map(fn ($candidata) => $candidata->coppia, $accesso->giocano()))->get(),
            );
        });

        return $prima;
    }

    /**
     * Sorteggia le coppie del turno che segue `$concluso`, tra i giocatori delle coppie che lo hanno superato
     * (vincendo o passando senza giocare). Va chiamato quando tutte le partite di `$concluso` sono decise
     * e il turno successivo non ha coppie.
     *
     * @return Fase|null il turno sorteggiato; `null` dopo la finale
     *
     * @throws SorteggioImpossibileException
     */
    public function sorteggiaDopo(Torneo $torneo, Fase $concluso): ?Fase
    {
        $successivo = $concluso->successiva();

        if ($successivo === null) {
            return null;
        }

        $this->sorteggiaTurno(
            $torneo,
            $successivo,
            Coppia::query()->whereKey($torneo->partiteDelTurno($concluso)->pluck('vincitrice_id'))->get(),
        );

        return $successivo;
    }

    /**
     * Toglie a un turno successivo al primo le coppie sorteggiate (le partite restano, vuote). Serve quando
     * cambia chi ha superato il turno precedente; il turno non deve avere risultati.
     */
    public function annullaTurno(Torneo $torneo, Fase $fase): void
    {
        // Prima si liberano le partite: eliminare una coppia eliminerebbe a cascata anche le sue partite.
        $torneo->partiteDelTurno($fase)->reorder()->update(['coppia_a_id' => null, 'coppia_b_id' => null]);
        $torneo->coppieDelleFinali()->where('fase', $fase->value)->delete();
    }

    /** In quel turno delle finali è stato inserito almeno un risultato. */
    public function turnoGiocato(Torneo $torneo, Fase $fase): bool
    {
        return $torneo->partiteDelTurno($fase)->whereNotNull('risultato')->exists();
    }

    /** Tutte le partite di quel turno sono decise: si può sorteggiare il successivo. */
    public function turnoConcluso(Torneo $torneo, Fase $fase): bool
    {
        return $torneo->partiteDelTurno($fase)->whereNull('vincitrice_id')->doesntExist();
    }

    /** Cancella il tabellone delle finali con i suoi risultati e le sue coppie; i gironi restano. */
    public function annullaTabellone(Torneo $torneo): void
    {
        DB::transaction(function () use ($torneo): void {
            $torneo->partiteDelleFinali()->delete();
            $torneo->coppieDelleFinali()->delete();
            $torneo->coppie()->update(['finali' => null, 'testa_di_serie' => null]);
        });
    }

    /**
     * Forma le coppie di un turno con i giocatori delle coppie in gara e le mette, due a due, nelle partite
     * del turno ancora senza coppie, dall'alto in basso.
     *
     * @param  Collection<int, Coppia>  $inGara  le coppie i cui giocatori vengono risorteggiati
     *
     * @throws SorteggioImpossibileException
     */
    private function sorteggiaTurno(Torneo $torneo, Fase $fase, Collection $inGara): void
    {
        $lati = Giocatore::query()
            ->whereKey($inGara->flatMap(fn (Coppia $coppia) => $coppia->giocatori()))
            ->pluck('lato', 'id');

        $giocatore = fn (int $id): GiocatoreSorteggio => new GiocatoreSorteggio($id, $lati[$id] ?? null);

        // Chi ha già giocato insieme: ogni coppia vista finora nel torneo, gironi (prestiti compresi) e finali.
        $giaInsieme = $torneo->vietaCoppieRipetute()
            ? $torneo->tutteLeCoppie()->get(['prima_fascia_id', 'seconda_fascia_id'])
                ->map(fn (Coppia $coppia) => $coppia->giocatori())
                ->all()
            : [];

        $latiDiversi = $torneo->latiNelSorteggio() !== LatiComplementari::Ignora;
        $consentiRipetizioni = $torneo->consenteRipetizioniInevitabili();

        $sorteggiate = match ($torneo->coppieNelleFinali()) {
            CoppieNelleFinali::Scambio => $this->scambio->sorteggia(
                $inGara->map(fn (Coppia $coppia) => [$giocatore($coppia->prima_fascia_id), $giocatore($coppia->seconda_fascia_id)])->values()->all(),
                $giaInsieme,
                $latiDiversi,
                $consentiRipetizioni,
            ),
            CoppieNelleFinali::Libero => $this->risorteggio->sorteggia(
                $inGara->map(fn (Coppia $coppia) => $giocatore($coppia->prima_fascia_id))->values()->all(),
                $inGara->map(fn (Coppia $coppia) => $giocatore($coppia->seconda_fascia_id))->values()->all(),
                $giaInsieme,
                $latiDiversi,
                $consentiRipetizioni,
                versoLaFinale: $fase === Fase::Semifinali,
            ),
        };

        $coppie = array_map(fn (CoppiaSorteggiata $coppia) => $torneo->tutteLeCoppie()->create([
            'fase' => $fase,
            'prima_fascia_id' => $coppia->prima,
            'seconda_fascia_id' => $coppia->seconda,
            'ripetuta' => $coppia->ripetuta,
        ]), $sorteggiate);

        // Le coppie escono dal sorteggio in ordine di tabellone: la prima incontra la seconda, e così via.
        foreach ($torneo->partiteDelTurno($fase)->whereNull('vincitrice_id')->get() as $indice => $partita) {
            $partita->update([
                'coppia_a_id' => $coppie[2 * $indice]->id,
                'coppia_b_id' => $coppie[2 * $indice + 1]->id,
            ]);
        }
    }
}
