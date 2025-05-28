<?php

namespace App\Http\Controllers;

use App\Models\Frecuencia;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class FrecuenciaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $frecuencias = Frecuencia::activos()->get();
            return response()->json($frecuencias);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener frecuencias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'descripcion' => 'required|string|max:255|unique:frecuencias,descripcion',
            ]);

            $frecuencia = Frecuencia::create([
                'descripcion' => $request->descripcion
            ]);

            return response()->json([
                'message' => 'Frecuencia registrada correctamente',
                'frecuencia' => $frecuencia
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar frecuencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Frecuencia $frecuencia): JsonResponse
    {
        try {
            $frecuencia->load(['casoTratamientos', 'resolucionTratamientos']);
            return response()->json($frecuencia);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener frecuencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Frecuencia $frecuencia): JsonResponse
    {
        try {
            $request->validate([
                'descripcion' => 'required|string|max:255|unique:frecuencias,descripcion,' . $frecuencia->id,
            ]);

            $frecuencia->update([
                'descripcion' => $request->descripcion
            ]);

            return response()->json([
                'message' => 'Frecuencia actualizada correctamente',
                'frecuencia' => $frecuencia
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar frecuencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Frecuencia $frecuencia): JsonResponse
    {
        try {
            if (
                $frecuencia->casoTratamientos()->count() > 0 ||
                $frecuencia->resolucionTratamientos()->count() > 0
            ) {
                return response()->json([
                    'message' => 'No se puede eliminar la frecuencia porque está siendo usada en tratamientos'
                ], 422);
            }

            $frecuencia->delete();

            return response()->json([
                'message' => 'Frecuencia eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar frecuencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
