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
            'ore.*.ricreazione' => ['nullable', 'integer', 'min:0', 'max:240'],
            'ore.*.nome' => ['nullable', 'string', 'max:40'],
            'ore.*.conteggio' => ['nullable', 'integer', 'min:15', 'max:240', 'multiple_of:15'],
            'ore.*.aula' => ['nullable', app(\App\Services\SedeCorrente::class)->esiste('aule')->where('tipo', \App\Enums\TipoAula::Pausa->value)],
            'pausa_prima' => ['nullable', 'array'],
            'pausa_prima.minuti' => ['nullable', 'integer', 'min:0', 'max:240'],
            'pausa_prima.nome' => ['nullable', 'string', 'max:40'],
            'pausa_prima.conteggio' => ['nullable', 'integer', 'min:15', 'max:240', 'multiple_of:15'],
            'pausa_prima.aula' => ['nullable', app(\App\Services\SedeCorrente::class)->esiste('aule')->where('tipo', \App\Enums\TipoAula::Pausa->value)],
        ];
    }

    /** Orari coerenti: ogni ora finisce dopo il suo inizio, le ore non si sovrappongono e la ricreazione (durata in minuti) finisce prima dell'ora successiva. */
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

            $minutiPrima = (int) $this->input('pausa_prima.minuti', 0);
            if ($minutiPrima > 0 && $ordini && (int) substr($ore[$ordini[0]]['inizio'], 0, 2) * 60 + (int) substr($ore[$ordini[0]]['inizio'], 3, 2) < $minutiPrima) {
                $v->errors()->add('pausa_prima.minuti', "La pausa prima della {$ordini[0]}ª ora ({$minutiPrima} minuti) comincerebbe prima di mezzanotte: accorciala o sposta in avanti l'inizio dell'ora.");
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
                    if (! $prossima) {
                        $v->errors()->add("ore.$ordine.ricreazione", "Dopo l'ultima ora ({$ordine}ª) non può esserci una ricreazione.");
                    } else {
                        $fineRicreazione = date('H:i', strtotime($ora['fine']) + (int) $ora['ricreazione'] * 60);
                        if ($prossima['inizio'] < $fineRicreazione) {
                            $v->errors()->add("ore.$ordine.ricreazione", "La ricreazione dopo la {$ordine}ª ora ({$ora['fine']}–{$fineRicreazione}, {$ora['ricreazione']} minuti) finisce dopo l'inizio della successiva ({$prossima['inizio']}): sposta l'inizio della successiva o accorcia la ricreazione.");
                        }
                    }
                }
                $precedenteFine = $ora['fine'];
            }
        });
    }
}
