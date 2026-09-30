<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRuolo
{
    public function handle(Request $request, Closure $next, string ...$ruoli): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->ruolo, $ruoli, true)) {
            abort(403, 'Non hai i permessi per accedere a questa pagina.');
        }

        return $next($request);
    }
}
