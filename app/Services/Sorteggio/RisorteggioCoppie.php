<?php

namespace App\Services\Sorteggio;

use App\Exceptions\SorteggioImpossibileException;
use Random\Randomizer;

/**
 * Nuovo sorteggio delle coppie tra i giocatori ancora in gara (a ogni turno delle finali): un giocatore
 * di prima fascia con uno di seconda, senza rimettere insieme due giocatori che nel torneo hanno già
 * fatto coppia.
 *
 * - Se esiste un sorteggio senza coppie ripetute, ne esce uno a caso tra tutti quelli possibili.
 * - Se non esiste (succede negli ultimi turni, con pochi giocatori) le coppie ripetute sono il minimo
 *   indispensabile e vengono segnalate; oppure, se le ripetizioni non sono consentite, il sorteggio è rifiutato.
 * - A parità di coppie ripetute si possono preferire le coppie con lati diversi (destra + sinistra).
 * - Nelle semifinali guarda avanti di un turno: la finale la giocano i quattro giocatori delle due coppie
 *   vincitrici, che per non restare insieme possono solo scambiarsi i compagni. Tra i sorteggi senza coppie
 *   ripetute sceglie quindi quelli che lasciano possibile una finale tutta nuova, chiunque vinca.
 *
 * Le coppie escono in ordine di tabellone: la prima incontra la seconda, la terza la quarta, e così via.
 *
 * Classe pura: nessuna dipendenza da Laravel. Il caso arriva da un Randomizer, sostituibile nei test.
 */
final class RisorteggioCoppie
{
    /** Estrazioni "alla cieca" prima di passare alla ricerca del sorteggio migliore. */
    private const TENTATIVI = 60;

    // Pesi della ricerca: una coppia ripetuta costa più di tutte le coppie con lo stesso lato messe insieme,
    // e una coppia con lo stesso lato più di tutti i numeri estratti a caso messi insieme (fino a 64 coppie).
    private const COSTO_RIPETUTA = 10_000_000;

    private const COSTO_STESSO_LATO = 100_000;

    private const CASO_MASSIMO = 999;

    public function __construct(
        private readonly Randomizer $caso = new Randomizer,
    ) {}

    /**
     * @param  list<GiocatoreSorteggio>  $prima  giocatori di prima fascia in gara
     * @param  list<GiocatoreSorteggio>  $seconda  giocatori di seconda fascia in gara (tanti quanti i primi)
     * @param  list<array{int, int}>  $giaInsieme  coppie già viste nel torneo: [prima fascia, seconda fascia]
     * @param  bool  $latiDiversi  preferisci le coppie destra + sinistra, senza mai ripetere una coppia per ottenerle
     * @param  bool  $consentiRipetizioni  se è inevitabile, ammetti coppie già viste (segnate `ripetuta`)
     * @param  bool  $versoLaFinale  il turno è una semifinale: le vincitrici dei due incontri giocheranno la finale
     * @return list<CoppiaSorteggiata> in ordine casuale: ogni coppia di posto pari incontra la successiva
     *
     * @throws SorteggioImpossibileException
     */
    public function sorteggia(
        array $prima,
        array $seconda,
        array $giaInsieme,
        bool $latiDiversi = false,
        bool $consentiRipetizioni = true,
        bool $versoLaFinale = false,
    ): array {
        if ($prima === [] || count($prima) !== count($seconda)) {
            throw new SorteggioImpossibileException(
                'Per risorteggiare le coppie servono giocatori di prima e di seconda fascia in numero uguale.'
            );
        }

        $viste = [];
        foreach ($giaInsieme as [$giocatorePrima, $giocatoreSeconda]) {
            $viste[$giocatorePrima][$giocatoreSeconda] = true;
        }

        $prima = $this->caso->shuffleArray($prima);

        if ($versoLaFinale && count($prima) === 4) {
            [$prima, $abbinamento] = $this->semifinali($prima, $seconda, $viste, $latiDiversi);
        } else {
            $abbinamento = $latiDiversi ? null : $this->estrai($prima, $seconda, $viste);
            $abbinamento ??= $this->migliore($prima, $seconda, $viste, $latiDiversi);
        }

        $coppie = [];
        $ripetute = 0;

        foreach ($abbinamento as $i => $compagno) {
            $ripetuta = isset($viste[$prima[$i]->id][$compagno->id]);
            $ripetute += (int) $ripetuta;
            $coppie[] = new CoppiaSorteggiata($prima[$i]->id, $compagno->id, null, $ripetuta);
        }

        if ($ripetute > 0 && ! $consentiRipetizioni) {
            throw new SorteggioImpossibileException(
                'Con i giocatori rimasti in gara non si possono formare coppie tutte nuove: '
                .($ripetute === 1 ? 'una coppia dovrebbe' : $ripetute.' coppie dovrebbero').' ripetersi.'
            );
        }

        return $coppie;
    }

