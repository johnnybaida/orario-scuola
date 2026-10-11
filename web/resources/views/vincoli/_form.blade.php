@php($vincolo = $vincolo ?? null)
@php($parametri = old('parametri', $vincolo?->parametri ?? []))

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
        <select name="tipo" id="tipo" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach ($etichette as $codice => $etichetta)
                <option value="{{ $codice }}" @selected(old('tipo', $vincolo?->tipo) === $codice)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="ambito_livello" class="block text-sm font-medium text-gray-700">Ambito</label>
        <select name="ambito_livello" id="ambito_livello" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach (['globale' => 'Globale', 'classe' => 'Classe', 'docente' => 'Docente'] as $valore => $etichetta)
                <option value="{{ $valore }}" @selected(old('ambito_livello', $vincolo?->ambito_livello) === $valore)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </div>
</div>

<div data-attiva-se="#ambito_livello=classe">
    <label class="block text-sm font-medium text-gray-700 mb-1">Classi
        <x-info testo="Si attiva con Ambito «Classe»." />
    </label>
    <div class="flex flex-wrap gap-3">
        @foreach ($classi as $classe)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="ambito_ids[]" value="{{ $classe->id }}"
                       @checked(in_array($classe->id, old('ambito_ids', $vincolo?->ambito_ids ?? [])))>
                {{ $classe->nomeCompleto() }}
            </label>
        @endforeach
    </div>
</div>

<div data-attiva-se="#ambito_livello=docente">
    <label class="block text-sm font-medium text-gray-700 mb-1">Docenti
        <x-info testo="Si attiva con Ambito «Docente»." />
    </label>
    <div class="flex flex-wrap gap-3 max-h-32 overflow-y-auto">
        @foreach ($docenti as $docente)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="ambito_ids[]" value="{{ $docente->id }}"
                       @checked(in_array($docente->id, old('ambito_ids', $vincolo?->ambito_ids ?? [])))>
                {{ $docente->nomeCompleto() }} <span class="text-gray-500">({{ \App\Services\AssistenzaPause::formatta($docente->oreAssegnate($assistenza)) }} h)</span>
            </label>
        @endforeach
    </div>
</div>

<hr class="border-gray-100">

{{-- D1_BLOCCO_MIN_CONSECUTIVO / D3_MAX_ORE_GIORNO: disciplina comune --}}
<div data-parametri-per="D1_BLOCCO_MIN_CONSECUTIVO">
    @include('vincoli._discipline', ['info' => "Il vincolo vale per ciascuna disciplina scelta. Con Ambito = Docente puoi non sceglierne nessuna: il blocco conta sulle lezioni del docente, in qualunque classe e materia. Con gli altri ambiti serve almeno una disciplina."])
    <div class="grid sm:grid-cols-2 gap-4 mt-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Ore di fila (almeno)</label>
            <input type="number" name="parametri[min_consecutive]" required min="2" max="6" value="{{ $parametri['min_consecutive'] ?? 2 }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">In quanti giorni (almeno)
                <x-info testo="In quanti giorni diversi della settimana deve esserci almeno un blocco di quelle ore di fila." />
            </label>
            <input type="number" name="parametri[n_blocchi_min]" min="1" max="5" value="{{ $parametri['n_blocchi_min'] ?? 1 }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
    </div>
</div>

<div data-parametri-per="D12_BLOCCO_MAX_CONSECUTIVO">
    @include('vincoli._discipline', ['info' => "Il limite vale per ciascuna disciplina scelta. Con Ambito = Docente puoi non sceglierne nessuna: conta sulle lezioni del docente, in qualunque classe e materia (è il «massimo di ore consecutive» di un docente). Con gli altri ambiti serve almeno una disciplina."])
    <label class="block text-sm font-medium text-gray-700 mt-2">Ore di fila (al massimo)
        <x-info testo="Quante ore di fila al massimo nello stesso giorno. Una ricreazione non interrompe la fila. 1 = mai due ore di seguito." />
    </label>
    <input type="number" name="parametri[max_consecutive]" required min="1" max="8" value="{{ $parametri['max_consecutive'] ?? 2 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="S5_DISTRIBUZIONE_SOSTEGNO">
    <p class="text-sm text-gray-500">Vale per i docenti di sostegno assegnati alle classi. Compila uno o entrambi i limiti.</p>
    <label class="block text-sm font-medium text-gray-700 mt-2">Docenti di sostegno insieme (al massimo)
        <x-info testo="Quanti docenti di sostegno al massimo possono essere presenti nella stessa classe nella stessa ora (di solito 1). Vuoto = nessun limite." />
    </label>
    <input type="number" name="parametri[max_insieme]" min="1" max="6" value="{{ $parametri['max_insieme'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <label class="block text-sm font-medium text-gray-700 mt-2">Tolleranza giornaliera (ore)
        <x-info testo="Serve a spalmare il sostegno sulla settimana. Ogni giorno la classe può avere al massimo la sua media giornaliera di ore di sostegno più questo numero. Esempio: 10 ore in 5 giorni e tolleranza 1 → al massimo 3 ore al giorno. Le indisponibilità dei docenti sono già escluse. 0 = il più uniforme possibile; vuoto = nessun limite." />
    </label>
    <input type="number" name="parametri[tolleranza_giorno]" min="0" max="6" value="{{ $parametri['tolleranza_giorno'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="D3_MAX_ORE_GIORNO">
    @include('vincoli._discipline', ['info' => 'Il limite vale per ogni classe separatamente, per ciascuna disciplina scelta. Non limita un docente che insegna in più classi: per quello usa «Ore minime/massime al giorno (T4)» o «Blocco consecutivo massimo (D12)» con Ambito «Docente».'])
    <label class="block text-sm font-medium text-gray-700 mt-2">Ore al giorno (al massimo)</label>
    <input type="number" name="parametri[max]" required min="1" max="6" value="{{ $parametri['max'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="D6_FASCIA_ORARIA">
    @include('vincoli._discipline', ['info' => 'La fascia vale per ciascuna disciplina scelta.'])
    <label class="block text-sm font-medium text-gray-700 mt-2">Queste ore sono</label>
    <select name="parametri[tipo]" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <option value="vietata" @selected(($parametri['tipo'] ?? null) === 'vietata')>Vietata</option>
        <option value="preferita" @selected(($parametri['tipo'] ?? null) === 'preferita')>Preferita</option>
    </select>
    @include('vincoli._slot')
