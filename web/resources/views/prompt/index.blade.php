@extends('layouts.app')

@section('titolo', 'Prompt per l\'AI')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Prompt per l'AI</h1>

    <x-guida>
        Testo con tutti i dati della sede (scansione oraria, aule, discipline, classi e quadri orari, docenti e cattedre, sostegno,
        laboratori, vincoli) e le regole del generatore, pronto da incollare in un assistente AI perché provi a costruire lui l'orario.
        Non contiene nomi di alunni, che l'applicazione non censisce. Riguarda la sede in cui stai lavorando.
    </x-guida>

    <div class="mb-3 flex items-center gap-4">
        <button type="button" data-copia="#testo-prompt" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Copia il testo</button>
        <a href="{{ route('prompt.index', ['scarica' => 1]) }}" class="text-sm underline text-gray-600">Scarica come file</a>
        <span class="text-xs text-gray-500">{{ number_format(mb_strlen($testo), 0, ',', '.') }} caratteri · circa {{ number_format((int) (mb_strlen($testo) / 4), 0, ',', '.') }} token</span>
    </div>

    <textarea id="testo-prompt" readonly rows="30" class="w-full font-mono text-xs">{{ $testo }}</textarea>
@endsection
