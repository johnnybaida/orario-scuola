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
        <x-info testo="Per scegliere le classi imposta Ambito = Classe." />
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
        <x-info testo="Per scegliere i docenti imposta Ambito = Docente." />
    </label>
    <div class="flex flex-wrap gap-3 max-h-32 overflow-y-auto">
        @foreach ($docenti as $docente)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="ambito_ids[]" value="{{ $docente->id }}"
                       @checked(in_array($docente->id, old('ambito_ids', $vincolo?->ambito_ids ?? [])))>
                {{ $docente->nomeCompleto() }}
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
            <label class="block text-sm font-medium text-gray-700">Min ore consecutive</label>
            <input type="number" name="parametri[min_consecutive]" required min="2" max="6" value="{{ $parametri['min_consecutive'] ?? 2 }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">N. blocchi minimi</label>
            <input type="number" name="parametri[n_blocchi_min]" min="1" max="5" value="{{ $parametri['n_blocchi_min'] ?? 1 }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
    </div>
</div>

<div data-parametri-per="D12_BLOCCO_MAX_CONSECUTIVO">
    @include('vincoli._discipline', ['info' => "Il limite vale per ciascuna disciplina scelta. Con Ambito = Docente puoi non sceglierne nessuna: conta sulle lezioni del docente, in qualunque classe e materia (è il «massimo di ore consecutive» di un docente). Con gli altri ambiti serve almeno una disciplina."])
    <label class="block text-sm font-medium text-gray-700 mt-2">Max ore consecutive
        <x-info testo="Quante ore di fila, nello stesso giorno, al massimo (le pause non interrompono la fila). 1 = mai due ore di seguito." />
    </label>
    <input type="number" name="parametri[max_consecutive]" required min="1" max="8" value="{{ $parametri['max_consecutive'] ?? 2 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="S5_DISTRIBUZIONE_SOSTEGNO">
    <p class="text-sm text-gray-500">Vale per i docenti di sostegno assegnati alle classi. Compila uno o entrambi i limiti.</p>
    <label class="block text-sm font-medium text-gray-700 mt-2">Max docenti di sostegno insieme
        <x-info testo="Quanti docenti di sostegno al massimo possono essere presenti nella stessa classe nella stessa ora (di solito 1). Vuoto = nessun limite." />
    </label>
    <input type="number" name="parametri[max_insieme]" min="1" max="6" value="{{ $parametri['max_insieme'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <label class="block text-sm font-medium text-gray-700 mt-2">Tolleranza giornaliera (ore)
        <x-info testo="Distribuisce le ore di sostegno della classe nella settimana: ogni giorno può averne al massimo la media (ore totali / giorni in cui un docente può esserci) più questa tolleranza. Le indisponibilità dei docenti sono già escluse. 0 = distribuzione il più uniforme possibile. Vuoto = nessun limite." />
    </label>
    <input type="number" name="parametri[tolleranza_giorno]" min="0" max="6" value="{{ $parametri['tolleranza_giorno'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="D3_MAX_ORE_GIORNO">
    @include('vincoli._discipline', ['info' => 'Il limite vale per ciascuna disciplina scelta e per ciascuna classe: non limita le ore di un docente su più classi (per quello usa «Ore minime/massime al giorno (T4)» o «Blocco consecutivo massimo (D12)» con Ambito Docente).'])
    <label class="block text-sm font-medium text-gray-700 mt-2">Max ore/giorno</label>
    <input type="number" name="parametri[max]" required min="1" max="6" value="{{ $parametri['max'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="D6_FASCIA_ORARIA">
    @include('vincoli._discipline', ['info' => 'La fascia vale per ciascuna disciplina scelta.'])
    <label class="block text-sm font-medium text-gray-700 mt-2">Tipo fascia</label>
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
        <option value="non_segue" @selected(($parametri['modo'] ?? null) === 'non_segue')>NON deve essere seguita da</option>
    </select>
    @php($opzioniClil = ['tutte' => 'Tutte le lezioni', 'con' => 'Solo le lezioni con CLIL', 'senza' => 'Solo le lezioni senza CLIL'])
    @include('vincoli._discipline', ['titolo' => 'Disciplina di partenza', 'info' => 'La lezione di queste discipline è quella «prima». Se ne scegli più d\'una, vale per ciascuna.'])
    <label class="block text-sm font-medium text-gray-700 mt-2">Lezioni della disciplina di partenza
        <x-info testo="Per distinguere, per esempio, la Geografia con il docente CLIL da quella senza: scegli «con CLIL» o «senza CLIL»." />
    </label>
    <select name="parametri[clil_prima]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($opzioniClil as $valore => $etichetta)
            <option value="{{ $valore }}" @selected(($parametri['clil_prima'] ?? 'tutte') === $valore)>{{ $etichetta }}</option>
        @endforeach
    </select>
    @include('vincoli._discipline', ['campo' => 'seguite_ids', 'titolo' => 'Disciplina che segue', 'info' => 'L\'ora subito dopo (nello stesso giorno; le pause non interrompono) deve essere (o non deve essere) di una di queste.'])
    <label class="block text-sm font-medium text-gray-700 mt-2">Lezioni della disciplina che segue
        <x-info testo="Per distinguere, per esempio, la Geografia con il docente CLIL da quella senza: scegli «con CLIL» o «senza CLIL»." />
    </label>
    <select name="parametri[clil_dopo]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($opzioniClil as $valore => $etichetta)
            <option value="{{ $valore }}" @selected(($parametri['clil_dopo'] ?? 'tutte') === $valore)>{{ $etichetta }}</option>
        @endforeach
    </select>
    <label class="block text-sm font-medium text-gray-700 mt-2">Almeno quante coppie nella settimana (facoltativo)
        <x-info testo="Vuoto = vale ogni volta che c'è una lezione di partenza (può essere infattibile se le ore della prima sono più di quelle della seconda: meglio allora il tipo Preferenziale). Con un numero, per ciascuna classe servono almeno tante coppie «prima + dopo» nella settimana. Solo per «Deve essere seguita da»." />
    </label>
    <input type="number" name="parametri[min_coppie]" min="1" max="30" value="{{ $parametri['min_coppie'] ?? '' }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <input type="hidden" name="parametri[inverso]" value="0">
    <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="parametri[inverso]" value="1" @checked($parametri['inverso'] ?? false)> Vale anche nell'ordine inverso
        <x-info testo="Solo per «NON deve essere seguita da»: vieta la coppia anche al contrario (per esempio né «Geografia, poi Geografia con CLIL» né «Geografia con CLIL, poi Geografia»)." />
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
        <x-info testo="Tetto giornaliero (vuoto = nessun tetto). Con il tipo Rigido la generazione diventa infattibile se le ore settimanali del docente superano tetto × giorni disponibili." />
    </label>
    <input type="number" name="parametri[max_ore]" min="1" max="9" value="{{ $parametri['max_ore'] ?? '' }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="T11_ORE_IN_FASCIA">
    <p class="text-sm text-gray-500">Ambito <strong>Docente</strong>: scegli i docenti. Ciascuno deve avere almeno questo numero di ore (lezioni o sostegno) tra gli slot selezionati.</p>
    <label class="block text-sm font-medium text-gray-700 mt-2">Ore minime negli slot
        <x-info testo="Quante ore, almeno, ogni docente scelto deve fare negli slot selezionati qui sotto (in qualunque classe e disciplina; contano anche le ore di sostegno). 1 = almeno un'ora in quella fascia." />
    </label>
    <input type="number" name="parametri[min_ore]" required min="1" max="30" value="{{ $parametri['min_ore'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    @include('vincoli._slot', ['etichettaSlot' => 'Slot in cui devono fare le ore'])
