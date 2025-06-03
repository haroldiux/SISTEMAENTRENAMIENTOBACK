<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
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

            // Datos generales del sistema
            $totalDocentes = \App\Models\Docente::where('estado', 1)->count();
            $totalEstudiantes = \App\Models\Estudiante::where('estado', 1)->count();
            $totalUsuarios = $totalDocentes + $totalEstudiantes;
            $totalCasos = Caso::count();

            $data = [
                'total_usuarios' => $totalUsuarios,
                'total_docentes' => $totalDocentes,
                'total_estudiantes' => $totalEstudiantes,
                'total_casos' => $totalCasos,
                'rol' => $user->role->nombre
            ];

            // Datos específicos según el rol
            if ($user->role->nombre === 'DOCENTE') {
                $docente = $user->docente;
                if ($docente) {
                    $data['mis_casos'] = Caso::where('docente_id', $docente->id)->count();
                    $data['evaluaciones_pendientes'] = \App\Models\Evaluacion::where('docente_id', $docente->id)
                        ->where('fecha_fin', '>=', now())
                        ->count();
                    $data['grupos_asignados'] = \App\Models\Grupo::where('docente_id', $docente->id)->count();
                } else {
                    $data['mis_casos'] = 0;
                    $data['evaluaciones_pendientes'] = 0;
                    $data['grupos_asignados'] = 0;
                }
            } elseif ($user->role->nombre === 'ESTUDIANTE') {
                $estudiante = $user->estudiante;
                if ($estudiante) {
                    $data['evaluaciones_pendientes'] = \App\Models\EvaluacionEstudiante::where('estudiante_id', $estudiante->id)
                        ->where('estado', 'pendiente')
                        ->count();
                    $data['evaluaciones_completadas'] = \App\Models\EvaluacionEstudiante::where('estudiante_id', $estudiante->id)
                        ->where('estado', 'completada')
                        ->count();
                } else {
                    $data['evaluaciones_pendientes'] = 0;
                    $data['evaluaciones_completadas'] = 0;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error en dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
