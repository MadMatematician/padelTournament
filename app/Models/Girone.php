<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un girone di un torneo: un gruppo di coppie che si incontrano tra loro (calendario all'italiana).
 */
class Girone extends Model
{
    protected $table = 'gironi';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'torneo_id',
        'nome',
        'posizione',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posizione' => 'integer',
        ];
    }

    /** Nome del girone in base all'ordine: Girone A, Girone B, … e dopo la Z "Girone 27". */
    public static function nomePerPosizione(int $posizione): string
    {
        return 'Girone '.($posizione < 26 ? chr(ord('A') + $posizione) : (string) ($posizione + 1));
    }

    /**
     * @return BelongsTo<Torneo, $this>
     */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /**
     * @return HasMany<Coppia, $this>
     */
    public function coppie(): HasMany
    {
        return $this->hasMany(Coppia::class);
    }

    /**
     * @return HasMany<Partita, $this>
     */
    public function partite(): HasMany
    {
        return $this->hasMany(Partita::class);
    }
}
