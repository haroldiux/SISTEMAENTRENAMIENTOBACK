<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Gestion;
use App\Models\Materia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class GrupoController extends Controller
{
    /**
     * Obtener todos los grupos por materia y gestión
     */
    public function getPorMateria(Request $request, Materia $materia)
    {
        $gestionId = $request->gestion_id;

        // Si se solicita la gestión activa
        if ($gestionId === 'activa') {
            $gestionActiva = Gestion::where('estado', 1)->first();

            if (!$gestionActiva) {
                return response()->json(['message' => 'No hay gestión activa'], 404);
            }

            $gestionId = $gestionActiva->id;
        }

        $grupos = Grupo::with(['docente' => function ($query) {
            $query->select('id', 'nombres', 'apellidos', 'user_id');
        }])
            ->where('materia_id', $materia->id)
            ->where('gestion_id', $gestionId)
            ->get();

        // Añadir el recuento de estudiantes para cada grupo
        foreach ($grupos as $grupo) {
            $estudiantesCount = $grupo->estudiantes()->count();
            $grupo->setAttribute('estudiantes_count', $estudiantesCount);


            // Formatear el nombre completo del docente si existe
            if ($grupo->docente) {
                $grupo->docente->nombre_completo = $grupo->docente->nombres . ' ' . $grupo->docente->apellidos;
            }
        }

        return response()->json($grupos);
    }

    /**
     * Actualizar un grupo específico
     */
    public function update(Request $request, Grupo $grupo)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $grupo->nombre = $request->nombre;
            $grupo->save();

            return response()->json($grupo);
        } catch (\Exception $e) {
            Log::error('Error en update', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al actualizar grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar grupos para una materia y gestión
     * Permite crear grupos individuales con nombre personalizado y docente asignado
     */
    public function generarGrupos(Request $request)
    {
        try {
            Log::info('Solicitud recibida en generarGrupos', ['request' => $request->all()]);

            $validator = Validator::make($request->all(), [
                'materia_id' => 'required|exists:materias,id',
                'cantidad' => 'required|integer|min:1|max:20',
                'nombre' => 'nullable|string|max:255',
                'docente_id' => 'required|exists:docentes,id',
            ]);

            if ($validator->fails()) {
                Log::error('Validación fallida', ['errors' => $validator->errors()]);
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Determinar la gestión (usa la proporcionada o la activa)
            $gestionId = $request->gestion_id;
            if (!$gestionId) {
                $gestionActiva = Gestion::where('estado', 1)->first();
                if (!$gestionActiva) {
                    return response()->json(['message' => 'No hay gestión activa'], 404);
                }
                $gestionId = $gestionActiva->id;
            }

            // Obtener el número máximo de grupo para esta materia y gestión
            $maxNumeroGrupo = Grupo::where('materia_id', $request->materia_id)
                ->where('gestion_id', $gestionId)
                ->count();

            $gruposCreados = [];

            // Si se proporciona un nombre, crear un solo grupo con ese nombre
            if (!empty($request->nombre) && $request->cantidad == 1) {
                // Convertir docenteId a integer o null para evitar el error de array
                $docenteId = $request->docente_id ? intval($request->docente_id) : null;

                Log::info('Creando grupo individual', [
                    'nombre' => $request->nombre,
                    'materia_id' => $request->materia_id,
                    'gestion_id' => $gestionId,
                    'docente_id' => $docenteId
                ]);

                $grupo = Grupo::create([
                    'nombre' => $request->nombre,
                    'materia_id' => $request->materia_id,
                    'gestion_id' => $gestionId,
                    'docente_id' => $docenteId
                ]);

                $gruposCreados[] = $grupo;
            } else {
                // Crear los grupos con nombres automáticos
                for ($i = 1; $i <= $request->cantidad; $i++) {
                    $numeroGrupo = $maxNumeroGrupo + $i;

                    $grupo = Grupo::create([
                        'nombre' => 'Grupo ' . $numeroGrupo,
                        'materia_id' => $request->materia_id,
                        'gestion_id' => $gestionId,
                        'docente_id' => intval($request->docente_id)
                    ]);

                    $gruposCreados[] = $grupo;
                }
            }

            return response()->json($gruposCreados, 201);
        } catch (\Exception $e) {
            Log::error('Error en generarGrupos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al generar grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar un docente a un grupo
     */
    public function asignarDocente(Request $request, Grupo $grupo)
    {
        try {
            $validator = Validator::make($request->all(), [
                'docente_id' => 'required|exists:docentes,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $grupo->docente_id = intval($request->docente_id);
            $grupo->save();

            return response()->json($grupo);
        } catch (\Exception $e) {
            Log::error('Error en asignarDocente', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al asignar docente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener los estudiantes de un grupo
     */
    public function getEstudiantes(Grupo $grupo)
    {
        $estudiantes = $grupo->estudiantes()->with('user:id,name')->get();

        // Agregar información de fecha de asignación
        foreach ($estudiantes as $estudiante) {
            $inscripcion = $estudiante->inscripciones()
                ->where('grupo_id', $grupo->id)
                ->first();

            if ($inscripcion) {
                $estudiante->pivot = [
                    'fecha_asignacion' => $inscripcion->created_at->format('Y-m-d')
                ];
            }
        }

        return response()->json($estudiantes);
    }

    /**
     * Asignar estudiantes a un grupo
     */
    public function asignarEstudiantes(Request $request, Grupo $grupo)
    {
        $validator = Validator::make($request->all(), [
            'estudiante_ids' => 'required|array',
            'estudiante_ids.*' => 'exists:estudiantes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Añadir estudiantes al grupo
        $grupo->estudiantes()->attach($request->estudiante_ids);

        return response()->json(['message' => 'Estudiantes asignados correctamente']);
    }

    /**
     * Remover estudiantes de un grupo
     */
    public function removerEstudiantes(Request $request, Grupo $grupo)
    {
        $validator = Validator::make($request->all(), [
            'estudiante_ids' => 'required|array',
            'estudiante_ids.*' => 'exists:estudiantes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Remover estudiantes del grupo
        $grupo->estudiantes()->detach($request->estudiante_ids);

        return response()->json(['message' => 'Estudiantes removidos correctamente']);
    }

    /**
     * Eliminar un grupo
     */
    public function destroy(Grupo $grupo)
    {
        // Verificar si hay estudiantes asignados
        if ($grupo->estudiantes()->count() > 0) {
            // Remover todas las asignaciones primero
            $grupo->estudiantes()->detach();
        }

        $grupo->delete();
        return response()->json(null, 204);
    }
}
