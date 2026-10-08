<?php

namespace App\Models;

use App\Enums\Fase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una coppia (squadra) di un torneo: un giocatore di prima fascia e uno di seconda.
 *
 * Se le fasce non sono pari, uno dei due può essere "in prestito" (`prestito_id`): ha già la sua coppia
 * e ne completa una seconda, che gioca in un altro girone.
 *
 * `fase` dice per quale fase la coppia è stata sorteggiata: quelle dei gironi (`gironi`) sono le coppie del
 * torneo; nelle finali i giocatori ancora in gara vengono risorteggiati a ogni turno, e ogni turno ha le sue
 * coppie. `ripetuta` segnala una coppia delle finali che si era già vista nel torneo (inevitabile).
 */
class Coppia extends Model
{
    protected $table = 'coppie';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'torneo_id',
        'fase',
        'girone_id',
        'prima_fascia_id',
        'seconda_fascia_id',
        'prestito_id',
        'ripetuta',
        'finali',
        'testa_di_serie',
    ];

    /** Valori di `finali`: come la coppia è entrata nel tabellone. */
    public const DIRETTA = 'diretta';

    public const RIPESCATA = 'ripescata';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'fase' => 'gironi',
        'ripetuta' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fase' => Fase::class,
            'ripetuta' => 'boolean',
            'testa_di_serie' => 'integer',
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
     * @return BelongsTo<Giocatore, $this>
     */
    public function primaFascia(): BelongsTo
    {
        return $this->belongsTo(Giocatore::class, 'prima_fascia_id');
    }

    /**
     * @return BelongsTo<Giocatore, $this>
     */
    public function secondaFascia(): BelongsTo
    {
        return $this->belongsTo(Giocatore::class, 'seconda_fascia_id');
    }

    /** La coppia è completata da un giocatore in prestito. */
    public function conPrestito(): bool
    {
        return $this->prestito_id !== null;
    }

    /** Il giocatore indicato gioca in questa coppia in prestito. */
    public function inPrestito(Giocatore $giocatore): bool
    {
        return $this->prestito_id === $giocatore->getKey();
    }

    /**
     * I due giocatori della coppia.
     *
     * @return list<int>
     */
    public function giocatori(): array
    {
        return [$this->prima_fascia_id, $this->seconda_fascia_id];
    }

    /** Nome breve della coppia per elenchi e calendario: "Rossi M. / Bianchi L.". */
    public function nome(): string
    {
        return $this->primaFascia->nomeBreve().' / '.$this->secondaFascia->nomeBreve();
    }
}
