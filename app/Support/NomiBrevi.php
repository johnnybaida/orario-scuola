<?php

namespace App\Support;

/** Nomi abbreviati per gli spazi stretti (riquadri del tabellone), tenendo la parte che distingue un'aula dall'altra. */
class NomiBrevi
{
    /**
     * «Aula DADA · Art 1» → «Art 1»; «Aula Italiano 1» → «Italiano 1»; «Laboratorio Tecnologia» → «Tecnologia».
     * Si tengono le ultime parole che stanno nel limite, perché in una scuola i nomi delle aule iniziano spesso allo
     * stesso modo e il numero o la materia stanno in fondo. Il nome completo resta nel tooltip.
     */
    public static function aula(string $nome, int $max = 12): string
    {
        if (mb_strlen($nome) <= $max) {
            return $nome;
        }

        $parole = array_values(array_filter(preg_split('/\s+/u', $nome), fn ($p) => preg_match('/[\p{L}\p{N}]/u', $p)));
        $breve = '';
        foreach (array_reverse($parole) as $parola) {
            $prova = $breve === '' ? $parola : $parola.' '.$breve;
            if (mb_strlen($prova) > $max) {
                break;
            }
            $breve = $prova;
        }

        return $breve !== '' ? $breve : '…'.mb_substr($nome, -($max - 1));
    }
}
