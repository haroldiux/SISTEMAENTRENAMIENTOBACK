<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Role;
use App\Models\User;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\Materia;
use App\Models\Grupo;
use App\Models\Gestion;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    /**
     * Mostrar estadísticas generales para el dashboard del administrador
     */
    public function index()
    {
        try {
            // Datos generales del sistema
            $totalDocentes = Docente::where('estado', 1)->count();
            $totalEstudiantes = Estudiante::where('estado', 1)->count();
            $totalUsuarios = User::where('estado', 1)->count();
            $totalCasos = Caso::count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_usuarios' => $totalUsuarios,
                    'total_docentes' => $totalDocentes,
                    'total_estudiantes' => $totalEstudiantes,
                    'total_casos' => $totalCasos
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en dashboard admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener distribución de casos por nivel de dificultad
     */
    public function casosPorDificultad()
    {
        try {
            $nivel1 = Caso::where('nivel_dificultad', 1)->count();
            $nivel2 = Caso::where('nivel_dificultad', 2)->count();
            $nivel3 = Caso::where('nivel_dificultad', 3)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'nivel1' => $nivel1,
                    'nivel2' => $nivel2,
                    'nivel3' => $nivel3
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en casosPorDificultad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener distribución de casos por dificultad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener distribución de casos por materia
     */
    public function casosPorMateria()
    {
        try {
            $casosPorMateria = DB::table('casos')
                ->join('materias', 'casos.materia_id', '=', 'materias.id')
                ->select('materias.id as materia_id', 'materias.nombre as materia_nombre', DB::raw('count(*) as cantidad'))
                ->groupBy('materias.id', 'materias.nombre')
                ->orderBy('cantidad', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $casosPorMateria
            ]);
        } catch (\Exception $e) {
            Log::error('Error en casosPorMateria: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener distribución de casos por materia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener casos recientes
     */
    public function casosRecientes()
    {
        try {
            $casosRecientes = DB::table('casos')
                ->join('docentes', 'casos.docente_id', '=', 'docentes.id')
                ->select(
                    'casos.id',
                    'casos.titulo',
                    'casos.nivel_dificultad',
                    DB::raw("CONCAT(docentes.nombres, ' ', docentes.apellidos) as docente_nombre"),
                    'casos.created_at'
                )
                ->orderBy('casos.created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $casosRecientes
            ]);
        } catch (\Exception $e) {
            Log::error('Error en casosRecientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener casos recientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estado de evaluaciones (activas vs completadas)
     */
    public function estadoEvaluaciones()
    {
        try {
            $now = now();
            $evaluacionesActivas = Evaluacion::where('fecha_fin', '>=', $now)->count();
            $evaluacionesCompletadas = Evaluacion::where('fecha_fin', '<', $now)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'activas' => $evaluacionesActivas,
                    'completadas' => $evaluacionesCompletadas
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en estadoEvaluaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado de evaluaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información de grupos e inscripciones
     */
    public function getGruposDashboard()
    {
        try {
            // Obtener gestión activa
            $gestionActiva = Gestion::where('estado', 1)->first();

            if (!$gestionActiva) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total' => 0,
                        'inscripciones' => 0
                    ]
                ]);
            }

            // Total de grupos en la gestión activa
            $totalGrupos = Grupo::where('gestion_id', $gestionActiva->id)->count();

            // Total de inscripciones en la gestión activa
            $totalInscripciones = DB::table('inscripcions')
                ->join('grupos', 'inscripcions.grupo_id', '=', 'grupos.id')
                ->where('grupos.gestion_id', $gestionActiva->id)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $totalGrupos,
                    'inscripciones' => $totalInscripciones
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getGruposDashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información de grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener evaluaciones para el dashboard
     */
    public function getEvaluacionesDashboard()
    {
        try {
            // Obtener gestión activa
            $gestionActiva = Gestion::where('estado', 1)->first();

            if (!$gestionActiva) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            // Obtener evaluaciones próximas a vencer
            $evaluaciones = DB::table('evaluaciones')
                ->join('materias', 'evaluaciones.materia_id', '=', 'materias.id')
                ->join('grupos', 'evaluaciones.grupo_id', '=', 'grupos.id')
                ->join('docentes', 'evaluaciones.docente_id', '=', 'docentes.id')
                ->where('evaluaciones.gestion_id', $gestionActiva->id)
                ->where('evaluaciones.fecha_fin', '>=', now())
                ->select(
                    'evaluaciones.id',
                    'evaluaciones.titulo',
                    'evaluaciones.fecha_inicio',
                    'evaluaciones.fecha_fin',
                    'materias.nombre as materia_nombre',
                    'grupos.nombre as grupo_nombre',
                    DB::raw("CONCAT(docentes.nombres, ' ', docentes.apellidos) as docente_nombre")
                )
                ->orderBy('evaluaciones.fecha_fin')
                ->limit(5)
                ->get();

            // Obtener el número de estudiantes para cada evaluación
            foreach ($evaluaciones as $evaluacion) {
                $evaluacion->estudiantes_count = DB::table('evaluacion_estudiantes')
                    ->join('evaluacion_casos', 'evaluacion_estudiantes.evaluacion_caso_id', '=', 'evaluacion_casos.id')
                    ->where('evaluacion_casos.evaluacion_id', $evaluacion->id)
                    ->count();
            }

            return response()->json([
                'success' => true,
                'data' => $evaluaciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getEvaluacionesDashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener evaluaciones pendientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
