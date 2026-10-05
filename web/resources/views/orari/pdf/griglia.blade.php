<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: sans-serif; font-size: 12px; }
        h1 { text-align: center; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; text-align: center; vertical-align: middle; }
        th { background: #eee; }
        .ordine { width: 48pt; background: #f5f5f5; font-weight: bold; }
        .orario { font-weight: normal; color: #555; }
        .cella { white-space: pre-line; }
        .ricreazione td { background: #fff7e0; color: #7a5b00; }
        .sostegno { color: #047857; }
    </style>
</head>
<body>
    {{-- Un foglio A4 orizzontale per ogni elemento di $fogli (classe o docente), con il titolo centrato e tutta la settimana. --}}
    @foreach ($fogli as $foglio)
        @php
            $slotPerGiorno = $foglio['slotPerGiorno'];
            $tutti = $slotPerGiorno->flatten();
            $maxOrdine = max(1, (int) $tutti->max('ordine'));
            $nGiorni = max(1, $slotPerGiorno->count());
            $ricreazione = fn ($o) => $o < $maxOrdine && $tutti->firstWhere('ordine', $o)?->ricreazione_minuti;
            $nRicreazioni = collect(range(1, $maxOrdine))->filter($ricreazione)->count();

            // Il foglio è un A4 orizzontale (842x595pt, margini 12mm): tutta la classe/docente/aula deve starci in una sola pagina.
            // Si stima l'altezza del contenuto a scala $k (font, spazi e a capo di ogni cella) e si sceglie la scala maggiore che ci sta.
            $altezzaUtile = 595 - 2 * 34 - 14;
            $larghezzaColonna = (842 - 2 * 34 - 48) / $nGiorni;
            $righeCella = function ($ordine, float $k) use ($slotPerGiorno, $foglio, $larghezzaColonna) {
                $perRiga = max(6, (int) floor(($larghezzaColonna - 8 * $k) / (9 * $k * 0.55)));
                $max = 2; // la colonna dell'ora ha sempre due righe (numero e orario)
                foreach ($slotPerGiorno as $slotGiorno) {
                    $slot = $slotGiorno->firstWhere('ordine', $ordine);
                    if (! $slot) {
                        continue;
                    }
                    $lezioni = \Illuminate\Support\Collection::wrap($foglio['lezioni']->get($slot->id));
                    $righe = 0.5 * max(0, $lezioni->count() - 1);
                    foreach ($lezioni as $lezione) {
                        foreach (explode("\n", $foglio['colonna']($lezione)) as $riga) {
                            $righe += max(1, (int) ceil(mb_strlen($riga) / $perRiga));
                        }
                    }
                    $righe += count($foglio['sostegni'][$slot->id] ?? []);
                    $max = max($max, $righe);
                }

                return $max;
            };
            $righeNaturali = fn (float $k) => collect(range(1, $maxOrdine))->map(fn ($o) => $righeCella($o, $k) * 9 * $k * 1.25 + 8 * $k + 1);
            $totale = fn (float $k) => (18 * 1.25 + 12) * $k + (9.75 * 1.25 + 14) * $k + 2 + $righeNaturali($k)->sum() + $nRicreazioni * (8.25 * 1.25 + 6) * $k + $nRicreazioni;
            $k = 1.0;
            while ($k > 0.3 && $totale($k) > $altezzaUtile) {
                $k = round($k - 0.05, 2);
            }
            // lo spazio che avanza si distribuisce sulle ore, così la settimana riempie il foglio
            $extra = max(0, $altezzaUtile - $totale($k)) / $maxOrdine;
            $altezze = $righeNaturali($k)->map(fn ($h) => max(1, round($h + $extra - 8 * $k - 1)))->values(); // dompdf somma padding e bordo all'altezza impostata
        @endphp
        <style>
            .f{{ $loop->index }} h1 { font-size: {{ round(18 * $k, 1) }}pt; margin: 0 0 {{ round(12 * $k, 1) }}pt 0; }
            .f{{ $loop->index }} th { font-size: {{ round(9.75 * $k, 1) }}pt; padding: {{ round(7 * $k, 1) }}pt; }
            .f{{ $loop->index }} td { font-size: {{ round(9 * $k, 1) }}pt; padding: {{ round(4 * $k, 1) }}pt; }
            .f{{ $loop->index }} .ordine { font-size: {{ round(9.75 * $k, 1) }}pt; }
            .f{{ $loop->index }} .orario { font-size: {{ round(7.5 * $k, 1) }}pt; }
            .f{{ $loop->index }} .sostegno { font-size: {{ round(8.25 * $k, 1) }}pt; }
            .f{{ $loop->index }} .ricreazione td { font-size: {{ round(8.25 * $k, 1) }}pt; padding: {{ round(3 * $k, 1) }}pt; }
        </style>
        <section class="f{{ $loop->index }}" @if (! $loop->last) style="page-break-after: always" @endif>
            <h1>{{ $foglio['titolo'] }}</h1>

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
                    @for ($ordine = 1; $ordine <= $maxOrdine; $ordine++)
                        @php
                            $primoSlot = $tutti->firstWhere('ordine', $ordine);
                            $altezza = $altezze[$ordine - 1];
                        @endphp
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
                                <td colspan="{{ $slotPerGiorno->count() + 1 }}">
                                    Ricreazione {{ substr($primoSlot->fine, 0, 5) }}-{{ $primoSlot->fineRicreazione() }}
                                    ({{ $primoSlot->ricreazione_minuti }} minuti)
                                </td>
                            </tr>
                        @endif
                    @endfor
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
