<?php

namespace App\Http\Controllers;

use App\Models\Medicamento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MedicamentoController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $medicamentos = Medicamento::activos()->get();
            return response()->json($medicamentos);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener medicamentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255|unique:medicamentos,nombre',
            ]);

            $medicamento = Medicamento::create([
                'nombre' => $request->nombre
            ]);

            return response()->json([
                'message' => 'Medicamento registrado correctamente',
                'medicamento' => $medicamento
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar medicamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Medicamento $medicamento): JsonResponse
    {
        try {
            $medicamento->load(['casoTratamientos', 'resolucionTratamientos']);
            return response()->json($medicamento);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener medicamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Medicamento $medicamento): JsonResponse
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255|unique:medicamentos,nombre,' . $medicamento->id,
            ]);

            $medicamento->update([
                'nombre' => $request->nombre
            ]);

            return response()->json([
                'message' => 'Medicamento actualizado correctamente',
                'medicamento' => $medicamento
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar medicamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Medicamento $medicamento): JsonResponse
    {
        try {
            // Verificar si está siendo usado en tratamientos
            if (
                $medicamento->casoTratamientos()->count() > 0 ||
                $medicamento->resolucionTratamientos()->count() > 0
            ) {
                return response()->json([
                    'message' => 'No se puede eliminar el medicamento porque está siendo usado en tratamientos'
                ], 422);
            }

            $medicamento->delete();

            return response()->json([
                'message' => 'Medicamento eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar medicamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
