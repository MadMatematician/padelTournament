<?php

namespace App\Http\Controllers\Amministrazione;

use App\Enums\Fase;
use App\Exceptions\SorteggioImpossibileException;
use App\Http\Controllers\Controller;
use App\Models\Coppia;
use App\Models\Giocatore;
use App\Models\Partita;
use App\Models\Torneo;
use App\Services\Finali\AccessoFinali;
use App\Services\Finali\CriterioRipescaggio;
use App\Services\Tornei\FinaliService;
use App\Services\Tornei\MiglioriGiocatoriService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Classifica dei gironi, accesso alle finali, riepilogo di tutte le squadre, cammino dei giocatori nelle finali
 * e migliori giocatori per categoria.
 */
class ClassificaController extends Controller
{
    public function __invoke(Torneo $torneo, FinaliService $finali, MiglioriGiocatoriService $migliori): View
    {
        $gironi = $torneo->gironi()->with(['coppie.primaFascia', 'coppie.secondaFascia'])->get();
        $haFinali = $torneo->haFinali();

        // Con le classifiche di adesso: definitivo solo quando tutte le partite dei gironi hanno un risultato.
        $accesso = null;
        $impedimento = null;

        if ($gironi->isNotEmpty()) {
            try {
                $accesso = $finali->accesso($torneo);
            } catch (SorteggioImpossibileException $errore) {
                $impedimento = $errore->getMessage();
            }
        }

        $coppie = $gironi->flatMap->coppie->keyBy('id');

        return view('amministrazione.classifica', [
            'torneo' => $torneo,
            'gironi' => $gironi,
            'coppie' => $coppie,
            'classifiche' => $finali->classifiche($torneo),
            'accesso' => $accesso,
            'impedimento' => $impedimento,
            'stati' => $this->stati($torneo, $coppie, $accesso, $finali->escluse($torneo), $haFinali),
            'cammini' => $haFinali ? $this->cammini($torneo, $coppie) : [],
            'migliori' => $this->migliori($torneo, $migliori),
            // Concluso = la finale ha una vincitrice.
            'concluso' => $torneo->partiteDelTurno(Fase::Finale)->whereNotNull('vincitrice_id')->exists(),
            'haFinali' => $haFinali,
            'partiteDiGirone' => $torneo->partiteDiGirone()->count(),
            'partiteDaGiocare' => $finali->partiteDaGiocare($torneo),
        ]);
    }

    /**
     * Per ogni coppia, la sua situazione rispetto alle finali: "diretta" (qualificata), "ripescata",
     * "esclusa" (coppia con un giocatore in prestito) oppure assente.
     *
     * A tabellone generato vale ciò che è stato deciso allora; prima vale la classifica di adesso, tranne
     * le ripescate a sorte, che non si conoscono finché il tabellone non viene generato.
     *
     * @param  Collection<int, Coppia>  $coppie
     * @param  list<int>  $escluse
     * @return array<int, string>
     */
    private function stati(Torneo $torneo, Collection $coppie, ?AccessoFinali $accesso, array $escluse, bool $haFinali): array
    {
        $stati = array_fill_keys($escluse, 'esclusa');

        if ($haFinali) {
            return $coppie->whereNotNull('finali')->mapWithKeys(fn (Coppia $coppia) => [$coppia->id => $coppia->finali])->all() + $stati;
        }

        if ($accesso === null) {
            return $stati;
        }

        foreach ($accesso->dirette as $candidata) {
            $stati[$candidata->coppia] = Coppia::DIRETTA;
        }

        if ($torneo->criterioDiRipescaggio() !== CriterioRipescaggio::Sorteggio) {
            foreach ($accesso->ripescate as $candidata) {
                $stati[$candidata->coppia] = Coppia::RIPESCATA;
            }
        }

        return $stati;
    }

    /**
     * I migliori giocatori di ogni categoria configurata (fascia d'età, genere…), dalla stessa classifica.
     *
     * @return list<array<string, mixed>>
     */
    private function migliori(Torneo $torneo, MiglioriGiocatoriService $migliori): array
    {
        $categorie = array_keys($migliori->categorie());

        if ($categorie === []) {
            return [];
        }

        $classifica = $migliori->classifica($torneo);

        return array_map(fn (string $categoria) => $migliori->migliori($torneo, $categoria, $classifica), $categorie);
    }

    /**
     * Il cammino nelle finali di ogni giocatore che vi è entrato. Nelle finali le coppie si risorteggiano a
     * ogni turno, quindi a passare il turno sono i giocatori: per ognuno la coppia con cui si è qualificato,
     * l'ultimo turno raggiunto e i compagni avuti turno per turno.
     *
     * @param  Collection<int, Coppia>  $coppie  le coppie dei gironi, per identificativo
     * @return list<array{giocatore: Giocatore, qualificata: ?Coppia, turno: string, ordine: int, compagni: list<string>}>
     */
    private function cammini(Torneo $torneo, Collection $coppie): array
    {
        $partite = $torneo->partiteDelleFinali()
            ->with(['coppiaA.primaFascia', 'coppiaA.secondaFascia', 'coppiaB.primaFascia', 'coppiaB.secondaFascia'])
            ->orderBy('posizione')
            ->get()
            ->groupBy(fn (Partita $partita) => $partita->fase->value);

        $qualificata = [];
        foreach ($coppie->whereNotNull('finali') as $coppia) {
            $qualificata[$coppia->prima_fascia_id] = $coppia;
            $qualificata[$coppia->seconda_fascia_id] = $coppia;
        }

        $cammini = [];

        // In ordine di turno: ogni turno successivo aggiorna il precedente.
        foreach (Fase::finali() as $ordine => $fase) {
            foreach ($partite->get($fase->value, []) as $partita) {
                foreach (array_filter([$partita->coppiaA, $partita->coppiaB]) as $coppia) {
                    $haVinto = $fase === Fase::Finale && $partita->vincitrice_id === $coppia->id;
                    $turno = match (true) {
                        $haVinto => 'Ha vinto il torneo',
                        $fase === Fase::Finale => 'Finalista',
                        default => $fase->etichetta(),
                    };

                    foreach ([[$coppia->primaFascia, $coppia->secondaFascia], [$coppia->secondaFascia, $coppia->primaFascia]] as [$giocatore, $compagno]) {
                        $cammini[$giocatore->id] ??= [
                            'giocatore' => $giocatore,
                            'qualificata' => $qualificata[$giocatore->id] ?? null,
                            'compagni' => [],
                        ];
                        $cammini[$giocatore->id]['turno'] = $turno;
                        $cammini[$giocatore->id]['ordine'] = $ordine + (int) $haVinto;
                        // Chi passa il primo turno senza giocare lo fa con la coppia dei gironi: nessun compagno nuovo.
                        $cammini[$giocatore->id]['compagni'][] = $fase->etichetta().': '
                            .($partita->bye() ? 'passa il turno' : 'con '.$compagno->nomeBreve());
                    }
                }
            }
        }

        return array_values($cammini);
    }
}
