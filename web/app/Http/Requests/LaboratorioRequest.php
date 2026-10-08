<?php

namespace App\Http\Requests;

use App\Models\Slot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaboratorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'aula_id' => ['nullable', 'exists:aule,id'],
            'n_partecipanti' => ['nullable', 'integer', 'min:1', 'max:500'],
            'attivo' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
            'docenti' => ['required', 'array', 'min:1'],
            'docenti.*' => ['exists:docenti,id'],
            'classi' => ['nullable', 'array'],
            'classi.*' => ['exists:classi,id'],
            'slot_ids' => ['required', 'array', 'min:1'],
            'slot_ids.*' => [Rule::exists('slot', 'id')->where(fn ($q) => $q->where('ordine', '>', Slot::ULTIMA_ORA_MATTINA))],
        ];
    }

    public function messages(): array
    {
        return [
            'docenti.required' => 'Indica almeno un docente.',
            'slot_ids.required' => 'Indica almeno un\'ora del pomeriggio.',
            'slot_ids.*.exists' => 'I laboratori si svolgono nelle ore del pomeriggio.',
        ];
    }
}
