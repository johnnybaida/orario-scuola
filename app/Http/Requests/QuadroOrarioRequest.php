<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuadroOrarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            // Righe ripetibili della pagina di modifica (assenti alla creazione).
            'righe' => ['nullable', 'array'],
            'righe.*.id' => ['nullable', 'integer'],
            'righe.*.disciplina_id' => ['required', 'exists:discipline,id', 'distinct'],
            'righe.*.ore_settimanali' => ['required', 'integer', 'min:1', 'max:40'],
        ];
    }
}
