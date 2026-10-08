<?php

namespace App\Http\Requests;

use App\Constraints\Catalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VincoloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ambitiConsentiti = ['globale', 'classe', 'docente', 'disciplina', 'aula'];
        if ($this->filled('tipo') && array_key_exists($this->input('tipo'), Catalogo::TIPI)) {
            $ambitiConsentiti = Catalogo::istanza($this->input('tipo'))->ambitiConsentiti();
        }

        $regole = [
            'tipo' => ['required', Rule::in(array_keys(Catalogo::TIPI))],
            'ambito_livello' => ['required', Rule::in($ambitiConsentiti)],
            'ambito_ids' => ['nullable', 'array'],
            'ambito_ids.*' => ['integer'],
            'severita' => ['required', 'in:rigido,preferenziale'],
            'peso' => ['required_if:severita,preferenziale', 'nullable', 'integer', 'min:1', 'max:100'],
            'attivo' => ['nullable', 'boolean'],
            'nota' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->filled('tipo') && array_key_exists($this->input('tipo'), Catalogo::TIPI)) {
            foreach (Catalogo::istanza($this->input('tipo'))->regoleParametri() as $campo => $regoleCampo) {
                $regole["parametri.{$campo}"] = $regoleCampo;
            }
        }

        return $regole;
    }

    /** Controlli che dipendono dall'ambito (es. D1: la disciplina è facoltativa solo per i docenti). */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            $tipo = $this->input('tipo');
            if ($v->errors()->isNotEmpty() || ! array_key_exists($tipo, Catalogo::TIPI)) {
                return;
            }
            $istanza = Catalogo::istanza($tipo);
            if (method_exists($istanza, 'erroriAmbito')) {
                foreach ($istanza->erroriAmbito((string) $this->input('ambito_livello'), (array) $this->input('parametri', [])) as $campo => $messaggio) {
                    $v->errors()->add($campo, $messaggio);
                }
            }
        });
    }
}
