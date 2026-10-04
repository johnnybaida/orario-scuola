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
<body class="bg-background text-foreground antialiased">
    <div class="min-h-screen flex flex-col md:flex-row">
        @auth
            @php($voci = [
                ['dashboard', 'Dashboard', 'dashboard', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>', null],
                ['sedi.index', 'Sedi', 'sedi.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/></svg>', 'consulta'],
                ['aule.index', 'Aule', 'aule.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3"/><path d="M13 20h9"/><path d="M10 12v.01"/><path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5 20V5.562a2 2 0 0 1 1.515-1.94l4-1A2 2 0 0 1 13 4.561Z"/></svg>', 'consulta'],
                ['scansione.index', 'Scansione oraria', 'scansione.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>', 'consulta'],
                ['discipline.index', 'Discipline', 'discipline.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>', 'consulta'],
                ['quadri-orari.index', 'Quadri orari', 'quadri-orari.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>', 'consulta'],
                ['docenti.index', 'Docenti', 'docenti.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', 'consulta'],
                ['classi.index', 'Classi', 'classi.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>', 'consulta'],
                ['cattedre.index', 'Cattedre', 'cattedre.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><path d="M8 12h8"/></svg>', 'consulta'],
                ['vincoli.index', 'Vincoli', 'vincoli.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3M14 2v4M8 10v4M16 18v4"/></svg>', 'consulta'],
                ['generazioni.index', 'Genera orario', 'generazioni.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>', 'consulta'],
                ['orari.index', 'Orari', 'orari.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>', 'consulta'],
                ['utenze.index', 'Utenze', 'utenze.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>', 'gestisci-utenze'],
                ['audit.index', 'Registro attività', 'audit.*', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>', 'approva-orari'],
            ])
            @php($classeVoce = fn ($attiva) => 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm whitespace-nowrap transition-colors duration-200 '.($attiva ? 'bg-white/15 text-white font-medium' : 'text-white/75 hover:bg-white/10 hover:text-white'))
            {{-- Su desktop la sidebar è alta quanto la viewport e resta ferma: le info utente e "Esci" stanno sempre in fondo. --}}
            <aside class="bg-primary text-white md:w-60 md:shrink-0 md:sticky md:top-0 md:h-screen md:self-start">
                <nav class="p-3 md:p-4 flex md:flex-col gap-1 overflow-x-auto md:h-full md:overflow-y-auto md:overflow-x-hidden" aria-label="Menu principale">
                    <a href="{{ route('dashboard') }}" class="hidden md:block font-semibold text-lg px-3 pb-4 text-white">Orario Scuola</a>
                    @php($inFondo = ['audit.index', 'utenze.index']) {{-- amministrazione: in basso, sopra l'utente --}}
                    @foreach ($voci as [$rotta, $etichetta, $pattern, $icona, $permesso])
                        @continue(in_array($rotta, $inFondo))
                        {{-- Le voci si vedono solo con il permesso giusto (null = tutti): 'consulta' per le anagrafiche, 'gestisci-utenze' per le utenze. --}}
                        @continue($permesso && ! auth()->user()->can($permesso))
                        @php($attiva = request()->routeIs(...explode('|', $pattern)))
                        <a href="{{ route($rotta) }}" @if ($attiva) aria-current="page" @endif class="{{ $classeVoce($attiva) }}">{!! $icona !!}{{ $etichetta }}</a>
                    @endforeach
                    
                    <div class="contents md:block md:mt-auto md:pt-4">
                    @foreach ($voci as [$rotta, $etichetta, $pattern, $icona, $permesso])
                        @continue(! in_array($rotta, $inFondo) || ($permesso && ! auth()->user()->can($permesso)))
                        @php($attiva = request()->routeIs(...explode('|', $pattern)))
                        <a href="{{ route($rotta) }}" @if ($attiva) aria-current="page" @endif class="{{ $classeVoce($attiva) }}">{!! $icona !!}{{ $etichetta }}</a>
                    @endforeach

                    <div class="shrink-0 ml-auto md:ml-0 flex items-center gap-3 md:block md:mt-2 md:pt-4 md:border-t md:border-white/15 text-sm px-3 md:px-0">
                        <p class="text-white/70 whitespace-nowrap"><span class="text-white font-medium">{{ auth()->user()->name }}</span><br class="hidden md:inline"> {{ \App\Support\Ruoli::etichetta(auth()->user()->ruolo) }}</p>
                        <form method="POST" action="{{ route('logout') }}" class="md:mt-2">
                            @csrf
                            <button type="submit" class="text-white/75 hover:text-white underline transition-colors duration-200 cursor-pointer">Esci</button>
                        </form>
                        {{-- Versione installata; per chi amministra compare anche l'avviso se su GitHub c'è una versione più recente. --}}
                        <p class="hidden md:block mt-3 text-xs text-white/60" @can('gestisci-utenze') data-aggiornamenti data-url="{{ route('aggiornamenti') }}" @endcan>
                            Versione {{ config('app.versione') }}
                            <a data-aggiornamento-link hidden target="_blank" rel="noopener" class="mt-1 block rounded bg-white/15 px-2 py-1 text-white underline"></a>
                        </p>
                    </div>
                    </div>
                </nav>
            </aside>
        @endauth

        <main class="flex-1 min-w-0">
            <div class="max-w-pagina mx-auto px-4 py-6">
                @auth
                    <div class="flex justify-end mb-3">
                        <button type="button" data-apri-guida aria-controls="pannello-guida" aria-expanded="false" title="Aiuto (F1)"
                                class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                            Aiuto <kbd class="hidden sm:inline rounded border border-gray-300 px-1 text-[10px] text-gray-500">F1</kbd>
                        </button>
                    </div>
                @endauth

                {{-- Messaggi del server: li mostra resources/js/toast.js come notifiche a comparsa (10 secondi). --}}
                @if (session('successo'))
                    <div data-flash="successo" hidden><span>{{ session('successo') }}</span></div>
                @endif

                @if ($errors->any())
                    <div data-flash="errore" hidden>
                        @foreach ($errors->all() as $errore)
                            <span>{{ $errore }}</span>
                        @endforeach
                    </div>
                @endif

                @yield('contenuto')
            </div>
        </main>
    </div>

    @auth
        <aside id="pannello-guida" hidden role="complementary" aria-label="Guida all'uso"
               data-url="{{ route('guida') }}" data-contesto="{{ \App\Support\Guida::sezionePer(request()->route()?->getName()) }}"
               class="fixed inset-y-0 right-0 z-40 flex w-full flex-col border-l border-gray-200 bg-white shadow-xl sm:w-[28rem]">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h2 class="font-semibold">Guida all'uso</h2>
                <button type="button" data-chiudi-guida aria-label="Chiudi la guida" class="text-2xl leading-none text-gray-500 hover:text-gray-900 transition-colors cursor-pointer">&times;</button>
            </div>
            <div class="px-4 pt-3">
                <input type="search" data-cerca-guida placeholder="Cerca nella guida…" aria-label="Cerca nella guida" autocomplete="off" class="w-full">
            </div>
            <nav data-menu-guida aria-label="Argomenti" class="flex flex-wrap gap-1.5 border-b border-gray-200 px-4 py-3"></nav>
            <div data-testo-guida class="guida-testo flex-1 overflow-y-auto px-4 py-4 text-sm">Caricamento…</div>
        </aside>
    @endauth
</body>
</html>

@endif
