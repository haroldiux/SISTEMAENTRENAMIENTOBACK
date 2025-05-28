<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocenteController extends Controller
{
    /**
     * Obtener lista de docentes con información para la selección
     */
    public function index()
    {
        try {
            Log::info('Iniciando DocenteController@index');

            // Obtener todos los docentes activos con sus datos de usuario
            $docentes = Docente::with('user:id,name')
                ->where('estado', 1)
                ->get();

            $docentesFormateados = [];

            // Formatear los datos para incluir name de la tabla users y facilitar la búsqueda
            foreach ($docentes as $docente) {
                // Verificar si existe la relación user
                if ($docente->user) {
                    $nuevoDocente = $docente->toArray();
                    $nuevoDocente['user_name'] = $docente->user->name;
                    $nuevoDocente['nombre_completo'] = "{$docente->nombres} {$docente->apellidos}";
                    $nuevoDocente['label'] = "{$docente->nombres} {$docente->apellidos} ({$docente->user->name})";

                    $docentesFormateados[] = $nuevoDocente;
                }
            }

            Log::info('DocenteController@index completado', ['cantidad_docentes' => count($docentesFormateados)]);
            return response()->json($docentesFormateados);
        } catch (\Exception $e) {
            // Log el error para debugging
            Log::error('Error en DocenteController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al obtener docentes', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Completar perfil de docente
     */
    public function completarPerfil(Request $request)
    {
        $request->validate([
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'correo' => 'required|email',
            'telefono' => 'required|string',
            'password' => 'required|string|min:6'
        ]);

        $user = $request->user();

        if ($user->docente) {
            return response()->json(['message' => 'Perfil ya completado'], 400);
        }

        $user->docente()->create([
            'nombres' => $request->nombres,
            'apellidos' => $request->apellidos,
            'correo' => $request->correo,
            'telefono' => $request->telefono,
            'estado' => 1
        ]);

        // 🔐 Cambiar contraseña del usuario
        $user->update([
            'password' => bcrypt($request->password)
        ]);

        return response()->json(['message' => 'Perfil docente registrado con éxito']);
    }
}
