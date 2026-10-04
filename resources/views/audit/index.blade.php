@extends('layouts.app')

@section('titolo', 'Registro attività')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Registro attività</h1>

    <x-guida>
        Elenco di tutte le operazioni: creazioni, modifiche, eliminazioni, generazioni, cambi di stato e modifiche
        manuali all'orario, con chi le ha fatte e quando. Il nome dell'elemento resta leggibile anche dopo la sua
        eliminazione. Il registro non si modifica né si cancella.
    </x-guida>

    @php($campo = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6 items-end">
        <div>
            <label for="entita" class="block text-sm font-medium text-gray-700">Elemento</label>
            <select name="entita" id="entita" class="{{ $campo }}">
                <option value="">Tutti</option>
                @foreach ($entita as $e)<option value="{{ $e }}" @selected(($filtri['entita'] ?? '') === $e)>{{ $e }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="azione" class="block text-sm font-medium text-gray-700">Azione</label>
            <select name="azione" id="azione" class="{{ $campo }}">
                <option value="">Tutte</option>
                @foreach (\App\Models\AuditLog::AZIONI as $k => $etichetta)<option value="{{ $k }}" @selected(($filtri['azione'] ?? '') === $k)>{{ $etichetta }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="utente" class="block text-sm font-medium text-gray-700">Utente</label>
            <select name="utente" id="utente" class="{{ $campo }}">
                <option value="">Tutti</option>
                @foreach ($utenti as $u)<option value="{{ $u->id }}" @selected((string) ($filtri['utente'] ?? '') === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="dal" class="block text-sm font-medium text-gray-700">Dal</label>
            <input type="date" name="dal" id="dal" value="{{ $filtri['dal'] ?? '' }}" class="{{ $campo }}">
        </div>
        <div>
            <label for="al" class="block text-sm font-medium text-gray-700">Al</label>
            <input type="date" name="al" id="al" value="{{ $filtri['al'] ?? '' }}" class="{{ $campo }}">
        </div>
        <div class="flex gap-3 items-center pb-1">
            <button type="submit" class="rounded bg-primary text-white px-4 py-1.5 text-sm cursor-pointer">Filtra</button>
            <a href="{{ route('audit.index') }}" class="text-sm underline text-gray-600">Azzera</a>
        </div>
        <div class="sm:col-span-3 lg:col-span-6">
            <label for="cerca" class="block text-sm font-medium text-gray-700">Nome dell'elemento</label>
            <input type="search" name="cerca" id="cerca" value="{{ $filtri['cerca'] ?? '' }}" class="{{ $campo }} sm:max-w-sm">
        </div>
    </form>

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">Quando</th>
                    <th class="px-4 py-2">Utente</th>
                    <th class="px-4 py-2">Azione</th>
                    <th class="px-4 py-2">Elemento</th>
                    <th class="px-4 py-2">Dettaglio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($voci as $voce)
                    <tr class="align-top">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $voce->creato_il->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-2">{{ $voce->user?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-2">{{ $voce->etichettaAzione() }}</td>
                        <td class="px-4 py-2"><span class="text-gray-500">{{ $voce->entita }}</span> {{ $voce->etichetta }}</td>
                        <td class="px-4 py-2 text-gray-600 break-words max-w-xl">
                            @foreach ($voce->dettaglio() as $riga)<div>{{ $riga }}</div>@endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Nessuna attività registrata.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $voci->links() }}</div>
@endsection
