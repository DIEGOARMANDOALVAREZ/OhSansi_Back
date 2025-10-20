<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ResponsableToken;
use App\Models\Responsable;

class AuthResponsable
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return response()->json(['message' => 'Token faltante.'], 401);
        }

        // compara contra hash guardado en BD
        $hash = hash('sha256', $bearer);
        $tokenRow = ResponsableToken::where('token', $hash)->first();
        if (!$tokenRow) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        if ($tokenRow->expires_at && now()->greaterThan($tokenRow->expires_at)) {
            return response()->json(['message' => 'Token expirado.'], 401);
        }

        $responsable = Responsable::find($tokenRow->responsable_id);
        if (!$responsable) {
            return response()->json(['message' => 'Responsable no encontrado.'], 401);
        }

        // inyecta el responsable para los controladores
        $request->merge(['responsable' => $responsable]);

        return $next($request);
    }
}
