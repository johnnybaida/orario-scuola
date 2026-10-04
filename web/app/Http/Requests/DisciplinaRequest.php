<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisciplinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $disciplina = $this->route('disciplina');

        return [
            'codice' => ['required', 'string', 'max:20', Rule::unique('discipline', 'codice')->ignore($disciplina)],
            'nome' => ['required', 'string', 'max:255'],
            'classe_concorso' => ['nullable', 'string', 'max:20'],
            // Stringa libera: deve coincidere con un Aula.tipo esistente (usato
            // anche per il modello DADA, un'aula dedicata a questa disciplina).
            'tipo_aula_richiesto' => ['nullable', 'string', 'max:50'],
            'padre_id' => ['nullable', 'exists:discipline,id'],
        ];
    }
}
