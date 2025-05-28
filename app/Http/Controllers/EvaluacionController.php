<?php

namespace App\Http\Controllers;

use App\Models\Evaluacion;
use App\Models\EvaluacionCaso;
use App\Models\EvaluacionEstudiante;
use App\Models\Gestion;
use App\Models\Grupo;
use App\Models\Caso;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Resolucion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // Añadir esta importación
use Illuminate\Support\Facades\Log; // Añadir esta importación

class EvaluacionController extends Controller
{
    /**
     * Obtener casos disponibles según criterios
     */
    public function getCasosDisponibles(Request $request)
    {
        $request->validate([
            'materia_id' => 'required|exists:materias,id',
            'filtro_nivel_dificultad' => 'required|integer',
            'solo_propios' => 'boolean'
        ]);

        $query = Caso::where('materia_id', $request->materia_id)
            ->where('nivel_dificultad', $request->filtro_nivel_dificultad);

        if ($request->solo_propios) {
            $query->where('docente_id', auth()->user()->docente->id);
        }

        $casos = $query->select('id', 'titulo', 'nivel_dificultad as nivel', 'materia_id', 'enunciado as descripcion', 'docente_id')
            ->with(['docente:id,nombres,apellidos'])
            ->get();


        // Mapear los datos para tener el nombre completo del docente
        $casos = $casos->map(function ($caso) {
            if ($caso->docente) {
                $caso->docente->nombre_completo = $caso->docente->nombres . ' ' . $caso->docente->apellidos;
            }
            return $caso;
        });

        return response()->json([
            'casos' => $casos,
            'total' => count($casos)
        ]);
    }

    /**
     * Obtener todas las evaluaciones creadas por un docente
     */
    public function getEvaluacionesDocente()
    {
        $docente = auth()->user()->docente;

        if (!$docente) {
            return response()->json([
                'message' => 'Usuario no es un docente'
            ], 403);
        }

        $evaluaciones = Evaluacion::where('docente_id', $docente->id)
            ->with(['materia:id,nombre', 'grupo:id,nombre', 'gestion:id,nombre'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'evaluaciones' => $evaluaciones
        ]);
    }

