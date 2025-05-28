<?php

namespace App\Http\Controllers;

use App\Models\Materia;
use Illuminate\Http\Request;

class MateriaController extends Controller
{
    // Listar materias
    public function index()
    {
        return Materia::all(); // puedes paginar si son muchas
    }

    // Registrar nueva materia
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255'
        ]);

        $materia = Materia::create($request->only('nombre'));

        return response()->json(['message' => 'Materia registrada', 'materia' => $materia], 201);
    }

    // Editar materia existente
    public function update(Request $request, Materia $materia)
    {
        $request->validate([
            'nombre' => 'required|string|max:255'
        ]);

        $materia->update($request->only('nombre'));

        return response()->json(['message' => 'Materia actualizada', 'materia' => $materia]);
    }
}
