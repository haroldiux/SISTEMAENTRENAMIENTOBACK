<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'user' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = User::where('name', $request->user)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Determinar si es la primera vez (password aún es el name original)
        $primeraVez = Hash::check($user->name, $user->password);

        // Determinar si ya completó su perfil (solo para docentes y estudiantes)
        $perfilCompletado = false;

        if ($user->role_id == 2) {
            $perfilCompletado = $user->docente()->exists();
        } elseif ($user->role_id == 3) {
            $perfilCompletado = $user->estudiante()->exists();
        }

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->nombre,
                'primera_vez' => $primeraVez,
                'profile_completed' => $perfilCompletado,
                'docente_id' => $user->role_id == 2 && $user->docente ? $user->docente->id : null,
                'estudiante_id' => $user->role_id == 3 && $user->estudiante ? $user->estudiante->id : null
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
