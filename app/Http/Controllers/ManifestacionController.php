<?php

namespace App\Http\Controllers;

use App\Models\Manifestacion;
use Illuminate\Http\Request;

class ManifestacionController extends Controller
{
    public function index()
    {
        return Manifestacion::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|integer|in:1,2,3'
        ]);

        $manifestacion = Manifestacion::create([
            'nombre' => strtoupper($request->nombre),
            'tipo' => $request->tipo
        ]);

        return response()->json([
            'message' => 'Manifestación registrada correctamente',
            'manifestacion' => $manifestacion
        ], 201);
    }

    public function update(Request $request, Manifestacion $manifestacion)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|integer|in:1,2,3'
        ]);

        $manifestacion->update([
            'nombre' => strtoupper($request->nombre),
            'tipo' => $request->tipo
        ]);

        return response()->json([
            'message' => 'Manifestación actualizada correctamente',
            'manifestacion' => $manifestacion
        ]);
    }
}
