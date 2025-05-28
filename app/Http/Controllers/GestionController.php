<?php

namespace App\Http\Controllers;

use App\Models\Gestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GestionController extends Controller
{
    /**
     * Obtener todas las gestiones
     */
    public function index()
    {
        $gestiones = Gestion::orderBy('anio', 'desc')->get();
        return response()->json($gestiones);
    }

    /**
     * Obtener la gestión activa
     */
    public function getActiva()
    {
        $gestion = Gestion::where('estado', 1)->first();
        return response()->json($gestion);
    }

    /**
     * Almacenar una nueva gestión
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'anio' => 'required|integer|min:2020|max:2099',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $gestion = Gestion::create([
            'nombre' => $request->nombre,
            'anio' => $request->anio,
            'estado' => false // Por defecto, una nueva gestión no está activa
        ]);

        return response()->json($gestion, 201);
    }

    public function update(Request $request, Gestion $gestion)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'anio' => 'required|integer|min:2020|max:2099',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $gestion->nombre = $request->nombre;
        $gestion->anio = $request->anio;
        $gestion->save();

        return response()->json($gestion);
    }

    /**
     * Cambiar el estado de una gestión
     */
    public function cambiarEstado(Request $request, Gestion $gestion)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Si estamos activando una gestión, primero desactivamos todas las demás
        if ($request->estado) {
            DB::table('gestions')->update(['estado' => false]);
        }

        $gestion->estado = $request->estado;
        $gestion->save();

        return response()->json($gestion);
    }

    /**
     * Eliminar una gestión
     */
    public function destroy(Gestion $gestion)
    {
        // No permitir eliminar una gestión activa
        if ($gestion->estado) {
            return response()->json(['message' => 'No se puede eliminar una gestión activa'], 422);
        }

        // Verificar si hay grupos asociados
        if ($gestion->grupos()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar una gestión con grupos asociados'], 422);
        }

        // Verificar si hay resoluciones asociadas
        if ($gestion->resoluciones()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar una gestión con resoluciones asociadas'], 422);
        }

        $gestion->delete();
        return response()->json(null, 204);
    }
}
