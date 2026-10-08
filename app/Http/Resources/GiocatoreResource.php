<?php

namespace App\Http\Resources;

use App\Models\Giocatore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Scheda di un giocatore in JSON.
 *
 * @mixin Giocatore
 */
class GiocatoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cognome' => $this->cognome,
            'genere' => $this->genere,
            'anno_nascita' => $this->anno_nascita,
            'fascia' => $this->fascia,
            'lato' => $this->lato,
            'circolo' => $this->circolo,
        ];
    }
}
