<?php

namespace App\Http\Requests;

use App\Models\Slot;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ScansioneOrariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ore' => ['required', 'array'],
            'ore.*.inizio' => ['required', 'date_format:H:i'],
            'ore.*.fine' => ['required', 'date_format:H:i'],
            'ore.*.ricreazione' => ['nullable', 'boolean'],
        ];
    }

    /** Orari coerenti: ogni ora finisce dopo il suo inizio, le ore non si sovrappongono e una ricreazione ha una pausa vera dopo l'ora. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ore = $this->input('ore', []);
            $ordini = Slot::query()->distinct()->orderBy('ordine')->pluck('ordine')->all();
            if (array_map('intval', array_keys($ore)) !== array_map('intval', $ordini) && $v->errors()->isEmpty()) {
                $v->errors()->add('ore', 'Mancano delle ore: ricarica la pagina e riprova.');
            }
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $precedenteFine = null;
            foreach ($ordini as $i => $ordine) {
                $ora = $ore[$ordine];
                if ($ora['fine'] <= $ora['inizio']) {
                    $v->errors()->add("ore.$ordine.fine", "La {$ordine}ª ora deve finire dopo il suo inizio.");
                }
                if ($precedenteFine !== null && $ora['inizio'] < $precedenteFine) {
                    $v->errors()->add("ore.$ordine.inizio", "La {$ordine}ª ora inizia prima della fine della precedente.");
                }
                if (! empty($ora['ricreazione'])) {
                    $prossima = $ore[$ordini[$i + 1] ?? null] ?? null;
                    if (! $prossima || $prossima['inizio'] <= $ora['fine']) {
                        $v->errors()->add("ore.$ordine.ricreazione", "Per la ricreazione dopo la {$ordine}ª ora serve una pausa tra la fine di questa ora e l'inizio della successiva.");
                    }
                }
                $precedenteFine = $ora['fine'];
            }
        });
    }
}
