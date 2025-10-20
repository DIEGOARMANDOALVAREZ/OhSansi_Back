<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\EvaluadorToken;

class AuthEvaluador
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        if (!$plain) {
            return response()->json(['message' => 'Token no provisto.'], 401);
        }

        $row = EvaluadorToken::with('evaluador')
            ->where('token', hash('sha256', $plain))
            ->first();

        if (!$row || !$row->evaluador) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        if ($row->evaluador->activo === false) {
            return response()->json(['message' => 'Evaluador inactivo.'], 401);
        }

        // Inyecta el evaluador para el controlador
        $request->merge(['evaluador' => $row->evaluador]);

        return $next($request);
    }
}
