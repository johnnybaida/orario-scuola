@php($utenza = $utenza ?? null)

<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="name" id="name" value="{{ old('name', $utenza?->name) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div>
    <label for="email" class="block text-sm font-medium text-gray-700">Email (username di accesso)</label>
    <input type="email" name="email" id="email" value="{{ old('email', $utenza?->email) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div>
    <label for="ruolo" class="block text-sm font-medium text-gray-700">Ruolo</label>
    <select name="ruolo" id="ruolo" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($ruoli as $ruolo)
            <option value="{{ $ruolo }}" @selected(old('ruolo', $utenza?->ruolo ?? 'docente') === $ruolo)>{{ \App\Support\Ruoli::etichetta($ruolo) }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="docente_id" class="block text-sm font-medium text-gray-700">Docente collegato</label>
    <select name="docente_id" id="docente_id" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <option value="">— Nessuno —</option>
        @foreach ($docenti as $docente)
            <option value="{{ $docente->id }}" @selected(old('docente_id', $utenza?->docente_id) == $docente->id)>{{ $docente->nomeCompleto() }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">Solo per il ruolo Docente: collega l'accesso alla sua anagrafica (ignorato per gli altri ruoli).</p>
</div>

<div>
    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
    <input type="password" name="password" id="password" minlength="8" autocomplete="new-password" @required(! $utenza)
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    @if ($utenza)
        <p class="mt-1 text-xs text-gray-500">Lascia vuoto per non cambiarla.</p>
    @endif
</div>
