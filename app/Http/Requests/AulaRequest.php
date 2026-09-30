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
            'sede_id' => ['required', 'exists:sedi,id'],
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:classe,laboratorio,palestra,aula_musica,aula_sostegno,aula_alternativa'],
            'capienza' => ['required', 'integer', 'min:1'],
        ];
    }
}
