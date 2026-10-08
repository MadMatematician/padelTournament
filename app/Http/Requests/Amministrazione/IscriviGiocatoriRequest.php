<?php

namespace App\Http\Requests\Amministrazione;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Iscrizione a un torneo di giocatori già presenti in anagrafica (uno o più).
 */
class IscriviGiocatoriRequest extends FormRequest
{
    /**
     * Contenitore dedicato: nella pagina del torneo c'è anche il form "Registra giocatore".
     */
    protected $errorBag = 'iscrizione';

    public function authorize(): bool
    {
        return $this->user()?->eAmministratore() ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'giocatori' => ['required', 'array'],
            'giocatori.*' => ['integer', 'distinct', Rule::exists('giocatori', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'giocatori.required' => 'Seleziona almeno un giocatore.',
            'giocatori.*.exists' => 'Uno dei giocatori selezionati non è più in anagrafica: ricarica la pagina.',
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
        ];
    }
}
