<?php

namespace App\Http\Requests;

use App\Support\Ruoli;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UtenzaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $utenza = $this->route('utenza');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utenza)],
            'ruolo' => ['required', Rule::in(Ruoli::tutti())],
            'docente_id' => ['nullable', 'exists:docenti,id'],
            // In modifica la password è opzionale: vuota = invariata.
            'password' => [$utenza ? 'nullable' : 'required', 'string', 'min:8'],
        ];
    }
}