    /**
     * Le quattro coppie delle semifinali, scelte guardando anche alla finale.
     *
     * Prova tutti i modi di formare le coppie e di metterle di fronte (72 in tutto) e tiene i migliori: prima
     * di tutto meno coppie ripetute adesso, poi meno coppie ripetute nelle finali che ne possono uscire (per
     * ognuno dei quattro esiti, le due coppie con i compagni scambiati), infine più coppie con lati diversi.
     * Tra i migliori sceglie il caso.
     *
     * @param  list<GiocatoreSorteggio>  $prima
     * @param  list<GiocatoreSorteggio>  $seconda
     * @param  array<int, array<int, true>>  $viste
     * @return array{list<GiocatoreSorteggio>, list<GiocatoreSorteggio>} giocatori di prima fascia in ordine di
     *                                                                   tabellone e, per ognuno, il compagno
     */
    private function semifinali(array $prima, array $seconda, array $viste, bool $latiDiversi): array
    {
        $vista = fn (GiocatoreSorteggio $giocatore, GiocatoreSorteggio $compagno): int => (int) isset($viste[$giocatore->id][$compagno->id]);

        $migliori = [];
        $minimo = null;

        foreach ($this->permutazioni([0, 1, 2, 3]) as $compagni) {
            $adesso = 0;
            $stessoLato = 0;

            foreach ($compagni as $i => $j) {
                $adesso += $vista($prima[$i], $seconda[$j]);
                $stessoLato += (int) ($latiDiversi && ! $prima[$i]->complementare($seconda[$j]));
            }

            // Tre modi di dividere le quattro coppie in due incontri.
            foreach ([[[0, 1], [2, 3]], [[0, 2], [1, 3]], [[0, 3], [1, 2]]] as [$primoIncontro, $secondoIncontro]) {
                $inFinale = 0;

                foreach ($primoIncontro as $i) {
                    foreach ($secondoIncontro as $k) {
                        // Vincono le coppie i e k: in finale si scambiano i compagni (o restano com'erano: due ripetute).
                        $inFinale += min(2, $vista($prima[$i], $seconda[$compagni[$k]]) + $vista($prima[$k], $seconda[$compagni[$i]]));
                    }
                }

                $valore = [$adesso, $inFinale, $stessoLato];

                if ($minimo === null || $valore < $minimo) {
                    $minimo = $valore;
                    $migliori = [];
                }

                if ($valore === $minimo) {
                    $migliori[] = [$compagni, [...$primoIncontro, ...$secondoIncontro]];
                }
            }
        }

        [$compagni, $ordine] = $migliori[$this->caso->getInt(0, count($migliori) - 1)];

        // Anche chi sta sopra e chi sotto, nel tabellone, è a caso.
        $incontri = $this->caso->shuffleArray([
            $this->caso->shuffleArray([$ordine[0], $ordine[1]]),
            $this->caso->shuffleArray([$ordine[2], $ordine[3]]),
        ]);
        $ordine = [...$incontri[0], ...$incontri[1]];

        return [
            array_map(fn (int $i) => $prima[$i], $ordine),
            array_map(fn (int $i) => $seconda[$compagni[$i]], $ordine),
        ];
    }

    /**
     * @param  list<int>  $elementi
     * @return list<list<int>>
     */
    private function permutazioni(array $elementi): array
    {
        if (count($elementi) <= 1) {
            return [$elementi];
        }

        $permutazioni = [];

        foreach ($elementi as $indice => $elemento) {
            $resto = $elementi;
            unset($resto[$indice]);

            foreach ($this->permutazioni(array_values($resto)) as $coda) {
                $permutazioni[] = [$elemento, ...$coda];
            }
        }

        return $permutazioni;
    }