</div>

<div data-parametri-per="D13_DISCIPLINA_SEGUITA">
    <label class="block text-sm font-medium text-gray-700">Regola</label>
    <select name="parametri[modo]" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <option value="segue" @selected(($parametri['modo'] ?? 'segue') === 'segue')>Deve essere seguita da</option>
        <option value="non_segue" @selected(($parametri['modo'] ?? null) === 'non_segue')>Non deve essere seguita da</option>
    </select>
    @php($opzioniClil = ['tutte' => 'Tutte le lezioni', 'con' => 'Solo le lezioni con CLIL', 'senza' => 'Solo le lezioni senza CLIL'])
    @include('vincoli._discipline', ['titolo' => 'Prima disciplina', 'info' => 'La lezione che viene prima. Se ne scegli più d\'una, la regola vale per ciascuna.'])
    <label class="block text-sm font-medium text-gray-700 mt-2">Quali lezioni della prima disciplina
        <x-info testo="Puoi limitare la regola alle sole ore con il docente CLIL in compresenza, o alle sole ore senza." />
    </label>
    <select name="parametri[clil_prima]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($opzioniClil as $valore => $etichetta)
            <option value="{{ $valore }}" @selected(($parametri['clil_prima'] ?? 'tutte') === $valore)>{{ $etichetta }}</option>
        @endforeach
    </select>
    @include('vincoli._discipline', ['campo' => 'seguite_ids', 'titolo' => 'Disciplina dell\'ora dopo', 'info' => 'L\'ora subito successiva, nello stesso giorno, è (o non è) di una di queste discipline. Una ricreazione in mezzo non conta.'])
    <label class="block text-sm font-medium text-gray-700 mt-2">Quali lezioni della disciplina dell'ora dopo
        <x-info testo="Puoi limitare la regola alle sole ore con il docente CLIL in compresenza, o alle sole ore senza." />
    </label>
    <select name="parametri[clil_dopo]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($opzioniClil as $valore => $etichetta)
            <option value="{{ $valore }}" @selected(($parametri['clil_dopo'] ?? 'tutte') === $valore)>{{ $etichetta }}</option>
        @endforeach
    </select>
    <label class="block text-sm font-medium text-gray-700 mt-2">Quante volte alla settimana (almeno, facoltativo)
        <x-info testo="Vuoto = vale ogni volta che c'è una lezione di partenza (è impossibile se le ore della prima sono più di quelle della seconda: meglio allora la severità Preferenziale). Con un numero, per ciascuna classe servono almeno tante coppie «prima + dopo» nella settimana. Solo per «Deve essere seguita da»." />
    </label>
    <input type="number" name="parametri[min_coppie]" min="1" max="30" value="{{ $parametri['min_coppie'] ?? '' }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <input type="hidden" name="parametri[inverso]" value="0">
    <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="parametri[inverso]" value="1" @checked($parametri['inverso'] ?? false)> Vale anche nell'ordine inverso
        <x-info testo="Solo per «Non deve essere seguita da»: vieta le due discipline di seguito in qualunque ordine." />
    </label>
</div>