    /**
     * Crear una nueva evaluación
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'filtro_nivel_dificultad' => 'required|integer',
            'materia_id' => 'required|exists:materias,id',
            'grupo_id' => 'required|exists:grupos,id',
            'modo_seleccion' => 'required|in:aleatorio,manual',
            'limite_casos' => 'required_if:modo_seleccion,aleatorio|integer|min:1',
            'casos_seleccionados' => 'required_if:modo_seleccion,manual|array',
            'casos_seleccionados.*' => 'required_if:modo_seleccion,manual|exists:casos,id',
        ]);

        // Obtener gestión académica activa
        $gestionActiva = Gestion::where('estado', 1)->first();

        if (!$gestionActiva) {
            return response()->json([
                'message' => 'No hay una gestión académica activa'
            ], 400);
        }

        // Verificar que el grupo pertenezca a la materia y gestión activa
        $grupo = Grupo::where('id', $request->grupo_id)
            ->where('materia_id', $request->materia_id)
            ->where('gestion_id', $gestionActiva->id)
            ->first();

        if (!$grupo) {
            return response()->json([
                'message' => 'El grupo seleccionado no corresponde a la materia o no pertenece a la gestión activa'
            ], 400);
        }

        // Iniciar transacción
        DB::beginTransaction();

        try {
            // Crear evaluación
            $evaluacion = new Evaluacion();
            $evaluacion->titulo = $request->titulo;
            $evaluacion->fecha_inicio = $request->fecha_inicio;
            $evaluacion->fecha_fin = $request->fecha_fin;
            $evaluacion->filtro_nivel_dificultad = $request->filtro_nivel_dificultad;
            $evaluacion->modo_seleccion = $request->modo_seleccion;
            $evaluacion->materia_id = $request->materia_id;
            $evaluacion->grupo_id = $request->grupo_id;
            $evaluacion->gestion_id = $gestionActiva->id;
            $evaluacion->docente_id = auth()->user()->docente->id;

            if ($request->modo_seleccion === 'aleatorio') {
                $evaluacion->limite_casos = $request->limite_casos;
            }

            $evaluacion->save();

            // Obtener casos
            if ($request->modo_seleccion === 'manual') {
                // Usar casos seleccionados
                $casoIds = $request->casos_seleccionados;
            } else {
                // Obtener casos aleatorios
                $casoIds = Caso::where('materia_id', $request->materia_id)
                    ->where('nivel_dificultad', $request->filtro_nivel_dificultad)
                    ->inRandomOrder()
                    ->limit($request->limite_casos)
                    ->pluck('id')
                    ->toArray();
            }

            // Guardar casos seleccionados
            $evaluacionCasos = [];
            foreach ($casoIds as $casoId) {
                $evaluacionCaso = EvaluacionCaso::create([
                    'evaluacion_id' => $evaluacion->id,
                    'caso_id' => $casoId
                ]);
                $evaluacionCasos[] = $evaluacionCaso;
            }

            // Obtener estudiantes del grupo
            $estudiantes = Inscripcion::where('grupo_id', $request->grupo_id)
                ->pluck('estudiante_id')
                ->toArray();

            // Asignar casos a estudiantes aleatoriamente
            if (count($estudiantes) > 0 && count($evaluacionCasos) > 0) {
                foreach ($estudiantes as $estudianteId) {
                    // Seleccionar un caso aleatorio para este estudiante
                    $evaluacionCaso = $evaluacionCasos[array_rand($evaluacionCasos)];

                    // Crear asignación
                    EvaluacionEstudiante::create([
                        'evaluacion_caso_id' => $evaluacionCaso->id,
                        'estudiante_id' => $estudianteId,
                        'completado' => 0
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Evaluación creada correctamente',
                'evaluacion' => $evaluacion
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear la evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalle de una evaluación
     */
    /**
     * Obtener detalle de una evaluación
     */
    public function show($id)
    {
        $evaluacion = Evaluacion::with([
            'materia:id,nombre',
            'grupo:id,nombre',
            'gestion:id,nombre,anio',
            'evaluacionCasos.caso:id,titulo,nivel_dificultad',
            'evaluacionCasos.asignacionesEstudiantes.estudiante:id,nombres,apellido1,apellido2',
            'docente:id,nombres,apellidos'
        ])
            ->findOrFail($id);

        // Verificar permisos - permitir tanto a docentes como a estudiantes ver la evaluación
        $esDocente = auth()->user()->docente && auth()->user()->docente->id === $evaluacion->docente_id;
        $esEstudiante = auth()->user()->estudiante && $this->estudianteTieneAsignacion(auth()->user()->estudiante->id, $id);

        if (!$esDocente && !$esEstudiante) {
            return response()->json([
                'message' => 'No tiene permisos para ver esta evaluación'
            ], 403);
        }

        return response()->json([
            'evaluacion' => $evaluacion
        ]);
    }

    /**
     * Verificar si un estudiante tiene asignación para una evaluación
     */
    private function estudianteTieneAsignacion($estudianteId, $evaluacionId)
    {
        return EvaluacionEstudiante::whereHas('evaluacionCaso', function ($query) use ($evaluacionId) {
            $query->where('evaluacion_id', $evaluacionId);
        })
            ->where('estudiante_id', $estudianteId)
            ->exists();
    }

    /**
     * Obtener evaluaciones pendientes para un estudiante (solo activas)
     */
    public function getPendientesEstudiante()
    {
        $estudiante = auth()->user()->estudiante;
        $ahora = now();

        if (!$estudiante) {
            return response()->json([
                'message' => 'Usuario no es un estudiante'
            ], 403);
        }

        // Obtener solo evaluaciones activas (fecha fin mayor o igual a ahora)
        $evaluacionesEstudiante = EvaluacionEstudiante::where('estudiante_id', $estudiante->id)
            ->where('completado', 0)
            ->whereHas('evaluacionCaso.evaluacion', function ($query) use ($ahora) {
                // La evaluación está activa si:
                // 1. La fecha de inicio es menor o igual a ahora (ya comenzó)
                // 2. Y la fecha de fin es mayor o igual a ahora (no ha terminado)
                $query->where('fecha_inicio', '<=', $ahora)
                    ->where('fecha_fin', '>=', $ahora);
            })
            ->with([
                'evaluacionCaso.evaluacion:id,titulo,fecha_inicio,fecha_fin,materia_id,docente_id',
                'evaluacionCaso.evaluacion.materia:id,nombre',
                'evaluacionCaso.evaluacion.docente:id,nombres,apellidos'
            ])
            ->get();

        // Transformar resultado para mantener la estructura esperada por el frontend
        $evaluaciones = $evaluacionesEstudiante->map(function ($asignacion) {
            return [
                'id'         => $asignacion->id,
                'evaluacion' => $asignacion->evaluacionCaso->evaluacion,
                'caso_id'    => $asignacion->evaluacionCaso->caso->id,
                'completado' => $asignacion->completado,
                'intentada'  => $asignacion->intentada ?? false,
            ];
        });

        return response()->json([
            'evaluaciones' => $evaluaciones
        ]);
    }

