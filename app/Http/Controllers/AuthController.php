<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Usuario;
use App\Models\Responsable;
use App\Models\ResponsableToken;
use App\Models\Evaluador;
use App\Models\EvaluadorToken;

class AuthController extends Controller
{
    /**
     * POST /auth/login
     * - Usuarios del sistema (Sanctum)
     * - Responsable (correo + CI) -> token plano
     * - Evaluador (correo + CI o correo + TOKEN emitido) -> token plano
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'correo'   => ['required', 'email'],
            'password' => ['required', 'string'], // usuarios: password | responsable: CI | evaluador: CI o TOKEN
            'device'   => ['sometimes', 'string'],
        ]);

        $correo = strtolower(trim($data['correo']));
        $secret = trim($data['password']);
        $device = $data['device'] ?? 'web';

        /** 1) Usuarios (Sanctum) */
        $user = Usuario::where('correo', $correo)->first();
        if ($user && Hash::check($secret, $user->password)) {
            $token = $user->createToken($device)->plainTextToken;

            return response()->json([
                'token'   => $token,
                'user'    => $user->load('roles'),
                'message' => 'Inicio de sesión exitoso (usuario del sistema).',
            ]);
        }

        /** 2) Responsable (correo + CI) -> token plano */
        $responsable = Responsable::where('correo', $correo)->first();
        if ($responsable && (string) $responsable->ci === $secret) {
            $plainToken = Str::random(64);

            ResponsableToken::create([
                'responsable_id' => $responsable->id,
                'name'           => $device,
                'token'          => hash('sha256', $plainToken),
                'abilities'      => ['*'],
            ]);

            $fakeUser = (object) [
                'id'        => $responsable->id,
                'nombres'   => $responsable->nombres,
                'apellidos' => $responsable->apellidos,
                'correo'    => $responsable->correo,
                'roles'     => [
                    (object)[ 'id' => 2, 'nombre' => 'Responsable Académico', 'slug' => 'RESPONSABLE' ],
                ],
            ];

            return response()->json([
                'token'   => $plainToken,
                'user'    => $fakeUser,
                'message' => 'Inicio de sesión exitoso (responsable académico).',
            ]);
        }

        /** 3) Evaluador (preferir CI, si no token emitido) */
        $evaluador = Evaluador::where('correo', $correo)->first();
        if ($evaluador) {
            // 3a) correo + CI
            if ($evaluador->ci && (string) $evaluador->ci === $secret) {
                $plainToken = Str::random(64);

                EvaluadorToken::create([
                    'evaluador_id' => $evaluador->id,
                    'name'         => $device,
                    'token'        => hash('sha256', $plainToken),
                    'abilities'    => ['*'],
                ]);

                $fakeUser = (object) [
                    'id'        => $evaluador->id,
                    'nombres'   => $evaluador->nombres,
                    'apellidos' => $evaluador->apellidos,
                    'correo'    => $evaluador->correo,
                    'roles'     => [
                        (object)[ 'id' => 3, 'nombre' => 'Evaluador', 'slug' => 'EVALUADOR' ],
                    ],
                ];

                return response()->json([
                    'token'   => $plainToken,
                    'user'    => $fakeUser,
                    'message' => 'Inicio de sesión exitoso (evaluador por CI).',
                ]);
            }

            // 3b) correo + TOKEN emitido
            $hash = hash('sha256', $secret);
            $tokenRow = EvaluadorToken::where('evaluador_id', $evaluador->id)
                        ->where('token', $hash)
                        ->first();

            if ($tokenRow) {
                // rotar token
                $plainToken = Str::random(64);
                EvaluadorToken::create([
                    'evaluador_id' => $evaluador->id,
                    'name'         => $device,
                    'token'        => hash('sha256', $plainToken),
                    'abilities'    => ['*'],
                ]);

                $fakeUser = (object) [
                    'id'        => $evaluador->id,
                    'nombres'   => $evaluador->nombres,
                    'apellidos' => $evaluador->apellidos,
                    'correo'    => $evaluador->correo,
                    'roles'     => [
                        (object)[ 'id' => 3, 'nombre' => 'Evaluador', 'slug' => 'EVALUADOR' ],
                    ],
                ];

                return response()->json([
                    'token'   => $plainToken,
                    'user'    => $fakeUser,
                    'message' => 'Inicio de sesión exitoso (evaluador por token).',
                ]);
            }
        }

        /** ❌ Credenciales inválidas */
        return response()->json(['message' => 'Credenciales inválidas.'], 422);
    }

    /** GET /auth/perfil (usuarios Sanctum) */
    public function perfil(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);
        return response()->json($user->load('roles'));
    }

    /** GET /responsable/perfil (token plano) */
    public function perfilResponsable(Request $request)
    {
        /** @var Responsable|null $responsable */
        $responsable = $request->input('responsable');
        if (!$responsable) return response()->json(['message' => 'No autenticado.'], 401);

        return response()->json([
            'id'        => $responsable->id,
            'nombres'   => $responsable->nombres,
            'apellidos' => $responsable->apellidos,
            'correo'    => $responsable->correo,
        ]);
    }

    /** GET /evaluador/perfil (token plano) */
    public function perfilEvaluador(Request $request)
    {
        /** @var Evaluador|null $evaluador */
        $evaluador = $request->input('evaluador');
        if (!$evaluador) return response()->json(['message' => 'No autenticado.'], 401);

        return response()->json([
            'id'        => $evaluador->id,
            'nombres'   => $evaluador->nombres,
            'apellidos' => $evaluador->apellidos,
            'correo'    => $evaluador->correo,
        ]);
    }

    /** POST /auth/logout */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()?->delete();
        }

        $bearer = $request->bearerToken();
        if ($bearer) {
            $hash = hash('sha256', $bearer);
            ResponsableToken::where('token', $hash)->delete();
            EvaluadorToken::where('token', $hash)->delete();
        }

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
