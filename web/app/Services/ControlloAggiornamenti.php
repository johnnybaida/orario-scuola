<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Confronta la versione installata (file VERSION) con il file VERSION del ramo principale del repository GitHub:
 * se online il numero è più alto, c'è una versione nuova (non servono tag né release).
 * L'unica chiamata verso l'esterno dell'applicazione: sola lettura, senza dati dell'utente, in cache (1 ora; 15 minuti
 * se fallisce, per non rallentare nulla quando manca la rete) e disattivabile con CONTROLLO_AGGIORNAMENTI=false.
 */
class ControlloAggiornamenti
{
    private const CHIAVE_CACHE = 'ultima_versione';

    /** @return array{versione: string, url: string}|null la versione più recente, solo se è più nuova di quella installata */
    public function disponibile(): ?array
    {
        if (! config('app.controllo_aggiornamenti')) {
            return null;
        }

        $ultima = Cache::get(self::CHIAVE_CACHE);
        if ($ultima === null) {
            $ultima = $this->interroga();
            Cache::put(self::CHIAVE_CACHE, $ultima ?? false, $ultima ? now()->addHour() : now()->addMinutes(15));
        }

        return $ultima && version_compare($ultima['versione'], config('app.versione'), '>') ? $ultima : null;
    }

    /** @return array{versione: string, url: string}|null */
    private function interroga(): ?array
    {
        $repo = config('app.repository');

        try {
            $risposta = Http::timeout(3)->get("https://raw.githubusercontent.com/{$repo}/main/VERSION");
        } catch (\Throwable) {
            return null;
        }

        $versione = $risposta->ok() ? trim($risposta->body()) : '';

        return preg_match('/^\d+\.\d+\.\d+$/', $versione) ? ['versione' => $versione, 'url' => "https://github.com/{$repo}"] : null;
    }
}