    /**
     * Obtener evaluaciones vencidas (no realizadas) para un estudiante
     */
    public function getEvaluacionesVencidas()
    {
        $estudiante = auth()->user()->estudiante;
        $ahora = now();

        if (!$estudiante) {
            return response()->json([
                'message' => 'Usuario no es un estudiante'
            ], 403);
        }

        // Obtener solo evaluaciones vencidas (fecha fin menor a ahora)
        $evaluacionesEstudiante = EvaluacionEstudiante::where('estudiante_id', $estudiante->id)
            ->where('completado', 0)
            ->whereHas('evaluacionCaso.evaluacion', function ($query) use ($ahora) {
                // La evaluación está vencida si la fecha de fin es menor a ahora
                $query->where('fecha_fin', '<', $ahora);
            })
            ->with([
                'evaluacionCaso.evaluacion:id,titulo,fecha_inicio,fecha_fin,materia_id,docente_id',
                'evaluacionCaso.evaluacion.materia:id,nombre',
                'evaluacionCaso.evaluacion.docente:id,nombres,apellidos'
            ])
            ->get();

        // Transformar resultado para mantener la estructura esperada por el frontend
        $evaluaciones = $evaluacionesEstudiante->map(function ($asignacion) {
            return [
                'id'         => $asignacion->id,
                'evaluacion' => $asignacion->evaluacionCaso->evaluacion,
                'caso_id'    => $asignacion->evaluacionCaso->caso->id,
                'completado' => $asignacion->completado,
                'intentada'  => $asignacion->intentada ?? false,
            ];
        });

        return response()->json([
            'evaluaciones' => $evaluaciones
        ]);
    }
    /**
     * Obtener caso para estudiante en una evaluación
     */
    public function getCasoEstudiante($evaluacionId)
    {
        $estudiante = auth()->user()->estudiante;

        if (!$estudiante) {
            return response()->json([
                'message' => 'Usuario no es un estudiante'
            ], 403);
        }

        $now = now();

        // Obtener evaluación
        $evaluacion = Evaluacion::findOrFail($evaluacionId);

        // Verificar si la evaluación está activa
        if ($now < $evaluacion->fecha_inicio || $now > $evaluacion->fecha_fin) {
            return response()->json([
                'message' => 'La evaluación no está activa en este momento'
            ], 400);
        }

        // Obtener asignación del estudiante
        $asignacion = EvaluacionEstudiante::whereHas('evaluacionCaso', function ($query) use ($evaluacionId) {
            $query->where('evaluacion_id', $evaluacionId);
        })
            ->where('estudiante_id', $estudiante->id)
            ->with('evaluacionCaso.caso:id,titulo')
            ->first();

        if (!$asignacion) {
            return response()->json([
                'message' => 'No tienes asignación para esta evaluación'
            ], 404);
        }

        // Verificar si ya completó
        if ($asignacion->completado) {
            return response()->json([
                'message' => 'Ya has completado esta evaluación'
            ], 400);
        }

        // Obtener el caso (información mínima)
        $caso = $asignacion->evaluacionCaso->caso;

        return response()->json([
            'evaluacion' => $evaluacion,
            'caso' => $caso
        ]);
    }

