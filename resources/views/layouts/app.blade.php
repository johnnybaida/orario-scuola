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
    <div class="min-h-screen flex flex-col">
        @auth
            <header class="bg-white border-b border-gray-200">
                <nav class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-4 flex-wrap">
                        <a href="{{ route('dashboard') }}" class="font-semibold text-lg">Orario Scuola</a>
                        <a href="{{ route('sedi.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Sedi/Aule</a>
                        <a href="{{ route('discipline.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Discipline</a>
                        <a href="{{ route('quadri-orari.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Quadri orari</a>
                        <a href="{{ route('docenti.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Docenti</a>
                        <a href="{{ route('classi.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Classi</a>
                        <a href="{{ route('cattedre.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cattedre</a>
                        <a href="{{ route('vincoli.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Vincoli</a>
                        <a href="{{ route('generazioni.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Genera orario</a>
                        <a href="{{ route('orari.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Orari</a>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-gray-500">{{ auth()->user()->name }} · {{ \App\Support\Ruoli::etichetta(auth()->user()->ruolo) }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-gray-600 hover:text-gray-900 underline">Esci</button>
                        </form>
                    </div>
                </nav>
            </header>
        @endauth

        <main class="flex-1">
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
