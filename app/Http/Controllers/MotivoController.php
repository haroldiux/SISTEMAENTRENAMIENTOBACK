<?php

namespace App\Http\Controllers;

use App\Models\Motivo;
use Illuminate\Http\Request;

class MotivoController extends Controller
{
    public function index()
    {
        return Motivo::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $nombre = strtoupper($request->nombre);

        $motivo = Motivo::create([
            'nombre' => $nombre
        ]);

        return response()->json([
            'message' => 'Motivo registrado correctamente',
            'motivo' => $motivo
        ], 201);
    }

    public function update(Request $request, Motivo $motivo)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $nombre = strtoupper($request->nombre);

        $motivo->update([
            'nombre' => $nombre
        ]);

        return response()->json([
            'message' => 'Motivo actualizado correctamente',
            'motivo' => $motivo
        ]);
    }
}
