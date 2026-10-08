{{-- Pagine per utenti autenticati: barra di navigazione, messaggi (`status` = riuscito, `errore` = non fatto),
     contenuto, finestra di conferma. --}}
@props(['title' => null])
@php
    $utente = auth()->user();
@endphp
<x-layouts.base :title="$title" class="pagina-app">
    <nav class="navbar navbar-expand-md navbar-dark barra-principale">
        <div class="container">
            <x-ui.brand :href="route('home')" class="navbar-brand" />

            @if ($utente)
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principale"
                        aria-controls="menu-principale" aria-expanded="false" aria-label="Apri il menu">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="menu-principale">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a @class(['nav-link', 'active' => request()->routeIs($utente->rottaIniziale())])
                               href="{{ route($utente->rottaIniziale()) }}">
                                <i class="bi bi-{{ $utente->ruolo->icona() }}" aria-hidden="true"></i>
                                {{ $utente->ruolo->etichettaRottaIniziale() }}
                            </a>
                        </li>
                        @if ($utente->eAmministratore())
                            <li class="nav-item">
                                <a @class(['nav-link', 'active' => request()->routeIs('amministrazione.mappa')])
                                   href="{{ route('amministrazione.mappa') }}">
                                    <i class="bi bi-diagram-3" aria-hidden="true"></i>
                                    Mappa del sito
                                </a>
                            </li>
                        @endif
                        {{ $menu ?? '' }}
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle" aria-hidden="true"></i>
                                {{ $utente->name }}
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <div class="dropdown-header">
                                    {{ $utente->username }} · {{ $utente->ruolo->etichetta() }}
                                </div>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Esci
                                    </button>
                                </form>
                            </div>
                        </li>
                    </ul>
                </div>
            @endif
        </div>
    </nav>

    <main class="container py-4">
        @if (session('status'))
            <x-ui.alert variant="success" icon="check-circle" dismissible>{{ session('status') }}</x-ui.alert>
        @endif
        @if (session('errore'))
            <x-ui.alert variant="danger" icon="exclamation-triangle" dismissible>{{ session('errore') }}</x-ui.alert>
        @endif

        {{ $slot }}
    </main>

    <x-ui.conferma />
</x-layouts.base>
