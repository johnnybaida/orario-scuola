<!DOCTYPE html>
<html lang="it">
@php($pxAula = max(7, $fontPx - 4))
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 8mm; }
        body { font-family: sans-serif; font-size: {{ $fontPx }}px; }
        h1 { font-size: 24px; margin: 0 0 10px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td div { white-space: nowrap; overflow: hidden; }
        th, td { border: 1px solid #999; padding: {{ $paddingPx }}px 2px; text-align: center; vertical-align: middle; overflow: hidden; line-height: 1.15; }
        th { background: #eee; }
        .classe { width: {{ $per === 'aula' ? 176 : 74 }}px; background: #f5f5f5; font-weight: bold; }
        .inizio-giorno { border-left: 2px solid #333; }
        .materia { font-weight: bold; }
        .sostegno { color: #047857; }
        .clil { color: #1d4ed8; }
        .aula { color: #555; }
        /* Altezza fissa delle celle (si divide il foglio tra le righe): serve a posizionare in basso il nome dell'aula, che è assoluto. */
        td { position: relative; height: {{ $altezzaCella }}px; }
        .aula-nome { position: absolute; bottom: 1px; left: 2px; font-size: {{ $pxAula }}px; color: #555; white-space: nowrap; }
        .piano { position: absolute; top: 1px; right: 2px; font-size: {{ max(7, $fontPx - 5) }}px; color: #555; }
        th.pausa, td.pausa { background-color: #fff7e0; }
        .legenda { margin-top: 8px; font-size: {{ max(7, $fontPx - 1) }}px; color: #444; }
    </style>
</head>
<body>
@include('orari.pdf._origine')
    <h1>{{ $titolo }}</h1>

    <table>
        <thead>
            <tr>
                <th class="classe" rowspan="2">{{ $per === 'aula' ? 'Aula' : 'Classe' }}</th>
                @foreach ($giorni as $giorno)
                    <th class="inizio-giorno" colspan="{{ count($colonnePerGiorno[$giorno]) }}">{{ \App\Models\Slot::GIORNI[$giorno] ?? "Giorno {$giorno}" }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($giorni as $giorno)
                    @foreach ($colonnePerGiorno[$giorno] as [$tipo, $ora])
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
                        @foreach ($colonnePerGiorno[$giorno] as [$tipo, $ora])
                            @if ($tipo === 'pausa')
                                @php($mensaCella = collect($celleMensa[$riga['id'].'-'.$giorno.'-'.$ora] ?? []))
                                <td @class(['inizio-giorno' => $loop->first, 'pausa']) @if ($mensaCella->isNotEmpty()) style="{{ \App\Support\ColoriDiscipline::stile($colori[$mensaCella->first()['disciplina']->id] ?? ['#ffffff', '#000000']) }}" @endif>
                                    @foreach ($mensaCella as $m)
                                        <div class="materia">{{ $adatta($m['disciplina']->codice, '', '', true) }}</div>
                                        <div>{{ $adatta(implode(', ', $m['docenti'])) }}</div>
                                    @endforeach
                                    @php($sorv = $celleSorveglianza[$riga['id'].'-'.$giorno.'-'.$ora] ?? [])
                                    @if ($sorv && $mensaCella->isEmpty())
                                        <div class="materia">{{ $adatta($nomiPausa[$ora] ?? 'Mensa', '', '', true) }}</div>
                                    @endif
                                    @foreach ($sorv as $cognome)
                                        <div>{{ $adatta($cognome) }}</div>
                                    @endforeach
                                </td>
                                @continue
                            @endif
                            @php($s = $slot->get($giorno.'-'.$ora))
                            @php($gruppo = $s ? $celle->get($s->id.'-'.$riga['id'], collect()) : collect())
                            @php($supporti = ($s && $per === 'classe') ? ($sostegni->get($s->id.'-'.$riga['id'])?->unique('docente_id') ?? collect()) : collect())
                            @php($primo = $gruppo->first())
                            @php($conAula = $per === 'classe' && $gruppo->contains(fn ($l) => $l->aulaDaMostrare() ?? isset($cambi[$l->id])))
                            <td @class(['inizio-giorno' => $loop->first]) @if ($primo) style="{{ \App\Support\ColoriDiscipline::stile($colori[$primo->cattedra->disciplina_id] ?? ['#ffffff', '#000000']) }}{{ $conAula ? ' padding-bottom: '.($fontPx - 1).'px;' : '' }}" @endif>
                                @foreach ($gruppo as $lezione)
                                    @if ($per === 'aula')
                                        {{-- Vista per aula: classe, materia e tutti i docenti presenti in quell'ora (titolare, CLIL, sostegno), una riga ciascuno. --}}
                                        <div class="materia">{{ $adatta($lezione->cattedra->classe->nomeCompleto(), '', '', true) }}</div>
                                        <div>{{ $adatta($lezione->cattedra->disciplina->nome) }}</div>
                                    @else
                                        <div class="materia">{{ $adatta($lezione->cattedra->disciplina->codice, '', '', true) }}</div>
                                        {{-- Il piano in piccolo in alto a destra e il nome dell'aula in piccolo in basso a sinistra della cella: non tolgono righe ai docenti.
                                             In grassetto se la classe cambia aula rispetto all'ora prima. --}}
                                        @if ($aulaCella = $lezione->aulaDaMostrare() ?? ($cambi[$lezione->id]['a'] ?? null))
                                            @if ($aulaCella->etichettaPiano(true))<span class="piano">{{ $aulaCella->etichettaPiano(true) }}</span>@endif
                                            <span class="aula-nome" @if (isset($cambi[$lezione->id])) style="font-weight: bold;" @endif>{{ $adatta($aulaCella->nome, '', '', isset($cambi[$lezione->id]), $pxAula) }}</span>
                                        @endif
                                    @endif
                                    <div>{{ $adatta($lezione->docenteEffettivo()->cognome) }}</div>
                                    @if ($lezione->docenteClilEffettivo())
                                        <div class="clil">{{ $adatta($lezione->docenteClilEffettivo()->cognome, 'C. ') }}</div>
                                    @endif
                                    @if ($per === 'aula')
                                        @foreach ($sostegni->get($lezione->slot_id.'-'.$lezione->cattedra->classe_id)?->unique('docente_id') ?? [] as $supporto)
                                            <div class="sostegno">{{ $adatta($supporto->docente->cognome, 'S. ') }}</div>
                                        @endforeach
                                    @endif
                                @endforeach
                                @foreach ($supporti as $supporto)
                                    <div class="sostegno">{{ $adatta($supporto->docente->cognome, 'S. ') }}</div>
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
        &nbsp;|&nbsp; <span class="sostegno"><strong>S.</strong> = docente di sostegno in compresenza</span>
        &nbsp;|&nbsp; <span class="clil"><strong>C.</strong> = docente CLIL in compresenza</span>
        @if ($per === 'classe')&nbsp;|&nbsp; <span class="aula"><strong>Aula</strong> in piccolo in basso a sinistra, <strong>piano</strong> in alto a destra (PT = piano terra, P1 = 1° piano…); in <strong>grassetto</strong> = la classe cambia aula rispetto all'ora prima</span>@endif
    </p>
</body>
</html>
