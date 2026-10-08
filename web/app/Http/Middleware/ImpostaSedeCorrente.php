<?php

namespace App\Http\Middleware;

use App\Models\Sede;
use App\Services\SedeCorrente;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Sede in cui lavora l'utente: quella scelta in sessione, altrimenti l'ultima usata, altrimenti la prima. */
class ImpostaSedeCorrente
{
    public function handle(Request $request, Closure $next): Response
    {
        $sedi = Sede::query()->orderBy('nome')->get();
        $id = collect([$request->session()->get('sede_id'), $request->user()?->ultima_sede_id])
            ->first(fn ($candidata) => $candidata && $sedi->contains('id', $candidata)) ?? $sedi->sortBy('id')->first()?->id;

        app(SedeCorrente::class)->imposta($id);
        $request->session()->put('sede_id', $id);
        View::share('sedi', $sedi);
        View::share('sedeCorrente', $sedi->firstWhere('id', $id));

        return $next($request);
    }
}
