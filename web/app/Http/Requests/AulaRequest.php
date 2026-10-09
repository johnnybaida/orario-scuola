<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return ['dada_discipline.required_if' => 'Spunta almeno una disciplina per l\'aula DADA.', 'dada_discipline.min' => 'Spunta almeno una disciplina per l\'aula DADA.'];
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            // Stringa libera: oltre ai tipi base, una scuola DADA può usare un
            // tipo dedicato per disciplina (es. "dada_ita", "dada_sec_ling": vedi App\Enums\TipoAula).
            'tipo' => ['required', 'string', 'max:50'],
            // Con tipo «dada»: le discipline che si svolgono in quest'aula (una o più); il tipo vero si ricava da loro.
            'dada_discipline' => ['nullable', 'required_if:tipo,dada', 'array', 'min:1'],
            'dada_discipline.*' => [app(\App\Services\SedeCorrente::class)->esiste('discipline')],
            'piano' => ['nullable', 'integer', 'between:-3,10'],
            'capienza' => ['required', 'integer', 'min:1'],
        ];
    }
}
