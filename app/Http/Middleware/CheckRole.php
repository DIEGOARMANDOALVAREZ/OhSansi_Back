<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $userSlugs = collect($user->roles ?? [])
            ->map(fn($r) => strtoupper((string)(is_array($r) ? ($r['slug'] ?? '') : ($r->slug ?? ''))))
            ->filter();

        $required = collect($roles)->map(fn($r) => strtoupper((string)$r))->filter();

        if ($userSlugs->intersect($required)->isEmpty()) {
            return response()->json(['message' => 'Acceso denegado (rol insuficiente).'], 403);
        }

        return $next($request);
    }
}
