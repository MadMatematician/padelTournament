<?php

namespace App\Http\Requests\Amministrazione;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvaTorneoRequest extends FormRequest
{
    /**
     * Gli errori finiscono in un contenitore dedicato: nella stessa pagina
     * c'è anche il form dei giocatori, che ha un campo con lo stesso nome.
     */
    protected $errorBag = 'torneo';

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
            'nome' => ['required', 'string', 'max:120'],
            'tipologia' => ['required', 'string', Rule::in(array_keys(config('tornei.tipologie')))],
            'data_inizio' => ['nullable', 'date'],
            'numero_turni' => [
                'required',
                'integer',
                'min:'.config('tornei.turni.minimo'),
                'max:'.config('tornei.turni.massimo'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome' => 'nome del torneo',
            'tipologia' => 'tipologia',
            'data_inizio' => 'data di inizio',
            'numero_turni' => 'numero di turni',
        ];
    }
}
