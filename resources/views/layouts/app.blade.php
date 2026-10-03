@if (request()->ajax())
    {{-- Richiesta della modale: solo il contenuto della pagina, senza menu né layout --}}
    @yield('contenuto')
@else
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titolo', 'Orario Scuola')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col md:flex-row">
        @auth
            @php($voci = [
                ['dashboard', 'Dashboard', 'dashboard'],
                ['sedi.index', 'Sedi/Aule', 'sedi.*|aule.*'],
                ['discipline.index', 'Discipline', 'discipline.*'],
                ['quadri-orari.index', 'Quadri orari', 'quadri-orari.*'],
                ['docenti.index', 'Docenti', 'docenti.*'],
                ['classi.index', 'Classi', 'classi.*'],
                ['cattedre.index', 'Cattedre', 'cattedre.*'],
                ['vincoli.index', 'Vincoli', 'vincoli.*'],
                ['generazioni.index', 'Genera orario', 'generazioni.*'],
                ['orari.index', 'Orari', 'orari.*'],
            ])
            <aside class="bg-white border-b md:border-b-0 md:border-r border-gray-200 md:w-56 md:shrink-0 md:min-h-screen flex flex-col">
                <nav class="p-4 flex flex-col gap-1 md:sticky md:top-0">
                    <a href="{{ route('dashboard') }}" class="font-semibold text-lg mb-2">Orario Scuola</a>
                    @foreach ($voci as [$rotta, $etichetta, $pattern])
                        <a href="{{ route($rotta) }}" @if (request()->routeIs(...explode('|', $pattern))) aria-current="page" @endif
                           class="rounded px-3 py-1.5 text-sm {{ request()->routeIs(...explode('|', $pattern)) ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">{{ $etichetta }}</a>
                    @endforeach
                    @can('gestisci-utenze')
                        <a href="{{ route('utenze.index') }}" @if (request()->routeIs('utenze.*')) aria-current="page" @endif
                           class="rounded px-3 py-1.5 text-sm {{ request()->routeIs('utenze.*') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">Utenze</a>
                    @endcan

                    <div class="mt-4 pt-4 border-t border-gray-200 text-sm">
                        <p class="text-gray-500">{{ auth()->user()->name }}<br>{{ \App\Support\Ruoli::etichetta(auth()->user()->ruolo) }}</p>
                        <form method="POST" action="{{ route('logout') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="text-gray-600 hover:text-gray-900 underline cursor-pointer">Esci</button>
                        </form>
                    </div>
                </nav>
            </aside>
        @endauth

        <main class="flex-1 min-w-0">
            <div class="max-w-6xl mx-auto px-4 py-6">
                @if (session('successo'))
                    <div class="mb-4 rounded bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
                        {{ session('successo') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $errore)
                                <li>{{ $errore }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('contenuto')
            </div>
        </main>
    </div>
</body>
</html>

@endif
