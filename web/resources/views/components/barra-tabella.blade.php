{{-- Barra sopra una tabella: filtri e ricerca a sinistra (slot), azioni come "Nuovo" a destra (slot "azioni"). --}}
<div class="mb-3 flex flex-wrap items-end justify-between gap-3">
    <div class="flex flex-wrap items-end gap-3">{{ $slot }}</div>
    <div class="flex items-center gap-3">{{ $azioni ?? '' }}</div>
</div>
