{{-- Discipline del vincolo: una o più; la regola vale per ciascuna. $info = suggerimento accanto all'etichetta. --}}
@php($scelte = array_map('intval', \App\Constraints\DisciplineVincolo::ids($parametri)))
<div data-discipline-vincolo>
    <div class="flex items-center gap-3">
        <span class="block text-sm font-medium text-gray-700">Discipline
            @isset($info)<x-info :testo="$info" />@endisset
        </span>
        <button type="button" data-seleziona="tutte" class="text-xs underline text-gray-500 cursor-pointer">Tutte</button>
        <button type="button" data-seleziona="nessuna" class="text-xs underline text-gray-500 cursor-pointer">Nessuna</button>
    </div>
    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 max-h-40 overflow-y-auto rounded border border-gray-200 p-2">
        @foreach ($discipline as $disciplina)
            <label class="flex items-center gap-1.5 text-sm text-gray-700">
                <input type="checkbox" name="parametri[disciplina_ids][]" value="{{ $disciplina->id }}" @checked(in_array($disciplina->id, $scelte, true))> {{ $disciplina->nome }}
            </label>
        @endforeach
    </div>
</div>
