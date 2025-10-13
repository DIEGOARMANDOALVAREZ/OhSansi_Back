<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $roleSlug)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $roleSlug = mb_strtolower($roleSlug);
        $has = $user->roles()->whereRaw('LOWER(slug) = ?', [$roleSlug])->exists();

        if (! $has) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
