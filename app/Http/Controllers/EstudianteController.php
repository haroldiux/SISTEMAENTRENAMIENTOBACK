<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Gestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;;

use Illuminate\Support\Facades\Hash;

class EstudianteController extends Controller
{
    public function getDisponibles(Request $request)
    {
        // Validar parámetros de búsqueda
        $grupoId = $request->grupo_id;
        $search = $request->search;

        // Obtener estudiantes activos
        $query = Estudiante::with('user:id,name')
            ->where('estado', 1);

        // Si se proporciona un grupo, excluir los estudiantes ya asignados a ese grupo
        if ($grupoId) {
            $estudiantesAsignados = DB::table('inscripcions')
                ->where('grupo_id', $grupoId)
                ->pluck('estudiante_id');

            $query->whereNotIn('id', $estudiantesAsignados);
        }

        // Si se proporciona un término de búsqueda, filtrar por código de estudiante
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        $estudiantes = $query->get();

        return response()->json($estudiantes);
    }

    public function completarPerfil(Request $request)
    {
        // Validaciones robustas
        $request->validate([
            'nombres'    => 'required|string|max:255',
            'apellido1'  => 'required|string|max:255',
            'apellido2'  => 'required|string|max:255',
            'correo'     => 'required|email|max:255',
            'telefono'   => 'required|string|max:20',
            'password'   => 'required|string|min:6'
        ]);

        $user = $request->user();

        // Evita duplicar perfil
        if ($user->estudiante) {
            return response()->json(['message' => 'El perfil ya fue completado anteriormente.'], 400);
        }

        // Crear el perfil del estudiante
        $user->estudiante()->create([
            'nombres'    => $request->nombres,
            'apellido1'  => $request->apellido1,
            'apellido2'  => $request->apellido2,
            'correo'     => $request->correo,
            'telefono'   => $request->telefono,
            'estado'     => 1
        ]);

        // Actualizar la contraseña del usuario
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json(['message' => 'Perfil de estudiante registrado correctamente.']);
    }
}