    /**
     * Estrae a caso un abbinamento dopo l'altro finché ne esce uno senza coppie già viste: così tutti i
     * sorteggi ammessi hanno la stessa probabilità. `null` se dopo i tentativi previsti non ne è uscito nessuno.
     *
     * @param  list<GiocatoreSorteggio>  $prima
     * @param  list<GiocatoreSorteggio>  $seconda
     * @param  array<int, array<int, true>>  $viste
     * @return list<GiocatoreSorteggio>|null il compagno di ogni giocatore di `$prima`, nello stesso ordine
     */
    private function estrai(array $prima, array $seconda, array $viste): ?array
    {
        for ($tentativo = 0; $tentativo < self::TENTATIVI; $tentativo++) {
            $compagni = $this->caso->shuffleArray($seconda);

            foreach ($prima as $i => $giocatore) {
                if (isset($viste[$giocatore->id][$compagni[$i]->id])) {
                    continue 2;
                }
            }

            return $compagni;
        }

        return null;
    }

    /**
     * L'abbinamento con meno coppie ripetute e, a parità, con meno coppie dello stesso lato (se i lati contano);
     * tra gli abbinamenti ugualmente buoni sceglie il caso, con un numero estratto per ogni possibile coppia.
     *
     * @param  list<GiocatoreSorteggio>  $prima
     * @param  list<GiocatoreSorteggio>  $seconda
     * @param  array<int, array<int, true>>  $viste
     * @return list<GiocatoreSorteggio>
     */
    private function migliore(array $prima, array $seconda, array $viste, bool $latiDiversi): array
    {
        $costi = [];

        foreach ($prima as $i => $giocatore) {
            foreach ($seconda as $j => $compagno) {
                $costi[$i][$j] = $this->caso->getInt(0, self::CASO_MASSIMO)
                    + (isset($viste[$giocatore->id][$compagno->id]) ? self::COSTO_RIPETUTA : 0)
                    + ($latiDiversi && ! $giocatore->complementare($compagno) ? self::COSTO_STESSO_LATO : 0);
            }
        }

        return array_map(fn (int $j) => $seconda[$j], $this->assegna($costi));
    }

    /**
     * Assegnazione di costo minimo (metodo ungherese, con i potenziali): a ogni riga una colonna diversa.
     *
     * @param  list<list<int>>  $costi  matrice quadrata
     * @return list<int> la colonna assegnata a ogni riga
     */
    private function assegna(array $costi): array
    {
        $n = count($costi);
        $potenzialeRiga = array_fill(0, $n + 1, 0);
        $potenzialeColonna = array_fill(0, $n + 1, 0);
        $rigaDi = array_fill(0, $n + 1, 0);      // riga assegnata a ogni colonna (da 1; 0 = nessuna)
        $precedente = array_fill(0, $n + 1, 0);

        for ($riga = 1; $riga <= $n; $riga++) {
            $rigaDi[0] = $riga;
            $colonna = 0;
            $minimo = array_fill(0, $n + 1, PHP_INT_MAX);
            $usata = array_fill(0, $n + 1, false);

            do {
                $usata[$colonna] = true;
                $corrente = $rigaDi[$colonna];
                $scarto = PHP_INT_MAX;
                $prossima = 0;

                for ($j = 1; $j <= $n; $j++) {
                    if ($usata[$j]) {
                        continue;
                    }

                    $ridotto = $costi[$corrente - 1][$j - 1] - $potenzialeRiga[$corrente] - $potenzialeColonna[$j];

                    if ($ridotto < $minimo[$j]) {
                        $minimo[$j] = $ridotto;
                        $precedente[$j] = $colonna;
                    }

                    if ($minimo[$j] < $scarto) {
                        $scarto = $minimo[$j];
                        $prossima = $j;
                    }
                }

                for ($j = 0; $j <= $n; $j++) {
                    if ($usata[$j]) {
                        $potenzialeRiga[$rigaDi[$j]] += $scarto;
                        $potenzialeColonna[$j] -= $scarto;
                    } else {
                        $minimo[$j] -= $scarto;
                    }
                }

                $colonna = $prossima;
            } while ($rigaDi[$colonna] !== 0);

            // Risale il cammino trovato e sposta le assegnazioni.
            do {
                $da = $precedente[$colonna];
                $rigaDi[$colonna] = $rigaDi[$da];
                $colonna = $da;
            } while ($colonna !== 0);
        }

        $colonnaDi = [];
        for ($j = 1; $j <= $n; $j++) {
            $colonnaDi[$rigaDi[$j] - 1] = $j - 1;
        }
        ksort($colonnaDi);

        return array_values($colonnaDi);
    }
}
