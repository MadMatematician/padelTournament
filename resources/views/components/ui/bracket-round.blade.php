{{--
    Un turno di <x-ui.bracket>. Props: title, note (riga sotto il titolo, es. come si formano le squadre del turno).
    Slot: gli incontri, dentro un <x-ui.bracket-node>.
--}}
@props(['title', 'note' => null])
<section {{ $attributes->class(['tabellone-turno']) }}>
    <h3 class="tabellone-titolo">{{ $title }}</h3>
    <p class="tabellone-nota">{{ $note }}</p>
    <div class="tabellone-incontri">
        {{ $slot }}
    </div>
</section>
