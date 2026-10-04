<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Confronta la versione installata (file VERSION) con l'ultimo tag "vX.Y.Z" del repository GitHub.
 * L'unica chiamata verso l'esterno dell'applicazione: sola lettura, senza dati dell'utente, in cache (6 ore; 1 ora se
 * fallisce, per non rallentare nulla quando manca la rete) e disattivabile con CONTROLLO_AGGIORNAMENTI=false.
 */
class ControlloAggiornamenti
{
    /** @return array{versione: string, url: string}|null la versione più recente, solo se è più nuova di quella installata */
    public function disponibile(): ?array
    {
        if (! config('app.controllo_aggiornamenti')) {
            return null;
        }

        $ultima = Cache::get('ultima_versione');
        if ($ultima === null) {
            $ultima = $this->interroga();
            Cache::put('ultima_versione', $ultima ?? false, $ultima ? now()->addHours(6) : now()->addHour());
        }

        return $ultima && version_compare($ultima['versione'], config('app.versione'), '>') ? $ultima : null;
    }

    /** @return array{versione: string, url: string}|null */
    private function interroga(): ?array
    {
        $repo = config('app.repository');

        try {
            $risposta = Http::timeout(3)->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get("https://api.github.com/repos/{$repo}/tags", ['per_page' => 100]);
        } catch (\Throwable) {
            return null;
        }

        if (! $risposta->ok()) {
            return null;
        }

        // I tag non sono ordinati per versione: si tiene il più alto tra quelli "vX.Y.Z".
        $versioni = collect($risposta->json())->pluck('name')
            ->filter(fn ($nome) => is_string($nome) && preg_match('/^v?\d+\.\d+\.\d+$/', $nome))
            ->map(fn ($nome) => ltrim($nome, 'v'));
        $massima = $versioni->reduce(fn ($m, $v) => $m === null || version_compare($v, $m, '>') ? $v : $m);

        return $massima ? ['versione' => $massima, 'url' => "https://github.com/{$repo}/releases/tag/v{$massima}"] : null;
    }
}
