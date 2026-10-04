<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 8mm; }
        body { font-family: sans-serif; font-size: {{ $fontPx }}px; }
        h1 { font-size: 16px; margin: 0 0 8px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: {{ (int) round($fontPx / 2.5) }}px 1px; text-align: center; vertical-align: middle; overflow: hidden; }
        th { background: #eee; }
        .classe { width: 34px; background: #f5f5f5; font-weight: bold; }
        .inizio-giorno { border-left: 2px solid #333; }
        .materia { font-weight: bold; }
        .sostegno { color: #047857; }
        .legenda { margin-top: 8px; font-size: {{ max(7, $fontPx - 1) }}px; color: #444; }
    </style>
</head>
<body>
    <h1>{{ $titolo }}</h1>

    <table>
        <thead>
            <tr>
                <th class="classe" rowspan="2">Classe</th>
                @foreach ($giorni as $giorno)
                    <th class="inizio-giorno" colspan="{{ count($ore) }}">{{ \App\Models\Slot::GIORNI[$giorno] ?? "Giorno {$giorno}" }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($giorni as $giorno)
                    @foreach ($ore as $ora)
                        <th @class(['inizio-giorno' => $loop->first])>{{ $ora }}ª</th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($classi as $classe)
                <tr>
                    <td class="classe">{{ $classe->nomeCompleto() }}</td>
                    @foreach ($giorni as $giorno)
                        @foreach ($ore as $ora)
                            @php($s = $slot->get($giorno.'-'.$ora))
                            @php($lezione = $s ? $lezioni->get($s->id.'-'.$classe->id)?->first() : null)
                            @php($supporti = $s ? ($sostegni->get($s->id.'-'.$classe->id)?->unique('docente_id') ?? collect()) : collect())
                            <td @class(['inizio-giorno' => $loop->first])>
                                @if ($lezione)
                                    <div class="materia">{{ \Illuminate\Support\Str::limit($lezione->cattedra->disciplina->codice, $limite, '…') }}</div>
                                    <div>{{ \Illuminate\Support\Str::limit($lezione->cattedra->docente->cognome, $limite, '…') }}</div>
                                @endif
                                @foreach ($supporti as $supporto)
                                    <div class="sostegno">S {{ \Illuminate\Support\Str::limit($supporto->docente->cognome, max($limite - 2, 3), '…') }}</div>
                                @endforeach
                            </td>
                        @endforeach
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Orari delle ore e ricreazioni (scansione oraria di istituto) --}}
    <p class="legenda">
        <strong>Orari:</strong>
        @foreach ($legendaOre as $ora)
            {{ $ora['ordine'] }}ª {{ $ora['inizio'] }}-{{ $ora['fine'] }}
            @if ($ora['ricreazione'])
                &middot; <strong>ricreazione</strong> {{ $ora['fine'] }}-{{ $ora['ricreazione']['fine'] }} ({{ $ora['ricreazione']['minuti'] }}')
            @endif
            @unless ($loop->last) &middot; @endunless
        @endforeach
    </p>

    <p class="legenda">
        @foreach ($discipline as $disciplina)
            <strong>{{ $disciplina->codice }}</strong> {{ $disciplina->nome }}@unless ($loop->last) &middot; @endunless
        @endforeach
        &nbsp;|&nbsp; <span class="sostegno"><strong>S</strong> = docente di sostegno in compresenza</span>
    </p>
</body>
</html>
