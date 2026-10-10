{{-- Riga di una pausa (ricreazione, mensa, accoglienza): bassa, con il testo per esteso su tutte le colonne. Se nessuno è assegnato alla pausa è una sola
     cella («Nome hh:mm-hh:mm (n') · aula»); se ci sono sorveglianti o docenti della mensa restano le celle dei giorni, con il nome nella prima colonna. --}}
@php($nomiPerGiorno = collect($slotPerGiorno)->map(fn ($slotGiorno, $giorno) => $cellaPausa($ordinePausa, $giorno)))
@php($dettaglio = "{$da}-{$a} ({$minuti}')".($aula ? ' · '.$aula : ''))
@if ($nomiPerGiorno->flatten()->isEmpty())
    <tr class="ricreazione">
        <td colspan="{{ count($slotPerGiorno) + 1 }}"><strong>{{ $nome }}</strong> &nbsp;{{ $dettaglio }}</td>
    </tr>
@else
    <tr class="ricreazione">
        <td class="ordine" style="font-size: 12px; padding: 2px 4px"><strong>{{ $nome }}</strong><br><span class="orario">{{ $dettaglio }}</span></td>
        @foreach ($slotPerGiorno as $giorno => $slotGiorno)
            <td>{{ implode(', ', $nomiPerGiorno[$giorno]) }}</td>
        @endforeach
    </tr>
@endif
