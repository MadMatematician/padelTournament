<?php

namespace App\Services\Tornei;

use App\Enums\Fase;
use App\Models\Giocatore;
use App\Models\Partita;
use App\Models\Torneo;
use App\Services\Classifica\ClassificaGiocatori;
use App\Services\Classifica\RendimentoGiocatore;
use BackedEnum;
use Illuminate\Support\Collection;

/**
 * I migliori giocatori di un torneo per categoria: fascia d'età, genere… (config/tornei.php →
 * classifica.migliori.categorie).
 *
 * Qui stanno la lettura dei dati e l'aggancio all'anagrafica; la regola è in ClassificaGiocatori.
 * Una categoria è un attributo del giocatore (`attributo`: colonna, cast o accessor di App\Models\Giocatore) con
 * le sue etichette (`valori`: chiave di configurazione "valore => etichetta"). Finché l'anagrafica non ha quel
 * dato la categoria risulta non disponibile, senza errori.
 */
class MiglioriGiocatoriService
{
    public function __construct(
        private readonly ClassificaGiocatori $classifica = new ClassificaGiocatori,
    ) {}

    /**
     * La classifica individuale di tutti i giocatori del torneo.
     *
     * @return list<RendimentoGiocatore>
     */
    public function classifica(Torneo $torneo): array
    {
        $partite = $torneo->partite()
            ->with(['coppiaA', 'coppiaB'])
            ->orderBy('posizione')
            ->get();

        return $this->classifica->calcola(
            $torneo->giocatori()->pluck('giocatori.id')->all(),
            $partite->filter->giocata()->map(fn (Partita $partita) => [
                'a' => $partita->coppiaA->giocatori(),
                'b' => $partita->coppiaB->giocatori(),
                'vincitrice' => $partita->vincitrice_id === $partita->coppia_a_id ? 'a' : 'b',
                'set_a' => $partita->set_a,
                'set_b' => $partita->set_b,
                'game_a' => $partita->game_a,
                'game_b' => $partita->game_b,
            ])->values()->all(),
            $this->turni($partite),
        );
    }

    /**
     * I migliori di ogni valore di una categoria, pronti per la pagina.
     *
     * @param  string  $categoria  chiave di `classifica.migliori.categorie`
     * @param  list<RendimentoGiocatore>|null  $classifica  la classifica già calcolata, per non rifarla
     * @return array{
     *     chiave: string, nome: string, icona: string, quanti: int,
     *     disponibile: bool, senza: int,
     *     gruppi: list<array{valore: int|string, etichetta: string, righe: list<array{riga: RendimentoGiocatore, giocatore: Giocatore, turno: string|null}>}>
     * }
     */
    public function migliori(Torneo $torneo, string $categoria, ?array $classifica = null): array
    {
        $definizione = $this->categorie()[$categoria];
        $quanti = (int) config('tornei.classifica.migliori.quanti', 3);
        $etichette = config($definizione['valori']) ?? [];

        $giocatori = $torneo->giocatori()->get()->keyBy('id');
        $valori = $giocatori->map(fn (Giocatore $giocatore) => $this->valore($giocatore, $definizione['attributo']));

        $migliori = $this->classifica->migliori(
            $classifica ?? $this->classifica($torneo),
            $valori->all(),
            $quanti,
        );

        // Nell'ordine delle etichette configurate; i valori senza etichetta in fondo, in ordine alfabetico.
        $ordine = array_flip(array_map('strval', array_keys($etichette)));
        uksort($migliori, fn ($x, $y) => [$ordine[(string) $x] ?? PHP_INT_MAX, (string) $x] <=> [$ordine[(string) $y] ?? PHP_INT_MAX, (string) $y]);

        $gruppi = [];
        foreach ($migliori as $valore => $righe) {
            $gruppi[] = [
                'valore' => $valore,
                'etichetta' => $etichette[$valore] ?? $this->etichettaDi($giocatori, $valori, $valore, $definizione['attributo']),
                'righe' => array_map(fn (RendimentoGiocatore $riga) => [
                    'riga' => $riga,
                    'giocatore' => $giocatori[$riga->giocatore],
                    'turno' => $this->etichettaTurno($riga->turno),
                ], $righe),
            ];
        }

        return [
            'chiave' => $categoria,
            'nome' => $definizione['nome'],
            'icona' => $definizione['icona'],
            'quanti' => $quanti,
            // Il dato c'è se almeno un iscritto ce l'ha.
            'disponibile' => $valori->filter(fn ($valore) => $valore !== null && $valore !== '')->isNotEmpty(),
            'senza' => $valori->filter(fn ($valore) => $valore === null || $valore === '')->count(),
            'gruppi' => $gruppi,
        ];
    }

