<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Radice del sito: smista verso il login o verso la pagina iniziale del ruolo.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $utente = $request->user();

        return redirect()->route($utente ? $utente->rottaIniziale() : 'login');
    }
}
