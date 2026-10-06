<?php

namespace App\Http\Controllers;

use App\Services\ListeCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvController extends Controller
{
    public function esporta(string $lista, ListeCsv $csv): StreamedResponse
    {
        $def = $this->definizione($lista);

        return response()->streamDownload(function () use ($csv, $lista, $def) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel legge gli accenti
            fputcsv($out, $def['colonne'], ';', escape: '\\');
            foreach ($csv->righe($lista) as $riga) {
                fputcsv($out, $riga, ';', escape: '\\');
            }
            fclose($out);
        }, "{$lista}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function form(string $lista): View
    {
        return view('csv.importa', ['lista' => $lista, 'def' => $this->definizione($lista, true), 'esito' => null]);
    }

    public function importa(Request $request, string $lista, ListeCsv $csv): View
    {
        $def = $this->definizione($lista, true);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        return view('csv.importa', [
            'lista' => $lista, 'def' => $def, 'esito' => $csv->importa($lista, $request->file('file')->getRealPath()),
        ]);
    }

    private function definizione(string $lista, bool $scrittura = false): array
    {
        $def = ListeCsv::LISTE[$lista] ?? abort(404);
        abort_if($scrittura && ($def['solo_export'] ?? false), 404);
        Gate::authorize($scrittura ? $def['permesso'] : 'consulta');

        return $def;
    }
}