    /**
     * Le categorie configurate (nessuna, se la configurazione non le prevede: la pagina resta senza pulsanti).
     *
     * @return array<string, array{nome: string, icona: string, attributo: string, valori: string}>
     */
    public function categorie(): array
    {
        return config('tornei.classifica.migliori.categorie', []);
    }

    /** Come si chiama il turno raggiunto: «Semifinali», «Finalista», «Ha vinto il torneo»; `null` fuori dalle finali. */
    public function etichettaTurno(int $turno): ?string
    {
        $finali = Fase::finali();

        return match (true) {
            $turno === RendimentoGiocatore::FUORI_DALLE_FINALI => null,
            $turno >= count($finali) => 'Ha vinto il torneo',
            $finali[$turno] === Fase::Finale => 'Finalista',
            default => $finali[$turno]->etichetta(),
        };
    }

    /**
     * Per ogni giocatore entrato nelle finali, l'ordine dell'ultimo turno raggiunto (vedi RendimentoGiocatore).
     *
     * @param  Collection<int, Partita>  $partite  tutte le partite del torneo, con le coppie
     * @return array<int, int>
     */
    private function turni(Collection $partite): array
    {
        $turni = [];

        // In ordine di turno: ogni turno successivo aggiorna il precedente.
        foreach (Fase::finali() as $ordine => $fase) {
            foreach ($partite->filter(fn (Partita $partita) => $partita->fase === $fase) as $partita) {
                foreach (array_filter([$partita->coppiaA, $partita->coppiaB]) as $coppia) {
                    $haVinto = $fase === Fase::Finale && $partita->vincitrice_id === $coppia->id;

                    foreach ($coppia->giocatori() as $giocatore) {
                        $turni[$giocatore] = $ordine + (int) $haVinto;
                    }
                }
            }
        }

        return $turni;
    }

    /**
     * Il valore della categoria per un giocatore, come chiave semplice. `null` se l'anagrafica non ha
     * (ancora) quel dato: colonna assente, valore vuoto.
     */
    private function valore(Giocatore $giocatore, string $attributo): int|string|null
    {
        $presente = array_key_exists($attributo, $giocatore->getAttributes())
            || $giocatore->hasGetMutator($attributo)
            || $giocatore->hasAttributeMutator($attributo);

        $valore = $presente ? $giocatore->getAttribute($attributo) : null;

        if ($valore instanceof BackedEnum) {
            $valore = $valore->value;
        }

        return is_int($valore) || is_string($valore) ? $valore : null;
    }

    /**
     * Etichetta di un valore che la configurazione non elenca: se l'attributo è un enum con `etichetta()`
     * la chiede a lui, altrimenti mostra il valore così com'è.
     *
     * @param  Collection<int, Giocatore>  $giocatori
     * @param  Collection<int, int|string|null>  $valori
     */
    private function etichettaDi(Collection $giocatori, Collection $valori, int|string $valore, string $attributo): string
    {
        $esempio = $giocatori[$valori->search($valore)]?->getAttribute($attributo);

        return $esempio instanceof BackedEnum && method_exists($esempio, 'etichetta')
            ? $esempio->etichetta()
            : (string) $valore;
    }
}
