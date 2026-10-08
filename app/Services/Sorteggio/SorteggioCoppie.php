<?php

namespace App\Services\Sorteggio;

use App\Exceptions\SorteggioImpossibileException;
use Random\Randomizer;

/**
 * Sorteggio delle coppie: un giocatore di prima fascia con uno di seconda.
 *
 * Se le fasce non sono pari, i giocatori in più della fascia più numerosa vengono abbinati a giocatori
 * dell'altra fascia presi "in prestito": chi va in prestito ha la sua coppia e ne completa una seconda.
 *
 * Classe pura: nessuna dipendenza da Laravel. Il caso arriva da un Randomizer, sostituibile nei test.
 */
final class SorteggioCoppie
{
    public function __construct(
        private readonly Randomizer $caso = new Randomizer,
    ) {}

    /**
     * @param  list<GiocatoreSorteggio>  $prima  giocatori di prima fascia
     * @param  list<GiocatoreSorteggio>  $seconda  giocatori di seconda fascia
     * @param  list<int>|null  $prestiti  chi va in prestito (identificativi di giocatori della fascia meno
     *                                    numerosa, tanti quanti i giocatori in più dell'altra); `null` = sorteggiati
     * @return list<CoppiaSorteggiata> prima le coppie ordinarie, poi quelle completate con un prestito
     *
     * @throws SorteggioImpossibileException
     */
    public function sorteggia(array $prima, array $seconda, LatiComplementari $lati, ?array $prestiti = null): array
    {
        if ($prima === [] || $seconda === []) {
            throw new SorteggioImpossibileException('Per formare le coppie serve almeno un giocatore per fascia.');
        }

        $primaInEccesso = count($prima) >= count($seconda);
        $maggiore = $this->mescola($primaInEccesso ? $prima : $seconda);
        $minore = $primaInEccesso ? $seconda : $prima;
        $eccesso = count($maggiore) - count($minore);

        $inPrestito = $this->scegliPrestiti($minore, $eccesso, $prestiti);

        // I giocatori in più sono estratti a caso: sono quelli che giocheranno con un compagno in prestito.
        $titolari = array_slice($maggiore, 0, count($minore));
        $inPiu = array_slice($maggiore, count($minore));

        $coppie = [];

        foreach ($this->abbina($minore, $titolari, $lati) as [$delMinore, $delMaggiore]) {
            $coppie[] = $this->coppia($delMinore, $delMaggiore, $primaInEccesso, null);
        }

        foreach ($this->abbina($inPrestito, $inPiu, $lati) as [$delMinore, $delMaggiore]) {
            $coppie[] = $this->coppia($delMinore, $delMaggiore, $primaInEccesso, $delMinore->id);
        }

        return $coppie;
    }

    /**
     * @param  list<GiocatoreSorteggio>  $minore
     * @param  list<int>|null  $prestiti
     * @return list<GiocatoreSorteggio>
     */
    private function scegliPrestiti(array $minore, int $eccesso, ?array $prestiti): array
    {
        if ($eccesso === 0) {
            if ($prestiti !== null && $prestiti !== []) {
                throw new SorteggioImpossibileException('Le fasce sono pari: non serve nessun giocatore in prestito.');
            }

            return [];
        }

        if ($eccesso > count($minore)) {
            throw new SorteggioImpossibileException(
                'Le fasce sono troppo sbilanciate: ogni giocatore può andare in prestito una sola volta.'
            );
        }

        if ($prestiti === null) {
            return array_slice($this->mescola($minore), 0, $eccesso);
        }

        if (count($prestiti) !== $eccesso || count(array_unique($prestiti)) !== $eccesso) {
            throw new SorteggioImpossibileException(
                $eccesso === 1
                    ? 'Va indicato un solo giocatore in prestito.'
                    : 'Vanno indicati '.$eccesso.' giocatori in prestito, tutti diversi.'
            );
        }

        $perId = [];
        foreach ($minore as $giocatore) {
            $perId[$giocatore->id] = $giocatore;
        }

        $scelti = [];
        foreach ($prestiti as $id) {
            if (! isset($perId[$id])) {
                throw new SorteggioImpossibileException(
                    'In prestito può andare solo un giocatore iscritto della fascia con meno giocatori.'
                );
            }
            $scelti[] = $perId[$id];
        }

        return $scelti;
    }

