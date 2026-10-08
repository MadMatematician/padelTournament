<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Ruolo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrazioneRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegistrazioneController extends Controller
{
    public function create(): View
    {
        return view('auth.registrazione');
    }

    public function store(RegistrazioneRequest $request): RedirectResponse
    {
        $utente = new User($request->safe()->only(['name', 'username', 'email', 'password']));

        // Chi si registra da solo è sempre un utente: gli amministratori non si creano da qui.
        $utente->ruolo = Ruolo::Utente;
        $utente->save();

        event(new Registered($utente));

        Auth::login($utente);
        $request->session()->regenerate();

        return redirect()
            ->route($utente->rottaIniziale())
            ->with('status', 'Registrazione completata. Benvenuto, '.$utente->name.'!');
    }
}
