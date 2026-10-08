{{--
    Formula del torneo, in un unico form:
    - sorteggio e gironi: lati nel sorteggio delle coppie, squadre per girone, sola andata o andata e ritorno;
    - finali: squadre che accedono da ogni girone e criterio dei ripescaggi;
    - formato delle partite, fase per fase (gironi e ogni turno delle finali): set, game, tie-break e, senza
      tie-break, se servono due game di scarto.
    Props: torneo (App\Models\Torneo). I valori salvati valgono per ciò che viene generato o inserito dopo.
--}}
@props(['torneo'])
@php
    $squadre = config('tornei.gironi.squadre');
    $qualificate = config('tornei.finali.qualificate');
    $formato = config('tornei.formato');
    $errori = $errors->getBag('formula');
@endphp
<x-ui.card id="formula" title="Formula" icon="sliders" {{ $attributes }}>
    <x-ui.form id="form-formula" :action="route('amministrazione.tornei.formula.update', $torneo)" method="PATCH" bag="formula">
        <x-ui.row>
            <x-ui.col lg="4">
                <x-ui.select id="formula-lati" name="lati_complementari" label="Lati nel sorteggio delle coppie" icon="shuffle"
                             :options="config('tornei.lati_complementari')" :value="$torneo->latiNelSorteggio()->value"
                             help="Destra e sinistra indicati dai giocatori. Vale dal prossimo sorteggio delle coppie." required />
                <x-ui.input id="formula-squadre" name="squadre_per_girone" type="number" label="Squadre per girone (massimo)" icon="diagram-3"
                            :min="$squadre['minimo']" :max="$squadre['massimo']" :value="$torneo->squadre_per_girone"
                            help="Il numero di gironi dipende da quante coppie ci sono." required />
                <x-ui.select id="formula-partite" name="andata_ritorno" label="Partite del girone" icon="arrow-left-right"
                             :options="config('tornei.gironi.formule')" :value="(int) $torneo->andata_ritorno"
                             help="All'italiana: ogni coppia incontra tutte le altre del girone." required />
                <x-ui.input id="formula-qualificate" name="qualificate_per_girone" type="number" label="Squadre che accedono alle finali, per girone" icon="trophy"
                            :min="$qualificate['minimo']" :max="$qualificate['massimo']" :value="$torneo->qualificatePerGirone()"
                            help="Il primo turno delle finali è il più piccolo che le contiene tutte (4, 8, 16 o 32 squadre)." required />
                <x-ui.select id="formula-ripescaggi" name="criterio_ripescaggio" label="Ripescaggi" icon="life-preserver"
                             :options="config('tornei.finali.ripescaggi')" :value="$torneo->criterioDiRipescaggio()->value"
                             help="Se le qualificate non riempiono il primo turno, passano le migliori escluse: prima le meglio piazzate, a parità di piazzamento con questo criterio (medie per partita)." required />
            </x-ui.col>

            <x-ui.col lg="8">
                <x-ui.text class="fw-semibold mb-1">Formato delle partite</x-ui.text>
                <x-ui.text muted small>
                    Per ogni fase: quanti set, a quanti game, e se sul pari si gioca il tie-break (standard a {{ $formato['punti_tie_break']['standard'] }} punti, oppure personalizzato).
                    Senza tie-break il set va a chi arriva per primo ai game previsti, anche con un solo game in più (a 4 game: 4-3);
                    con «Due game di scarto» si continua finché una coppia ha due game di vantaggio (5-3, 6-4…).
                    Vale per i risultati inseriti da adesso in poi.
                </x-ui.text>

                @if ($errori->has('formati') || $errori->has('formati.*'))
                    <x-ui.alert variant="danger" icon="exclamation-triangle">Controlla il formato delle partite: uno o più valori non sono validi.</x-ui.alert>
                @endif

                <x-ui.table :headers="['Fase', 'Set', 'Game', 'Tie-break', 'Punti tie-break', 'Due game di scarto']" class="tabella-formati">
                    @foreach (\App\Enums\Fase::cases() as $fase)
                        @php
                            $attuale = $torneo->formato($fase);
                            $tieBreak = match (true) {
                                ! $attuale->tieBreak => 'no',
                                $attuale->puntiTieBreak === $formato['punti_tie_break']['standard'] => 'standard',
                                default => 'personalizzato',
                            };
                            $campo = fn (string $nome) => 'formati['.$fase->value.']['.$nome.']';
                            $id = fn (string $nome) => 'formato-'.$fase->value.'-'.$nome;
                        @endphp
                        <x-ui.tr data-formato-fase :data-formato-standard="$formato['punti_tie_break']['standard']">
                            <x-ui.td class="fw-semibold text-nowrap">{{ $fase->etichetta() }}</x-ui.td>
                            <x-ui.td>
                                <x-ui.select :id="$id('set')" :name="$campo('set')" :options="$formato['set']" :value="$attuale->set"
                                             :aria-label="'Set, '.$fase->etichetta()" compact />
                            </x-ui.td>
                            <x-ui.td>
                                <x-ui.input :id="$id('game')" :name="$campo('game')" type="number" :min="$formato['game']['minimo']" :max="$formato['game']['massimo']"
                                            :value="$attuale->game" :aria-label="'Game per set, '.$fase->etichetta()" compact />
                            </x-ui.td>
                            <x-ui.td>
                                <x-ui.select :id="$id('tie-break')" :name="$campo('tie_break')" :options="$formato['tie_break']" :value="$tieBreak"
                                             :aria-label="'Tie-break, '.$fase->etichetta()" data-formato-tie-break compact />
                            </x-ui.td>
                            <x-ui.td>
                                <x-ui.input :id="$id('punti')" :name="$campo('punti_tie_break')" type="number"
                                            :min="$formato['punti_tie_break']['minimo']" :max="$formato['punti_tie_break']['massimo']"
                                            :value="$attuale->tieBreak ? $attuale->puntiTieBreak : null" :aria-label="'Punti del tie-break, '.$fase->etichetta()"
                                            data-formato-punti compact />
                            </x-ui.td>
                            <x-ui.td>
                                <x-ui.checkbox :id="$id('scarto')" :name="$campo('due_game_scarto')" :checked="$attuale->dueGameDiScarto"
                                               :disabled="$tieBreak !== 'no'" :aria-label="'Due game di scarto senza tie-break, '.$fase->etichetta()"
                                               title="Si sceglie solo senza tie-break" data-formato-scarto />
                            </x-ui.td>
                        </x-ui.tr>
                    @endforeach
                </x-ui.table>
            </x-ui.col>
        </x-ui.row>

        <x-ui.button type="submit" variant="outline-primary" icon="check-lg" class="mt-2">Salva formula</x-ui.button>
    </x-ui.form>
</x-ui.card>
