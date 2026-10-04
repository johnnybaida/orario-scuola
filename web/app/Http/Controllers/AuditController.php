<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $filtri = $request->validate([
            'entita' => ['nullable', 'string'], 'azione' => ['nullable', 'string'], 'utente' => ['nullable', 'integer'],
            'dal' => ['nullable', 'date'], 'al' => ['nullable', 'date'], 'cerca' => ['nullable', 'string', 'max:100'],
        ]);

        $voci = AuditLog::query()->with('user:id,name')
            ->when($filtri['entita'] ?? null, fn ($q, $v) => $q->where('entita', $v))
            ->when($filtri['azione'] ?? null, fn ($q, $v) => $q->where('azione', $v))
            ->when($filtri['utente'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filtri['dal'] ?? null, fn ($q, $v) => $q->where('creato_il', '>=', $v.' 00:00:00'))
            ->when($filtri['al'] ?? null, fn ($q, $v) => $q->where('creato_il', '<=', $v.' 23:59:59'))
            ->when($filtri['cerca'] ?? null, fn ($q, $v) => $q->where('etichetta', 'like', "%{$v}%"))
            ->latest('id')->paginate(50)->withQueryString();

        return view('audit.index', [
            'voci' => $voci, 'filtri' => $filtri,
            'entita' => AuditLog::query()->distinct()->orderBy('entita')->pluck('entita'),
            'utenti' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
