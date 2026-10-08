<?php

namespace App\Services;

use App\Models\Sede;

/**
 * La sede in cui si lavora: nelle richieste web la imposta il middleware (scelta salvata in sessione), nella generazione il job.
 * Senza sede impostata (comandi, seeder) i modelli per sede non sono filtrati; chi crea dati usa comunque la sede predefinita.
 */
class SedeCorrente
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function imposta(?int $id): void
    {
        $this->id = $id;
    }

    /** La sede impostata o, in mancanza, la prima (ne nasce una se non ce n'è nessuna): serve ai dati creati fuori da una richiesta. */
    public function predefinita(): int
    {
        return $this->id
            ?? Sede::query()->orderBy('id')->value('id')
            ?? Sede::query()->create(['nome' => 'Sede principale'])->id;
    }
}
