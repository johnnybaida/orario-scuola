<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 8px; }
        h1 { font-size: 16px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 2px; text-align: center; vertical-align: top; }
        th { background: #eee; }
        .intestazione { width: 60px; background: #f5f5f5; font-weight: bold; }
        .cella { white-space: pre-line; }
    </style>
</head>
<body>
    <h1>{{ $titolo }}</h1>

    <table>
        <thead>
            <tr>
                <th class="intestazione">Giorno / Ora</th>
                @foreach ($classi as $classe)
                    <th>{{ $classe->nomeCompleto() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($slot as $s)
                <tr>
                    <td class="intestazione">G{{ $s->giorno }} - {{ $s->ordine }}ª</td>
                    @foreach ($classi as $classe)
                        @php($gruppo = $lezioni->get($s->id.'-'.$classe->id))
                        @php($lezione = $gruppo?->first())
                        <td>
                            @if ($lezione)
                                <span class="cella">{{ $lezione->cattedra->disciplina->nome }}
{{ $lezione->cattedra->docente->cognome }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
