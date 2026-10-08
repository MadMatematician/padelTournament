<?php

namespace App\Http\Controllers\Amministrazione;

use App\Http\Controllers\Controller;
use App\Services\Mappa\MappaDelSito;
use App\Services\Mappa\SezioneMappa;
use Illuminate\View\View;

/**
 * Mappa del sito: tutte le rotte, lette dai file di route, con la descrizione della loro destinazione.
 */
class MappaController extends Controller
{
    public function __invoke(MappaDelSito $mappa): View
    {
        $sezioni = $mappa->sezioni();

        return view('amministrazione.mappa', [
            'sezioni' => $sezioni,
            'totale' => $sezioni->sum(fn (SezioneMappa $sezione) => count($sezione->voci)),
        ]);
    }
}
