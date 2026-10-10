@extends('layouts.app')

@section('titolo', 'Sostituisci un docente')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-2">Sostituisci un docente in «{{ $orario->etichetta() }}»</h1>
    <p class="mb-4 text-sm text-gray-600">Passa le ore di un docente assente a un altro, senza rigenerare l'orario e senza toccare le cattedre. Le ore in cui il supplente non è libero restano all'assente e le sistemi una per una dal pulsante ✎ di ogni ora.</p>

    <form method="POST" action="{{ route('orari.sostituzione', $orario) }}" class="form-colonne">
        @csrf
        <div>
            <label for="assente_id" class="block text-sm font-medium text-gray-700">Docente da sostituire</label>
            <select name="assente_id" id="assente_id" required data-ricerca class="mt-1 block w-full">
                <option value="">— scegli —</option>
                @foreach ($docenti as $docente)
                    <option value="{{ $docente->id }}" @selected(old('assente_id') == $docente->id)>{{ $docente->nomeCompleto() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="supplente_id" class="block text-sm font-medium text-gray-700">Supplente</label>
            <select name="supplente_id" id="supplente_id" required data-ricerca class="mt-1 block w-full">
                <option value="">— scegli —</option>
                @foreach ($tuttiIDocenti as $docente)
                    <option value="{{ $docente->id }}" @selected(old('supplente_id') == $docente->id)>{{ $docente->nomeCompleto() }}</option>
                @endforeach
            </select>
        </div>

        <fieldset class="sm:col-span-2">
            <legend class="block text-sm font-medium text-gray-700">Cosa sostituire</legend>
            <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                <input type="hidden" name="titolare" value="0"><label class="flex items-center gap-1.5"><input type="checkbox" name="titolare" value="1" checked> Lezioni (come titolare)</label>
                <input type="hidden" name="clil" value="0"><label class="flex items-center gap-1.5"><input type="checkbox" name="clil" value="1" checked> Ore CLIL</label>
                <input type="hidden" name="sostegno" value="0"><label class="flex items-center gap-1.5"><input type="checkbox" name="sostegno" value="1" checked> Ore di sostegno</label>
            </div>
        </fieldset>

        <fieldset class="sm:col-span-2">
            <legend class="block text-sm font-medium text-gray-700">Giorni <span class="font-normal text-gray-500">(nessuno spuntato = tutta la settimana)</span></legend>
            <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                @foreach ($giorni as $giorno)
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="giorni[]" value="{{ $giorno }}"> {{ \App\Models\Slot::GIORNI[$giorno] ?? "Giorno {$giorno}" }}</label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="sm:col-span-2">
            <legend class="block text-sm font-medium text-gray-700">Dove applicarla</legend>
            <label class="mt-1 flex items-start gap-2 text-sm">
                <input type="radio" name="modo" value="copia" checked class="mt-1">
                <span><strong>Crea una copia dell'orario</strong> (consigliato): l'orario di partenza non cambia; al rientro del docente si ripubblica l'originale.</span>
            </label>
            <label class="mt-1 flex items-start gap-2 text-sm {{ $orario->modificabile() ? '' : 'text-gray-400' }}">
                <input type="radio" name="modo" value="bozza" class="mt-1" @disabled(! $orario->modificabile())>
                <span>Applica a questa bozza{{ $orario->modificabile() ? '' : ' (solo per gli orari in bozza)' }}.</span>
            </label>
        </fieldset>

        <div class="sm:col-span-2">
            <label for="nome" class="block text-sm font-medium text-gray-700">Nome della copia <span class="font-normal text-gray-500">(facoltativo)</span></label>
            <input type="text" name="nome" id="nome" maxlength="120" class="mt-1 block w-full" placeholder="es. Sostituzione Rossi dal 12/10 al 12/11">
        </div>

        @if ($errors->any())
            <p class="sm:col-span-2 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Sostituisci</button>
    </form>
@endsection
