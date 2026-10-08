<?php

use App\Http\Middleware\SoloAmbientiDiTest;
use App\Http\Middleware\VerificaRuolo;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ruolo' => VerificaRuolo::class,
            'ambiente-test' => SoloAmbientiDiTest::class,
        ]);

        // Le rotte di test fuori dagli ambienti previsti non esistono per nessuno: il 404 viene prima del login.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: SoloAmbientiDiTest::class,
        );

        // Ospiti → login; utenti già autenticati → pagina iniziale del proprio ruolo.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // L'API risponde sempre in JSON, anche aperta dal browser (errori di validazione, 404…).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $errore) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