    /**
     * Marcar evaluación como completada por estudiante
     * (Se llama después de enviar la resolución)
     */
    public function marcarCompletada(Request $request, $evaluacionId)
    {
        $estudiante = auth()->user()->estudiante;
        $resolucionId = $request->resolucion_id;

        if (!$estudiante) {
            return response()->json([
                'message' => 'Usuario no es un estudiante'
            ], 403);
        }

        // Verificar que la resolución exista y corresponda al estudiante
        $resolucion = Resolucion::where('id', $resolucionId)
            ->where('estudiante_id', $estudiante->id)
            ->first();

        if (!$resolucion) {
            return response()->json([
                'message' => 'La resolución no existe o no pertenece a este estudiante'
            ], 404);
        }

        // Obtener asignación del estudiante
        $asignacion = EvaluacionEstudiante::whereHas('evaluacionCaso', function ($query) use ($evaluacionId) {
            $query->where('evaluacion_id', $evaluacionId);
        })
            ->where('estudiante_id', $estudiante->id)
            ->first();

        if (!$asignacion) {
            return response()->json([
                'message' => 'No tienes asignación para esta evaluación'
            ], 404);
        }

        $asignacion->completado = true;
        $asignacion->save();

        return response()->json([
            'message' => 'Evaluación marcada como completada'
        ]);
    }

    /**
     * Obtener estadísticas de una evaluación
     */
    public function getEstadisticas($id)
    {
        $evaluacion = Evaluacion::findOrFail($id);

        // Verificar permisos
        if (auth()->user()->docente->id !== $evaluacion->docente_id) {
            return response()->json([
                'message' => 'No tiene permisos para ver esta evaluación'
            ], 403);
        }

        // Obtener total de estudiantes asignados
        $totalEstudiantes = DB::table('evaluacion_estudiantes')
            ->join('evaluacion_casos', 'evaluacion_estudiantes.evaluacion_caso_id', '=', 'evaluacion_casos.id')
            ->where('evaluacion_casos.evaluacion_id', $id)
            ->count();

        // Obtener total de estudiantes que completaron
        $completados = DB::table('evaluacion_estudiantes')
            ->join('evaluacion_casos', 'evaluacion_estudiantes.evaluacion_caso_id', '=', 'evaluacion_casos.id')
            ->where('evaluacion_casos.evaluacion_id', $id)
            ->where('evaluacion_estudiantes.completado', true)
            ->count();

        // Calcular porcentaje
        $porcentajeCompletado = $totalEstudiantes > 0
            ? round(($completados / $totalEstudiantes) * 100, 2)
            : 0;

        return response()->json([
            'total_estudiantes' => $totalEstudiantes,
            'completados' => $completados,
            'pendientes' => $totalEstudiantes - $completados,
            'porcentaje_completado' => $porcentajeCompletado
        ]);
    }

    /**
     * Obtener caso para una evaluación específica
     */
    public function getCasoEvaluacion($id)
    {
        $docente = auth()->user()->docente;
        $estudiante = auth()->user()->estudiante;

        // Verificar permisos (docente o estudiante con asignación)
        $evaluacion = Evaluacion::findOrFail($id);

        if ($docente && $docente->id === $evaluacion->docente_id) {
            // Es el docente de la evaluación
        } elseif ($estudiante) {
            // Verificar que el estudiante tenga asignada esta evaluación
            $asignacion = EvaluacionEstudiante::whereHas('evaluacionCaso', function ($query) use ($id) {
                $query->where('evaluacion_id', $id);
            })
                ->where('estudiante_id', $estudiante->id)
                ->first();

            if (!$asignacion) {
                return response()->json([
                    'message' => 'No tienes asignada esta evaluación'
                ], 403);
            }

            // Verificar si la evaluación está activa
            $now = now();
            if ($now < $evaluacion->fecha_inicio || $now > $evaluacion->fecha_fin) {
                return response()->json([
                    'message' => 'La evaluación no está activa en este momento'
                ], 400);
            }

            // Obtener el caso asignado a este estudiante
            $evaluacionCaso = $asignacion->evaluacionCaso;
            $caso = $evaluacionCaso->caso;

            return response()->json([
                'evaluacion' => $evaluacion,
                'caso' => $caso
            ]);
        } else {
            return response()->json([
                'message' => 'No tienes permisos para ver esta evaluación'
            ], 403);
        }
    }