<div data-parametri-per="T4_ORE_GIORNO">
    <p class="text-sm text-gray-500">Ambito <strong>Docente</strong> (quelli scelti) oppure <strong>Globale</strong> (tutti i docenti). Compila uno o entrambi i limiti. Contano lezioni e sostegno; i giorni in cui il docente non può esserci (indisponibilità) non contano.</p>
    <label class="block text-sm font-medium text-gray-700 mt-2">Ore minime al giorno
        <x-info testo="Ogni giorno in cui il docente può esserci deve avere almeno queste ore (1 = ogni docente viene tutti i giorni). Se un docente ha meno ore della settimana che giorni disponibili, con la severità Rigido il minimo si riduce da solo; con Preferenziale il generatore le distribuisce sul maggior numero di giorni possibile." />
    </label>
    <input type="number" name="parametri[min_ore]" min="1" max="9" value="{{ $parametri['min_ore'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <label class="block text-sm font-medium text-gray-700 mt-2">Ore massime al giorno
        <x-info testo="Tetto giornaliero (vuoto = nessun tetto). Con la severità Rigido la generazione è impossibile se le ore settimanali del docente superano tetto × giorni disponibili." />
    </label>
    <input type="number" name="parametri[max_ore]" min="1" max="9" value="{{ $parametri['max_ore'] ?? '' }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="T11_ORE_IN_FASCIA">
    <p class="text-sm text-gray-500">Ambito <strong>Docente</strong>: scegli i docenti. Ciascuno deve avere almeno questo numero di ore (lezioni o sostegno) tra gli slot selezionati.</p>
    <label class="block text-sm font-medium text-gray-700 mt-2">Ore nelle fasce scelte (almeno)
        <x-info testo="Quante ore almeno ogni docente scelto deve avere tra le ore spuntate qui sotto, in qualunque classe e disciplina (conta anche il sostegno). 1 = almeno un'ora in quella fascia." />
    </label>
    <input type="number" name="parametri[min_ore]" required min="1" max="30" value="{{ $parametri['min_ore'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    @include('vincoli._slot', ['etichettaSlot' => 'Ore della settimana in cui devono esserci'])
</div>

<div data-parametri-per="T2_GIORNO_LIBERO">
    <label class="block text-sm font-medium text-gray-700">Giorni liberi</label>
    <input type="number" name="parametri[n_giorni]" required min="1" max="3" value="{{ $parametri['n_giorni'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <label class="block text-sm font-medium text-gray-700 mt-2">Giorno libero preferito (facoltativo)</label>
    <select name="parametri[preferenze][]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <option value="">— Nessuna preferenza —</option>
        @foreach ([1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato'] as $g => $nome)
            <option value="{{ $g }}" @selected(($parametri['preferenze'][0] ?? null) == $g)>{{ $nome }}</option>
        @endforeach
    </select>
</div>

<div data-parametri-per="T3_MAX_ORE_BUCHE">
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Ore buche al giorno (al massimo)
                <x-info testo="Ora buca = ora vuota tra due lezioni dello stesso docente nello stesso giorno." />
            </label>
            <input type="number" name="parametri[max_per_giorno]" min="0" max="6" value="{{ $parametri['max_per_giorno'] ?? '' }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Ore buche alla settimana (al massimo)</label>
            <input type="number" name="parametri[max_per_settimana]" min="0" max="20" value="{{ $parametri['max_per_settimana'] ?? '' }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
    </div>
</div>

<div data-parametri-per="C5_SPOSTAMENTI_PIANO">
    <label class="block text-sm font-medium text-gray-700">Piani che si possono salire o scendere senza penalità
        <x-info testo="Tra due ore di fila la classe può salire o scendere questo numero di piani senza penalità; ogni piano in più è penalizzato. 0 = ogni cambio di piano è penalizzato. Serve soprattutto con la didattica DADA. I piani si indicano su aule e classi; le aule e le classi senza piano non contano." />
    </label>
    <input type="number" name="parametri[soglia]" min="0" max="10" value="{{ $parametri['soglia'] ?? 0 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<hr class="border-gray-100">

<div class="grid sm:grid-cols-3 gap-4">
    <div>
        <label for="severita" class="block text-sm font-medium text-gray-700">Severità</label>
        <select name="severita" id="severita" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            <option value="rigido" @selected(old('severita', $vincolo?->severita) === 'rigido')>Rigido</option>
            <option value="preferenziale" @selected(old('severita', $vincolo?->severita) === 'preferenziale')>Preferenziale</option>
        </select>
    </div>
    <div data-attiva-se="#severita=preferenziale" data-richiesto>
        <label for="peso" class="block text-sm font-medium text-gray-700">Peso (da 1 a 100)
            <x-info testo="Si attiva con severità «Preferenziale». Più alto è il peso, più il generatore evita di violare il vincolo." />
        </label>
        <input type="number" name="peso" id="peso" min="1" max="100" value="{{ old('peso', $vincolo?->peso) }}"
               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    </div>
    <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            {{-- Una casella non spuntata non viene inviata: il valore 0 nascosto permette di spegnere il vincolo. --}}
            <input type="hidden" name="attivo" value="0">
            <input type="checkbox" name="attivo" value="1" @checked(old('attivo', $vincolo?->attivo ?? true))> Attivo
        </label>
    </div>
</div>

<div>
    <label for="nota" class="block text-sm font-medium text-gray-700">Nota</label>
    <textarea name="nota" id="nota" rows="2" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">{{ old('nota', $vincolo?->nota) }}</textarea>
</div>
