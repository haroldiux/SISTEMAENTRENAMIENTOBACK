<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use Illuminate\Http\Request;

class ExamenController extends Controller
{
    public function index()
    {
        return Examen::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $examen = Examen::create($request->only('nombre'));

        return response()->json([
            'message' => 'Examen registrado correctamente',
            'examen' => $examen
        ], 201);
    }

    public function update(Request $request, Examen $examen)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $examen->update($request->only('nombre'));

        return response()->json([
            'message' => 'Examen actualizado correctamente',
            'examen' => $examen
        ]);
    }
}
