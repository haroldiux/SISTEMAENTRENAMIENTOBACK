<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CasoController extends Controller
{
    /**
     * Lista todos los casos con sus relaciones asociadas
     */
    public function index()
    {
        $casos = Caso::with([
            'configuraciones',
            'presentas',
            'casoMotivos',
            'examenComplementarios',
            'casoTratamientos.medicamento',
            'casoTratamientos.dosis',
            'casoTratamientos.frecuencia',
            'casoTratamientos.duracion',
            'docente:id,nombres,apellidos' // Incluye solo los campos necesarios para optimizar la consulta
        ])->get();

        return response()->json($casos);
    }

    public function detalleCompleto(Caso $caso)
    {
        // Cargar caso principal con relaciones
        $caso->load([
            'configuraciones',
            'presentas.manifestacion',
            'presentas.variable',
            'presentas.localizacion',
            'casoMotivos.motivo',
            'examenComplementarios.examen',
            'casoTratamientos.medicamento',
            'casoTratamientos.dosis',
            'casoTratamientos.frecuencia',
            'casoTratamientos.duracion'
        ]);

        // MOTIVOS DE CONSULTA (aleatorios como ya lo teníamos)
        $motivosCorrectosIds = $caso->casoMotivos->pluck('motivo_id')->toArray();
        $motivosCorrectos = \App\Models\Motivo::whereIn('id', $motivosCorrectosIds)->get();
        $cantidadFaltante = 15 - $motivosCorrectos->count();
        $otrosMotivos = \App\Models\Motivo::whereNotIn('id', $motivosCorrectosIds)
            ->inRandomOrder()
            ->limit($cantidadFaltante)
            ->get();
        $motivoConsultas_estudiante = $motivosCorrectos->map(function ($motivo) {
            return [
                'id' => $motivo->id,
                'nombre' => $motivo->nombre,
                'respuestaCorrecta' => 1,
                'respuestaEstudiante' => 0,
            ];
        })->concat($otrosMotivos->map(function ($motivo) {
            return [
                'id' => $motivo->id,
                'nombre' => $motivo->nombre,
                'respuestaCorrecta' => 0,
                'respuestaEstudiante' => 0,
            ];
        }))->shuffle()->values();

        // EXAMENES (aleatorios como ya lo teníamos)
        $examenesDelCaso = $caso->examenComplementarios->pluck('examen');
        // Si no hay exámenes para el caso, retorna un array vacío en lugar de generar aleatorios
        if ($examenesDelCaso->isEmpty()) {
            $examenes_estudiante = [];
        } else {
            $cantidadFaltanteExamenes = 15 - $examenesDelCaso->count();
            $otrosExamenes = \App\Models\Examen::whereNotIn('id', $examenesDelCaso->pluck('id'))
                ->inRandomOrder()
                ->limit($cantidadFaltanteExamenes)
                ->get();
            $examenes_estudiante = $examenesDelCaso->map(function ($examen) {
                return [
                    'id' => $examen->id,
                    'nombre' => $examen->nombre,
                    'respuestaCorrecta' => 1,
                    'respuestaEstudiante' => 0,
                ];
            })->concat($otrosExamenes->map(function ($examen) {
                return [
                    'id' => $examen->id,
                    'nombre' => $examen->nombre,
                    'respuestaCorrecta' => 0,
                    'respuestaEstudiante' => 0,
                ];
            }))->shuffle()->values();
        }

        // MANIFESTACIONES, VARIABLES Y LOCALIZACIONES
        // Obtener todos los presentas agrupados por tipo de manifestación
        $presentasPorTipo = [1 => [], 2 => [], 3 => []];

        foreach ($caso->presentas as $presenta) {
            $tipoManifestacion = $presenta->manifestacion->tipo;
            if (isset($presentasPorTipo[$tipoManifestacion])) {
                $presentasPorTipo[$tipoManifestacion][] = $presenta;
            }
        }

        // Opciones para el frontend
        $opcionesSignos = [
            'manifestaciones' => [],
            'caracteristicas' => [],
            'parametros' => [],
            'localizacion' => [],
        ];

        $opcionesSintomas = [
            'manifestaciones' => [],
            'caracteristicas' => [],
            'parametros' => [],
            'localizacion' => [],
        ];

        $opcionesSindromes = [
            'manifestaciones' => [],
            // Los síndromes no tienen características, variables ni localización
        ];

        // 1. OBTENER TODAS LAS CARACTERÍSTICAS (sin filtrar) - Solo para signos y síntomas
        $todasCaracteristicas = \App\Models\Caracteristica::all()->pluck('clasificacion')->toArray();
        $opcionesSignos['caracteristicas'] = $todasCaracteristicas;
        $opcionesSintomas['caracteristicas'] = $todasCaracteristicas;

        // 2. MANIFESTACIONES (15 por tipo)
        $manifestacionesCorrectas = [1 => [], 2 => [], 3 => []];

        foreach ([1, 2, 3] as $tipo) {
            // Obtener manifestaciones correctas de presentas
            $manifestacionesCorrectasIds = collect($presentasPorTipo[$tipo])->pluck('manifestacion_id')->unique()->toArray();
            $manifestacionesCorrectas[$tipo] = \App\Models\Manifestacion::whereIn('id', $manifestacionesCorrectasIds)
                ->where('tipo', $tipo)
                ->get()
                ->pluck('nombre')
                ->toArray();

            // Obtener otras manifestaciones aleatorias hasta completar 15
            $cantidadFaltanteManifestaciones = 15 - count($manifestacionesCorrectas[$tipo]);
            $otrasManifestaciones = \App\Models\Manifestacion::whereNotIn('id', $manifestacionesCorrectasIds)
                ->where('tipo', $tipo)
                ->inRandomOrder()
                ->limit($cantidadFaltanteManifestaciones)
                ->get()
                ->pluck('nombre')
                ->toArray();

            // Combinar y mezclar
            $todasManifestaciones = array_merge($manifestacionesCorrectas[$tipo], $otrasManifestaciones);
            shuffle($todasManifestaciones);

            // Asignar según el tipo
            if ($tipo == 1) {
                $opcionesSignos['manifestaciones'] = $todasManifestaciones;
            } elseif ($tipo == 2) {
                $opcionesSintomas['manifestaciones'] = $todasManifestaciones;
            } elseif ($tipo == 3) {
                $opcionesSindromes['manifestaciones'] = $todasManifestaciones;
            }
        }

        // 3. VARIABLES (15 en total) - Solo para signos y síntomas
        $variablesCorrectasIds = $caso->presentas->pluck('variable_id')->filter()->unique()->toArray();
        $variablesCorrectas = \App\Models\Variable::whereIn('id', $variablesCorrectasIds)
            ->get()
            ->pluck('parametro')
            ->toArray();

        $cantidadFaltanteVariables = 15 - count($variablesCorrectas);
        $otrasVariables = \App\Models\Variable::whereNotIn('id', $variablesCorrectasIds)
            ->inRandomOrder()
            ->limit($cantidadFaltanteVariables)
            ->get()
            ->pluck('parametro')
            ->toArray();

        $todasVariables = array_merge($variablesCorrectas, $otrasVariables);
        shuffle($todasVariables);

        // Asignar solo a signos y síntomas
        $opcionesSignos['parametros'] = $todasVariables;
        $opcionesSintomas['parametros'] = $todasVariables;

        // 4. LOCALIZACIONES (15 en total) - Solo para signos y síntomas
        $localizacionesCorrectasIds = $caso->presentas->pluck('localizacion_id')->filter()->unique()->toArray();
        $localizacionesCorrectas = \App\Models\Localizacion::whereIn('id', $localizacionesCorrectasIds)
            ->get()
            ->pluck('nombre')
            ->toArray();

        $cantidadFaltanteLocalizaciones = 15 - count($localizacionesCorrectas);
        $otrasLocalizaciones = \App\Models\Localizacion::whereNotIn('id', $localizacionesCorrectasIds)
            ->inRandomOrder()
            ->limit($cantidadFaltanteLocalizaciones)
            ->get()
            ->pluck('nombre')
            ->toArray();

        $todasLocalizaciones = array_merge($localizacionesCorrectas, $otrasLocalizaciones);
        shuffle($todasLocalizaciones);

        // Asignar solo a signos y síntomas
        $opcionesSignos['localizacion'] = $todasLocalizaciones;
        $opcionesSintomas['localizacion'] = $todasLocalizaciones;

        // Crear estructura de presentas para el frontend (misma que tenías)
        $presentas_estudiante = [
            'tipo1' => [],
            'tipo2' => [],
            'tipo3' => [],
        ];

        foreach ($caso->presentas as $pres) {
            $tipo = $pres->manifestacion->tipo;
            if (isset($presentas_estudiante["tipo$tipo"])) {
                $presentas_estudiante["tipo$tipo"][] = [
                    'id' => $pres->id,
                    'manifestacion_id' => $pres->manifestacion_id,
                    'nombreManifestacion' => $pres->manifestacion->nombre,
                    'respuestaCorrecta' => 1,
                ];
            }
        }

        // Para verificación, obtener elementos correctos
        $motivosCorrectos = $motivosCorrectos->map(function ($motivo) {
            return [
                'id' => $motivo->id,
                'nombre' => $motivo->nombre
            ];
        })->values();

        $examenesCorrectos = $examenesDelCaso->map(function ($examen) {
            return [
                'id' => $examen->id,
                'nombre' => $examen->nombre
            ];
        })->values();

        // TRATAMIENTOS (aleatorios + correctos)
        $todosMedicamentos  = \App\Models\Medicamento::all();
        $todasDosis         = \App\Models\Dosis::all();
        $todasFrecuencias   = \App\Models\Frecuencia::all();
        $todasDuraciones    = \App\Models\Duracion::all();

        // 1) Fila de tratamientos correctos
        $correctos = $caso->casoTratamientos->map(function ($t) {
            return [
                'medicamento'    => $t->medicamento->nombre,
                'dosis'          => $t->dosis->descripcion,
                'frecuencia'     => $t->frecuencia->descripcion,
                'duracion'       => $t->duracion->descripcion,
                'medicamento_id' => $t->medicamento_id,
                'dosis_id'       => $t->dosis_id,
                'frecuencia_id'  => $t->frecuencia_id,
                'duracion_id'    => $t->duracion_id,
                'respuestaCorrecta' => 1,
            ];
        });

        // 2) Completar hasta 15 filas con datos aleatorios
        $faltantes = 15 - $correctos->count();
        $randomRows = collect();
        for ($i = 0; $i < max(0, $faltantes); $i++) {
            $m = $todosMedicamentos->random();
            $d = $todasDosis->random();
            $f = $todasFrecuencias->random();
            $du = $todasDuraciones->random();
            $randomRows->push([
                'medicamento'    => $m->nombre,
                'dosis'          => $d->descripcion,
                'frecuencia'     => $f->descripcion,
                'duracion'       => $du->descripcion,
                'medicamento_id' => $m->id,
                'dosis_id'       => $d->id,
                'frecuencia_id'  => $f->id,
                'duracion_id'    => $du->id,
                'respuestaCorrecta' => 0,
            ]);
        }

        // Mezclar y convertir a array
        $filasTratamiento = $correctos
            ->concat($randomRows)
            ->shuffle()
            ->values()
            ->toArray();

        // MODIFICACIÓN: Limitar opciones a 15 y asegurar que incluyan las correctas
        $medicamentosCorrectos = $caso->casoTratamientos->pluck('medicamento.nombre')->unique()->toArray();
        $dosisCorrectas = $caso->casoTratamientos->pluck('dosis.descripcion')->unique()->toArray();
        $frecuenciasCorrectas = $caso->casoTratamientos->pluck('frecuencia.descripcion')->unique()->toArray();
        $duracionesCorrectas = $caso->casoTratamientos->pluck('duracion.descripcion')->unique()->toArray();

        // Función para obtener opciones (máximo 15, incluyendo correctas, mezcladas)
        function obtenerOpciones($correctas, $todas, $limite = 15)
        {
            // Primero incluir todas las correctas
            $opciones = collect($correctas);

            // Añadir opciones aleatorias hasta completar el límite
            $faltantes = $limite - $opciones->count();
            if ($faltantes > 0) {
                $opcionesAleatorias = $todas->whereNotIn('nombre', $correctas)
                    ->whereNotIn('descripcion', $correctas) // Para manejar tanto 'nombre' como 'descripcion'
                    ->random(min($faltantes, $todas->whereNotIn('nombre', $correctas)->whereNotIn('descripcion', $correctas)->count()))
                    ->map(function ($item) {
                        return isset($item->nombre) ? $item->nombre : $item->descripcion;
                    });

                $opciones = $opciones->concat($opcionesAleatorias);
            }

            // Mezclar y devolver
            return $opciones->shuffle()->values()->toArray();
        }

        // Opciones para cada select (máximo 15 por tipo)
        $opcionesTratamientos = [
            'medicamentos' => obtenerOpciones($medicamentosCorrectos, $todosMedicamentos, 15),
            'dosis' => obtenerOpciones($dosisCorrectas, $todasDosis, 15),
            'frecuencias' => obtenerOpciones($frecuenciasCorrectas, $todasFrecuencias, 15),
            'duraciones' => obtenerOpciones($duracionesCorrectas, $todasDuraciones, 15),
        ];

        return response()->json([
            'caso' => $caso,
            'motivoConsultas_estudiante' => $motivoConsultas_estudiante,
            'examenes_estudiante' => $examenes_estudiante,
            'presentas_estudiante' => $presentas_estudiante,
            'opciones' => [
                'signos' => $opcionesSignos,
                'sintomas' => $opcionesSintomas,
                'sindromes' => $opcionesSindromes
            ],
            // Para verificación: elementos correctos
            'motivos_correctos' => $motivosCorrectos,
            'examenes_correctos' => $examenesCorrectos,
            'manifestaciones_correctas' => [
                'signos' => $manifestacionesCorrectas[1],
                'sintomas' => $manifestacionesCorrectas[2],
                'sindromes' => $manifestacionesCorrectas[3],
            ],
            'variables_correctas' => $variablesCorrectas,
            'localizaciones_correctas' => $localizacionesCorrectas,
            'tratamientos_correctos' => $caso->casoTratamientos->map(function ($tratamiento) {
                return [
                    'medicamento' => $tratamiento->medicamento->nombre,
                    'dosis' => $tratamiento->dosis->descripcion,
                    'frecuencia' => $tratamiento->frecuencia->descripcion,
                    'duracion' => $tratamiento->duracion->descripcion,
                    'observaciones' => $tratamiento->observaciones
                ];
            }),
            'diagnostico_correcto' => $caso->diagnostico,
            'tratamientos_estudiante'       => $filasTratamiento,
            'opciones_tratamientos'         => $opcionesTratamientos,
        ]);
    }

    /**
     * Registra un nuevo caso con configuración, motivos, presentas, exámenes complementarios y tratamientos
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'nivel_dificultad' => 'required|integer',
            'enunciado' => 'required|string',
            'diagnostico' => 'nullable|string',
            'materia_id' => 'required|exists:materias,id',
            'docente_id' => 'required|exists:docentes,id',

            'configuracion' => 'required|array',
            'configuracion.tiempo_resolucion' => 'required|integer',
            'configuracion.tiempo_vista_enunciado' => 'required|integer',
            'configuracion.tipo' => 'required|integer|in:0,1',

            'caso_motivos' => 'required|array|min:1',
            'caso_motivos.*.motivo_id' => 'required|exists:motivos,id',

            'presentas' => 'required|array|min:1',
            'presentas.*.manifestacion_id' => 'required|exists:manifestacions,id',
            'presentas.*.localizacion_id' => 'nullable|exists:localizacions,id',
            'presentas.*.variable_id' => 'nullable|exists:variables,id',

            'examenes_complementarios' => 'nullable|array',
            'examenes_complementarios.*.examen_id' => 'required|exists:examens,id',
            'examenes_complementarios.*.archivo' => 'nullable',

            'tratamientos' => 'nullable|array',
            'tratamientos.*.medicamento_id' => 'required|exists:medicamentos,id',
            'tratamientos.*.dosis_id' => 'required|exists:dosis,id',
            'tratamientos.*.frecuencia_id' => 'required|exists:frecuencias,id',
            'tratamientos.*.duracion_id' => 'required|exists:duraciones,id',
            'tratamientos.*.observaciones' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Crear caso
            $caso = Caso::create($request->only([
                'titulo',
                'nivel_dificultad',
                'enunciado',
                'diagnostico',
                'materia_id',
                'docente_id'
            ]));

            // Crear configuración (solo una)
            $caso->configuraciones()->create($request->input('configuracion'));

            // Crear motivos
            foreach ($request->input('caso_motivos') as $motivo) {
                $caso->casoMotivos()->create($motivo);
            }

            // Crear presentas
            foreach ($request->input('presentas') as $presenta) {
                // Si algún campo es null, asegurar que se envíe como null
                $presenta['localizacion_id'] = $presenta['localizacion_id'] ?? null;
                $presenta['variable_id'] = $presenta['variable_id'] ?? null;

                $caso->presentas()->create($presenta);
            }

            // Crear exámenes complementarios (si hay)
            if ($request->filled('examenes_complementarios')) {
                foreach ($request->input('examenes_complementarios') as $examen) {
                    $archivoPath = null;
                    // Procesar archivo si viene en base64
                    if (!empty($examen['archivo']) && isset($examen['archivo']['base64'])) {
                        $archivoBase64 = $examen['archivo']['base64'];
                        $nombreArchivo = $examen['archivo']['name'] ?? Str::random(16) . '.pdf';
                        $tipoArchivo = $examen['archivo']['type'] ?? 'application/pdf';

                        // Extraer el contenido del archivo base64 (eliminar el prefijo "data:image/png;base64,")
                        $contenido = base64_decode(explode(',', $archivoBase64)[1] ?? $archivoBase64);

                        // Guardar el archivo
                        $archivoPath = Storage::disk('public')->put('examenes/' . $nombreArchivo, $contenido);

                        // Si queremos la ruta completa:
                        $archivoPath = 'examenes/' . $nombreArchivo;
                    }

                    // Crear registro de examen con la ruta del archivo
                    $caso->examenComplementarios()->create([
                        'examen_id' => $examen['examen_id'],
                        'archivo' => $archivoPath
                    ]);
                }
            }

            // Crear tratamientos (si hay)
            if ($request->filled('tratamientos')) {
                foreach ($request->input('tratamientos') as $tratamiento) {
                    $caso->casoTratamientos()->create([
                        'medicamento_id' => $tratamiento['medicamento_id'],
                        'dosis_id' => $tratamiento['dosis_id'],
                        'frecuencia_id' => $tratamiento['frecuencia_id'],
                        'duracion_id' => $tratamiento['duracion_id'],
                        'observaciones' => $tratamiento['observaciones'] ?? null
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Caso registrado correctamente',
                'caso' => $caso->load([
                    'configuraciones',
                    'presentas',
                    'casoMotivos',
                    'examenComplementarios',
                    'casoTratamientos.medicamento',
                    'casoTratamientos.dosis',
                    'casoTratamientos.frecuencia',
                    'casoTratamientos.duracion'
                ])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error al registrar el caso',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Caso $caso)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'nivel_dificultad' => 'required|integer',
            'enunciado' => 'required|string',
            'diagnostico' => 'nullable|string',
            'materia_id' => 'required|exists:materias,id',
            'docente_id' => 'required|exists:docentes,id',

            'configuracion' => 'required|array',
            'configuracion.tiempo_resolucion' => 'required|integer',
            'configuracion.tiempo_vista_enunciado' => 'required|integer',
            'configuracion.tipo' => 'required|integer|in:0,1',

            'caso_motivos' => 'required|array|min:1',
            'caso_motivos.*.motivo_id' => 'required|exists:motivos,id',

            'presentas' => 'required|array|min:1',
            'presentas.*.manifestacion_id' => 'required|exists:manifestacions,id',
            'presentas.*.localizacion_id' => 'nullable|exists:localizacions,id',
            'presentas.*.variable_id' => 'nullable|exists:variables,id',

            'examenes_complementarios' => 'nullable|array',
            'examenes_complementarios.*.examen_id' => 'required|exists:examens,id',
            'examenes_complementarios.*.archivo' => 'nullable|string',

            'tratamientos' => 'nullable|array',
            'tratamientos.*.medicamento_id' => 'required|exists:medicamentos,id',
            'tratamientos.*.dosis_id' => 'required|exists:dosis,id',
            'tratamientos.*.frecuencia_id' => 'required|exists:frecuencias,id',
            'tratamientos.*.duracion_id' => 'required|exists:duraciones,id',
            'tratamientos.*.observaciones' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $caso->update($request->only([
                'titulo',
                'nivel_dificultad',
                'enunciado',
                'diagnostico',
                'materia_id',
                'docente_id'
            ]));

            // Configuración (reemplazar la anterior)
            $caso->configuraciones()->delete();
            $caso->configuraciones()->create($request->input('configuracion'));

            // Motivos
            $caso->casoMotivos()->delete();
            foreach ($request->input('caso_motivos') as $motivo) {
                $caso->casoMotivos()->create($motivo);
            }

            // Presentaciones
            $caso->presentas()->delete();
            foreach ($request->input('presentas') as $presenta) {
                $caso->presentas()->create($presenta);
            }

            // Exámenes complementarios
            $caso->examenComplementarios()->delete();
            if ($request->filled('examenes_complementarios')) {
                foreach ($request->input('examenes_complementarios') as $examen) {
                    $caso->examenComplementarios()->create($examen);
                }
            }

            // Tratamientos
            $caso->casoTratamientos()->delete();
            if ($request->filled('tratamientos')) {
                foreach ($request->input('tratamientos') as $tratamiento) {
                    $caso->casoTratamientos()->create([
                        'medicamento_id' => $tratamiento['medicamento_id'],
                        'dosis_id' => $tratamiento['dosis_id'],
                        'frecuencia_id' => $tratamiento['frecuencia_id'],
                        'duracion_id' => $tratamiento['duracion_id'],
                        'observaciones' => $tratamiento['observaciones'] ?? null
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Caso actualizado correctamente',
                'caso' => $caso->load([
                    'configuraciones',
                    'casoMotivos',
                    'presentas',
                    'examenComplementarios',
                    'casoTratamientos.medicamento',
                    'casoTratamientos.dosis',
                    'casoTratamientos.frecuencia',
                    'casoTratamientos.duracion'
                ])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al actualizar el caso',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function solicitarExamenes(Request $request, Caso $caso)
    {
        $request->validate([
            'examenes' => 'required|array',
        ]);

        // Obtener los nombres de exámenes solicitados
        $examenesNombres = $request->input('examenes');

        // Buscar los exámenes que coinciden con los nombres solicitados
        $examenesObjetos = \App\Models\Examen::whereIn('nombre', $examenesNombres)->get();
        $examenesIds = $examenesObjetos->pluck('id')->toArray();

        // Obtener TODOS los exámenes complementarios del caso
        // Ya no filtramos por los solicitados para mostrar todos
        $examenesDisponibles = $caso->examenComplementarios()
            ->with('examen')
            ->get()
            ->map(function ($examenComplementario) {
                return [
                    'id' => $examenComplementario->id,
                    'nombre' => $examenComplementario->examen->nombre,
                    'archivo' => $examenComplementario->archivo
                ];
            });

        // Registrar la solicitud (aquí podrías guardar un registro de la solicitud si es necesario)

        return response()->json([
            'mensaje' => 'Solicitud de exámenes procesada correctamente',
            'examenesDisponibles' => $examenesDisponibles,
            'totalSolicitados' => count($examenesNombres),
            'totalDisponibles' => $examenesDisponibles->count()
        ]);
    }

    /**
     * Eliminar un caso
     */
    public function destroy(Caso $caso)
    {
        try {
            DB::beginTransaction();

            // Verificar si el caso tiene resoluciones asociadas
            if ($caso->resoluciones()->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar el caso porque tiene resoluciones asociadas'
                ], 422);
            }

            // Eliminar archivos de exámenes complementarios
            foreach ($caso->examenComplementarios as $examen) {
                if ($examen->archivo) {
                    Storage::disk('public')->delete($examen->archivo);
                }
            }

            // Eliminar el caso (las relaciones se eliminan automáticamente por cascade)
            $caso->delete();

            DB::commit();

            return response()->json([
                'message' => 'Caso eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar el caso',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener casos por filtros
     */
    public function filtrar(Request $request)
    {
        try {
            $query = Caso::with([
                'materia:id,nombre',
                'docente:id,nombres,apellidos',
                'configuraciones'
            ]);

            // Filtros
            if ($request->filled('materia_id')) {
                $query->where('materia_id', $request->materia_id);
            }

            if ($request->filled('nivel_dificultad')) {
                $query->where('nivel_dificultad', $request->nivel_dificultad);
            }

            if ($request->filled('docente_id')) {
                $query->where('docente_id', $request->docente_id);
            }

            if ($request->filled('con_diagnostico')) {
                if ($request->con_diagnostico) {
                    $query->whereNotNull('diagnostico');
                } else {
                    $query->whereNull('diagnostico');
                }
            }

            if ($request->filled('con_tratamientos')) {
                if ($request->con_tratamientos) {
                    $query->whereHas('casoTratamientos');
                } else {
                    $query->whereDoesntHave('casoTratamientos');
                }
            }

            if ($request->filled('buscar')) {
                $buscar = $request->buscar;
                $query->where(function ($q) use ($buscar) {
                    $q->where('titulo', 'like', "%{$buscar}%")
                        ->orWhere('enunciado', 'like', "%{$buscar}%")
                        ->orWhere('diagnostico', 'like', "%{$buscar}%");
                });
            }

            // Ordenamiento
            $query->orderBy($request->get('orden_por', 'created_at'), $request->get('direccion', 'desc'));

            // Paginación
            $casos = $query->paginate($request->get('per_page', 15));

            return response()->json($casos);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al filtrar casos',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function getDiagnosticosAleatorios(Caso $caso)
    {
        // Si el caso no tiene diagnóstico, devolvemos array vacío
        if (! $caso->diagnostico) {
            return response()->json(['diagnosticos' => []]);
        }

        // Tomar hasta 14 diagnósticos de otros casos, únicos y al azar
        $otros = Caso::whereNotNull('diagnostico')
            ->where('diagnostico', '<>', '')
            ->where('id', '<>', $caso->id)
            ->inRandomOrder()
            ->pluck('diagnostico')
            ->unique()
            ->take(14)
            ->toArray();

        // Añadir el diagnóstico "correcto" y mezclar
        $lista = array_merge($otros, [$caso->diagnostico]);
        shuffle($lista);

        return response()->json(['diagnosticos' => $lista]);
    }
}
