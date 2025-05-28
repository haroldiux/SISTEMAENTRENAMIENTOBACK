<?php

namespace App\Http\Controllers;

use App\Models\Duracion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DuracionController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $duraciones = Duracion::activos()->get();
            return response()->json($duraciones);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener duraciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'descripcion' => 'required|string|max:255|unique:duraciones,descripcion',
            ]);

            $duracion = Duracion::create([
                'descripcion' => $request->descripcion
            ]);

            return response()->json([
                'message' => 'Duración registrada correctamente',
                'duracion' => $duracion
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar duración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Duracion $duracion): JsonResponse
    {
        try {
            $duracion->load(['casoTratamientos', 'resolucionTratamientos']);
            return response()->json($duracion);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener duración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Duracion $duracion): JsonResponse
    {
        try {
            $request->validate([
                'descripcion' => 'required|string|max:255|unique:duraciones,descripcion,' . $duracion->id,
            ]);

            $duracion->update([
                'descripcion' => $request->descripcion
            ]);

            return response()->json([
                'message' => 'Duración actualizada correctamente',
                'duracion' => $duracion
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar duración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Duracion $duracion): JsonResponse
    {
        try {
            if (
                $duracion->casoTratamientos()->count() > 0 ||
                $duracion->resolucionTratamientos()->count() > 0
            ) {
                return response()->json([
                    'message' => 'No se puede eliminar la duración porque está siendo usada en tratamientos'
                ], 422);
            }

            $duracion->delete();

            return response()->json([
                'message' => 'Duración eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar duración',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
