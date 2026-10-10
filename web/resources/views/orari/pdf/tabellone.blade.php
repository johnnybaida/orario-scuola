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
        .classe { width: {{ $per === 'aula' ? 88 : 48 }}px; background: #f5f5f5; font-weight: bold; }
        .inizio-giorno { border-left: 2px solid #333; }
        .materia { font-weight: bold; }
        .sostegno { color: #047857; }
        th.pausa, td.pausa { background-color: #fff7e0; }
        .legenda { margin-top: 8px; font-size: {{ max(7, $fontPx - 1) }}px; color: #444; }
    </style>
</head>
<body>
@include('orari.pdf._origine')
    <h1>{{ $titolo }}</h1>

    {{-- Colonne di ogni giorno: le ore, più una colonna per ogni pausa in cui si svolge una disciplina «senza ora» (mensa). --}}
    @php($colonne = collect(in_array(0, $colonnePausa) ? [['pausa', 0]] : [])->merge(collect($ore)->flatMap(fn ($o) => in_array($o, $colonnePausa) ? [['ora', $o], ['pausa', $o]] : [['ora', $o]]))->values())

    <table>
        <thead>
            <tr>
                <th class="classe" rowspan="2">{{ $per === 'aula' ? 'Aula' : 'Classe' }}</th>
                @foreach ($giorni as $giorno)
                    <th class="inizio-giorno" colspan="{{ $colonne->count() }}">{{ \App\Models\Slot::GIORNI[$giorno] ?? "Giorno {$giorno}" }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($giorni as $giorno)
                    @foreach ($colonne as [$tipo, $ora])
                        @if ($tipo === 'ora')
                            <th @class(['inizio-giorno' => $loop->first])>{{ $ora }}ª</th>
                        @else
                            <th @class(['inizio-giorno' => $loop->first, 'pausa'])>{{ $nomiPausa[$ora] ?? 'Pausa' }}</th>
                        @endif
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($righe as $riga)
                <tr>
                    <td class="classe">{{ $riga['etichetta'] }}</td>
                    @foreach ($giorni as $giorno)
                        @foreach ($colonne as [$tipo, $ora])
                            @if ($tipo === 'pausa')
                                @php($mensaCella = collect($celleMensa[$riga['id'].'-'.$giorno.'-'.$ora] ?? []))
                                <td @class(['inizio-giorno' => $loop->first, 'pausa']) @if ($mensaCella->isNotEmpty()) style="{{ \App\Support\ColoriDiscipline::stile($colori[$mensaCella->first()['disciplina']->id] ?? ['#ffffff', '#000000']) }}" @endif>
                                    @foreach ($mensaCella as $m)
                                        <div class="materia">{{ \Illuminate\Support\Str::limit($m['disciplina']->codice, $limite, '…') }}</div>
                                        <div>{{ \Illuminate\Support\Str::limit(implode(', ', $m['docenti']), $limite, '…') }}</div>
                                    @endforeach
                                    @php($sorv = $celleSorveglianza[$riga['id'].'-'.$giorno.'-'.$ora] ?? [])
                                    @if ($sorv && $mensaCella->isEmpty())
                                        <div class="materia">{{ \Illuminate\Support\Str::limit($nomiPausa[$ora] ?? 'Mensa', $limite, '…') }}</div>
                                    @endif
                                    @foreach ($sorv as $cognome)
                                        <div>{{ \Illuminate\Support\Str::limit($cognome, $limite, '…') }}</div>
                                    @endforeach
                                </td>
                                @continue
                            @endif
                            @php($s = $slot->get($giorno.'-'.$ora))
                            @php($gruppo = $s ? $celle->get($s->id.'-'.$riga['id'], collect()) : collect())
                            @php($supporti = ($s && $per === 'classe') ? ($sostegni->get($s->id.'-'.$riga['id'])?->unique('docente_id') ?? collect()) : collect())
                            @php($primo = $gruppo->first())
                            <td @class(['inizio-giorno' => $loop->first]) @if ($primo) style="{{ \App\Support\ColoriDiscipline::stile($colori[$primo->cattedra->disciplina_id] ?? ['#ffffff', '#000000']) }}" @endif>
                                @foreach ($gruppo as $lezione)
                                    @if ($per === 'aula')
                                        <div class="materia">{{ \Illuminate\Support\Str::limit($lezione->cattedra->classe->nomeCompleto(), $limite, '…') }}</div>
                                        <div>{{ \Illuminate\Support\Str::limit($lezione->cattedra->disciplina->codice, $limite, '…') }}</div>
                                    @else
                                        <div class="materia">{{ \Illuminate\Support\Str::limit($lezione->cattedra->disciplina->codice, $limite, '…') }}</div>
                                        <div>{{ \Illuminate\Support\Str::limit($lezione->cattedra->docente->cognome, $limite, '…') }}</div>
                                    @endif
                                @endforeach
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
            @if ($ora['prima'])<strong>{{ $ora['prima']['nome'] }}</strong> {{ $ora['prima']['da'] }}-{{ $ora['inizio'] }} ({{ $ora['prima']['minuti'] }}'){{ $ora['prima']['aula'] ? ' · '.$ora['prima']['aula'] : '' }} &middot; @endif
            {{ $ora['ordine'] }}ª {{ $ora['inizio'] }}-{{ $ora['fine'] }}
            @if ($ora['ricreazione'])
                &middot; <strong>{{ $ora['ricreazione']['nome'] }}</strong> {{ $ora['fine'] }}-{{ $ora['ricreazione']['fine'] }} ({{ $ora['ricreazione']['minuti'] }}'){{ $ora['ricreazione']['aula'] ? ' · '.$ora['ricreazione']['aula'] : '' }}
            @endif
            @unless ($loop->last) &middot; @endunless
        @endforeach
    </p>

    <p class="legenda">
        @foreach ($discipline as $disciplina)
            <span style="{{ \App\Support\ColoriDiscipline::stile($colori[$disciplina->id] ?? ['#ffffff', '#000000']) }} padding: 0 3px;"><strong>{{ $disciplina->codice }}</strong> {{ $disciplina->nome }}</span>@unless ($loop->last) &middot; @endunless
        @endforeach
        &nbsp;|&nbsp; <span class="sostegno"><strong>S</strong> = docente di sostegno in compresenza</span>
    </p>
</body>
</html>
