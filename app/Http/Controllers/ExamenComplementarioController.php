<?php

namespace App\Http\Controllers;

use App\Models\ExamenComplementario;
use App\Models\Caso;
use App\Models\Gestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExamenComplementarioController extends Controller
{
    /**
     * Obtiene los exámenes complementarios disponibles para el estudiante
     */
    public function getExamenesEstudiante()
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

            // Obtener casos resueltos por el estudiante
            $casosResueltoIds = DB::table('resolucions')
                ->where('estudiante_id', $estudiante->id)
                ->where('gestion_id', $gestionActiva->id)
                ->pluck('caso_id');

            // Obtener exámenes complementarios de esos casos
            $examenesComplementarios = DB::table('examen_complementarios')
                ->select(
                    'examen_complementarios.id',
                    'examen_complementarios.caso_id',
                    'examens.nombre',
                    'examen_complementarios.archivo'
                )
                ->join('examens', 'examen_complementarios.examen_id', '=', 'examens.id')
                ->whereIn('examen_complementarios.caso_id', $casosResueltoIds)
                ->get();

            // Obtener información de los casos relacionados
            $casosIds = $examenesComplementarios->pluck('caso_id')->unique();
            $casos = DB::table('casos')
                ->select('id', 'titulo')
                ->whereIn('id', $casosIds)
                ->get()
                ->keyBy('id');

            // Añadir el título del caso a cada examen
            $examenesComplementarios = $examenesComplementarios->map(function ($examen) use ($casos) {
                $examen->caso_titulo = $casos[$examen->caso_id]->titulo ?? 'Caso clínico';
                return $examen;
            });

            return response()->json([
                'success' => true,
                'data' => $examenesComplementarios
            ]);
        } catch (\Exception $e) {
            Log::error('Error en exámenes complementarios: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener exámenes complementarios',
                'error' => $e->getMessage(),
                'trace' => env('APP_DEBUG', false) ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Muestra un examen complementario específico
     */
    public function show($id)
    {
        try {
            $examenComplementario = DB::table('examen_complementarios')
                ->select(
                    'examen_complementarios.*',
                    'examens.nombre as examen_nombre',
                    'casos.titulo as caso_titulo'
                )
                ->join('examens', 'examen_complementarios.examen_id', '=', 'examens.id')
                ->join('casos', 'examen_complementarios.caso_id', '=', 'casos.id')
                ->where('examen_complementarios.id', $id)
                ->first();

            if (!$examenComplementario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Examen complementario no encontrado'
                ], 404);
            }

            // Verificar que el estudiante tenga acceso a este examen
            $user = Auth::user();
            $estudiante = DB::table('estudiantes')->where('user_id', $user->id)->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no es un estudiante'
                ], 403);
            }

            // Verificar si el estudiante ha resuelto el caso asociado
            $haResuelto = DB::table('resolucions')
                ->where('estudiante_id', $estudiante->id)
                ->where('caso_id', $examenComplementario->caso_id)
                ->exists();

            if (!$haResuelto && !$user->role_id == 1) { // Si no es admin y no ha resuelto el caso
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a este examen complementario'
                ], 403);
            }

            // Generar URL para el archivo si existe
            if ($examenComplementario->archivo) {
                $examenComplementario->archivo_url = url(Storage::url($examenComplementario->archivo));
            }

            return response()->json([
                'success' => true,
                'data' => $examenComplementario
            ]);
        } catch (\Exception $e) {
            Log::error('Error al mostrar examen complementario: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el examen complementario',
                'error' => $e->getMessage(),
                'trace' => env('APP_DEBUG', false) ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}
