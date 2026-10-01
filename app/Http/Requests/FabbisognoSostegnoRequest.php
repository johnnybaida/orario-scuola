<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FabbisognoSostegnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codice_anonimo' => [
                'required', 'string', 'max:50',
                Rule::unique('fabbisogni_sostegno', 'codice_anonimo')->where('classe_id', $this->route('classe')->id),
            ],
            'ore_settimanali' => ['required', 'integer', 'min:1', 'max:40'],
            'docente_unico' => ['nullable', 'boolean'],
        ];
    }
}
