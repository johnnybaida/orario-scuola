<?php

namespace App\Http\Controllers;

use App\Models\Aula;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Orario;
use App\Services\Export\OrarioPdfExporter;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function classe(Orario $orario, Classe $classe, OrarioPdfExporter $exporter): Response
    {
        return $exporter->classe($orario, $classe)->stream("orario-{$classe->nomeCompleto()}.pdf");
    }

    public function classi(Orario $orario, OrarioPdfExporter $exporter): Response
    {
        return $exporter->classi($orario)->stream('orario-tutte-le-classi.pdf');
    }

    public function docente(Orario $orario, Docente $docente, OrarioPdfExporter $exporter): Response
    {
        return $exporter->docente($orario, $docente)->stream("orario-{$docente->nomeCompleto()}.pdf");
    }

    public function docenti(Orario $orario, OrarioPdfExporter $exporter): Response
    {
        return $exporter->docenti($orario)->stream('orario-tutti-i-docenti.pdf');
    }

    public function aula(Orario $orario, Aula $aula, OrarioPdfExporter $exporter): Response
    {
        return $exporter->aule($orario, collect([$aula]))->stream("orario-{$aula->nome}.pdf");
    }

    public function aule(Orario $orario, OrarioPdfExporter $exporter): Response
    {
        return $exporter->aule($orario)->stream('orario-tutte-le-aule.pdf');
    }

    public function generale(Orario $orario, OrarioPdfExporter $exporter): Response
    {
        return $exporter->generale($orario)->stream('orario-generale.pdf');
    }
}
