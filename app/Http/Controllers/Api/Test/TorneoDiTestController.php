<?php

namespace App\Http\Controllers\Api\Test;

use App\Http\Controllers\Controller;
use App\Http\Requests\Test\GeneraTorneoDiTestRequest;
use App\Http\Resources\TorneoResource;
use App\Models\Torneo;
use Illuminate\Http\JsonResponse;

/**
 * Rotta di test dell'API: `/api/test/{giocatori?}` crea con la factory un torneo che ha già tutti i giocatori
 * iscritti e lo restituisce in JSON con l'elenco completo.
 */
class TorneoDiTestController extends Controller
{
    public function __invoke(GeneraTorneoDiTestRequest $request): JsonResponse
    {
        $torneo = Torneo::factory()->conGiocatori($request->numeroGiocatori())->create();

        $torneo->load(['giocatori' => fn ($giocatori) => $giocatori->orderBy('fascia')->orderBy('cognome')->orderBy('nome')]);

        return TorneoResource::make($torneo)->response()->setStatusCode(201);
    }
}
