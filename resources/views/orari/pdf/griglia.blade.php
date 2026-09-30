<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        h1 { font-size: 16px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px; text-align: center; vertical-align: top; }
        th { background: #eee; }
        .ordine { width: 40px; background: #f5f5f5; font-weight: bold; }
        .cella { white-space: pre-line; }
    </style>
</head>
<body>
    <h1>{{ $titolo }}</h1>

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
                <tr>
                    <td class="ordine">{{ $ordine }}ª</td>
                    @foreach ($slotPerGiorno as $giorno => $slotGiorno)
                        @php($slot = $slotGiorno->firstWhere('ordine', $ordine))
                        <td>
                            @if ($slot)
                                @php($lezione = $lezioni->get($slot->id))
                                <span class="cella">{{ $lezione ? $colonna($lezione) : '' }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endfor
        </tbody>
    </table>
</body>
</html>
