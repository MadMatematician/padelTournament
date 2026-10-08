<?php

namespace App\Http\Requests\Amministrazione;

use App\Models\Giocatore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inserimento di uno o più giocatori in una volta sola: il form invia una riga per giocatore
 * (`giocatori[0][nome]`, `giocatori[0][cognome]`, …). Le righe lasciate vuote vengono ignorate.
 */
class SalvaGiocatoriRequest extends FormRequest
{
    /**
     * Gli errori finiscono in un contenitore dedicato: nella stessa pagina
     * ci sono altri form (tornei, iscrizione dall'anagrafica).
     */
    protected $errorBag = 'giocatore';

    public function authorize(): bool
    {
        return $this->user()?->eAmministratore() ?? false;
    }

    /**
     * Scarta le righe del tutto vuote (l'ultima lo è sempre: è quella in attesa del prossimo giocatore).
     * Le altre conservano il proprio indice: dopo un invio respinto il form viene riproposto con i valori
     * inviati dal browser, e gli errori (`giocatori.3.cognome`) devono cadere sulle stesse righe.
     */
    protected function prepareForValidation(): void
    {
        $righe = $this->input('giocatori');

        if (! is_array($righe)) {
            return;
        }

        $compilate = array_filter($righe, function (mixed $riga): bool {
            if (! is_array($riga)) {
                return true; // riga malformata: la respinge la validazione
            }

            return collect($riga)->contains(fn (mixed $valore) => is_array($valore) || trim((string) $valore) !== '');
        });

        $this->merge(['giocatori' => $compilate]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        [$primoAnno, $ultimoAnno] = Giocatore::anniNascitaAmmessi();

        return [
            'giocatori' => ['required', 'array', 'max:'.config('tornei.giocatori.massimo_per_inserimento')],
            'giocatori.*' => ['array'],
            'giocatori.*.nome' => ['required', 'string', 'max:60'],
            'giocatori.*.cognome' => ['required', 'string', 'max:60'],
            'giocatori.*.genere' => ['required', 'string', Rule::in(array_keys(config('tornei.giocatori.generi')))],
            'giocatori.*.anno_nascita' => ['required', 'integer', 'between:'.$primoAnno.','.$ultimoAnno],
            'giocatori.*.fascia' => ['required', 'integer', Rule::in(array_keys(config('tornei.giocatori.fasce')))],
            'giocatori.*.lato' => ['nullable', 'string', Rule::in(array_keys(config('tornei.giocatori.lati')))],
            'giocatori.*.circolo' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'giocatori.required' => 'Inserisci almeno un giocatore.',
            'giocatori.max' => 'Si possono inserire al massimo :max giocatori alla volta.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'giocatori' => 'giocatori',
            'giocatori.*' => 'giocatore',
            'giocatori.*.nome' => 'nome',
            'giocatori.*.cognome' => 'cognome',
            'giocatori.*.genere' => 'genere',
            'giocatori.*.anno_nascita' => 'anno di nascita',
            'giocatori.*.fascia' => 'fascia',
            'giocatori.*.lato' => 'lato preferito',
            'giocatori.*.circolo' => 'circolo',
        ];
    }

    /**
     * I giocatori validati, pronti per `Giocatore::create()`.
     *
     * Il circolo è testo libero: per non ritrovarsi lo stesso circolo scritto in due modi (e quindi due voci
     * nel filtro) gli spazi vengono normalizzati e, se esiste già un circolo con lo stesso nome a meno di
     * maiuscole e minuscole, si riusa la grafia esistente.
     *
     * @return list<array<string, mixed>>
     */
    public function giocatori(): array
    {
        $grafie = Giocatore::circoli()->mapWithKeys(fn (string $circolo) => [mb_strtolower($circolo) => $circolo])->all();

        return array_map(function (array $giocatore) use (&$grafie): array {
            $circolo = trim((string) preg_replace('/\s+/u', ' ', (string) ($giocatore['circolo'] ?? '')));

            if ($circolo === '') {
                $giocatore['circolo'] = null;

                return $giocatore;
            }

            $chiave = mb_strtolower($circolo);
            $grafie[$chiave] ??= $circolo;
            $giocatore['circolo'] = $grafie[$chiave];

            return $giocatore;
        }, array_values($this->validated('giocatori')));
    }
}
