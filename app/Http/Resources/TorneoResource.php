<?php

namespace App\Http\Resources;

use App\Models\Torneo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Torneo in JSON; con i giocatori iscritti quando la relazione è caricata.
 *
 * @mixin Torneo
 */
class TorneoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'tipologia' => $this->tipologia,
            'data_inizio' => $this->data_inizio?->format('Y-m-d'),
            'numero_turni' => $this->numero_turni,
            // Pagina di gestione del torneo nel sito (riservata all'amministratore).
            'gestione' => route('amministrazione.tornei.show', $this->resource),
            'iscritti' => $this->whenLoaded('giocatori', fn () => $this->giocatori->count()),
            'iscritti_per_fascia' => $this->whenLoaded('giocatori', fn () => $this->giocatori->countBy('fascia')),
            'giocatori' => GiocatoreResource::collection($this->whenLoaded('giocatori')),
        ];
    }
}
