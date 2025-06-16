<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Evaluacion;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DocenteDashboardController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $docente = $user->docente;

            if (!$docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no es un docente'
                ], 403);
            }

            // Datos específicos del docente
            $misCasos = Caso::where('docente_id', $docente->id)->count();
            $evaluacionesPendientes = Evaluacion::where('docente_id', $docente->id)
                ->where('fecha_fin', '>=', now())
                ->count();
            $gruposAsignados = Grupo::where('docente_id', $docente->id)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'mis_casos' => $misCasos,
                    'evaluaciones_pendientes' => $evaluacionesPendientes,
                    'grupos_asignados' => $gruposAsignados
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en dashboard docente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // ===== ADICIONES AL CONTROLADOR EXISTENTE =====
    // Agregar estas funciones al DocenteDashboardController.php

    public function getEvaluacionesDashboard()
    {
        // Obtener el controlador de evaluación
        $evaluacionController = app()->make(\App\Http\Controllers\EvaluacionController::class);

        // Obtener la respuesta del método existente
        $response = $evaluacionController->getDocenteEvaluaciones();

        // Extraer datos de la respuesta
        $responseData = json_decode($response->getContent(), true);

        // Verificar si hay un error
        if (!isset($responseData['evaluaciones'])) {
            return response()->json([
                'success' => false,
                'message' => $responseData['message'] ?? 'Error al obtener evaluaciones'
            ], $response->getStatusCode());
        }

        // Transformar los datos al formato requerido por el dashboard
        $evaluaciones = collect($responseData['evaluaciones'])->map(function ($evaluacion) {
            return [
                'id' => $evaluacion['id'],
                'titulo' => $evaluacion['titulo'],
                'fecha_inicio' => $evaluacion['fecha_inicio'],
                'fecha_fin' => $evaluacion['fecha_fin'],
                'grupo' => [
                    'id' => $evaluacion['grupo']['id'] ?? null,
                    'nombre' => $evaluacion['grupo']['nombre'] ?? 'Sin grupo'
                ],
                'materia' => [
                    'id' => $evaluacion['materia']['id'] ?? null,
                    'nombre' => $evaluacion['materia']['nombre'] ?? 'Sin materia'
                ],
                'completados' => $evaluacion['estadisticas']['completados'] ?? 0,
                'total_estudiantes' => $evaluacion['estadisticas']['total_estudiantes'] ?? 0
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => $evaluaciones
        ]);
    }

    /**
     * Obtiene los grupos para el dashboard del docente
     * Reutiliza la lógica existente pero devuelve el formato requerido por el dashboard
     */
    public function getGruposDashboard(Request $request)
    {
        // Obtener el controlador de grupos
        $grupoController = app()->make(\App\Http\Controllers\GrupoController::class);

        // Obtener la respuesta del método existente
        $response = $grupoController->getGruposDocente($request);

        // Extraer datos de la respuesta
        $responseData = json_decode($response->getContent(), true);

        // Verificar si hay un error
        if (!$responseData['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos'
            ], $response->getStatusCode());
        }

        // Transformar los datos al formato requerido por el dashboard
        $grupos = collect($responseData['grupos'])->map(function ($grupo) {
            return [
                'id' => $grupo['id'],
                'nombre' => $grupo['nombre'],
                'materia' => [
                    'id' => $grupo['materia']['id'] ?? null,
                    'nombre' => $grupo['materia']['nombre'] ?? 'Sin materia'
                ],
                'total_estudiantes' => count($grupo['inscripciones'] ?? [])
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => $grupos
        ]);
    }

    /**
     * Obtiene los casos recientes del docente
     */
    public function casosRecientes()
    {
        try {
            $user = auth()->user();

            if (!$user || !$user->docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no es un docente'
                ], 403);
            }

            // Obtener casos recientes con información de materia
            $casos = Caso::where('docente_id', $user->docente->id)
                ->with('materia')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($caso) {
                    return [
                        'id' => $caso->id,
                        'titulo' => $caso->titulo,
                        'nivel_dificultad' => $caso->nivel_dificultad,
                        'created_at' => $caso->created_at,
                        'materia' => [
                            'id' => $caso->materia->id ?? null,
                            'nombre' => $caso->materia->nombre ?? 'Sin materia'
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $casos
            ]);
        } catch (\Exception $e) {
            Log::error('Error en casos recientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener casos recientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene la cantidad de casos por nivel de dificultad
     */
    public function casosPorDificultad()
    {
        try {
            $user = auth()->user();

            if (!$user || !$user->docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no es un docente'
                ], 403);
            }

            // Obtener casos por nivel de dificultad
            $casosPorDificultad = Caso::where('docente_id', $user->docente->id)
                ->select('nivel_dificultad', DB::raw('count(*) as total'))
                ->groupBy('nivel_dificultad')
                ->pluck('total', 'nivel_dificultad')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $casosPorDificultad
            ]);
        } catch (\Exception $e) {
            Log::error('Error en casos por dificultad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de casos por dificultad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el estado de las evaluaciones del docente
     */
    public function estadoEvaluaciones()
    {
        try {
            $user = auth()->user();

            if (!$user || !$user->docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no es un docente'
                ], 403);
            }

            $ahora = now();

            // Evaluaciones activas (fecha fin > hoy + 2 días)
            $activas = Evaluacion::where('docente_id', $user->docente->id)
                ->where('fecha_fin', '>', $ahora)
                ->where('fecha_fin', '>', $ahora->copy()->addDays(2))
                ->count();

            // Evaluaciones por vencer (fecha fin entre hoy y 2 días)
            $porVencer = Evaluacion::where('docente_id', $user->docente->id)
                ->where('fecha_fin', '>', $ahora)
                ->where('fecha_fin', '<=', $ahora->copy()->addDays(2))
                ->count();

            // Evaluaciones vencidas (fecha fin < hoy)
            $vencidas = Evaluacion::where('docente_id', $user->docente->id)
                ->where('fecha_fin', '<', $ahora)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'activas' => $activas,
                    'por_vencer' => $porVencer,
                    'vencidas' => $vencidas
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en estado de evaluaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado de evaluaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
