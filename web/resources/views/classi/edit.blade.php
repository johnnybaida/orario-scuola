@extends('layouts.app')

@section('titolo', 'Classe')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">{{ $classe->nomeCompleto() }}</h1>

    <x-guida>
        Gli "slot attivi" sono le ore della scansione settimanale che questa classe usa davvero: tutte le classi a
        tempo normale usano solo il mattino, quelle a tempo prolungato anche i pomeriggi di rientro. Il generatore
        copre esattamente questi slot (né di più, né di meno). Cattedre e sostegno si modificano qui sotto: aggiungi
        o rimuovi le righe, poi salva una volta sola.
    </x-guida>

    @php($puoCattedre = auth()->user()->can('gestisci-anagrafica'))
    @php($docentiSostegno = $docenti->where('tipo_posto', 'sostegno'))

    <form method="POST" action="{{ route('classi.update', $classe) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="sezioni_extra" value="1">
        @if ($puoCattedre)
            <input type="hidden" name="cattedre_inviate" value="1">
        @endif

        <fieldset @disabled(! auth()->user()->can('gestisci-docenti-classi')) class="min-w-0 space-y-6">
            <div class="form-colonne bg-white border border-gray-200 rounded-lg p-6">
                @include('classi._form')
            </div>

            <div class="grid lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)] gap-6 items-start">
                {{-- Gli slot attivi occupano poco: il resto della riga va alle cattedre. --}}
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="font-medium mb-3">Slot attivi (H5)</h2>
                    <p class="text-sm text-gray-500 mb-3">Ore della scansione oraria di istituto usate da questa classe. Spuntando un giorno di rientro si attivano le sue ore pomeridiane.</p>
                    @php($oreMensa = $classe->oreMensa())
                    @php($attesi = $classe->quadroOrario->ore_totali - $oreMensa)
                    <p class="mb-4 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm" data-conta-slot data-attesi="{{ $attesi }}">
                        Servono <strong>{{ $attesi }}</strong> ore di lezione
                        ({{ $classe->quadroOrario->ore_totali }}h del quadro{{ $oreMensa ? " meno {$oreMensa}h di mensa" : '' }}).
                        Ne hai spuntate <strong data-conta-slot-n>{{ $slotAttiviIds->count() }}</strong>.
                        <x-info testo="Gli slot attivi devono coincidere con le ore di lezione del quadro. Le ore di mensa del quadro contano nel totale ma non sono lezioni." />
                    </p>

                    <div class="overflow-x-auto">
                        <table class="text-xs border-collapse">
                            <thead>
                                <tr>
                                    <th class="p-1"></th>
                                    @foreach (['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'] as $i => $g)
                                        @if (isset($slotPerGiorno[$i + 1]))
                                            <th class="p-1 text-center">{{ $g }}</th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @php($maxOrdine = $slotPerGiorno->flatten()->max('ordine'))
                                @for ($ordine = 1; $ordine <= $maxOrdine; $ordine++)
                                    <tr>
                                        <td class="p-1 text-gray-500">{{ $ordine }}ª</td>
                                        @foreach ($slotPerGiorno as $giorno => $slotGiorno)
                                            @php($slot = $slotGiorno->firstWhere('ordine', $ordine))
                                            <td class="p-1 text-center">
                                                @if ($slot)
                                                    <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}" data-giorno="{{ $slot->giorno }}" @if ($slot->ordine > \App\Models\Slot::ULTIMA_ORA_MATTINA) data-pomeridiano @endif
                                                           @checked($slotAttiviIds->contains($slot->id))>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                <fieldset @disabled(! $puoCattedre) class="min-w-0 bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="font-medium mb-3">Cattedre
                        @unless ($puoCattedre)
                            <x-info testo="Solo amministratore e referente orario possono modificare le cattedre." />
                        @endunless
                    </h2>
                    <x-righe-ripetibili :righe="old('cattedre', $cattedre)" partial="classi._riga-cattedra" :blocca="$docenti->isEmpty() || $discipline->isEmpty() ? 'Servono almeno un docente e una disciplina: censiscili prima nelle rispettive sezioni.' : null" :dati="['docenti' => $docenti, 'discipline' => $discipline]" etichetta="Aggiungi cattedra" />
                    <span id="ore-quadro" hidden>{{ $classe->quadroOrario->ore_totali }}</span>
                    {{-- Le ore di mensa dichiarate dal quadro si sommano alle ore delle cattedre: 34 di discipline + 2 di mensa = 36. --}}
                    @php($oreMensaQuadro = (int) $classe->quadroOrario->ore_mensa)
                    <input type="hidden" data-somma="mensa" value="{{ $oreMensaQuadro }}">
                    <p class="mt-3 text-sm font-medium">Totale ore / quadro orario:
                        <span data-totale="cattedre+mensa" data-riferimento="#ore-quadro"></span>
                        @if ($oreMensaQuadro)
                            <span class="font-normal text-gray-500">(<span data-totale="cattedre"></span> di discipline + {{ $oreMensaQuadro }} di mensa)</span>
                        @endif
                    </p>
                </fieldset>

                <div class="bg-white border border-gray-200 rounded-lg p-6 lg:col-span-2">
                    <h2 class="font-medium mb-1">Sostegno</h2>
                <x-guida>
                    Gli alunni non sono censiti: ogni fabbisogno è identificato solo da un codice anonimo (es.
                    "1B-S1") e dalle ore settimanali di sostegno. Le ore dei docenti assegnati devono coprire la
                    somma dei fabbisogni (modalità "per alunno") o il fabbisogno più alto (modalità "per classe"):
                    il generatore programma le compresenze di conseguenza.
                </x-guida>

                    <div class="mb-4 flex items-center gap-2">
                        <label for="conteggio_sostegno" class="text-sm text-gray-700">Conteggio ore</label>
                        <select name="conteggio_sostegno" id="conteggio_sostegno">
                            <option value="" @selected(is_null($classe->conteggio_sostegno))>Default della sede ({{ \App\Models\Impostazioni::correnti()->conteggio_sostegno }})</option>
                            <option value="per_alunno" @selected($classe->conteggio_sostegno === 'per_alunno')>Per alunno</option>
                            <option value="per_classe" @selected($classe->conteggio_sostegno === 'per_classe')>Per classe</option>
                        </select>
                    </div>

                    <h3 class="text-sm font-medium text-gray-700 mb-2">Fabbisogni</h3>
                    <x-righe-ripetibili :righe="old('fabbisogni', $fabbisogni)" partial="classi._riga-fabbisogno" etichetta="Aggiungi fabbisogno" />

                    <h3 class="text-sm font-medium text-gray-700 mt-6 mb-2">Docenti di sostegno assegnati</h3>
                    <x-righe-ripetibili :righe="old('assegnazioni', $assegnazioni)" partial="classi._riga-assegnazione" :blocca="$docentiSostegno->isEmpty() ? 'Nessun docente di sostegno: in Docenti imposta Tipo posto = Sostegno.' : null" :dati="['docentiSostegno' => $docentiSostegno]" etichetta="Aggiungi docente di sostegno" />
                    <p class="mt-3 text-sm font-medium">Totale ore assegnate / richieste:
                        <span data-totale="assegnate" data-riferimento="somma:richieste"></span>
                    </p>
                </div>
            </div>
        </fieldset>

        @if (auth()->user()->can('gestisci-docenti-classi'))
            <x-barra-salvataggio :annulla="route('classi.index')" />
        @endif
    </form>
@endsection
