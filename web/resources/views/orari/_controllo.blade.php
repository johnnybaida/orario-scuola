{{-- Controllo dell'orario: stato reale, ricalcolato a ogni apertura (sparisce da solo quando i problemi vengono risolti).
     Richiede $orario, $problemi (già filtrati per ciò che si sta guardando), $classiOrario (id => Classe) e $ambito
     («questa classe», «questo docente», «questa aula», «tutto l'orario»). Opzionale $classeCorrente: non si linka a se stessa. --}}
@php($erroriLive = collect($problemi)->where('gravita', 'errore'))
@php($nAvvisi = count($problemi) - $erroriLive->count())
<div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $problemi === [] ? 'bg-green-50 border-green-200 text-green-800' : ($erroriLive->isNotEmpty() ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800') }}">
    <div class="flex items-center justify-between gap-3 {{ $problemi === [] ? '' : 'mb-2' }}">
        <p class="font-medium">
            Controllo dell'orario
            @if ($problemi === [])
                — nessun problema per {{ $ambito }}
            @else
                — {{ $erroriLive->count() }} {{ $erroriLive->count() === 1 ? 'errore' : 'errori' }}, {{ $nAvvisi }} {{ $nAvvisi === 1 ? 'avviso' : 'avvisi' }}
            @endif
        </p>
        @unless (request()->routeIs('orari.controllo'))
            <a href="{{ route('orari.controllo', $orario) }}" class="text-xs underline whitespace-nowrap">Tutto l'orario</a>
        @endunless
    </div>
    @if ($problemi !== [])
        <ul class="space-y-1">
            @foreach (array_slice($problemi, 0, 8) as $problema)
                <li>
                    <span class="font-mono text-xs uppercase">[{{ $problema['gravita'] }}]</span> {{ $problema['testo'] }}
                    @foreach ($problema['classi'] as $altraId)
                        @if ($altraId !== ($classeCorrente ?? null) && $classiOrario->has($altraId))
                            <a href="{{ route('orari.classe', [$orario, $altraId]) }}" class="ml-1 whitespace-nowrap underline">Apri {{ $classiOrario[$altraId]->nomeCompleto() }}</a>
                        @endif
                    @endforeach
                </li>
            @endforeach
        </ul>
        @if (count($problemi) > 8)
            <p class="mt-1 text-xs">… e altri {{ count($problemi) - 8 }}: vedi «Tutto l'orario».</p>
        @endif
    @endif
</div>
