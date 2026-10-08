<?php

namespace App\Models;

use App\Enums\Fase;
use App\Services\Finali\CoppieNelleFinali;
use App\Services\Finali\CriterioRipescaggio;
use App\Services\Punteggio\FormatoPartita;
use App\Services\Sorteggio\LatiComplementari;
use Database\Factories\TorneoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Torneo extends Model
{
    /** @use HasFactory<TorneoFactory> */
    use HasFactory;

    protected $table = 'tornei';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'tipologia',
        'data_inizio',
        'numero_turni',
        'squadre_per_girone',
        'andata_ritorno',
        'lati_complementari',
        'qualificate_per_girone',
        'criterio_ripescaggio',
        'formati',
    ];

    /**
     * I tornei nuovi prendono la formula predefinita dalla configurazione (lo stesso valore è anche il default
     * della colonna, per chi scrive nella tabella senza passare dal modello).
     */
    protected static function booted(): void
    {
        static::creating(function (Torneo $torneo): void {
            $torneo->squadre_per_girone ??= config('tornei.gironi.squadre.predefinito');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_inizio' => 'date',
            'numero_turni' => 'integer',
            'squadre_per_girone' => 'integer',
            'andata_ritorno' => 'boolean',
            'lati_complementari' => LatiComplementari::class,
            'qualificate_per_girone' => 'integer',
            'criterio_ripescaggio' => CriterioRipescaggio::class,
            'formati' => 'array',
        ];
    }

    /**
     * Giocatori iscritti al torneo (tabella `iscrizioni`).
     *
     * @return BelongsToMany<Giocatore, $this>
     */
    public function giocatori(): BelongsToMany
    {
        return $this->belongsToMany(Giocatore::class, 'iscrizioni')->withTimestamps();
    }

    /**
     * Le coppie del torneo: quelle sorteggiate tra gli iscritti, che giocano i gironi.
     *
     * @return HasMany<Coppia, $this>
     */
    public function coppie(): HasMany
    {
        return $this->tutteLeCoppie()->where('fase', Fase::Gironi->value);
    }

    /**
     * Le coppie risorteggiate nelle finali, turno per turno.
     *
     * @return HasMany<Coppia, $this>
     */
    public function coppieDelleFinali(): HasMany
    {
        return $this->tutteLeCoppie()->where('fase', '!=', Fase::Gironi->value);
    }

    /**
     * Ogni coppia vista nel torneo, gironi e finali: è l'elenco di chi ha già giocato insieme.
     *
     * @return HasMany<Coppia, $this>
     */
    public function tutteLeCoppie(): HasMany
    {
        return $this->hasMany(Coppia::class);
    }

    /**
     * Gironi, nell'ordine in cui sono stati formati (Girone A, B, …).
     *
     * @return HasMany<Girone, $this>
     */
    public function gironi(): HasMany
    {
        return $this->hasMany(Girone::class)->orderBy('posizione');
    }

    /**
     * Tutte le partite del torneo: gironi e finali.
     *
     * @return HasMany<Partita, $this>
     */
    public function partite(): HasMany
    {
        return $this->hasMany(Partita::class);
    }

    /**
     * Partite dei gironi.
     *
     * @return HasMany<Partita, $this>
     */
    public function partiteDiGirone(): HasMany
    {
        return $this->partite()->where('fase', Fase::Gironi->value);
    }

    /**
     * Partite delle finali (tutti i turni a eliminazione diretta).
     *
     * @return HasMany<Partita, $this>
     */
    public function partiteDelleFinali(): HasMany
    {
        return $this->partite()->where('fase', '!=', Fase::Gironi->value);
    }

    /**
     * Partite di un turno delle finali, dall'alto in basso.
     *
     * @return HasMany<Partita, $this>
     */
    public function partiteDelTurno(Fase $fase): HasMany
    {
        return $this->partite()->where('fase', $fase->value)->orderBy('posizione');
    }

    /** Il tabellone delle finali è stato generato. */
    public function haFinali(): bool
    {
        return $this->partiteDelleFinali()->exists();
    }

    /**
     * Formato delle partite di una fase: quello impostato nella formula, altrimenti il predefinito
     * (config/tornei.php → formato.predefinito, con le eccezioni di formato.predefinito_per_fase).
     */
    public function formato(Fase $fase): FormatoPartita
    {
        $formato = array_merge(
            config('tornei.formato.predefinito'),
            config('tornei.formato.predefinito_per_fase.'.$fase->value, []),
            $this->formati[$fase->value] ?? [],
        );

        return new FormatoPartita(
            set: (int) $formato['set'],
            game: (int) $formato['game'],
            tieBreak: (bool) $formato['tie_break'],
            puntiTieBreak: (int) $formato['punti_tie_break'],
            // I formati salvati prima che esistesse la scelta non hanno la chiave: vale il predefinito.
            dueGameDiScarto: (bool) ($formato['due_game_scarto'] ?? false),
        );
    }

    /** Squadre che accedono alle finali da ogni girone. */
    public function qualificatePerGirone(): int
    {
        return $this->qualificate_per_girone ?? config('tornei.finali.qualificate.predefinito');
    }

    /** Criterio con cui si scelgono le ripescate. */
    public function criterioDiRipescaggio(): CriterioRipescaggio
    {
        return $this->criterio_ripescaggio
            ?? CriterioRipescaggio::from(config('tornei.finali.ripescaggio_predefinito'));
    }

    /** Le coppie sono state sorteggiate: da qui in poi le iscrizioni sono chiuse. */
    public function sorteggiato(): bool
    {
        return $this->coppie()->exists();
    }

    /**
     * Regole della tipologia del torneo (config/tornei.php → tipologie).
     *
     * @return array<string, mixed>
     */
    public function regole(): array
    {
        return config('tornei.tipologie.'.$this->tipologia)
            ?? config('tornei.tipologie.'.config('tornei.tipologia_predefinita'));
    }

    /**
     * Quanto contano i lati preferiti nel sorteggio delle coppie di questo torneo
     * (se non è impostato vale il predefinito della tipologia).
     */
    public function latiNelSorteggio(): LatiComplementari
    {
        return $this->lati_complementari
            ?? LatiComplementari::from($this->regole()['sorteggio']['lati_complementari']);
    }

    /** Come si formano le coppie a ogni turno delle finali (regola della tipologia). */
    public function coppieNelleFinali(): CoppieNelleFinali
    {
        return CoppieNelleFinali::from($this->regole()['sorteggio']['finali'] ?? CoppieNelleFinali::Scambio->value);
    }

    /**
     * Nei nuovi sorteggi delle finali, due giocatori che hanno già fatto coppia nel torneo non vengono
     * rimessi insieme (regola della tipologia).
     */
    public function vietaCoppieRipetute(): bool
    {
        return (bool) ($this->regole()['sorteggio']['vieta_coppie_ripetute'] ?? true);
    }

    /** Se una coppia nuova è impossibile, la ripetizione è ammessa (e segnalata) invece di fermare il torneo. */
    public function consenteRipetizioniInevitabili(): bool
    {
        return (bool) ($this->regole()['sorteggio']['consenti_ripetizione_se_impossibile'] ?? true);
    }

    /** Etichetta della formula delle partite di girone. */
    public function etichettaFormula(): string
    {
        return config('tornei.gironi.formule.'.(int) $this->andata_ritorno);
    }

    /** Etichetta della tipologia (config/tornei.php); la chiave stessa se non è più configurata. */
    public function etichettaTipologia(): string
    {
        return config('tornei.tipologie.'.$this->tipologia.'.etichetta', $this->tipologia);
    }
}
