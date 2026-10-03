<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $docente = $this->route('docente');

        return [
            'nome' => ['required', 'string', 'max:255'],
            'cognome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('docenti', 'email')->ignore($docente)],
            'tipo_contratto' => ['required', 'in:tempo_indeterminato,tempo_determinato_annuale,tempo_determinato_fino_termine,supplenza_breve'],
            'tipo_posto' => ['required', 'in:comune,sostegno,potenziamento,irc,strumento'],
            'regime' => ['required', 'in:tempo_pieno,part_time_orizzontale,part_time_verticale,part_time_misto'],
            'ore_dovute' => ['required', 'integer', 'min:1', 'max:24'],
            'coe' => ['nullable', 'boolean'],
            'classi_concorso' => ['nullable', 'array'],
            'classi_concorso.*' => ['string', 'max:20'],
            'sedi' => ['nullable', 'array'],
            'sedi.*' => ['exists:sedi,id'],
        ];
    }
}
