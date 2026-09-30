<?php

namespace App\Http\Controllers;

use App\Http\Requests\SedeRequest;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SedeController extends Controller
{
    public function index(): View
    {
        return view('sedi.index', [
            'sedi' => Sede::query()->withCount('aule', 'classi')->orderBy('nome')->get(),
        ]);
    }

    public function create(): View
    {
        return view('sedi.create');
    }

    public function store(SedeRequest $request): RedirectResponse
    {
        Sede::query()->create($request->validated());

        return redirect()->route('sedi.index')->with('successo', 'Sede creata.');
    }

    public function edit(Sede $sede): View
    {
        return view('sedi.edit', ['sede' => $sede]);
    }

    public function update(SedeRequest $request, Sede $sede): RedirectResponse
    {
        $sede->update($request->validated());

        return redirect()->route('sedi.index')->with('successo', 'Sede aggiornata.');
    }

    public function destroy(Sede $sede): RedirectResponse
    {
        $sede->delete();

        return redirect()->route('sedi.index')->with('successo', 'Sede eliminata.');
    }
}
