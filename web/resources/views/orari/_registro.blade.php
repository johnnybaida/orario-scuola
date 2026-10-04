{{-- Registro delle modifiche (esiti dei tentativi): condiviso da griglia della classe e tabellone. Richiede $orario e $avvisi. --}}
    @if ($avvisi->isNotEmpty())
        {{-- Cronologia degli esiti: NON descrive lo stato attuale (lo fa il Controllo qui sopra). Neutra, per non sembrare un allarme. --}}
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div>
                    <p class="font-medium text-gray-800">Registro delle modifiche</p>
                    <p class="text-xs text-gray-500">Esito dei tentativi fatti su questo orario: una modifica <strong>rifiutata</strong> non ha cambiato nulla. Lo stato attuale è nel Controllo qui sopra.</p>
                </div>
                <form method="POST" action="{{ route('orari.avvisi.azzera', $orario) }}">
                    @csrf
                    <button type="submit" class="text-xs underline text-gray-600 whitespace-nowrap">Azzera registro</button>
                </form>
            </div>
            <ul class="space-y-1">
                @foreach ($avvisi as $avviso)
                    <li class="{{ $avviso->tipo === 'errore' ? 'text-red-700' : 'text-amber-800' }}">
                        <span class="font-mono text-xs uppercase">[{{ $avviso->tipo === 'errore' ? 'modifica rifiutata' : 'avviso' }}]</span>
                        {{ $avviso->messaggio }}
                        <span class="text-gray-400 text-xs">— {{ $avviso->creato_il->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
