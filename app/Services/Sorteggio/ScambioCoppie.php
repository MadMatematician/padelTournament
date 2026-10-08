<?php

namespace App\Services\Sorteggio;

use App\Exceptions\SorteggioImpossibileException;
use Random\Randomizer;

/**
 * Nuove coppie a ogni turno delle finali, con la garanzia che due giocatori non facciano coppia due volte:
 * si sorteggia quali coppie in gara si incontrano, e le due coppie sorteggiate insieme si scambiano i compagni
 * (da 1+2 e 3+4 nascono 1+4 e 3+2, che giocano l'una contro l'altra).
 *
 * Perché funziona sempre: dopo l'incontro, di due giocatori che erano in coppia ne resta in gara al più uno
 * (erano avversari). Chi arriva al turno successivo ha quindi tra i giocatori in gara un solo ex compagno, quello
 * del turno appena giocato, e con lo scambio lo ritrova di fronte. Vale per qualunque numero di turni, finale
 * compresa. Un sorteggio libero tra tutti i giocatori rimasti (RisorteggioCoppie) non può garantirlo: in finale,
 * con quattro giocatori, le coppie possibili sono due sole.
 *
 * Classe pura: nessuna dipendenza da Laravel. Il caso arriva da un Randomizer, sostituibile nei test.
 */
final class ScambioCoppie
{
    /** Fin qui si provano tutti gli accoppiamenti (con 8 coppie sono 105); oltre, un campione estratto a caso. */
    private const COPPIE_PER_ELENCO_COMPLETO = 8;

    private const CAMPIONE = 300;

    public function __construct(
        private readonly Randomizer $caso = new Randomizer,
    ) {}

    /**
     * @param  list<array{GiocatoreSorteggio, GiocatoreSorteggio}>  $coppie  le coppie in gara: [prima fascia, seconda fascia]
     * @param  list<array{int, int}>  $giaInsieme  coppie già viste nel torneo: [prima fascia, seconda fascia]
     * @param  bool  $latiDiversi  tra gli accoppiamenti ammessi preferisci quelli che danno coppie destra + sinistra
     * @param  bool  $consentiRipetizioni  se è inevitabile, ammetti coppie già viste (segnate `ripetuta`)
     * @return list<CoppiaSorteggiata> in ordine di tabellone: ogni coppia di posto pari incontra la successiva
     *
     * @throws SorteggioImpossibileException
     */
    public function sorteggia(array $coppie, array $giaInsieme, bool $latiDiversi = false, bool $consentiRipetizioni = true): array
    {
        if ($coppie === [] || count($coppie) % 2 !== 0) {
            throw new SorteggioImpossibileException('Per formare gli incontri le coppie in gara devono essere in numero pari.');
        }

        $viste = [];
        foreach ($giaInsieme as [$giocatorePrima, $giocatoreSeconda]) {
            $viste[$giocatorePrima][$giocatoreSeconda] = true;
        }

        // Quanto "costa" far incontrare due coppie: [coppie già viste, coppie con lo stesso lato] dopo lo scambio.
        $costo = function (int $i, int $k) use ($coppie, $viste, $latiDiversi): array {
            [$primaI, $secondaI] = $coppie[$i];
            [$primaK, $secondaK] = $coppie[$k];

            return [
                (int) isset($viste[$primaI->id][$secondaK->id]) + (int) isset($viste[$primaK->id][$secondaI->id]),
                $latiDiversi ? (int) ! $primaI->complementare($secondaK) + (int) ! $primaK->complementare($secondaI) : 0,
            ];
        };

        $incontri = $this->accoppia(count($coppie), $costo);

        $sorteggiate = [];
        $ripetute = 0;

        foreach ($incontri as [$i, $k]) {
            foreach ([[$coppie[$i][0], $coppie[$k][1]], [$coppie[$k][0], $coppie[$i][1]]] as [$giocatore, $compagno]) {
                $ripetuta = isset($viste[$giocatore->id][$compagno->id]);
                $ripetute += (int) $ripetuta;
                $sorteggiate[] = new CoppiaSorteggiata($giocatore->id, $compagno->id, null, $ripetuta);
            }
        }

        if ($ripetute > 0 && ! $consentiRipetizioni) {
            throw new SorteggioImpossibileException(
                'Con i giocatori rimasti in gara non si possono formare coppie tutte nuove: '
                .($ripetute === 1 ? 'una coppia dovrebbe' : $ripetute.' coppie dovrebbero').' ripetersi.'
            );
        }

        return $sorteggiate;
    }

    /**
     * Divide le coppie in incontri, a caso. Di norma basta la prima estrazione; se costa qualcosa (una coppia
     * già vista: succede solo con dati non nati da questo sorteggio; oppure lati uguali) sceglie a caso tra gli
     * accoppiamenti che costano meno.
     *
     * @param  callable(int, int): array{int, int}  $costo
     * @return list<array{int, int}> gli incontri, come indici delle due coppie; ordine e lati sono casuali
     */
    private function accoppia(int $numero, callable $costo): array
    {
        $valuta = function (array $incontri) use ($costo): array {
            $totale = [0, 0];
            foreach ($incontri as [$i, $k]) {
                [$viste, $lati] = $costo($i, $k);
                $totale[0] += $viste;
                $totale[1] += $lati;
            }

            return $totale;
        };

        $estrai = fn (): array => array_chunk($this->caso->shuffleArray(range(0, $numero - 1)), 2);

        $incontri = $estrai();

        if ($valuta($incontri) === [0, 0]) {
            return $incontri;
        }

        $candidati = $numero <= self::COPPIE_PER_ELENCO_COMPLETO
            ? $this->tuttiGliAccoppiamenti(range(0, $numero - 1))
            : array_map(fn () => $estrai(), range(1, self::CAMPIONE));

        $migliori = [];
        $minimo = null;

        foreach ($candidati as $candidato) {
            $valore = $valuta($candidato);

            if ($minimo === null || $valore < $minimo) {
                $minimo = $valore;
                $migliori = [];
            }

            if ($valore === $minimo) {
                $migliori[] = $candidato;
            }
        }

        // L'elenco completo è in ordine fisso: si rimescolano gli incontri e chi sta sopra in ognuno.
        $scelto = $migliori[$this->caso->getInt(0, count($migliori) - 1)];

        return $this->caso->shuffleArray(array_map(fn (array $incontro) => $this->caso->shuffleArray($incontro), $scelto));
    }

    /**
     * Tutti i modi di dividere gli elementi in gruppi di due.
     *
     * @param  list<int>  $elementi  in numero pari
     * @return list<list<array{int, int}>>
     */
    private function tuttiGliAccoppiamenti(array $elementi): array
    {
        if ($elementi === []) {
            return [[]];
        }

        $primo = array_shift($elementi);
        $accoppiamenti = [];

        foreach ($elementi as $indice => $compagno) {
            $resto = $elementi;
            unset($resto[$indice]);

            foreach ($this->tuttiGliAccoppiamenti(array_values($resto)) as $coda) {
                $accoppiamenti[] = [[$primo, $compagno], ...$coda];
            }
        }

        return $accoppiamenti;
    }
}
