@php($quadro = $quadro ?? null)

<div>
    <label for="nome" class="block text-sm font-medium text-gray-700">Nome quadro</label>
    <input type="text" name="nome" id="nome" value="{{ old('nome', $quadro?->nome) }}" required placeholder="Es. Tempo normale 30h" class="mt-1 block w-full">
</div>

<div>
    <h2 class="font-medium mb-2">Discipline</h2>
    <x-righe-ripetibili :righe="old('righe', $righe)" partial="quadri-orari._riga" esaurito="Tutte le discipline sono già nel quadro orario." :dati="['discipline' => $discipline]"
                        :blocca="$discipline->isEmpty() ? 'Nessuna disciplina censita: aggiungila prima nella sezione Discipline.' : null" etichetta="Aggiungi disciplina" />
    <div class="mt-4 flex items-center gap-2">
        <label for="ore_mensa" class="text-sm font-medium text-gray-700">Ore di mensa</label>
        <input type="number" name="ore_mensa" id="ore_mensa" min="0" max="20" value="{{ old('ore_mensa', $quadro?->ore_mensa ?? 0) }}" data-somma="quadro" class="w-20">
        <x-info testo="Facoltativo, per il tempo prolungato: quante ore alla settimana la classe passa in mensa (di solito una per giorno di rientro). Contano nel totale del quadro ma non sono lezioni: gli slot attivi della classe sono le ore delle discipline. Chi sorveglia la mensa si indica nella pagina Mensa." />
    </div>
    <p class="mt-3 text-sm font-medium">Totale ore settimanali (discipline + mensa): <span data-totale="quadro">{{ $quadro?->ore_totali ?? 0 }}</span></p>
</div>
