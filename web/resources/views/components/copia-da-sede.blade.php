{{-- Offre di copiare un'area (discipline, quadri, ...) da un'altra sede, solo se qui è vuota e altrove no. --}}
@props(['area'])
@can('gestisci-anagrafica')
    @php($copia = app(\App\Services\CopiaDaSede::class))
    @if ($copia->vuota($area) && ($origini = $copia->origini($area))->isNotEmpty())
        <form method="POST" action="{{ route('sede.copia', $area) }}" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            @csrf
            <span>Quest'area è vuota in questa sede. Puoi copiare <strong>{{ mb_strtolower(\App\Services\CopiaDaSede::AREE[$area][0]) }}</strong> da un'altra sede:</span>
            <select name="sede_id" required class="rounded border-gray-300 text-sm" aria-label="Sede da cui copiare">
                @foreach ($origini as $origine)
                    <option value="{{ $origine['sede']->id }}">{{ $origine['sede']->nome }} ({{ $origine['elementi'] }})</option>
                @endforeach
            </select>
            <button type="submit" class="bg-primary text-white rounded px-3 py-1.5 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Copia</button>
            @if (in_array($area, ['quadri', 'vincoli'], true))
                <x-info testo="{{ $area === 'quadri' ? 'Servono le stesse discipline (stessi codici) in questa sede: copiale prima.' : 'Si copiano solo i vincoli per tutte le classi e i docenti; per disciplina e ore servono le stesse discipline e la stessa scansione. Copia prima quelle.' }}" />
            @endif
        </form>
    @endif
@endcan
