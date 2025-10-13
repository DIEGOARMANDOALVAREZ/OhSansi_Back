<?php
// app/Http/Controllers/Auth/LoginController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $cred = $request->validate([
            'correo'   => ['required', 'email'],
            'password' => ['required'],
            'device'   => ['nullable', 'string'],
        ]);

        if (! Auth::attempt(['correo' => $cred['correo'], 'password' => $cred['password']])) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        /** @var \App\Models\Usuario $user */
        $user = Auth::user();

        $token = $user->createToken($cred['device'] ?? 'web')->plainTextToken;

        // si quieres enviar roles al front
        $user->load('roles:id,nombre,slug');

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }
}
