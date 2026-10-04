<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Registra nell'audit log creazione, modifica ed eliminazione del modello (chi, quando, valori prima e dopo).
 * Non registra gli attributi riservati (password: solo "***"), i timestamp e quelli di auditEsclusi().
 * Le eliminazioni a cascata fatte dal database (es. le cattedre di un docente) non generano eventi: si
 * registra l'eliminazione dell'elemento principale.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn (Model $m) => $m->registraAudit('creazione', null, $m->datiAudit($m->getAttributes())));
        static::updated(function (Model $m) {
            $dopo = $m->datiAudit($m->getChanges());
            if ($dopo) {
                $m->registraAudit('modifica', Arr::only($m->datiAudit($m->getOriginal()), array_keys($dopo)), $dopo);
            }
        });
        static::deleted(fn (Model $m) => $m->registraAudit('eliminazione', $m->datiAudit($m->getOriginal()), null));
    }

    /** Attributi da non registrare (rumore, es. l'avanzamento di una generazione). */
    protected function auditEsclusi(): array
    {
        return [];
    }

    /** Nome leggibile dell'elemento, salvato accanto al registro. */
    public function etichettaAudit(): ?string
    {
        if (method_exists($this, 'nomeCompleto')) {
            return $this->nomeCompleto();
        }
        foreach (['nome', 'name', 'codice', 'tipo'] as $campo) {
            if (! empty($this->{$campo})) {
                return (string) $this->{$campo};
            }
        }

        return null;
    }

    private function datiAudit(array $attributi): array
    {
        $riservati = array_intersect_key($attributi, array_flip($this->getHidden()));
        $dati = Arr::except($attributi, [...$this->getHidden(), 'created_at', 'updated_at', ...$this->auditEsclusi()]);

        return $dati + array_map(fn () => '***', $riservati);
    }

    private function registraAudit(string $azione, ?array $prima, ?array $dopo): void
    {
        AuditLog::registra(class_basename($this), (int) $this->getKey(), $azione, $prima, $dopo, $this->etichettaAudit());
    }
}
