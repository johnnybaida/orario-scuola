<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            // Stringa libera: oltre ai tipi base, una scuola DADA può usare un
            // tipo dedicato per disciplina (es. "dada_ita", "dada_sec_ling": vedi App\Enums\TipoAula).
            'tipo' => ['required', 'string', 'max:50'],
            'piano' => ['nullable', 'integer', 'between:-3,10'],
            'capienza' => ['required', 'integer', 'min:1'],
        ];
    }
}
