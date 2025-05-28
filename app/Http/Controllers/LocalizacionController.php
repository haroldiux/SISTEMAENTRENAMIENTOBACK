<?php

namespace App\Http\Controllers;

use App\Models\Localizacion;
use Illuminate\Http\Request;

class LocalizacionController extends Controller
{
    public function index()
    {
        return Localizacion::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $localizacion = Localizacion::create($request->only('nombre'));

        return response()->json([
            'message' => 'Localización registrada correctamente',
            'localizacion' => $localizacion
        ], 201);
    }

    public function update(Request $request, Localizacion $localizacion)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $localizacion->update($request->only('nombre'));

        return response()->json([
            'message' => 'Localización actualizada correctamente',
            'localizacion' => $localizacion
        ]);
    }
}
