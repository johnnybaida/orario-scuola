@extends('layouts.app')

@section('titolo', 'Importa classi')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Importa classi da CSV</h1>

    <div class="bg-white border border-gray-200 rounded-lg p-6 max-w-lg space-y-4">
        <p class="text-sm text-gray-600">
            Colonne attese: <code>anno_corso, sezione, sede, quadro_orario, tempo_scuola, n_alunni</code>.
            <code>sede</code> e <code>quadro_orario</code> sono il nome esatto già censito; se non trovati si usa il primo disponibile.
            Gli slot attivi vengono impostati di default sulla scansione mattutina.
        </p>

        <form method="POST" action="{{ route('classi.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="file" name="file" accept=".csv,text/csv" required class="block w-full text-sm">
            <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Importa</button>
        </form>
    </div>
@endsection
