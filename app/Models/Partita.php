<?php

namespace App\Models;

use App\Enums\Fase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una partita del torneo: di un girone (calendario all'italiana) oppure delle finali (tabellone a
 * eliminazione diretta, dove `posizione` è il posto dall'alto nel turno). Le partite delle finali nascono
 * tutte insieme; quelle dei turni successivi al primo restano senza coppie finché il turno precedente non
 * è concluso e i giocatori che lo hanno superato vengono risorteggiati.
 *
 * Il risultato è l'elenco dei set (`risultato`); set e game totali e la vincitrice sono ricopiati
 * nelle colonne `set_*`, `game_*` e `vincitrice_id`.
 */
class Partita extends Model
{
    protected $table = 'partite';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'torneo_id',
        'girone_id',
        'fase',
        'posizione',
        'giornata',
        'ritorno',
        'coppia_a_id',
        'coppia_b_id',
        'risultato',
        'set_a',
        'set_b',
        'game_a',
        'game_b',
        'vincitrice_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'fase' => 'gironi',
        'posizione' => 0,
        'giornata' => 0,
        'ritorno' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fase' => Fase::class,
            'posizione' => 'integer',
            'giornata' => 'integer',
            'ritorno' => 'boolean',
            'risultato' => 'array',
            'set_a' => 'integer',
            'set_b' => 'integer',
            'game_a' => 'integer',
            'game_b' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Torneo, $this>
     */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /**
     * @return BelongsTo<Girone, $this>
     */
    public function girone(): BelongsTo
    {
        return $this->belongsTo(Girone::class);
    }

    /**
     * @return BelongsTo<Coppia, $this>
     */
    public function coppiaA(): BelongsTo
    {
        return $this->belongsTo(Coppia::class, 'coppia_a_id');
    }

    /**
     * @return BelongsTo<Coppia, $this>
     */
    public function coppiaB(): BelongsTo
    {
        return $this->belongsTo(Coppia::class, 'coppia_b_id');
    }

    /**
     * Il gruppo di partite a cui appartiene, quello che si completa insieme: il suo girone oppure, nelle
     * finali, il suo turno. Serve alla finestra del risultato per accorgersi che si è passati a un altro.
     */
    public function chiaveGruppo(): string
    {
        return $this->fase === Fase::Gironi ? 'girone-'.$this->girone_id : 'fase-'.$this->fase->value;
    }

    /** Il nome del gruppo: «Girone A», «Semifinali». */
    public function nomeGruppo(): string
    {
        return $this->fase === Fase::Gironi ? (string) $this->girone?->nome : $this->fase->etichetta();
    }

    /** La partita ha un risultato. */
    public function giocata(): bool
    {
        return $this->risultato !== null;
    }

    /** Tutte e due le coppie sono note: si può inserire il risultato. */
    public function completa(): bool
    {
        return $this->coppia_a_id !== null && $this->coppia_b_id !== null;
    }

    /**
     * Passaggio del turno senza giocare: nel primo turno delle finali il posto di fronte è rimasto vuoto.
     */
    public function bye(): bool
    {
        return $this->vincitrice_id !== null && $this->risultato === null;
    }

    /** Il turno delle finali ha già deciso chi passa: partita giocata oppure passaggio senza giocare. */
    public function decisa(): bool
    {
        return $this->vincitrice_id !== null;
    }

    /** Il punteggio come si scrive a mano: "6-4 7-6(7-5)". Stringa vuota se non è stata giocata. */
    public function punteggio(): string
    {
        return collect($this->risultato ?? [])
            ->map(fn (array $set) => $set['a'].'-'.$set['b']
                .(($set['tb_a'] ?? null) !== null ? '('.$set['tb_a'].'-'.$set['tb_b'].')' : ''))
            ->implode(' ');
    }

    /**
     * I game di una coppia set per set (per il tabellone).
     *
     * @param  'a'|'b'  $lato
     * @return list<int>
     */
    public function gameDi(string $lato): array
    {
        return array_map(fn (array $set) => (int) $set[$lato], $this->risultato ?? []);
    }
}
