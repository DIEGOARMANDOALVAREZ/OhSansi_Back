<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuario; // 👈 importa tu modelo

class DashboardController extends Controller
{
    public function index()
    {
        // Obtiene el ID autenticado y trae el usuario con sus roles de una vez
        $userId = Auth::id();
        $user = Usuario::with('roles')->findOrFail($userId);

        return view('dashboard.index', compact('user'));
    }
}