    /**
     * Marcar una evaluación como intentada por un estudiante
     * (Se llama antes de iniciar la evaluación)
     */
    public function marcarIntentada(Request $request, $id)
    {
        $request->validate([
            'estudiante_id' => 'required|exists:estudiantes,id'
        ]);

        $evaluacion = Evaluacion::findOrFail($id);
        $now = now();

        // Verificar si la evaluación está activa
        if ($now < $evaluacion->fecha_inicio || $now > $evaluacion->fecha_fin) {
            return response()->json([
                'message' => 'La evaluación no está activa en este momento'
            ], 400);
        }

        // Obtener asignación del estudiante
        $asignacion = EvaluacionEstudiante::whereHas('evaluacionCaso', function ($query) use ($id) {
            $query->where('evaluacion_id', $id);
        })
            ->where('estudiante_id', $request->estudiante_id)
            ->first();

        if (!$asignacion) {
            return response()->json([
                'message' => 'No tienes asignación para esta evaluación'
            ], 404);
        }

        // Verificar si ya completó
        if ($asignacion->completado) {
            return response()->json([
                'message' => 'Ya has completado esta evaluación'
            ], 400);
        }

        // Marcar como intentada verificando la estructura de la tabla
        if (Schema::hasColumn('evaluacion_estudiantes', 'intentada')) {
            // La columna existe, actualizamos normalmente
            $asignacion->intentada = true;
            $asignacion->fecha_intento = now();
            $asignacion->save();

            Log::info("Evaluación {$id} marcada como intentada por estudiante {$request->estudiante_id}");
        } else {
            // La columna no existe, registramos el intento en logs y notificamos
            Log::warning("Columna 'intentada' no encontrada en la tabla evaluacion_estudiantes. Ejecute la migración");
            Log::info("Evaluación {$id} intentada por estudiante {$request->estudiante_id} (sin registro en BD)");
        }

        return response()->json([
            'message' => 'Evaluación marcada como intentada'
        ]);
    }

    /**
     * Eliminar una evaluación
     */
    public function destroy($id)
    {
        $evaluacion = Evaluacion::findOrFail($id);

        // Verificar permisos
        if (auth()->user()->docente->id !== $evaluacion->docente_id) {
            return response()->json([
                'message' => 'No tiene permisos para eliminar esta evaluación'
            ], 403);
        }

        // Verificar si la evaluación ya ha comenzado
        if (now() > $evaluacion->fecha_inicio) {
            return response()->json([
                'message' => 'No se puede eliminar una evaluación que ya ha comenzado'
            ], 400);
        }

        // Iniciar transacción
        DB::beginTransaction();

        try {
            // Eliminar todas las asignaciones a estudiantes
            EvaluacionEstudiante::whereHas('evaluacionCaso', function ($query) use ($id) {
                $query->where('evaluacion_id', $id);
            })
                ->delete();

            // Eliminar casos de la evaluación
            EvaluacionCaso::where('evaluacion_id', $id)->delete();

            // Eliminar evaluación
            $evaluacion->delete();

            DB::commit();

            return response()->json([
                'message' => 'Evaluación eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar la evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener caso específico para un estudiante en una evaluación
     */
    public function getCasoEvaluacionEstudiante(Request $request, $id)
    {
        // Verificar si hay un usuario autenticado
        if (!auth()->user()) {
            return response()->json([
                'message' => 'No hay un usuario autenticado'
            ], 401);
        }

        // Verificar si el usuario tiene un estudiante asociado
        if (!auth()->user()->estudiante) {
            return response()->json([
                'message' => 'Usuario no tiene un perfil de estudiante asociado'
            ], 403);
        }

        $estudiante = auth()->user()->estudiante;

        if (!$estudiante) {
            return response()->json([
                'message' => 'Usuario no es un estudiante'
            ], 403);
        }

        // Obtener evaluación
        $evaluacion = Evaluacion::findOrFail($id);

        // Obtener asignación del estudiante
        $asignacion = EvaluacionEstudiante::whereHas('evaluacionCaso', function ($query) use ($id) {
            $query->where('evaluacion_id', $id);
        })
            ->where('estudiante_id', $estudiante->id)
            ->with('evaluacionCaso.caso')
            ->first();

        if (!$asignacion) {
            return response()->json([
                'message' => 'No tienes asignación para esta evaluación'
            ], 404);
        }

        // Obtener el caso asignado a este estudiante
        $caso = $asignacion->evaluacionCaso->caso;

        return response()->json([
            'evaluacion' => $evaluacion,
            'caso' => $caso,
            'intentada' => $asignacion->intentada,
            'completada' => $asignacion->completado
        ]);
    }
}
