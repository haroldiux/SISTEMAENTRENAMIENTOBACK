<?php

namespace App\Http\Controllers;

use App\Models\CasoTratamiento;
use App\Models\Caso;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CasoTratamientoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CasoTratamiento::with([
                'caso:id,titulo',
                'medicamento:id,nombre',
                'dosis:id,descripcion',
                'frecuencia:id,descripcion',
                'duracion:id,descripcion'
            ]);

            if ($request->has('caso_id')) {
                $query->where('caso_id', $request->caso_id);
            }

            $tratamientos = $query->get();
            return response()->json($tratamientos);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tratamientos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'caso_id' => 'required|exists:casos,id',
                'medicamento_id' => 'required|exists:medicamentos,id',
                'dosis_id' => 'required|exists:dosis,id',
                'frecuencia_id' => 'required|exists:frecuencias,id',
                'duracion_id' => 'required|exists:duraciones,id',
                'observaciones' => 'nullable|string'
            ]);

            $tratamiento = CasoTratamiento::create($request->all());
            $tratamiento->load([
                'medicamento',
                'dosis',
                'frecuencia',
                'duracion'
            ]);

            return response()->json([
                'message' => 'Tratamiento registrado correctamente',
                'tratamiento' => $tratamiento
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(CasoTratamiento $casoTratamiento): JsonResponse
    {
        try {
            $casoTratamiento->load([
                'caso',
                'medicamento',
                'dosis',
                'frecuencia',
                'duracion'
            ]);
            return response()->json($casoTratamiento);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, CasoTratamiento $casoTratamiento): JsonResponse
    {
        try {
            $request->validate([
                'medicamento_id' => 'required|exists:medicamentos,id',
                'dosis_id' => 'required|exists:dosis,id',
                'frecuencia_id' => 'required|exists:frecuencias,id',
                'duracion_id' => 'required|exists:duraciones,id',
                'observaciones' => 'nullable|string'
            ]);

            $casoTratamiento->update($request->all());
            $casoTratamiento->load([
                'medicamento',
                'dosis',
                'frecuencia',
                'duracion'
            ]);

            return response()->json([
                'message' => 'Tratamiento actualizado correctamente',
                'tratamiento' => $casoTratamiento
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(CasoTratamiento $casoTratamiento): JsonResponse
    {
        try {
            $casoTratamiento->delete();

            return response()->json([
                'message' => 'Tratamiento eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
