@extends('layouts.app')

@section('titolo', 'Docente')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">{{ $docente->nomeCompleto() }}</h1>

    <x-guida>
        La griglia delle indisponibilità blocca gli slot in cui il docente non può avere lezione (es. part-time,
        servizio in un'altra scuola): il generatore automatico e l'editor manuale li rispettano sempre.
    </x-guida>

    @php($puoGestire = auth()->user()->can('gestisci-docenti-classi'))
    @php($puoCattedre = auth()->user()->can('gestisci-anagrafica'))

    <form method="POST" action="{{ route('docenti.update', $docente) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="sezioni_extra" value="1">
        @if ($puoCattedre)
            <input type="hidden" name="cattedre_inviate" value="1">
            <input type="hidden" name="assistenze_inviate" value="1">
        @endif
        @if ($puoGestire)
            <input type="hidden" name="sospensioni_inviate" value="1">
        @endif

        <fieldset @disabled(! $puoGestire) class="min-w-0 space-y-6">
            <div class="form-colonne bg-white border border-gray-200 rounded-lg p-6">
                @include('docenti._form')
            </div>

            <div class="grid lg:grid-cols-2 gap-6 items-start">
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="font-medium mb-3">Indisponibilità (H6/T1)</h2>
                    <p class="text-sm text-gray-500 mb-4">Seleziona gli slot in cui il docente non può avere lezione.</p>

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
                                                    <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}"
                                                           @checked($indisponibiliIds->contains($slot->id))>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="font-medium mb-1">Sospensioni e assenze lunghe</h2>
                    <p class="mb-3 text-sm text-gray-500">Periodi in cui il docente non presta servizio (sospensione, malattia lunga, congedo). Lascia vuota la data di fine se non è nota. Indica i supplenti, poi dalla sospensione salvata passa loro le cattedre con un clic (e le riporti al titolare quando rientra).</p>
                    <x-righe-ripetibili :righe="old('sospensioni', $sospensioni)" partial="docenti._riga-sospensione" :dati="['docentiSupplenti' => $docentiSupplenti]" etichetta="Aggiungi sospensione" />
                </div>

                <fieldset @disabled(! $puoCattedre) class="min-w-0 bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="font-medium mb-1">Assistenza alle pause (mensa)
                        @unless ($puoCattedre)
                            <x-info testo="Solo amministratore e referente orario possono modificare l'assistenza." />
                        @endunless
                    </h2>
                    <p class="mb-3 text-sm text-gray-500">Giorni e pause in cui il docente sorveglia gli alunni, per esempio la mensa. Le pause si definiscono in Scansione oraria. Vale per tutti gli orari e le ore (60 minuti = 1 ora) si sommano al totale delle ore assegnate. Per la <strong>mensa</strong> usa la pagina <a href="{{ route('mensa.index') }}" class="underline">Mensa</a>, dove assegni i docenti classe per classe.</p>
                    <x-righe-ripetibili :righe="old('assistenze', $assistenze)" partial="docenti._riga-assistenza" :dati="['pause' => $pause]" :blocca="$pause->isEmpty() ? 'Nessuna pausa definita: indica la durata di una ricreazione in Scansione oraria.' : null" etichetta="Aggiungi assistenza" />
                </fieldset>

                <fieldset @disabled(! $puoCattedre) class="min-w-0 bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="font-medium mb-3">Cattedre
                        @unless ($puoCattedre)
                            <x-info testo="Solo amministratore e referente orario possono modificare le cattedre." />
                        @endunless
                    </h2>
                    <x-righe-ripetibili :righe="old('cattedre', $cattedre)" partial="docenti._riga-cattedra" :blocca="$classi->isEmpty() || $discipline->isEmpty() ? 'Servono almeno una classe e una disciplina: censiscile prima nelle rispettive sezioni.' : null" :dati="['classi' => $classi, 'discipline' => $discipline]" etichetta="Aggiungi cattedra" />
                    <p class="mt-3 text-sm font-medium">Totale ore assegnate / dovute (cattedre + sostegno + CLIL + assistenza alle pause):
                        <span data-totale="cattedre" data-riferimento="#ore_dovute"></span>
                    </p>
                </fieldset>

                @if ($sostegno->isNotEmpty() || $clil->isNotEmpty())
                    {{-- Sola lettura: il sostegno si assegna nella scheda della classe (con i fabbisogni), la compresenza CLIL nella cattedra. Le ore si sommano al totale sopra. --}}
                    <section class="min-w-0 bg-white border border-gray-200 rounded-lg p-6">
                        <h2 class="font-medium mb-1">Sostegno e compresenze CLIL
                            <x-info testo="Ore assegnate in altri modi: il sostegno si imposta nella scheda della classe (sezione Sostegno), la compresenza CLIL nella cattedra della classe. Qui sono in sola lettura e contano nel totale delle ore." />
                        </h2>
                        @if ($sostegno->isNotEmpty())
                            <h3 class="mt-3 text-sm font-medium text-gray-700">Sostegno</h3>
                            <ul class="mt-1 divide-y divide-gray-100 text-sm">
                                @foreach ($sostegno as $a)
                                    <li class="flex items-center justify-between gap-4 py-1.5">
                                        <a href="{{ route('classi.edit', $a->classe) }}" class="text-primary underline">{{ $a->classe->nomeCompleto() }}</a>
                                        <span class="text-gray-700">{{ $a->ore }} h</span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-1 text-xs text-gray-500">Totale sostegno: {{ $sostegno->sum('ore') }} h <input type="hidden" data-somma="cattedre" value="{{ $sostegno->sum('ore') }}"></p>
                        @endif
                        @if ($clil->isNotEmpty())
                            <h3 class="mt-3 text-sm font-medium text-gray-700">Compresenza CLIL</h3>
                            <ul class="mt-1 divide-y divide-gray-100 text-sm">
                                @foreach ($clil as $c)
                                    <li class="flex items-center justify-between gap-4 py-1.5">
                                        <span><a href="{{ route('classi.edit', $c->classe) }}" class="text-primary underline">{{ $c->classe->nomeCompleto() }}</a> · {{ $c->disciplina->nome }} <span class="text-gray-500">con {{ $c->docente->nomeCompleto() }}</span></span>
                                        <span class="text-gray-700">{{ $c->ore_clil }} h</span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-1 text-xs text-gray-500">Totale CLIL: {{ $clil->sum('ore_clil') }} h <input type="hidden" data-somma="cattedre" value="{{ $clil->sum('ore_clil') }}"></p>
                        @endif
                    </section>
                @endif
            </div>
        </fieldset>

        @if ($puoGestire)
            <x-barra-salvataggio :annulla="route('docenti.index')" />
        @endif
    </form>
@endsection
