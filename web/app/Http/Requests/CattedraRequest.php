<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CattedraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cattedra = $this->route('cattedra');

        return [
            'docente_id' => ['required', app(\App\Services\SedeCorrente::class)->esiste('docenti')],
            'classe_id' => ['required', app(\App\Services\SedeCorrente::class)->esiste('classi')],
            'disciplina_id' => [
                'required', app(\App\Services\SedeCorrente::class)->esiste('discipline'),
                Rule::unique('cattedre')->where(fn ($q) => $q->where('docente_id', $this->input('docente_id'))->where('classe_id', $this->input('classe_id')))->ignore($cattedra),
            ],
            'ore' => ['required', 'integer', 'min:1', 'max:20'],
            'compresenza' => ['nullable', 'boolean'],
        ];
    }
}
