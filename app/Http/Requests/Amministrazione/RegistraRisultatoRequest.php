<?php

namespace App\Http\Requests\Amministrazione;

use App\Models\Partita;
use App\Services\Punteggio\EsitoPartita;
use App\Services\Punteggio\PunteggioNonValidoException;
use App\Services\Punteggio\ValutatorePunteggio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Risultato di una partita: una riga per set (`set[0][a]`, `set[0][b]` i game delle due coppie,
 * `set[0][tb_a]`, `set[0][tb_b]` i punti del tie-break, facoltativi). Le righe vuote in coda sono ignorate.
 * Il punteggio viene controllato contro il formato della fase a cui appartiene la partita.
 */
class RegistraRisultatoRequest extends FormRequest
{
    /**
     * Contenitore dedicato: fa riaprire la finestra del risultato (`partita_id` dice per quale partita).
     */
    protected $errorBag = 'risultato';

    private ?EsitoPartita $esito = null;

    public function authorize(): bool
    {
        return $this->user()?->eAmministratore() ?? false;
    }

    /** La partita deve essere del torneo indicato nell'indirizzo. */
    protected function prepareForValidation(): void
    {
        abort_unless($this->route('partita')->torneo_id === $this->route('torneo')->id, 404);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'partita_id' => ['nullable', 'integer'],
            'set' => ['required', 'array', 'max:'.max(array_keys(config('tornei.formato.set')))],
            'set.*' => ['array'],
            'set.*.a' => ['nullable', 'integer', 'min:0', 'max:99'],
            'set.*.b' => ['nullable', 'integer', 'min:0', 'max:99'],
            'set.*.tb_a' => ['nullable', 'integer', 'min:0', 'max:99'],
            'set.*.tb_b' => ['nullable', 'integer', 'min:0', 'max:99'],
        ];
    }

    /**
     * Dopo i controlli di forma, quello di merito: il punteggio deve essere possibile con il formato della fase.
     * L'errore viene messo sul set a cui si riferisce.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Partita $partita */
                $partita = $this->route('partita');

                try {
                    $this->esito = (new ValutatorePunteggio)->valuta(
                        $partita->torneo->formato($partita->fase),
                        $this->input('set'),
                    );
                } catch (PunteggioNonValidoException $errore) {
                    $validator->errors()->add(
                        $errore->set === null ? 'set' : 'set.'.$errore->set.'.a',
                        $errore->getMessage(),
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'set.required' => 'Inserisci il punteggio di almeno un set.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'set' => 'punteggio',
            'set.*.a' => 'game',
            'set.*.b' => 'game',
            'set.*.tb_a' => 'punti del tie-break',
            'set.*.tb_b' => 'punti del tie-break',
        ];
    }

    /** L'esito del punteggio inserito (disponibile dopo la validazione). */
    public function esito(): EsitoPartita
    {
        return $this->esito;
    }
}
