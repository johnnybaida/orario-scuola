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

    /**
     * Sostegno: i fabbisogni (alunni con il codice anonimo e le ore) sono facoltativi; se ci sono, le ore dei docenti assegnati devono
     * corrispondere a quelle richieste (conteggio «per alunno»: somma dei fabbisogni; «per classe»: il più alto). Senza fabbisogni le ore
     * dei docenti sono il bisogno. Il controllo scatta quando le assegnazioni cambiano: una classe con dati vecchi resta modificabile.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            $classe = $this->route('classe');
            if (! $classe || $v->errors()->isNotEmpty() || ! $this->boolean('sezioni_extra')) {
                return;
            }

            $nuove = collect($this->input('assegnazioni', []))->mapWithKeys(fn ($a) => [(int) $a['docente_id'] => (int) $a['ore']])->sortKeys();
            $fabbisogni = collect($this->input('fabbisogni', []))->pluck('ore_settimanali')->map(fn ($o) => (int) $o);
            if ($nuove->isEmpty() || $fabbisogni->isEmpty()) {
                return;
            }
            $attuali = $classe->assegnazioniSostegno()->pluck('ore', 'docente_id')->map(fn ($o) => (int) $o)->sortKeys();
            if ($nuove->all() === $attuali->all()) {
                return;   // assegnazioni invariate
            }

            $perClasse = ($this->input('conteggio_sostegno') ?: \App\Models\Impostazioni::correnti()->conteggio_sostegno) === 'per_classe';
            $richieste = $perClasse ? $fabbisogni->max() : $fabbisogni->sum();
            $assegnate = $nuove->sum();
            if ($assegnate !== $richieste) {
                $v->errors()->add('assegnazioni', "Le ore dei docenti di sostegno ({$assegnate}h) non corrispondono a quelle richieste dai fabbisogni ({$richieste}h, conteggio ".($perClasse ? 'per classe: il fabbisogno più alto' : 'per alunno: la somma dei fabbisogni').'): correggi le ore dei fabbisogni o quelle dei docenti, oppure togli i fabbisogni.');
            }
        });
    }
}
