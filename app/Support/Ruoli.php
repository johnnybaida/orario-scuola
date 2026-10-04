<?php

namespace App\Support;

class Ruoli
{
    const AMMINISTRATORE = 'amministratore';

    const DS = 'ds';

    const REFERENTE_ORARIO = 'referente_orario';

    const REFERENTE_SOSTITUZIONI = 'referente_sostituzioni';

    const SEGRETERIA = 'segreteria';

    const DOCENTE = 'docente';

    /** Chi gestisce l'intera anagrafica (sedi, aule, discipline, quadri orari, cattedre). */
    const GESTIONE_ANAGRAFICA = [self::AMMINISTRATORE, self::REFERENTE_ORARIO];

    /** Chi, in più, gestisce anche docenti e classi (la segreteria inserisce le anagrafiche di base). */
    const GESTIONE_DOCENTI_CLASSI = [self::AMMINISTRATORE, self::REFERENTE_ORARIO, self::SEGRETERIA];

    /** Chi approva, pubblica e archivia gli orari. */
    const APPROVAZIONE = [self::AMMINISTRATORE, self::DS];

    /** Chi può consultare tutto in sola lettura. */
    const CONSULTAZIONE = [
        self::AMMINISTRATORE, self::DS, self::REFERENTE_ORARIO, self::REFERENTE_SOSTITUZIONI, self::SEGRETERIA,
    ];

    public static function tutti(): array
    {
        return [self::AMMINISTRATORE, self::DS, self::REFERENTE_ORARIO, self::REFERENTE_SOSTITUZIONI, self::SEGRETERIA, self::DOCENTE];
    }

    public static function etichetta(string $ruolo): string
    {
        return match ($ruolo) {
            self::AMMINISTRATORE => 'Amministratore',
            self::DS => 'Dirigente Scolastico',
            self::REFERENTE_ORARIO => 'Referente Orario',
            self::REFERENTE_SOSTITUZIONI => 'Referente Sostituzioni',
            self::SEGRETERIA => 'Segreteria',
            self::DOCENTE => 'Docente',
            default => $ruolo,
        };
    }
}
