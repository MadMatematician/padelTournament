<?php

namespace App\Http\Requests\Amministrazione;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formula di un torneo: peso dei lati nel sorteggio delle coppie, squadre per girone, sola andata o andata
 * e ritorno, squadre che accedono alle finali, criterio dei ripescaggi e, per ogni fase (gironi e ogni turno
 * delle finali), formato delle partite: `formati[fase][set|game|tie_break|punti_tie_break]`, dove `tie_break`
 * è "no", "standard" o "personalizzato" e i punti contano solo nell'ultimo caso.
 */
class AggiornaFormulaRequest extends FormRequest
{
    protected $errorBag = 'formula';

    public function authorize(): bool
    {
        return $this->user()?->eAmministratore() ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $formato = config('tornei.formato');

        $regole = [
            'lati_complementari' => ['required', 'string', Rule::in(array_keys(config('tornei.lati_complementari')))],
            'squadre_per_girone' => [
                'required',
                'integer',
                'min:'.config('tornei.gironi.squadre.minimo'),
                'max:'.config('tornei.gironi.squadre.massimo'),
            ],
            'andata_ritorno' => ['required', Rule::in(array_map('strval', array_keys(config('tornei.gironi.formule'))))],
            'qualificate_per_girone' => [
                'required',
                'integer',
                'min:'.config('tornei.finali.qualificate.minimo'),
                'max:'.config('tornei.finali.qualificate.massimo'),
            ],
            'criterio_ripescaggio' => ['required', 'string', Rule::in(array_keys(config('tornei.finali.ripescaggi')))],
            'formati' => ['required', 'array'],
        ];

        // Un formato per ogni fase: gironi e ogni turno delle finali.
        foreach (array_keys(config('tornei.fasi')) as $fase) {
            $regole['formati.'.$fase] = ['required', 'array'];
            $regole['formati.'.$fase.'.set'] = ['required', Rule::in(array_map('strval', array_keys($formato['set'])))];
            $regole['formati.'.$fase.'.game'] = ['required', 'integer', 'min:'.$formato['game']['minimo'], 'max:'.$formato['game']['massimo']];
            $regole['formati.'.$fase.'.tie_break'] = ['required', 'string', Rule::in(array_keys($formato['tie_break']))];
            // I punti servono solo per il tie-break personalizzato: negli altri casi il campo viene ignorato.
            $regole['formati.'.$fase.'.punti_tie_break'] = [
                'exclude_unless:formati.'.$fase.'.tie_break,personalizzato',
                'required',
                'integer',
                'min:'.$formato['punti_tie_break']['minimo'],
                'max:'.$formato['punti_tie_break']['massimo'],
            ];
            // Casella di spunta: non spuntata (o spenta, quando c'è il tie-break) non arriva affatto.
            $regole['formati.'.$fase.'.due_game_scarto'] = ['nullable', 'boolean'];
        }

        return $regole;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $nomi = [
            'squadre_per_girone' => 'squadre per girone',
            'andata_ritorno' => 'partite',
            'lati_complementari' => 'lati nel sorteggio',
            'qualificate_per_girone' => 'squadre che accedono alle finali',
            'criterio_ripescaggio' => 'criterio dei ripescaggi',
            'formati' => 'formato delle partite',
        ];

        foreach (config('tornei.fasi') as $fase => $etichetta) {
            $nomi['formati.'.$fase] = 'formato ('.mb_strtolower($etichetta).')';
            $nomi['formati.'.$fase.'.set'] = 'set';
            $nomi['formati.'.$fase.'.game'] = 'game';
            $nomi['formati.'.$fase.'.tie_break'] = 'tie-break';
            $nomi['formati.'.$fase.'.punti_tie_break'] = 'punti del tie-break';
            $nomi['formati.'.$fase.'.due_game_scarto'] = 'due game di scarto';
        }

        return $nomi;
    }

    /**
     * I valori da salvare sul torneo.
     *
     * @return array<string, mixed>
     */
    public function formula(): array
    {
        $standard = config('tornei.formato.punti_tie_break.standard');
        $formati = [];

        foreach (array_keys(config('tornei.fasi')) as $fase) {
            $formato = $this->validated('formati.'.$fase);

            $formati[$fase] = [
                'set' => (int) $formato['set'],
                'game' => (int) $formato['game'],
                'tie_break' => $formato['tie_break'] !== 'no',
                'punti_tie_break' => $formato['tie_break'] === 'personalizzato' ? (int) $formato['punti_tie_break'] : $standard,
                // I due game di scarto si scelgono solo senza tie-break: con il tie-break la spunta non conta.
                'due_game_scarto' => $formato['tie_break'] === 'no' && (bool) ($formato['due_game_scarto'] ?? false),
            ];
        }

        return [
            'lati_complementari' => (string) $this->validated('lati_complementari'),
            'squadre_per_girone' => (int) $this->validated('squadre_per_girone'),
            'andata_ritorno' => (bool) (int) $this->validated('andata_ritorno'),
            'qualificate_per_girone' => (int) $this->validated('qualificate_per_girone'),
            'criterio_ripescaggio' => (string) $this->validated('criterio_ripescaggio'),
            'formati' => $formati,
        ];
    }
}
