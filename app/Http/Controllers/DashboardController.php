<?php

namespace App\Http\Controllers;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'nClassi' => Classe::query()->count(),
            'nDocenti' => Docente::query()->count(),
            'nCattedre' => Cattedra::query()->count(),
        ]);
    }
}
