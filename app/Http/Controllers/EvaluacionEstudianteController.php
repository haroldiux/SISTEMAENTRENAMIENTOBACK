<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\Gestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvaluacionEstudianteController extends Controller
{
    public function getPendientes()
    {
        try {
            $user = Auth::user();
            $estudiante = DB::table('estudiantes')->where('user_id', $user->id)->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no es un estudiante'
                ], 403);
            }

            // Obtener gestión activa
            $gestionActiva = Gestion::where('estado', 1)->first();

            if (!$gestionActiva) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            // Obtener grupos del estudiante
            $inscripciones = DB::table('inscripcions')
                ->where('estudiante_id', $estudiante->id)
                ->pluck('grupo_id')
                ->toArray();

            // Obtener evaluaciones pendientes
            $evaluacionesPendientes = Evaluacion::whereIn('grupo_id', $inscripciones)
                ->where('fecha_fin', '>=', now())
                ->where('fecha_inicio', '<=', now())
                ->with(['materia', 'grupo', 'evaluacionCasos'])
                ->orderBy('fecha_fin')
                ->get();

            // Filtrar solo las no completadas
            $evaluacionesPendientes = $evaluacionesPendientes->filter(function ($evaluacion) use ($estudiante) {
                $evaluacionCasos = DB::table('evaluacion_casos')
                    ->where('evaluacion_id', $evaluacion->id)
                    ->get();

                if ($evaluacionCasos->isEmpty()) {
                    return false;
                }

                foreach ($evaluacionCasos as $evaluacionCaso) {
                    $evaluacionEstudiante = DB::table('evaluacion_estudiantes')
                        ->where('evaluacion_caso_id', $evaluacionCaso->id)
                        ->where('estudiante_id', $estudiante->id)
                        ->first();

                    if (!$evaluacionEstudiante || !$evaluacionEstudiante->completado) {
                        return true;
                    }
                }

                return false;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $evaluacionesPendientes
            ]);

        } catch (\Exception $e) {
            Log::error('Error en evaluaciones pendientes: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener evaluaciones pendientes',
                'error' => $e->getMessage(),
                'trace' => env('APP_DEBUG', false) ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}
