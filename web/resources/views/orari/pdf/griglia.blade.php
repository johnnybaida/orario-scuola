<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm; }
        /* Foglio A3 orizzontale (stampabile anche in A4 «adatta alla pagina»): misure ×1,2 rispetto al vecchio A4. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 14px; }
        h1 { font-size: 29px; text-align: center; margin: 0 0 20px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 5px; text-align: center; vertical-align: middle; }
        th { background: #eee; font-size: 16px; padding: 9px; }
        .ordine { width: 78px; background: #f5f5f5; font-weight: bold; font-size: 16px; }
        .orario { font-weight: normal; font-size: 12px; color: #555; }
        .cella { white-space: pre-line; }
        .ricreazione td { background: #fff7e0; color: #7a5b00; font-size: 13px; padding: 4px; }
        .sostegno { color: #047857; font-size: 13px; }
    </style>
</head>
<body>
    {{-- Un foglio A3 orizzontale per ogni elemento di $fogli (classe o docente), con il titolo centrato e tutta la settimana. --}}
    @foreach ($fogli as $foglio)
        <section @if (! $loop->last) style="page-break-after: always" @endif>
            <h1>{{ $foglio['titolo'] }}</h1>

            @php($slotPerGiorno = $foglio['slotPerGiorno'])
            <table>
                <thead>
                    <tr>
                        <th class="ordine">Ora</th>
                        @foreach (['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'] as $i => $nome)
                            @if (isset($slotPerGiorno[$i + 1]))
                                <th>{{ $nome }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{-- Chi c'è nella pausa dopo l'ora $ordine (0 = prima della prima ora) in un giorno: i sorveglianti (assistenza alle pause) e, nel foglio
                         di una classe, nei giorni di rientro i docenti delle cattedre «senza ora» (mensa). --}}
                    @php($cellaPausa = function ($ordine, $giorno) use ($foglio, $slotPerGiorno, $sorveglianti) {
                        $nomi = $sorveglianti[$ordine][$giorno] ?? [];
                        if (! $nomi && isset($foglio['slotAttiviIds']) && $ordine > 0) {
                            // Giorno di rientro: nel foglio della classe i docenti della mensa (disciplina «senza ora» collegata a questa pausa, o a nessuna).
                            $rientro = $slotPerGiorno[$giorno]->contains(fn ($s) => $s->ordine > $ordine && $foglio['slotAttiviIds']->contains($s->id));
                            foreach ($rientro ? ($foglio['mensa'] ?? []) : [] as $m) {
                                if ($m['pausa'] === $ordine || $m['pausa'] === null) {
                                    $nomi[] = $m['disciplina'].': '.implode(', ', $m['docenti']);
                                }
                            }
                        }

                        return $nomi;
                    })
                    @php($maxOrdine = $slotPerGiorno->flatten()->max('ordine'))
                    {{-- Ricreazioni: ore seguite da una pausa (intervallo_dopo), con orario e durata; il loro spazio si toglie all'altezza delle ore. --}}
                    @php($nRicreazioni = collect(range(1, max(1, $maxOrdine) - 1))->filter(fn ($o) => $slotPerGiorno->flatten()->firstWhere('ordine', $o)?->ricreazione_minuti)->count())
                    {{-- Pausa prima della prima ora (es. accoglienza): una riga sopra la prima ora; finisce quando questa comincia. --}}
                    @php($primoSlotAssoluto = $slotPerGiorno->flatten()->sortBy('ordine')->first())
                    @php($pausaPrima = $primoSlotAssoluto?->pausa_prima_minuti ? $primoSlotAssoluto : null)
                    @if ($pausaPrima)
                        @php($nRicreazioni++)
                        <tr class="ricreazione">
                            <td class="ordine" style="font-size: 13px">{{ $pausaPrima->nomePausaPrima() }}<br><span class="orario">{{ $pausaPrima->inizioPausaPrima() }}-{{ substr($pausaPrima->inizio, 0, 5) }} ({{ $pausaPrima->pausa_prima_minuti }}')</span>@if ($pausaPrima->pausaPrimaAula)<br><span class="orario">{{ $pausaPrima->pausaPrimaAula->nomeConPiano() }}</span>@endif</td>
                            @foreach ($slotPerGiorno as $giorno => $slotGiorno)
                                <td>{{ implode(', ', $cellaPausa(0, $giorno)) }}</td>
                            @endforeach
                        </tr>
                    @endif
                    {{-- Nel foglio di una classe si saltano le ore che la classe non usa mai (es. la 7ª ora liberata dalla mensa). --}}
                    @php($ordiniVisibili = collect(range(1, max(1, $maxOrdine)))->filter(fn ($o) => ! isset($foglio['slotAttiviIds']) || $slotPerGiorno->flatten()->contains(fn ($s) => $s->ordine === $o && $foglio['slotAttiviIds']->contains($s->id)))->values())
                    @foreach ($ordiniVisibili as $ordine)
                        @php($primoSlot = $slotPerGiorno->flatten()->firstWhere('ordine', $ordine))
                        {{-- Celle alte quanto serve perché la settimana riempia il foglio A3 orizzontale (fino a 9 ore); dompdf rispetta l'altezza solo sulle celle. --}}
                        @php($altezza = (int) floor((590 - 26 * $nRicreazioni) / max(1, $ordiniVisibili->count())))
                        <tr>
                            <td class="ordine" style="height: {{ $altezza }}pt">{{ $ordine }}ª@if ($primoSlot)<br><span class="orario">{{ substr($primoSlot->inizio, 0, 5) }}-{{ substr($primoSlot->fine, 0, 5) }}</span>@endif</td>
                            @foreach ($slotPerGiorno as $giorno => $slotGiorno)
                                @php($slot = $slotGiorno->firstWhere('ordine', $ordine))
                                <td style="height: {{ $altezza }}pt">
                                    @if ($slot)
                                        {{-- Una cella può avere più lezioni (aula con capienza > 1): una sotto l'altra. --}}
                                        @foreach (\Illuminate\Support\Collection::wrap($foglio['lezioni']->get($slot->id)) as $lezione)
                                            @if (! $loop->first)<hr style="border: 0; border-top: 1px solid #bbb; margin: 3px 0">@endif
                                            <span class="cella">{{ $foglio['colonna']($lezione) }}</span>
                                        @endforeach
                                        @foreach ($foglio['sostegni'][$slot->id] ?? [] as $cognome)
                                            <br><span class="sostegno">S {{ $cognome }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        @if ($primoSlot?->ricreazione_minuti && $ordine < $maxOrdine)
                            <tr class="ricreazione">
                                <td class="ordine" style="font-size: 13px">{{ $primoSlot->nomePausa() }}<br><span class="orario">{{ substr($primoSlot->fine, 0, 5) }}-{{ $primoSlot->fineRicreazione() }} ({{ $primoSlot->ricreazione_minuti }}')</span>@if ($primoSlot->ricreazioneAula)<br><span class="orario">{{ $primoSlot->ricreazioneAula->nomeConPiano() }}</span>@endif</td>
                                @foreach ($slotPerGiorno as $giorno => $slotGiorno)
                                    <td>{{ implode(', ', $cellaPausa($ordine, $giorno)) }}</td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
            @if (! empty($foglio['laboratori']))
                <p style="font-size: 13px; margin-top: 6px;"><strong>Laboratori pomeridiani:</strong> {{ implode(' · ', $foglio['laboratori']) }}</p>
            @endif
            @if (! empty($foglio['senzaOra']))
                <p style="font-size: 13px; margin-top: 6px;"><strong>Mensa e attività senza ora:</strong> {{ implode(' · ', $foglio['senzaOra']) }}</p>
            @endif
            @if (! empty($foglio['assistenze']))
                <p style="font-size: 13px; margin-top: 6px;"><strong>Assistenza alle pause:</strong> {{ implode(' · ', $foglio['assistenze']) }}</p>
            @endif
        </section>
    @endforeach
</body>
</html>
