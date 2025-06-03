<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    public function index()
    {
        try {
            // Datos generales del sistema
            $totalDocentes = \App\Models\Docente::where('estado', 1)->count();
            $totalEstudiantes = \App\Models\Estudiante::where('estado', 1)->count();
            $totalUsuarios = $totalDocentes + $totalEstudiantes;
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
}
