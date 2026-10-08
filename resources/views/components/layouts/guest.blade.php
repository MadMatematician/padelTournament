{{-- Pagine per ospiti (login, registrazione): scheda centrata sotto al marchio. --}}
@props(['title' => null, 'wide' => false])
<x-layouts.base :title="$title" class="pagina-ospite">
    <main class="container">
        <div class="row justify-content-center align-items-center min-vh-100 py-4">
            <div @class(['col-12', 'col-sm-10 col-md-7 col-lg-5 col-xl-4' => ! $wide, 'col-md-10 col-lg-8 col-xl-6' => $wide])>
                <div class="text-center mb-4">
                    <x-ui.brand class="marchio-grande" />
                </div>
                {{ $slot }}
            </div>
        </div>
    </main>
</x-layouts.base>
