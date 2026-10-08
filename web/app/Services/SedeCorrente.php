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

    /** Regola «esiste» limitata alla sede corrente (gli id di un'altra sede non valgono). */
    public function esiste(string $tabella, string $colonna = 'id'): \Illuminate\Validation\Rules\Exists
    {
        return \Illuminate\Validation\Rule::exists($tabella, $colonna)->where(fn ($q) => $this->id !== null ? $q->where('sede_id', $this->id) : $q);
    }

    /** Regola «unico» limitata alla sede corrente. */
    public function unico(string $tabella, string $colonna): \Illuminate\Validation\Rules\Unique
    {
        return \Illuminate\Validation\Rule::unique($tabella, $colonna)->where(fn ($q) => $this->id !== null ? $q->where('sede_id', $this->id) : $q);
    }

    /** La sede impostata o, in mancanza, la prima (ne nasce una se non ce n'è nessuna): serve ai dati creati fuori da una richiesta. */
    public function predefinita(): int
    {
        return $this->id
            ?? Sede::query()->orderBy('id')->value('id')
            ?? Sede::query()->create(['nome' => 'Sede principale'])->id;
    }
}