</div>

<div data-parametri-per="T2_GIORNO_LIBERO">
    <label class="block text-sm font-medium text-gray-700">N. giorni liberi richiesti</label>
    <input type="number" name="parametri[n_giorni]" required min="1" max="3" value="{{ $parametri['n_giorni'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <label class="block text-sm font-medium text-gray-700 mt-2">Giorno preferito (opzionale)</label>
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
            <label class="block text-sm font-medium text-gray-700">Max buche/giorno</label>
            <input type="number" name="parametri[max_per_giorno]" min="0" max="6" value="{{ $parametri['max_per_giorno'] ?? '' }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Max buche/settimana</label>
            <input type="number" name="parametri[max_per_settimana]" min="0" max="20" value="{{ $parametri['max_per_settimana'] ?? '' }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
    </div>
</div>

<div data-parametri-per="C5_SPOSTAMENTI_PIANO">
    <label class="block text-sm font-medium text-gray-700">Piani di differenza senza penalità
        <x-info testo="Tra due ore consecutive la classe può cambiare piano di questo numero di piani senza penalità; oltre, ogni piano in più pesa. 0 = ogni cambio di piano pesa. Serve soprattutto con la didattica DADA. I piani si indicano su aule e classi; le aule e le classi senza piano non contano." />
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
        <label for="peso" class="block text-sm font-medium text-gray-700">Peso (1-100)
            <x-info testo="Il peso serve solo ai vincoli preferenziali: imposta Severità = Preferenziale." />
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
