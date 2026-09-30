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
            'docente_id' => ['required', 'exists:docenti,id'],
            'classe_id' => ['required', 'exists:classi,id'],
            'disciplina_id' => [
                'required', 'exists:discipline,id',
                Rule::unique('cattedre')->where(fn ($q) => $q->where('docente_id', $this->input('docente_id'))->where('classe_id', $this->input('classe_id')))->ignore($cattedra),
            ],
            'ore' => ['required', 'integer', 'min:1', 'max:20'],
            'compresenza' => ['nullable', 'boolean'],
        ];
    }
}
