<?php

namespace Database\Seeders;

use App\Models\Docente;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;

/**
 * Corpo docente realistico (~40 persone) per una scuola di 15 classi con
 * quadro orario a 30h. I docenti usati nelle cattedre (vedi CattedraSeeder)
 * sono quelli nei "pool" per classe di concorso, dimensionati sulla domanda
 * oraria complessiva (ore cattedra piena = 18h). Il resto è variazione
 * anagrafica realistica (part-time, COE, contratti a termine) non utilizzata
 * nell'assegnazione automatica delle cattedre.
 */
class DocenteSeeder extends Seeder
{
    /** @var array<string, int> */
    public const FABBISOGNO_POOL = [
        'A022' => 9, // Lettere: Italiano + Storia + Geografia
        'A028' => 5, // Matematica e Scienze
        'A060' => 2, // Tecnologia
        'AB25' => 3, // Inglese
        'AC25' => 2, // Francese (seconda lingua)
        'A001' => 2, // Arte e immagine
        'A030' => 2, // Musica
        'A049' => 2, // Scienze motorie
        'IRC' => 1,  // Religione cattolica
    ];

    /** @var array<string, array<int>> nomi pool popolati, riusati da CattedraSeeder */
    public static array $pools = [];

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $usateEmail = [];

        $creaDocente = function (?string $classeConcorso, array $override = []) use ($faker, &$usateEmail): Docente {
            $nome = $faker->firstName();
            $cognome = $faker->lastName();
            $slug = \Illuminate\Support\Str::slug("{$nome}.{$cognome}", '.');
            $email = "{$slug}@scuola.test";
            $i = 1;
            while (in_array($email, $usateEmail, true)) {
                $email = "{$slug}{$i}@scuola.test";
                $i++;
            }
            $usateEmail[] = $email;

            $docente = Docente::query()->create(array_merge([
                'nome' => $nome,
                'cognome' => $cognome,
                'email' => $email,
                'tipo_contratto' => 'tempo_indeterminato',
                'tipo_posto' => 'comune',
                'regime' => 'tempo_pieno',
                'ore_dovute' => 18,
                'coe' => false,
            ], $override));

            if ($classeConcorso) {
                $docente->classiConcorso()->create(['classe_concorso' => $classeConcorso]);
            }

            return $docente;
        };

        // Pool usati per l'assegnazione automatica delle cattedre.
        foreach (self::FABBISOGNO_POOL as $classeConcorso => $numero) {
            self::$pools[$classeConcorso] = [];
            for ($i = 0; $i < $numero; $i++) {
                $override = $classeConcorso === 'IRC'
                    ? ['tipo_posto' => 'irc']
                    : [];
                self::$pools[$classeConcorso][] = $creaDocente($classeConcorso, $override)->id;
            }
        }

        // Potenziamento: ore a disposizione per progetti e sostituzioni (L. 107/2015).
        for ($i = 0; $i < 2; $i++) {
            $creaDocente('A022', ['tipo_posto' => 'potenziamento']);
        }

        // Variazione anagrafica realistica, non usata nelle cattedre automatiche.
        $creaDocente('A022', ['coe' => true, 'regime' => 'part_time_orizzontale', 'ore_dovute' => 9]);
        $creaDocente('A028', ['tipo_contratto' => 'tempo_determinato_annuale']);
        $creaDocente('AB25', ['tipo_contratto' => 'supplenza_breve', 'ore_dovute' => 6]);
        $creaDocente('A049', ['regime' => 'part_time_verticale', 'ore_dovute' => 12]);
        $creaDocente(null, ['tipo_posto' => 'sostegno']);
        $creaDocente(null, ['tipo_posto' => 'sostegno']);
        $creaDocente('A001', ['regime' => 'part_time_orizzontale', 'ore_dovute' => 9]);
        $creaDocente('A030', ['tipo_contratto' => 'tempo_determinato_fino_termine']);
        $creaDocente('A060', ['regime' => 'part_time_orizzontale', 'ore_dovute' => 9]);
        $creaDocente('AC25', ['tipo_contratto' => 'supplenza_breve', 'ore_dovute' => 6]);
    }
}
