<?php

namespace App\Http\Requests\Test;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Torneo di test: il numero di giocatori arriva dal link (`/test/24`, `/api/test/24`);
 * se manca vale `tornei.test.giocatori.predefinito`. Limiti in `tornei.test.giocatori`.
 */
class GeneraTorneoDiTestRequest extends FormRequest
{
    /** Chi può usare le rotte di test lo decidono i middleware delle rotte. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'giocatori' => $this->route('giocatori') ?? config('tornei.test.giocatori.predefinito'),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'giocatori' => [
                'required',
                'integer',
                'min:'.config('tornei.test.giocatori.minimo'),
                'max:'.config('tornei.test.giocatori.massimo'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'giocatori.min' => 'Un torneo di test ha almeno :min giocatori.',
            'giocatori.max' => 'Un torneo di test ha al massimo :max giocatori.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'giocatori' => 'numero di giocatori',
        ];
    }

    /** Numero di giocatori da generare, già validato. */
    public function numeroGiocatori(): int
    {
        return (int) $this->validated('giocatori');
    }

    /**
     * Il numero sta nel link, non in un form: nel sito non c'è una pagina a cui tornare con gli errori sui campi,
     * quindi il messaggio va in cima alla pagina di amministrazione. L'API risponde 422 in JSON come sempre.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->is('api/*') || $this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(
            redirect()
                ->route('amministrazione.dashboard')
                ->with('errore', $validator->errors()->first())
        );
    }
}
