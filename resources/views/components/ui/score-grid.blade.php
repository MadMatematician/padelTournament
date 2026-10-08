{{--
    Griglia per inserire il punteggio di una partita, a tabellone: una riga per coppia, una colonna per set,
    più due righe per i punti del tie-break. I campi si chiamano `name[i][a]`, `name[i][b]`, `name[i][tb_a]`,
    `name[i][tb_b]` (i = indice del set, da 0).
    Props: name, sets (colonne presenti), visible (colonne in uso: le altre sono nascoste e disattivate),
    tieBreak (mostra le righe del tie-break), teamA, teamB (nomi delle coppie).
    Gli errori (`name.i.a`) e i valori riproposti si leggono dal contenitore (`bag`) del form: vedi <x-ui.form>.
    Gli attributi `data-punteggio*` servono a torneo.js per riempire la griglia quando la finestra viene
    riusata per un'altra partita e per mostrare gli errori di un salvataggio fatto in sottofondo.
--}}
@aware(['bag' => 'default'])
@props(['name' => 'set', 'sets' => 5, 'visible' => null, 'tieBreak' => true, 'teamA' => '', 'teamB' => ''])
@php
    $visible ??= $sets;
    $errori = $errors->getBag($bag);
    $inviati = ($bag === 'default' || $errori->any()) ? (array) old($name, []) : [];
    $valore = fn (int $set, string $campo) => $inviati[$set][$campo] ?? '';
    $errato = fn (int $set) => $errori->has($name.'.'.$set.'.*');
    $righe = [
        ['campo' => 'a', 'coppia' => 'a', 'tie_break' => false],
        ['campo' => 'b', 'coppia' => 'b', 'tie_break' => false],
        ['campo' => 'tb_a', 'coppia' => 'a', 'tie_break' => true],
        ['campo' => 'tb_b', 'coppia' => 'b', 'tie_break' => true],
    ];
@endphp
<div {{ $attributes->class(['table-responsive']) }} data-punteggio>
    <table class="table table-sm table-borderless align-middle tabella-punteggio mb-2">
        <thead>
            <tr>
                <th scope="col"><span class="visually-hidden">Coppia</span></th>
                @for ($set = 0; $set < $sets; $set++)
                    <th scope="col" data-punteggio-set="{{ $set }}" @class(['text-center', 'd-none' => $set >= $visible])>{{ $set + 1 }}° set</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($righe as $riga)
                <tr @if ($riga['tie_break']) data-punteggio-tie-break @endif @class(['d-none' => $riga['tie_break'] && ! $tieBreak])>
                    <th scope="row" @class(['fw-normal small text-body-secondary' => $riga['tie_break']])>
                        @if ($riga['tie_break'])
                            tie-break
                        @endif
                        <span data-punteggio-coppia="{{ $riga['coppia'] }}">{{ $riga['coppia'] === 'a' ? $teamA : $teamB }}</span>
                    </th>
                    @for ($set = 0; $set < $sets; $set++)
                        <td data-punteggio-set="{{ $set }}" @class(['d-none' => $set >= $visible])>
                            <input type="number" inputmode="numeric" min="0" max="99" autocomplete="off"
                                   name="{{ $name }}[{{ $set }}][{{ $riga['campo'] }}]"
                                   id="{{ $name }}-{{ $set }}-{{ str_replace('_', '-', $riga['campo']) }}"
                                   value="{{ $valore($set, $riga['campo']) }}"
                                   aria-label="{{ $riga['tie_break'] ? 'Punti del tie-break' : 'Game' }}, {{ $riga['coppia'] === 'a' ? 'prima' : 'seconda' }} coppia, {{ $set + 1 }}° set"
                                   @disabled($set >= $visible || ($riga['tie_break'] && ! $tieBreak))
                                   @if ($set === 0 && $riga['campo'] === 'a') data-fuoco @endif
                                   @class(['form-control', 'text-center', 'form-control-sm' => $riga['tie_break'], 'is-invalid' => $errato($set)])>
                        </td>
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Modello di una riga di errore: torneo.js lo copia quando il punteggio viene respinto senza ricaricare la pagina. --}}
    <template data-punteggio-errore>
        <div class="invalid-feedback d-block"></div>
    </template>

    <div data-punteggio-errori>
        @for ($set = 0; $set < $sets; $set++)
            @foreach ($errori->get($name.'.'.$set.'.*') as $messaggi)
                @foreach ($messaggi as $messaggio)
                    <div class="invalid-feedback d-block">{{ $set + 1 }}° set: {{ $messaggio }}</div>
                @endforeach
            @endforeach
        @endfor
    </div>
</div>
