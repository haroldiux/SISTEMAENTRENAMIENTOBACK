<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Evaluacion;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
}
