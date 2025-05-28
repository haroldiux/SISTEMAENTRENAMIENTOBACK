<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CambioContrasenaController extends Controller
{
    /**
     * Cambiar la contraseña en el primer inicio de sesión
     */
    public function cambiarContrasenaInicial(Request $request)
    {
        try {
            // Validar la solicitud
            $request->validate([
                'password' => 'required|string|min:6',
            ]);

            $user = $request->user();

            // Verificar que el usuario existe
            if (!$user) {
                return response()->json(['message' => 'Usuario no encontrado'], 404);
            }

            // Verificar que la nueva contraseña sea diferente a la anterior
            if (Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'La nueva contraseña debe ser diferente a la actual'], 422);
            }

            // Actualizar la contraseña
            $user->update([
                'password' => Hash::make($request->password)
            ]);

            return response()->json(['message' => 'Contraseña actualizada correctamente']);
        } catch (\Exception $e) {
            Log::error('Error al cambiar contraseña inicial: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cambiar la contraseña: ' . $e->getMessage()], 500);
        }
    }
}
