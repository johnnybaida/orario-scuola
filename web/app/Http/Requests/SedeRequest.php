<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SedeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'indirizzo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
