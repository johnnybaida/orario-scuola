@extends('layouts.app')

@section('titolo', 'Controllo orario')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-2">Controllo di «{{ $orario->etichetta() }}» <x-stato-orario :orario="$orario" class="align-middle ml-2" /></h1>

    <x-guida>
        Lo stato <strong>reale</strong> dell'orario, ricalcolato ogni volta che apri la pagina: docenti in due posti nello stesso
        momento, docenti indisponibili, classi con due lezioni insieme, ore fuori scansione, aule oltre la capienza, ore diverse
        da quelle previste e ore senza lezione. Diversamente dal registro degli avvisi, un problema sparisce da solo quando lo risolvi.
    </x-guida>

    @php($errori = collect($problemi)->where('gravita', 'errore'))
    @php($avvisi = collect($problemi)->where('gravita', 'avviso'))

    <p class="mb-4 text-sm {{ $errori->isEmpty() ? 'text-green-700' : 'text-red-700' }}">
        @if ($problemi === [])
            Nessun problema: l'orario è coerente.
        @else
            {{ $errori->count() }} {{ $errori->count() === 1 ? 'errore' : 'errori' }} e {{ $avvisi->count() }} {{ $avvisi->count() === 1 ? 'avviso' : 'avvisi' }}.
        @endif
    </p>

    @foreach (['errore' => [$errori, 'Errori', 'border-red-200 bg-red-50 text-red-800'], 'avviso' => [$avvisi, 'Avvisi', 'border-amber-200 bg-amber-50 text-amber-800']] as [$elenco, $titolo, $colori])
        @continue($elenco->isEmpty())
        <section class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $colori }}">
            <h2 class="mb-2 font-medium">{{ $titolo }}</h2>
            <ul class="space-y-1">
                @foreach ($elenco as $problema)
                    <li>
                        {{ $problema['testo'] }}
                        @foreach ($problema['classi'] as $classeId)
                            @if ($classi->has($classeId))
                                <a href="{{ route('orari.classe', [$orario, $classeId]) }}" class="ml-1 whitespace-nowrap underline">Apri {{ $classi[$classeId]->nomeCompleto() }}</a>
                            @endif
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <a href="{{ route('orari.index') }}" class="text-sm underline text-gray-600">Torna agli orari</a>
@endsection
