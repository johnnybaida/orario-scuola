<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Confronta la versione installata (file VERSION) con il file VERSION del ramo principale del repository GitHub:
 * se online il numero è più alto, c'è una versione nuova (non servono tag né release).
 * L'unica chiamata verso l'esterno dell'applicazione: sola lettura, senza dati dell'utente, mai in cache (la pagina la
 * chiede solo aprendo la dashboard, quindi al login) e disattivabile con CONTROLLO_AGGIORNAMENTI=false.
 */
class ControlloAggiornamenti
{
    /** @return array{versione: string, url: string}|null la versione più recente, solo se è più nuova di quella installata */
    public function disponibile(): ?array
    {
        if (! config('app.controllo_aggiornamenti')) {
            return null;
        }

        $ultima = $this->interroga();

        return $ultima && version_compare($ultima['versione'], config('app.versione'), '>') ? $ultima : null;
    }

    /** @return array{versione: string, url: string}|null */
    private function interroga(): ?array
    {
        $repo = config('app.repository');

        try {
            // il parametro t evita la cache della CDN di GitHub
            $risposta = Http::timeout(3)->get("https://raw.githubusercontent.com/{$repo}/main/VERSION?t=".time());
        } catch (\Throwable) {
            return null;
        }

        $versione = $risposta->ok() ? trim($risposta->body()) : '';

        return preg_match('/^\d+\.\d+\.\d+$/', $versione) ? ['versione' => $versione, 'url' => "https://github.com/{$repo}"] : null;
    }
}
