<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 24px; text-align: center; margin: 0 0 16px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 4px; text-align: center; vertical-align: middle; }
        th { background: #eee; font-size: 13px; padding: 7px; }
        .ordine { width: 64px; background: #f5f5f5; font-weight: bold; font-size: 13px; }
        .orario { font-weight: normal; font-size: 9px; color: #555; }
        .cella { white-space: pre-line; }
        .sostegno { color: #047857; font-size: 11px; }
    </style>
</head>
<body>
    {{-- Un foglio A4 orizzontale per ogni elemento di $fogli (classe o docente), con il titolo centrato e tutta la settimana. --}}
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
                    @php($maxOrdine = $slotPerGiorno->flatten()->max('ordine'))
                    @for ($ordine = 1; $ordine <= $maxOrdine; $ordine++)
                        @php($primoSlot = $slotPerGiorno->flatten()->firstWhere('ordine', $ordine))
                        {{-- Celle alte quanto serve perché la settimana riempia il foglio A4 orizzontale (fino a 9 ore); dompdf rispetta l'altezza solo sulle celle. --}}
                        @php($altezza = (int) floor(400 / max(1, $maxOrdine)))
                        <tr>
                            <td class="ordine" style="height: {{ $altezza }}pt">{{ $ordine }}ª@if ($primoSlot)<br><span class="orario">{{ substr($primoSlot->inizio, 0, 5) }}-{{ substr($primoSlot->fine, 0, 5) }}</span>@endif</td>
                            @foreach ($slotPerGiorno as $giorno => $slotGiorno)
                                @php($slot = $slotGiorno->firstWhere('ordine', $ordine))
                                <td style="height: {{ $altezza }}pt">
                                    @if ($slot)
                                        @php($lezione = $foglio['lezioni']->get($slot->id))
                                        <span class="cella">{{ $lezione ? $foglio['colonna']($lezione) : '' }}</span>
                                        @foreach ($foglio['sostegni'][$slot->id] ?? [] as $cognome)
                                            <br><span class="sostegno">S {{ $cognome }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endfor
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
