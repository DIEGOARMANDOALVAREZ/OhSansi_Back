<?php
// app/Http/Controllers/AuthController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'correo'   => ['required','email'],
            'password' => ['required','string'],
            'device'   => ['sometimes','string'], // opcional
        ]);

        $user = Usuario::where('correo', $data['correo'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 422);
        }

        // crea token de acceso (Sanctum PAT)
        $token = $user->createToken($data['device'] ?? 'web')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'user'   => $user->load('roles'),
        ]);
    }

    public function perfil(Request $request)
    {
        return $request->user()->load('roles');
    }

    public function logout(Request $request)
    {
        // invalida SOLO el token actual
        $request->user()->currentAccessToken()?->delete();
        return response()->noContent();
    }
}
