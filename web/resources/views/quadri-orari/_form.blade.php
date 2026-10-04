@php($quadro = $quadro ?? null)

<div>
    <label for="nome" class="block text-sm font-medium text-gray-700">Nome quadro</label>
    <input type="text" name="nome" id="nome" value="{{ old('nome', $quadro?->nome) }}" required placeholder="Es. Tempo normale 30h" class="mt-1 block w-full">
</div>

<div>
    <h2 class="font-medium mb-2">Discipline</h2>
    <x-righe-ripetibili :righe="old('righe', $righe)" partial="quadri-orari._riga" esaurito="Tutte le discipline sono già nel quadro orario." :dati="['discipline' => $discipline]"
                        :blocca="$discipline->isEmpty() ? 'Nessuna disciplina censita: aggiungila prima nella sezione Discipline.' : null" etichetta="Aggiungi disciplina" />
    <p class="mt-3 text-sm font-medium">Totale ore settimanali: <span data-totale="quadro">{{ $quadro?->ore_totali ?? 0 }}</span></p>
</div>
