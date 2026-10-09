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
                Rule::unique('classi', 'sezione')->where(fn ($q) => $q->where('anno_corso', $this->input('anno_corso'))->where('sede_id', app(\App\Services\SedeCorrente::class)->predefinita()))->ignore($classe),
            ],
            'aula_base_id' => ['nullable', app(\App\Services\SedeCorrente::class)->esiste('aule')],
            'quadro_orario_id' => ['required', app(\App\Services\SedeCorrente::class)->esiste('quadri_orari')],
            'tempo_scuola' => ['required', 'in:normale,prolungato'],
            'n_alunni' => ['required', 'integer', 'min:0', 'max:35'],
            'piano' => ['nullable', 'integer', 'between:-3,10'],
            'rientri' => ['nullable', 'array'],
            'rientri.*' => ['integer', 'between:1,6'],
            // Sezioni della pagina di modifica (assenti alla creazione).
            'slot_ids' => ['nullable', 'array'],
            'slot_ids.*' => [app(\App\Services\SedeCorrente::class)->esiste('slot')],
            'conteggio_sostegno' => ['nullable', 'in:per_alunno,per_classe'],
            'cattedre' => ['nullable', 'array'],
            'cattedre.*.id' => ['nullable', 'integer'],
            'cattedre.*.docente_id' => ['required', app(\App\Services\SedeCorrente::class)->esiste('docenti')],
            'cattedre.*.disciplina_id' => ['required', app(\App\Services\SedeCorrente::class)->esiste('discipline')],
            'cattedre.*.ore' => ['required', 'integer', 'min:1', 'max:20'],
            'cattedre.*.compresenza' => ['nullable', 'boolean'],
            'cattedre.*.docente_clil_id' => ['nullable', app(\App\Services\SedeCorrente::class)->esiste('docenti')],
            'cattedre.*.ore_clil' => ['nullable', 'integer', 'min:0', 'max:20'],
            'fabbisogni' => ['nullable', 'array'],
            'fabbisogni.*.id' => ['nullable', 'integer'],
            'fabbisogni.*.codice_anonimo' => ['required', 'string', 'max:50', 'distinct'],
            'fabbisogni.*.ore_settimanali' => ['required', 'integer', 'min:1', 'max:40'],
            'fabbisogni.*.docente_unico' => ['nullable', 'boolean'],
            'assegnazioni' => ['nullable', 'array'],
            'assegnazioni.*.id' => ['nullable', 'integer'],
            'assegnazioni.*.docente_id' => ['required', app(\App\Services\SedeCorrente::class)->esiste('docenti'), 'distinct'],
            'assegnazioni.*.ore' => ['required', 'integer', 'min:1', 'max:40'],
        ];
    }
}
