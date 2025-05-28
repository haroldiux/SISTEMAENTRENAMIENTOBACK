<?php

namespace App\Http\Controllers;

use App\Models\Caracteristica;
use Illuminate\Http\Request;

class CaracteristicaController extends Controller
{
    public function index()
    {
        return Caracteristica::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'clasificacion' => 'required|string|max:255',
        ]);

        $caracteristica = Caracteristica::create([
            'clasificacion' => strtoupper($request->clasificacion)
        ]);

        return response()->json([
            'message' => 'Característica registrada correctamente',
            'caracteristica' => $caracteristica
        ], 201);
    }

    public function update(Request $request, Caracteristica $caracteristica)
    {
        $request->validate([
            'clasificacion' => 'required|string|max:255',
        ]);

        $caracteristica->update([
            'clasificacion' => strtoupper($request->clasificacion)
        ]);

        return response()->json([
            'message' => 'Característica actualizada correctamente',
            'caracteristica' => $caracteristica
        ]);
    }
}
