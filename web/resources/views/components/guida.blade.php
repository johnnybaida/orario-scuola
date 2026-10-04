@props(['titolo' => null])

<div class="mb-4 rounded-lg bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 text-sm">
    @if ($titolo)
        <p class="font-medium mb-1">{{ $titolo }}</p>
    @endif
    <div class="space-y-1">{{ $slot }}</div>
</div>