    /**
     * Abbina uno a uno due gruppi della stessa dimensione.
     *
     * Con i lati "preferito" o "obbligatorio" cerca il maggior numero di coppie complementari
     * (abbinamento massimo su grafo bipartito, cammini aumentanti); l'ordine in cui giocatori e
     * candidati vengono provati è casuale, quindi lo è anche il risultato tra quelli ammessi.
     *
     * @param  list<GiocatoreSorteggio>  $a
     * @param  list<GiocatoreSorteggio>  $b
     * @return list<array{GiocatoreSorteggio, GiocatoreSorteggio}>
     */
    private function abbina(array $a, array $b, LatiComplementari $lati): array
    {
        $a = $this->mescola($a);
        $b = $this->mescola($b);

        if ($lati === LatiComplementari::Ignora) {
            return array_map(null, $a, $b);
        }

        // $compagnoDi[indice in $b] = indice in $a
        $compagnoDi = [];

        foreach (array_keys($a) as $i) {
            $visti = [];
            $this->cercaCompagno($i, $a, $b, $compagnoDi, $visti);
        }

        $coppie = [];
        foreach ($compagnoDi as $j => $i) {
            $coppie[] = [$a[$i], $b[$j]];
        }

        // Chi è rimasto fuori ha solo compagni con il suo stesso lato.
        $liberiA = array_values(array_diff(array_keys($a), $compagnoDi));
        $liberiB = array_values(array_diff(array_keys($b), array_keys($compagnoDi)));

        if ($liberiA !== [] && $lati === LatiComplementari::Obbligatorio) {
            throw new SorteggioImpossibileException(
                'Con i giocatori iscritti non si riescono a formare solo coppie destra + sinistra.'
            );
        }

        foreach ($liberiA as $posizione => $i) {
            $coppie[] = [$a[$i], $b[$liberiB[$posizione]]];
        }

        return $coppie;
    }

    /**
     * Cammino aumentante: prova a dare un compagno complementare al giocatore `$i`,
     * spostando se serve chi era già abbinato.
     *
     * @param  list<GiocatoreSorteggio>  $a
     * @param  list<GiocatoreSorteggio>  $b
     * @param  array<int, int>  $compagnoDi
     * @param  array<int, true>  $visti
     */
    private function cercaCompagno(int $i, array $a, array $b, array &$compagnoDi, array &$visti): bool
    {
        foreach ($this->mescola(array_keys($b)) as $j) {
            if (isset($visti[$j]) || ! $a[$i]->complementare($b[$j])) {
                continue;
            }

            $visti[$j] = true;

            if (! isset($compagnoDi[$j]) || $this->cercaCompagno($compagnoDi[$j], $a, $b, $compagnoDi, $visti)) {
                $compagnoDi[$j] = $i;

                return true;
            }
        }

        return false;
    }

    private function coppia(GiocatoreSorteggio $delMinore, GiocatoreSorteggio $delMaggiore, bool $primaInEccesso, ?int $prestito): CoppiaSorteggiata
    {
        return $primaInEccesso
            ? new CoppiaSorteggiata($delMaggiore->id, $delMinore->id, $prestito)
            : new CoppiaSorteggiata($delMinore->id, $delMaggiore->id, $prestito);
    }

    /**
     * @template T
     *
     * @param  list<T>  $elementi
     * @return list<T>
     */
    private function mescola(array $elementi): array
    {
        return $elementi === [] ? [] : $this->caso->shuffleArray($elementi);
    }
}
