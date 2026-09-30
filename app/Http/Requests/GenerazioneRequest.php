<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerazioneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_limit_s' => ['required', 'integer', 'min:10', 'max:900'],
            'seed' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
