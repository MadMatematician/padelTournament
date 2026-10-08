<?php

namespace App\Http\Controllers\AreaUtente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('area-utente.dashboard', [
            'utente' => $request->user(),
        ]);
    }
}
