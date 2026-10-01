<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssegnazioneSostegnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'docente_id' => [
                'required', 'exists:docenti,id',
                Rule::unique('assegnazioni_sostegno', 'docente_id')->where('classe_id', $this->route('classe')->id),
            ],
            'ore' => ['required', 'integer', 'min:1', 'max:40'],
        ];
    }
}
