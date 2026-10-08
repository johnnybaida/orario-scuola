@extends('layouts.app')

@section('titolo', 'Sostituzione')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-2">Sostituzione di {{ $sospensione->docente->nomeCompleto() }}</h1>
    <p class="mb-6 text-sm text-gray-500">{{ $sospensione->etichettaMotivo() }} · {{ $sospensione->periodo() }}
        · <a href="{{ route('docenti.edit', $sospensione->docente) }}" class="underline">Scheda del docente</a></p>

    <x-guida>
        Le cattedre del docente sospeso passano ai <strong>supplenti</strong> indicati sulla sua sospensione: le lezioni dell'orario
        restano le stesse e le eredita il supplente. Quando il titolare rientra, <strong>Riporta al titolare</strong> rimette le
        cattedre a lui. Se togli la sospensione dalla scheda, le cattedre tornano al titolare da sole.
    </x-guida>

    @if ($errors->any())
        <div class="mb-4 rounded bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
        <h2 class="font-medium mb-3">Cattedre da passare ai supplenti</h2>
        @if ($sospensione->supplenti->isEmpty())
            <p class="text-sm text-amber-700">Nessun supplente indicato: sceglilo nella sospensione, dalla <a href="{{ route('docenti.edit', $sospensione->docente) }}" class="underline">scheda del docente</a>, e salva.</p>
        @elseif ($daAssegnare->isEmpty())
            <p class="text-sm text-gray-500">Il titolare non ha cattedre da passare.</p>
        @else
            <form method="POST" action="{{ route('sostituzioni.assegna', $sospensione) }}" class="space-y-3">
                @csrf
                <table class="w-full text-sm">
                    <thead class="text-gray-500 text-left"><tr><th class="py-1">Classe</th><th>Disciplina</th><th>Ore</th><th>Supplente</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($daAssegnare as $c)
                            <tr>
                                <td class="py-2">{{ $c->classe->nomeCompleto() }}</td>
                                <td>{{ $c->disciplina->nome }}</td>
                                <td>{{ $c->ore }}</td>
                                <td>
                                    <select name="assegnazioni[{{ $c->id }}]" aria-label="Supplente per {{ $c->classe->nomeCompleto() }} {{ $c->disciplina->nome }}">
                                        @if ($sospensione->supplenti->count() > 1)<option value="">— resta al titolare —</option>@endif
                                        @foreach ($sospensione->supplenti as $s)
                                            <option value="{{ $s->id }}">{{ $s->nomeCompleto() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Passa ai supplenti</button>
            </form>
        @endif
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="font-medium mb-3">Cattedre già passate ai supplenti</h2>
        @if ($assegnate->isEmpty())
            <p class="text-sm text-gray-500">Nessuna.</p>
        @else
            <table class="w-full text-sm mb-4">
                <thead class="text-gray-500 text-left"><tr><th class="py-1">Classe</th><th>Disciplina</th><th>Ore</th><th>Supplente</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($assegnate as $c)
                        <tr><td class="py-2">{{ $c->classe->nomeCompleto() }}</td><td>{{ $c->disciplina->nome }}</td><td>{{ $c->ore }}</td><td>{{ $c->docente->nomeCompleto() }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <form method="POST" action="{{ route('sostituzioni.ripristina', $sospensione) }}">
                @csrf
                <button type="submit" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50 transition-colors cursor-pointer">Riporta al titolare</button>
            </form>
        @endif
    </div>
@endsection
