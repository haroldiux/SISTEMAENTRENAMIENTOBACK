<?php

namespace App\Http\Controllers;

use App\Models\Variable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VariableController extends Controller
{
    /**
     * Obtener variables filtradas por manifestacion_id y caracteristica_id
     */
    public function index()
    {
        $variables = Variable::with(['manifestacion', 'caracteristica'])->get();

        return response()->json($variables);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'parametro' => 'required|string|max:255',
            'manifestacion_id' => 'required|exists:manifestacions,id',
            'caracteristica_id' => 'required|exists:caracteristicas,id',
        ]);

        $variable = Variable::create([
            'parametro' => strtoupper($request->parametro),
            'manifestacion_id' => $request->manifestacion_id,
            'caracteristica_id' => $request->caracteristica_id,
        ]);

        return response()->json([
            'message' => 'Parámetro registrado correctamente',
            'variable' => $variable
        ], 201);
    }

    public function update(Request $request, Variable $variable): JsonResponse
    {
        $request->validate([
            'parametro' => 'required|string|max:255',
            'manifestacion_id' => 'required|exists:manifestacions,id',
            'caracteristica_id' => 'required|exists:caracteristicas,id',
        ]);

        $variable->update([
            'parametro' => strtoupper($request->parametro),
            'manifestacion_id' => $request->manifestacion_id,
            'caracteristica_id' => $request->caracteristica_id,
        ]);

        return response()->json([
            'message' => 'Parámetro actualizado correctamente',
            'variable' => $variable
        ]);
    }
    public function filtrarPorManifestacionYCaracteristica(Request $request): JsonResponse
    {
        $variables = Variable::query();

        if ($request->filled('manifestacion_id')) {
            $variables->where('manifestacion_id', $request->manifestacion_id);
        }

        if ($request->filled('caracteristica_id')) {
            $variables->where('caracteristica_id', $request->caracteristica_id);
        }

        return response()->json($variables->get());
    }
}
