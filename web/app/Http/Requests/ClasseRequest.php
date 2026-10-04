<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $classe = $this->route('classe');

        return [
            'anno_corso' => ['required', 'integer', 'min:1', 'max:3'],
            'sezione' => [
                'required', 'string', 'max:10',
                Rule::unique('classi', 'sezione')->where(fn ($q) => $q->where('anno_corso', $this->input('anno_corso'))->where('sede_id', $this->input('sede_id')))->ignore($classe),
            ],
            'sede_id' => ['required', 'exists:sedi,id'],
            'aula_base_id' => ['nullable', 'exists:aule,id'],
            'quadro_orario_id' => ['required', 'exists:quadri_orari,id'],
            'tempo_scuola' => ['required', 'in:normale,prolungato'],
            'n_alunni' => ['required', 'integer', 'min:0', 'max:35'],
            'rientri' => ['nullable', 'array'],
            'rientri.*' => ['integer', 'between:1,6'],
            // Sezioni della pagina di modifica (assenti alla creazione).
            'slot_ids' => ['nullable', 'array'],
            'slot_ids.*' => ['exists:slot,id'],
            'conteggio_sostegno' => ['nullable', 'in:per_alunno,per_classe'],
            'cattedre' => ['nullable', 'array'],
            'cattedre.*.id' => ['nullable', 'integer'],
            'cattedre.*.docente_id' => ['required', 'exists:docenti,id'],
            'cattedre.*.disciplina_id' => ['required', 'exists:discipline,id'],
            'cattedre.*.ore' => ['required', 'integer', 'min:1', 'max:20'],
            'cattedre.*.compresenza' => ['nullable', 'boolean'],
            'fabbisogni' => ['nullable', 'array'],
            'fabbisogni.*.id' => ['nullable', 'integer'],
            'fabbisogni.*.codice_anonimo' => ['required', 'string', 'max:50', 'distinct'],
            'fabbisogni.*.ore_settimanali' => ['required', 'integer', 'min:1', 'max:40'],
            'fabbisogni.*.docente_unico' => ['nullable', 'boolean'],
            'assegnazioni' => ['nullable', 'array'],
            'assegnazioni.*.id' => ['nullable', 'integer'],
            'assegnazioni.*.docente_id' => ['required', 'exists:docenti,id', 'distinct'],
            'assegnazioni.*.ore' => ['required', 'integer', 'min:1', 'max:40'],
        ];
    }
}
