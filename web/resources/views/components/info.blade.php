{{-- Icona "i" con tooltip (hover e focus da tastiera): spiega come attivare un controllo disabilitato.
     Su <span tabindex> e non <button>, così resta raggiungibile anche dentro un fieldset disabilitato.
     Il tooltip è `fixed` e lo posiziona resources/js/info.js: così non è tagliato dai contenitori con overflow (tabelle) e la sua
     larghezza non dipende dalla colonna in cui sta l'icona. --}}
@props(['testo'])

<span data-info {{ $attributes->merge(['class' => 'relative inline-flex align-middle group']) }}>
    <span tabindex="0" role="img" aria-label="{{ $testo }}"
          class="inline-flex items-center justify-center size-4 rounded-full text-gray-500 hover:text-primary focus-visible:text-primary cursor-help">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
    </span>
    <span role="tooltip" class="pointer-events-none fixed z-50 left-0 top-0 w-max max-w-[22rem] rounded bg-gray-900 px-2 py-1.5 text-xs font-normal normal-case tracking-normal text-white opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100">{{ $testo }}</span>
</span>
