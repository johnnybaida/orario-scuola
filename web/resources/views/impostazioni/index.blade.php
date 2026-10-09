@extends('layouts.app')

@section('titolo', 'Impostazioni')

@section('contenuto')
    <div class="mb-6 flex items-center gap-4">
        <h1 class="text-xl font-semibold">Impostazioni</h1>
        <x-csv-azioni lista="impostazioni" />
    </div>

    <x-guida>
        Le impostazioni valgono per la sede in cui stai lavorando ({{ $impostazioni->sede?->nome }}). Per ora c'è una sola
        impostazione: come si contano le ore dei docenti di sostegno, valida per tutte le classi che non ne scelgono una propria.
    </x-guida>

    @php($puoModificare = auth()->user()->can('gestisci-anagrafica'))
    <form method="POST" action="{{ route('impostazioni.update') }}">
        @csrf
        @method('PUT')

        <fieldset @disabled(! $puoModificare) class="min-w-0 bg-white border border-gray-200 rounded-lg p-6 space-y-3">
            <h2 class="font-medium">Sostegno</h2>
            <div>
                <label for="conteggio_sostegno" class="block text-sm font-medium text-gray-700">Conteggio predefinito delle ore di sostegno
                    <x-info testo="Per alunno: ogni ora di un docente di sostegno vale per un solo alunno. Per classe: un docente di sostegno segue contemporaneamente più alunni della classe e l'ora vale per ciascuno. Ogni classe può scegliere un conteggio proprio nella sua scheda." />
                </label>
                <select name="conteggio_sostegno" id="conteggio_sostegno" required class="mt-1 block w-full max-w-xs rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    @foreach (\App\Models\Impostazioni::CONTEGGI as $valore => $etichetta)
                        <option value="{{ $valore }}" @selected(old('conteggio_sostegno', $impostazioni->conteggio_sostegno) === $valore)>{{ $etichetta }}</option>
                    @endforeach
                </select>
            </div>
        </fieldset>

        @if ($puoModificare)
            <x-barra-salvataggio :annulla="route('dashboard')" />
        @endif
    </form>
@endsection
