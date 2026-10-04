<?php

namespace App\Enums;

use App\Models\Disciplina;

/**
 * Tipi di aula noti, con il loro nome leggibile. `aule.tipo` e `discipline.tipo_aula_richiesto` restano stringhe
 * libere (una scuola può inventare altri tipi, es. per le aule DADA di una disciplina): questo enum dà un nome ai
 * valori standard, evita di ripetere stringhe «magiche» e sa descrivere anche quelli non previsti.
 */
enum TipoAula: string
{
    case Classe = 'classe';
    case Laboratorio = 'laboratorio';
    case Palestra = 'palestra';
    case AulaMusica = 'aula_musica';
    case AulaSostegno = 'aula_sostegno';
    case AulaAlternativa = 'aula_alternativa';
    /** DADA: l'aula delle lingue straniere diverse dall'inglese (francese, spagnolo, tedesco...). */
    case DadaSecondaLingua = 'dada_sec_ling';

    /** Prefisso dei tipi DADA (aula dedicata a una disciplina). */
    public const PREFISSO_DADA = 'dada_';

    /** Codici delle discipline che condividono l'aula DADA delle seconde lingue. */
    private const CODICI_SECONDA_LINGUA = ['FRA', 'SPA', 'TED'];

    public function etichetta(): string
    {
        return match ($this) {
            self::Classe => 'Aula della classe',
            self::Laboratorio => 'Laboratorio',
            self::Palestra => 'Palestra',
            self::AulaMusica => 'Aula di musica',
            self::AulaSostegno => 'Aula di sostegno',
            self::AulaAlternativa => 'Aula per l\'attività alternativa',
            self::DadaSecondaLingua => 'DADA · Seconda lingua',
        };
    }

    /** @return list<string> i tipi comuni (non DADA), in ordine */
    public static function comuni(): array
    {
        return array_values(array_map(fn (self $t) => $t->value, array_filter(self::cases(), fn (self $t) => ! self::eDada($t->value))));
    }

    public static function eDada(string $tipo): bool
    {
        return str_starts_with($tipo, self::PREFISSO_DADA);
    }

    /** Nome leggibile di un tipo qualunque: quello dell'enum, «DADA · Xxx» per gli altri DADA, altrimenti il valore ripulito. */
    public static function etichettaDi(string $tipo): string
    {
        if ($noto = self::tryFrom($tipo)) {
            return $noto->etichetta();
        }
        if (self::eDada($tipo)) {
            return 'DADA · '.ucfirst(str_replace('_', ' ', substr($tipo, strlen(self::PREFISSO_DADA))));
        }

        return ucfirst(str_replace('_', ' ', $tipo));
    }

    /** Il tipo DADA di una disciplina: comune a tutte le seconde lingue, altrimenti «dada_{codice}». */
    public static function dadaPer(Disciplina $disciplina): string
    {
        if (in_array(strtoupper($disciplina->codice), self::CODICI_SECONDA_LINGUA, true) || stripos($disciplina->nome, 'seconda lingua') !== false) {
            return self::DadaSecondaLingua->value;
        }

        return self::PREFISSO_DADA.\Illuminate\Support\Str::slug($disciplina->codice, '_');
    }
}
