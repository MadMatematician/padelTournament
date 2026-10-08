<?php

namespace App\Models;

use Database\Factories\GiocatoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Scheda anagrafica di un giocatore: non è un account e non dà accesso all'applicazione.
 * Partecipa ai tornei attraverso le iscrizioni (tabella `iscrizioni`).
 */
class Giocatore extends Model
{
    /** @use HasFactory<GiocatoreFactory> */
    use HasFactory;

    protected $table = 'giocatori';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'cognome',
        'genere',
        'anno_nascita',
        'fascia',
        'lato',
        'circolo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fascia' => 'integer',
            'anno_nascita' => 'integer',
        ];
    }

    /**
     * Tornei a cui il giocatore è iscritto.
     *
     * @return BelongsToMany<Torneo, $this>
     */
    public function tornei(): BelongsToMany
    {
        return $this->belongsToMany(Torneo::class, 'iscrizioni')->withTimestamps();
    }

    /**
     * Circoli presenti in anagrafica, in ordine alfabetico (suggerimenti nei form).
     *
     * @return Collection<int, string>
     */
    public static function circoli(): Collection
    {
        return static::query()
            ->whereNotNull('circolo')
            ->distinct()
            ->orderBy('circolo')
            ->pluck('circolo');
    }

    /**
     * Primo e ultimo anno di nascita accettati oggi, dall'età ammessa in config/tornei.php.
     *
     * @return array{int, int}
     */
    public static function anniNascitaAmmessi(): array
    {
        $anno = (int) now()->year;

        return [
            $anno - (int) config('tornei.giocatori.eta.massima'),
            $anno - (int) config('tornei.giocatori.eta.minima'),
        ];
    }

    /** Il giocatore fa parte di una coppia sorteggiata, in qualunque torneo. */
    public function inCoppia(): bool
    {
        return Coppia::query()
            ->where('prima_fascia_id', $this->getKey())
            ->orWhere('seconda_fascia_id', $this->getKey())
            ->exists();
    }

    public function nomeCompleto(): string
    {
        return $this->nome.' '.$this->cognome;
    }

    /** Cognome e iniziale del nome, per le coppie e il calendario: "Rossi M.". */
    public function nomeBreve(): string
    {
        return $this->cognome.' '.mb_strtoupper(mb_substr($this->nome, 0, 1)).'.';
    }

    /** Etichetta del genere (config/tornei.php), `null` per le schede che non lo hanno. */
    public function etichettaGenere(): ?string
    {
        if ($this->genere === null) {
            return null;
        }

        return config('tornei.giocatori.generi.'.$this->genere, $this->genere);
    }

    /** Etichetta della fascia (config/tornei.php). */
    public function etichettaFascia(): string
    {
        return config('tornei.giocatori.fasce.'.$this->fascia, 'Fascia '.$this->fascia);
    }

    /** Etichetta del lato preferito, `null` se non indicato. */
    public function etichettaLato(): ?string
    {
        if ($this->lato === null) {
            return null;
        }

        return config('tornei.giocatori.lati.'.$this->lato, $this->lato);
    }
}
