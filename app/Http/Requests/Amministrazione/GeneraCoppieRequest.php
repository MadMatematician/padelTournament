<?php

namespace App\Http\Requests\Amministrazione;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sorteggio delle coppie. Con le fasce pari non servono dati; con le fasce non pari va scelto come
 * completare le coppie: `prestito` = "sorteggia" (i giocatori in prestito vengono estratti)
 * oppure "indicati" (sono quelli elencati in `giocatori_prestito`).
 */
class GeneraCoppieRequest extends FormRequest
{
    public const SORTEGGIA = 'sorteggia';

    public const INDICATI = 'indicati';

    /**
     * Contenitore dedicato: fa riaprire la finestra del prestito.
     */
    protected $errorBag = 'sorteggio';

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
            'prestito' => ['nullable', 'string', Rule::in([self::SORTEGGIA, self::INDICATI])],
            'giocatori_prestito' => ['exclude_unless:prestito,'.self::INDICATI, 'required', 'array'],
            'giocatori_prestito.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'giocatori_prestito.required' => 'Indica chi va in prestito, oppure scegli di sorteggiarlo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prestito' => 'modo di completare le coppie',
            'giocatori_prestito' => 'giocatori in prestito',
            'giocatori_prestito.*' => 'giocatore in prestito',
        ];
    }

    /** L'amministratore ha scelto come completare le coppie quando le fasce non sono pari. */
    public function prestitoDeciso(): bool
    {
        return $this->validated('prestito') !== null;
    }

    /**
     * I giocatori indicati per il prestito; `null` se vanno sorteggiati.
     *
     * @return list<int>|null
     */
    public function prestiti(): ?array
    {
        if ($this->validated('prestito') !== self::INDICATI) {
            return null;
        }

        return array_map('intval', array_values($this->validated('giocatori_prestito')));
    }
}
