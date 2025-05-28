<?php

namespace App\Http\Controllers;

use App\Models\Dosis;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DosisController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $dosis = Dosis::activos()->get();
            return response()->json($dosis);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener dosis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'descripcion' => 'required|string|max:255|unique:dosis,descripcion',
            ]);

            $dosis = Dosis::create([
                'descripcion' => $request->descripcion
            ]);

            return response()->json([
                'message' => 'Dosis registrada correctamente',
                'dosis' => $dosis
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar dosis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Dosis $dosis): JsonResponse
    {
        try {
            $dosis->load(['casoTratamientos', 'resolucionTratamientos']);
            return response()->json($dosis);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener dosis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Dosis $dosis): JsonResponse
    {
        try {
            $request->validate([
                'descripcion' => 'required|string|max:255|unique:dosis,descripcion,' . $dosis->id,
            ]);

            $dosis->update([
                'descripcion' => $request->descripcion
            ]);

            return response()->json([
                'message' => 'Dosis actualizada correctamente',
                'dosis' => $dosis
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar dosis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Dosis $dosis): JsonResponse
    {
        try {
            if (
                $dosis->casoTratamientos()->count() > 0 ||
                $dosis->resolucionTratamientos()->count() > 0
            ) {
                return response()->json([
                    'message' => 'No se puede eliminar la dosis porque está siendo usada en tratamientos'
                ], 422);
            }

            $dosis->delete();

            return response()->json([
                'message' => 'Dosis eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar dosis',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
