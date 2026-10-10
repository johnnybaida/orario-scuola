<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 6mm 8mm 9mm 8mm; }
        /* Foglio A3 orizzontale (stampabile anche in A4 «adatta alla pagina»): misure ×1,2 rispetto al vecchio A4. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 14px; }
        h1 { font-size: 26px; text-align: center; margin: 0 0 8px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 3px; text-align: center; vertical-align: middle; line-height: 1.15; }
        th { background: #eee; font-size: 16px; padding: 5px; }
        .ordine { width: 78px; background: #f5f5f5; font-weight: bold; font-size: 16px; }
        .orario { font-weight: normal; font-size: 12px; color: #555; }
        .cella { white-space: pre-line; }
        .ricreazione td { background: #fff7e0; color: #7a5b00; font-size: 12px; padding: 2px 4px; }
        .ricreazione .orario { font-size: 11px; }
        .sostegno { color: #047857; font-size: 13px; }
    </style>
</head>
<body>
@include('orari.pdf._origine')
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
                    @php($cellaPausa = function ($ordine, $giorno) use ($foglio, $slotPerGiorno, $sorveglianti, $pauseMensa) {
                        if ($foglio['senzaDocentiInPausa'] ?? false) {
                            return [];
                        }
                        $voci = collect($sorveglianti[$ordine][$giorno] ?? []);
                        $mensa = in_array($ordine, $pauseMensa, true);
                        // Giorno di rientro: la classe ha ore dopo la pausa (solo per la mensa; le ricreazioni valgono per tutti).
                        $rientro = isset($foglio['slotAttiviIds']) && $ordine > 0
                            && $slotPerGiorno[$giorno]->contains(fn ($s) => $s->ordine > $ordine && $foglio['slotAttiviIds']->contains($s->id));
                        if (isset($foglio['classeId'])) {
                            // Foglio di una classe: i docenti che sorvegliano proprio lei (nessuna classe indicata = tutte quelle in mensa).
                            $nomi = $mensa && ! $rientro ? [] : $voci->filter(fn ($v) => empty($v['classi']) || in_array($foglio['classeId'], $v['classi'], true))->pluck('nome')->all();
                        } elseif (isset($foglio['docenteId'])) {
                            // Foglio di un docente: solo la sua sorveglianza, con le classi se indicate.
                            $mia = $voci->firstWhere('docente_id', $foglio['docenteId']);
                            $nomi = $mia ? [$mia['nome'].($mia['classiNomi'] ? ' ('.implode(', ', $mia['classiNomi']).')' : '')] : [];
                        } else {
                            $nomi = $voci->pluck('nome')->all();
                        }
                        if (! $nomi && isset($foglio['slotAttiviIds']) && $ordine > 0 && $rientro) {
                            // Metodo precedente: i docenti delle cattedre di una disciplina «senza ora» collegata a questa pausa, o a nessuna.
                            foreach ($foglio['mensa'] ?? [] as $m) {
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
                        @include('orari.pdf._riga-pausa', ['nome' => $pausaPrima->nomePausaPrima(), 'da' => $pausaPrima->inizioPausaPrima(), 'a' => substr($pausaPrima->inizio, 0, 5), 'minuti' => $pausaPrima->pausa_prima_minuti, 'aula' => $pausaPrima->pausaPrimaAula?->nomeConPiano(), 'ordinePausa' => 0])
                    @endif
                    {{-- Nel foglio di una classe si saltano le ore che la classe non usa mai (es. la 7ª ora liberata dalla mensa). --}}
                    @php($ordiniVisibili = collect(range(1, max(1, $maxOrdine)))->filter(fn ($o) => ! isset($foglio['slotAttiviIds']) || $slotPerGiorno->flatten()->contains(fn ($s) => $s->ordine === $o && $foglio['slotAttiviIds']->contains($s->id)))->values())
                    @foreach ($ordiniVisibili as $ordine)
                        @php($primoSlot = $slotPerGiorno->flatten()->firstWhere('ordine', $ordine))
                        {{-- Celle alte quanto serve perché la settimana riempia il foglio A3 orizzontale (fino a 9 ore; margini stretti, pause su una riga); dompdf rispetta l'altezza solo sulle celle. --}}
                        @php($righeExtra = collect([$foglio['laboratori'] ?? [], $foglio['senzaOra'] ?? [], $foglio['assistenze'] ?? []])->filter()->count())
                        @php($altezza = (int) floor((680 - 19 * $nRicreazioni - 24 * $righeExtra) / max(1, $ordiniVisibili->count())))
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
                                            <br><span class="sostegno">S. {{ $cognome }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        @if ($primoSlot?->ricreazione_minuti && $ordine < $maxOrdine)
                            @include('orari.pdf._riga-pausa', ['nome' => $primoSlot->nomePausa(), 'da' => substr($primoSlot->fine, 0, 5), 'a' => $primoSlot->fineRicreazione(), 'minuti' => $primoSlot->ricreazione_minuti, 'aula' => $primoSlot->ricreazioneAula?->nomeConPiano(), 'ordinePausa' => $ordine])
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
