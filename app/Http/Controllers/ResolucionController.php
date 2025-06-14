<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Resolucion;
use App\Models\ResolucionMotivo;
use App\Models\ResolucionExamen;
use App\Models\ResolucionManifestacion;
use App\Models\EvaluacionEstudiante;
use App\Models\EvaluacionCaso;
use App\Models\Caso;
use App\Models\Gestion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResolucionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'caso_id' => 'required|exists:casos,id',
            'estudiante_id' => 'required|exists:estudiantes,id',
            'motivos' => 'array',
            'elementos_clinicos' => 'array',
            'examenes' => 'array',
            'evaluacion_id' => 'nullable|exists:evaluaciones,id' // Nuevo campo opcional
        ]);

        try {
            // Obtener el caso
            $caso = Caso::with([
                'casoMotivos.motivo',
                'presentas.manifestacion',
                'presentas.variable',
                'presentas.localizacion',
                'examenComplementarios.examen'
            ])->findOrFail($request->caso_id);

            // Comenzar transacción
            DB::beginTransaction();

            // Calcular puntuación
            $puntuacion = $this->calcularPuntuacion($request, $caso);

            // Crear registro de resolución
            $resolucion = Resolucion::create([
                'estudiante_id' => $request->estudiante_id,
                'caso_id' => $request->caso_id,
                'gestion_id' => 1, // Ajustar para obtener la gestión activa
                'tipo' => 0, // Ajustar según tu sistema
                'puntaje' => $puntuacion['nota_final'],
                'fecha_resolucion' => now()
            ]);

            // Guardar motivos
            foreach ($request->motivos as $motivo) {
                ResolucionMotivo::create([
                    'resolucion_id' => $resolucion->id,
                    'motivo_id' => $motivo['id'],
                    'respuesta' => $motivo['seleccionado']
                ]);
            }

            // Guardar exámenes
            foreach ($request->examenes as $examen) {
                ResolucionExamen::create([
                    'resolucion_id' => $resolucion->id,
                    'examen_id' => $examen['id'],
                    'respuesta' => $examen['seleccionado']
                ]);
            }

            // Guardar elementos clínicos (signos, síntomas, síndromes)
            $this->guardarElementosClinicos($resolucion, $request->elementos_clinicos, $caso);

            // NUEVA SECCIÓN: Verificar si esta resolución es parte de una evaluación
            if ($request->has('evaluacion_id')) {
                $evaluacionId = $request->evaluacion_id;

                // Buscar la relación entre la evaluación y el caso
                $evaluacionCaso = EvaluacionCaso::where('evaluacion_id', $evaluacionId)
                    ->where('caso_id', $request->caso_id)
                    ->first();

                if ($evaluacionCaso) {
                    // Buscar la asignación del estudiante
                    $asignacion = EvaluacionEstudiante::where('evaluacion_caso_id', $evaluacionCaso->id)
                        ->where('estudiante_id', $request->estudiante_id)
                        ->first();

                    if ($asignacion) {
                        // Marcar como completada
                        $asignacion->completado = true;
                        $asignacion->save();

                        Log::info("Evaluación {$evaluacionId}, caso {$request->caso_id} marcado como completado para el estudiante {$request->estudiante_id}");
                    } else {
                        Log::warning("No se encontró asignación de estudiante para la evaluación {$evaluacionId}, caso {$request->caso_id}");
                    }
                } else {
                    Log::warning("No se encontró relación entre la evaluación {$evaluacionId} y el caso {$request->caso_id}");
                }
            }

            DB::commit();

            return response()->json([
                'mensaje' => 'Resolución registrada correctamente',
                'puntuacion' => $puntuacion,
                'resultados' => $this->prepararResultadosParaFrontend($request, $caso),
                'id' => $resolucion->id // Retornar el ID de la resolución para uso en el frontend
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Añadir más información para depuración
            Log::error('Error en resolución: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'Error al registrar la resolución',
                'details' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }


    /**
     * Calcula la puntuación de una resolución de caso clínico
     *
     * @param Request $request La solicitud con las respuestas del estudiante
     * @param Caso $caso El caso clínico a evaluar
     * @return array Puntuación y detalles de la evaluación
     */
    private function calcularPuntuacion($request, $caso)
    {
        // Inicializar contadores para cada sección
        $motivosCorrectos = 0;
        $motivosIncorrectos = 0;
        $examenesCorrectos = 0;
        $examenesIncorrectos = 0;
        $elementosCorrectos = 0;
        $elementosIncorrectos = 0;
        $diagnosticoCorrecto = 0;
        $tratamientosCorrectos = 0;
        $tratamientosIncorrectos = 0;

        // Evaluar motivos de consulta
        foreach ($request->motivos as $motivo) {
            if ($motivo['seleccionado']) {
                if ($motivo['respuestaCorrecta']) {
                    $motivosCorrectos++;
                } else {
                    $motivosIncorrectos++;
                }
            }
        }

        // Evaluar exámenes complementarios
        foreach ($request->examenes as $examen) {
            if ($examen['seleccionado']) {
                if ($examen['respuestaCorrecta']) {
                    $examenesCorrectos++;
                } else {
                    $examenesIncorrectos++;
                }
            }
        }

        // Evaluar elementos clínicos
        $elementosEvaluacion = $this->evaluarElementosClinicos($request->elementos_clinicos, $caso);
        $elementosCorrectos = $elementosEvaluacion['correctos'];
        $elementosIncorrectos = $elementosEvaluacion['incorrectos'];

        // Evaluar diagnóstico
        $diagnosticoCorrecto = 0;
        if ($request->has('diagnostico') && $request->diagnostico) {
            $resultadoDiagnostico = $this->verificarDiagnostico($request->diagnostico, $caso);
            if ($resultadoDiagnostico['correcto']) {
                $diagnosticoCorrecto = 1;
            }
        }

        // Evaluar tratamientos
        if ($request->has('tratamientos') && is_array($request->tratamientos)) {
            foreach ($request->tratamientos as $tratamiento) {
                // Solo evaluar tratamientos que tengan al menos el medicamento
                if (!empty($tratamiento['medicamento'])) {
                    // Para tratamientos, toda la fila debe estar correcta
                    if ($this->verificarTratamientoCompleto($tratamiento, $caso->casoTratamientos)) {
                        $tratamientosCorrectos++;
                    } else {
                        $tratamientosIncorrectos++;
                    }
                }
            }
        }

        // Aplicar regla: cada 2 incorrectos resta 1 correcto (solo para motivos y exámenes)
        $motivosPuntaje = max(0, $motivosCorrectos - floor($motivosIncorrectos / 2));
        $examenesPuntaje = max(0, $examenesCorrectos - floor($examenesIncorrectos / 2));

        // Para elementos clínicos y tratamientos no se aplica esta penalización
        $elementosPuntaje = $elementosCorrectos;
        $tratamientosPuntaje = $tratamientosCorrectos;

        // Calcular totales para el caso
        $motivosTotal = $caso->casoMotivos->count();
        $examenesTotal = $caso->examenComplementarios->count();
        $elementosTotal = $caso->presentas->count();
        $tratamientosTotal = $caso->casoTratamientos ? $caso->casoTratamientos->count() : 0;
        $hayDiagnostico = !empty($caso->diagnostico_id);

        // Verificar qué secciones están disponibles en este caso
        $hayExamenes = $examenesTotal > 0;
        $hayTratamientos = $tratamientosTotal > 0;

        // Definir pesos base según las secciones disponibles
        // Priorizar elementos clínicos y diagnóstico como se solicitó
        $pesoMotivos = 0.15;
        $pesoElementos = 0.35; // Mayor peso a elementos clínicos
        $pesoExamenes = $hayExamenes ? 0.15 : 0;
        $pesoDiagnostico = $hayDiagnostico ? 0.25 : 0;
        $pesoTratamientos = $hayTratamientos ? 0.10 : 0;

        // Normalizar pesos para que sumen 1
        $sumaPesos = $pesoMotivos + $pesoElementos + $pesoExamenes + $pesoDiagnostico + $pesoTratamientos;
        if ($sumaPesos > 0) {
            $pesoMotivos = $pesoMotivos / $sumaPesos;
            $pesoElementos = $pesoElementos / $sumaPesos;
            $pesoExamenes = $pesoExamenes / $sumaPesos;
            $pesoDiagnostico = $pesoDiagnostico / $sumaPesos;
            $pesoTratamientos = $pesoTratamientos / $sumaPesos;
        }

        // Calcular porcentajes por sección
        $porcentajeMotivos = $motivosTotal > 0 ? ($motivosPuntaje / $motivosTotal) * 100 : 0;
        $porcentajeElementos = $elementosTotal > 0 ? ($elementosPuntaje / $elementosTotal) * 100 : 0;
        $porcentajeExamenes = $examenesTotal > 0 ? ($examenesPuntaje / $examenesTotal) * 100 : 0;
        $porcentajeDiagnostico = $diagnosticoCorrecto * 100; // 0 o 100
        $porcentajeTratamientos = $tratamientosTotal > 0 ? ($tratamientosPuntaje / $tratamientosTotal) * 100 : 0;

        // Calcular nota ponderada final
        $notaFinal = (
            ($porcentajeMotivos / 100) * $pesoMotivos +
            ($porcentajeElementos / 100) * $pesoElementos +
            ($porcentajeExamenes / 100) * $pesoExamenes +
            ($porcentajeDiagnostico / 100) * $pesoDiagnostico +
            ($porcentajeTratamientos / 100) * $pesoTratamientos
        ) * 100;

        // Redondear a dos decimales
        $notaFinal = round($notaFinal, 2);

        // Devolver puntuación completa con todos los detalles
        return [
            'nota_final' => $notaFinal,
            'detalle' => [
                // Detalles para motivos de consulta
                'motivos' => [
                    'correctos' => $motivosCorrectos,
                    'incorrectos' => $motivosIncorrectos,
                    'puntaje' => $motivosPuntaje,
                    'total' => $motivosTotal,
                    'porcentaje' => round($porcentajeMotivos, 2),
                    'peso' => $pesoMotivos
                ],
                // Detalles para elementos clínicos
                'elementos_clinicos' => [
                    'correctos' => $elementosCorrectos,
                    'incorrectos' => $elementosIncorrectos,
                    'puntaje' => $elementosPuntaje,
                    'total' => $elementosTotal,
                    'porcentaje' => round($porcentajeElementos, 2),
                    'peso' => $pesoElementos
                ],
                // Detalles para exámenes complementarios
                'examenes' => [
                    'correctos' => $examenesCorrectos,
                    'incorrectos' => $examenesIncorrectos,
                    'puntaje' => $examenesPuntaje,
                    'total' => $examenesTotal,
                    'porcentaje' => round($porcentajeExamenes, 2),
                    'peso' => $pesoExamenes
                ],
                // Detalles para diagnóstico
                'diagnostico' => [
                    'correcto' => $diagnosticoCorrecto == 1,
                    'porcentaje' => round($porcentajeDiagnostico, 2),
                    'peso' => $pesoDiagnostico
                ],
                // Detalles para tratamientos
                'tratamientos' => [
                    'correctos' => $tratamientosCorrectos,
                    'incorrectos' => $tratamientosIncorrectos,
                    'puntaje' => $tratamientosPuntaje,
                    'total' => $tratamientosTotal,
                    'porcentaje' => round($porcentajeTratamientos, 2),
                    'peso' => $pesoTratamientos
                ]
            ]
        ];
    }

    /**
     * Verifica si un tratamiento completo coincide con alguno de los tratamientos del caso
     *
     * @param array $tratamiento Tratamiento a verificar
     * @param Collection $casoTratamientos Tratamientos correctos del caso
     * @return bool True si el tratamiento es correcto
     */
    private function verificarTratamientoCompleto($tratamiento, $casoTratamientos)
    {
        if (empty($casoTratamientos)) {
            return false;
        }

        // Extraer los IDs o valores según el formato recibido
        $medId = $this->extraerValor($tratamiento['medicamento']);
        $dosisId = $this->extraerValor($tratamiento['dosis']);
        $frecId = $this->extraerValor($tratamiento['frecuencia']);
        $durId = $this->extraerValor($tratamiento['duracion']);

        // Verificar si coincide con algún tratamiento del caso
        foreach ($casoTratamientos as $casoTratamiento) {
            // Todos los campos deben coincidir exactamente para ser correcto
            if (
                $casoTratamiento->medicamento_id == $medId &&
                $casoTratamiento->dosis_id == $dosisId &&
                $casoTratamiento->frecuencia_id == $frecId &&
                $casoTratamiento->duracion_id == $durId
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrae el valor o ID de un campo, manejando diferentes formatos posibles
     *
     * @param mixed $campo Campo a evaluar
     * @return mixed Valor extraído
     */
    private function extraerValor($campo)
    {
        if (is_null($campo)) {
            return null;
        }

        if (is_numeric($campo)) {
            return $campo;
        }

        if (is_string($campo)) {
            return $campo;
        }

        if (is_array($campo)) {
            if (isset($campo['id'])) return $campo['id'];
            if (isset($campo['value'])) return $campo['value'];
        }

        if (is_object($campo)) {
            if (isset($campo->id)) return $campo->id;
            if (isset($campo->value)) return $campo->value;
        }

        return $campo;
    }

    /**
     * Evalúa cada campo de un tratamiento por separado
     */
    private function evaluarCamposTratamiento($tratamiento, $casoTratamientos)
    {
        $estados = [
            'medicamento' => 'incorrecto',
            'dosis' => 'incorrecto',
            'frecuencia' => 'incorrecto',
            'duracion' => 'incorrecto'
        ];

        if (empty($casoTratamientos)) return $estados;

        $medId = $this->extraerValor($tratamiento['medicamento']);

        // Primero encontramos si el medicamento coincide con alguno
        foreach ($casoTratamientos as $casoTratamiento) {
            if ($casoTratamiento->medicamento_id == $medId) {
                $estados['medicamento'] = 'correcto';

                // Verificar los demás campos solo para este medicamento
                $dosisId = $this->extraerValor($tratamiento['dosis']);
                $frecId = $this->extraerValor($tratamiento['frecuencia']);
                $durId = $this->extraerValor($tratamiento['duracion']);

                if ($casoTratamiento->dosis_id == $dosisId) {
                    $estados['dosis'] = 'correcto';
                }

                if ($casoTratamiento->frecuencia_id == $frecId) {
                    $estados['frecuencia'] = 'correcto';
                }

                if ($casoTratamiento->duracion_id == $durId) {
                    $estados['duracion'] = 'correcto';
                }

                break; // Encontramos el medicamento, no seguir buscando
            }
        }

        return $estados;
    }

    private function evaluarElementosClinicos($elementosClinicos, $caso)
    {
        $correctos = 0;
        $incorrectos = 0;

        // Obtener todas las presentas del caso
        $presentasCaso = $caso->presentas;

        // NUEVA FUNCIÓN VERIFICAR ELEMENTO - MÁS ESTRICTA
        $verificarElemento = function ($elemento) use ($presentasCaso) {
            foreach ($presentasCaso as $presenta) {
                // Verificar si coincide la manifestación
                if ($presenta->manifestacion->nombre !== $elemento['manifestacion']) {
                    continue; // Manifestación no coincide, pasar al siguiente presenta
                }

                // Para síndromes solo verificamos la manifestación
                if ($presenta->manifestacion->tipo === 3) {
                    return true;
                }

                // Para signos y síntomas TODOS los campos deben ser correctos

                // 1. Verificar característica si se requiere
                if ($presenta->variable && $presenta->variable->caracteristica) {
                    if (
                        !isset($elemento['caracteristica']) ||
                        !$elemento['caracteristica'] ||
                        $presenta->variable->caracteristica->clasificacion !== $elemento['caracteristica']
                    ) {
                        continue; // Característica incorrecta, probar otro presenta
                    }
                }

                // 2. Verificar parámetro si se requiere
                if ($presenta->variable && $presenta->variable->parametro) {
                    if (
                        !isset($elemento['parametro']) ||
                        !$elemento['parametro'] ||
                        $presenta->variable->parametro !== $elemento['parametro']
                    ) {
                        continue; // Parámetro incorrecto, probar otro presenta
                    }
                }

                // 3. Verificar localización si se requiere
                if ($presenta->localizacion) {
                    if (
                        !isset($elemento['localizacion']) ||
                        !$elemento['localizacion'] ||
                        $presenta->localizacion->nombre !== $elemento['localizacion']
                    ) {
                        continue; // Localización incorrecta, probar otro presenta
                    }
                }

                // Si llegamos aquí, el elemento coincide completamente
                return true;
            }

            // Si hemos revisado todas las presentas y ninguna coincide completamente
            return false;
        };

        // Evaluar signos
        foreach ($elementosClinicos['signos'] as $signo) {
            if ($verificarElemento($signo)) {
                $correctos++;
            } else {
                $incorrectos++;
            }
        }

        // Evaluar síntomas
        foreach ($elementosClinicos['sintomas'] as $sintoma) {
            if ($verificarElemento($sintoma)) {
                $correctos++;
            } else {
                $incorrectos++;
            }
        }

        // Evaluar síndromes
        foreach ($elementosClinicos['sindromes'] as $sindrome) {
            if ($verificarElemento($sindrome)) {
                $correctos++;
            } else {
                $incorrectos++;
            }
        }

        return [
            'correctos' => $correctos,
            'incorrectos' => $incorrectos
        ];
    }

    private function guardarElementosClinicos($resolucion, $elementosClinicos, $caso)
    {
        // Función para guardar un elemento clínico
        $guardarElemento = function ($elemento, $tipo) use ($resolucion, $caso) {
            // Buscar manifestación por nombre
            $manifestacion = \App\Models\Manifestacion::where('nombre', $elemento['manifestacion'])
                ->where('tipo', $tipo)
                ->first();

            if (!$manifestacion) return;

            // Buscar característica por clasificación si existe
            $caracteristica_id = null;
            if (isset($elemento['caracteristica']) && $elemento['caracteristica']) {
                $caracteristica = \App\Models\Caracteristica::where('clasificacion', $elemento['caracteristica'])->first();
                $caracteristica_id = $caracteristica ? $caracteristica->id : null;
            }

            // Buscar variable por parámetro si existe
            $variable_id = null;
            if (isset($elemento['parametro']) && $elemento['parametro']) {
                $variable = \App\Models\Variable::where('parametro', $elemento['parametro'])
                    ->when($caracteristica_id, function ($query) use ($caracteristica_id) {
                        return $query->where('caracteristica_id', $caracteristica_id);
                    })
                    ->first();
                $variable_id = $variable ? $variable->id : null;
            }

            // Buscar localización por nombre si existe
            $localizacion_id = null;
            if (isset($elemento['localizacion']) && $elemento['localizacion']) {
                $localizacion = \App\Models\Localizacion::where('nombre', $elemento['localizacion'])->first();
                $localizacion_id = $localizacion ? $localizacion->id : null;
            }

            // Verificar si es correcto
            $correcto = false;
            foreach ($caso->presentas as $presenta) {
                if ($presenta->manifestacion_id === $manifestacion->id) {
                    if ($tipo === 3) { // Síndrome
                        $correcto = true;
                        break;
                    } else {
                        // Para signos y síntomas verificar variable, característica y localización
                        $coincideVariable = !$variable_id || $presenta->variable_id === $variable_id;
                        $coincideCaracteristica = !$caracteristica_id ||
                            ($presenta->variable &&
                                $presenta->variable->caracteristica_id === $caracteristica_id);
                        $coincideLocalizacion = !$localizacion_id || $presenta->localizacion_id === $localizacion_id;

                        if ($coincideVariable && $coincideCaracteristica && $coincideLocalizacion) {
                            $correcto = true;
                            break;
                        }
                    }
                }
            }

            // Crear registro
            ResolucionManifestacion::create([
                'resolucion_id' => $resolucion->id,
                'manifestacion_id' => $manifestacion->id,
                'variable_id' => $variable_id, // Puede ser null
                'localizacion_id' => $localizacion_id, // Puede ser null
                'respuesta' => $correcto
            ]);
        };

        // Guardar signos (tipo 1)
        foreach ($elementosClinicos['signos'] as $signo) {
            $guardarElemento($signo, 1);
        }

        // Guardar síntomas (tipo 2)
        foreach ($elementosClinicos['sintomas'] as $sintoma) {
            $guardarElemento($sintoma, 2);
        }

        // Guardar síndromes (tipo 3)
        foreach ($elementosClinicos['sindromes'] as $sindrome) {
            $guardarElemento($sindrome, 3);
        }
    }

    /**
     * Modifica la función prepararResultadosParaFrontend para mostrar estados por campo
     */
    private function prepararResultadosParaFrontend($request, $caso)
    {
        // Aquí generamos los resultados para que el frontend pueda
        // mostrar visualmente qué respuestas son correctas e incorrectas

        $resultados = [
            'motivos' => [],
            'examenes' => [],
            'elementos_clinicos' => [
                'signos' => [],
                'sintomas' => [],
                'sindromes' => []
            ],
            'diagnostico' => null,
            'tratamientos' => []
        ];

        // Preparar resultados de motivos (solo marcamos los seleccionados)
        $motivosCorrectos = $caso->casoMotivos->pluck('motivo_id')->toArray();
        foreach ($request->motivos as $motivo) {
            // Solo agregamos estado a los motivos seleccionados
            $estado = '';
            if ($motivo['seleccionado']) {
                $estado = in_array($motivo['id'], $motivosCorrectos) ? 'correcto' : 'incorrecto';
            }

            $resultados['motivos'][] = [
                'id' => $motivo['id'],
                'nombre' => $motivo['nombre'],
                'estado' => $estado,
                'seleccionado' => $motivo['seleccionado']
            ];
        }

        // Preparar resultados de exámenes (solo marcamos los seleccionados)
        $examenesCorrectos = $caso->examenComplementarios->pluck('examen_id')->toArray();
        foreach ($request->examenes as $examen) {
            // Solo agregamos estado a los exámenes seleccionados
            $estado = '';
            if ($examen['seleccionado']) {
                $estado = in_array($examen['id'], $examenesCorrectos) ? 'correcto' : 'incorrecto';
            }

            $resultados['examenes'][] = [
                'id' => $examen['id'],
                'nombre' => $examen['nombre'],
                'estado' => $estado,
                'seleccionado' => $examen['seleccionado']
            ];
        }

        // Elementos clínicos: ahora evaluamos cada campo por separado

        // Signos
        foreach ($request->elementos_clinicos['signos'] as $index => $signo) {
            // Evaluamos cada campo individualmente
            $estadosPorCampo = $this->evaluarCamposElementoClinico($signo, $caso, 1);

            $resultados['elementos_clinicos']['signos'][$index] = [
                'manifestacion' => $signo['manifestacion'],
                'caracteristica' => $signo['caracteristica'] ?? null,
                'parametro' => $signo['parametro'] ?? null,
                'localizacion' => $signo['localizacion'] ?? null,
                'estados' => $estadosPorCampo
            ];
        }

        // Síntomas
        foreach ($request->elementos_clinicos['sintomas'] as $index => $sintoma) {
            // Evaluamos cada campo individualmente
            $estadosPorCampo = $this->evaluarCamposElementoClinico($sintoma, $caso, 2);

            $resultados['elementos_clinicos']['sintomas'][$index] = [
                'manifestacion' => $sintoma['manifestacion'],
                'caracteristica' => $sintoma['caracteristica'] ?? null,
                'parametro' => $sintoma['parametro'] ?? null,
                'localizacion' => $sintoma['localizacion'] ?? null,
                'estados' => $estadosPorCampo
            ];
        }

        // Síndromes (solo tienen manifestación)
        foreach ($request->elementos_clinicos['sindromes'] as $index => $sindrome) {
            // Para síndromes solo evaluamos la manifestación
            $esCorrecta = $this->evaluarElementoClinico($sindrome, $caso, 3);

            $resultados['elementos_clinicos']['sindromes'][$index] = [
                'manifestacion' => $sindrome['manifestacion'],
                'estados' => [
                    'manifestacion' => $esCorrecta ? 'correcto' : 'incorrecto'
                ]
            ];
        }

        // Preparar diagnóstico para el frontend
        if ($request->has('diagnostico') && $request->diagnostico) {
            $resultadoDiagnostico = $this->verificarDiagnostico($request->diagnostico, $caso);
            $resultados['diagnostico'] = [
                'valor' => $resultadoDiagnostico['seleccionado'],
                'esperado' => $resultadoDiagnostico['esperado'],
                'estado' => $resultadoDiagnostico['correcto'] ? 'correcto' : 'incorrecto'
            ];
        }

        // Preparar tratamientos
        if ($request->has('tratamientos') && is_array($request->tratamientos)) {
            foreach ($request->tratamientos as $tratamiento) {
                // Solo evaluar tratamientos completos
                if (
                    isset($tratamiento['medicamento']) && $tratamiento['medicamento'] &&
                    isset($tratamiento['dosis']) && $tratamiento['dosis'] &&
                    isset($tratamiento['frecuencia']) && $tratamiento['frecuencia'] &&
                    isset($tratamiento['duracion']) && $tratamiento['duracion']
                ) {

                    // Evaluar estado de cada campo
                    $estados = $this->evaluarCamposTratamiento($tratamiento, $caso->casoTratamientos);

                    // Añadir al resultado
                    $resultados['tratamientos'][] = [
                        'medicamento' => $tratamiento['medicamento'],
                        'dosis' => $tratamiento['dosis'],
                        'frecuencia' => $tratamiento['frecuencia'],
                        'duracion' => $tratamiento['duracion'],
                        'estados' => $estados,
                        'estado' => ($estados['medicamento'] === 'correcto' &&
                            $estados['dosis'] === 'correcto' &&
                            $estados['frecuencia'] === 'correcto' &&
                            $estados['duracion'] === 'correcto') ? 'correcto' : 'incorrecto'
                    ];
                }
            }
        }

        return $resultados;
    }

    /**
     * Obtiene el diagnóstico directamente del caso
     *
     * @param mixed $diagnosticoEstudiante El diagnóstico seleccionado por el estudiante
     * @param Caso $caso El caso que contiene el diagnóstico correcto
     * @return array Información sobre el diagnóstico
     */
    private function verificarDiagnostico($diagnosticoEstudiante, $caso)
    {
        // Si no hay diagnóstico del estudiante o no hay diagnóstico en el caso
        if (empty($diagnosticoEstudiante) || empty($caso->diagnostico)) {
            return [
                'correcto' => false,
                'seleccionado' => $diagnosticoEstudiante,
                'esperado' => $caso->diagnostico
            ];
        }

        // Comparación directa con el diagnóstico del caso
        $esCorrecto = false;

        // Si el diagnóstico estudiante es un ID, compáralo con diagnóstico_id si existe
        if (is_numeric($diagnosticoEstudiante) && !empty($caso->diagnostico_id)) {
            $esCorrecto = (int)$diagnosticoEstudiante === (int)$caso->diagnostico_id;
        }
        // Si el diagnóstico es texto, comparamos con el campo diagnóstico del caso
        else if (is_string($diagnosticoEstudiante)) {
            // Comparación insensible a mayúsculas/minúsculas
            $esCorrecto = strtolower(trim($diagnosticoEstudiante)) === strtolower(trim($caso->diagnostico));
        }
        // Si el diagnóstico es un objeto o array, extrae el valor relevante
        else if (is_array($diagnosticoEstudiante) || is_object($diagnosticoEstudiante)) {
            $valor = null;

            if (is_array($diagnosticoEstudiante)) {
                $valor = $diagnosticoEstudiante['value'] ?? $diagnosticoEstudiante['id'] ?? $diagnosticoEstudiante['label'] ?? null;
            } else {
                $valor = $diagnosticoEstudiante->value ?? $diagnosticoEstudiante->id ?? $diagnosticoEstudiante->label ?? null;
            }

            if (!is_null($valor)) {
                if (is_numeric($valor) && !empty($caso->diagnostico_id)) {
                    $esCorrecto = (int)$valor === (int)$caso->diagnostico_id;
                } else if (is_string($valor)) {
                    $esCorrecto = strtolower(trim($valor)) === strtolower(trim($caso->diagnostico));
                }
            }
        }

        return [
            'correcto' => $esCorrecto,
            'seleccionado' => $diagnosticoEstudiante,
            'esperado' => $caso->diagnostico
        ];
    }

    /**
     * Nueva función para evaluar cada campo de un elemento clínico por separado
     */
    private function evaluarCamposElementoClinico($elemento, $caso, $tipo)
    {
        // Inicializar estados para cada campo
        $estados = [
            'manifestacion' => 'incorrecto',
            'caracteristica' => 'incorrecto',
            'parametro' => 'incorrecto',
            'localizacion' => 'incorrecto'
        ];

        // Manifestación correcta
        $manifestacionCorrecta = false;
        $presentaCoincidente = null;

        // Buscar presentas correspondientes al tipo y manifestación
        foreach ($caso->presentas as $presenta) {
            if ($presenta->manifestacion->tipo !== $tipo) continue;

            if ($presenta->manifestacion->nombre === $elemento['manifestacion']) {
                $manifestacionCorrecta = true;
                $estados['manifestacion'] = 'correcto';
                $presentaCoincidente = $presenta;
                break;
            }
        }

        // Si la manifestación es correcta, evaluar los demás campos
        if ($manifestacionCorrecta && $presentaCoincidente && $tipo !== 3) {
            // IMPORTANTE: Verificar si la característica es requerida
            $caracteristicaRequerida = $presentaCoincidente->variable &&
                $presentaCoincidente->variable->caracteristica;

            // Verificar característica
            if ($caracteristicaRequerida) {
                // Si se requiere característica pero no se especificó, es incorrecta
                if (!isset($elemento['caracteristica']) || !$elemento['caracteristica']) {
                    $estados['caracteristica'] = 'incorrecto';
                } else if ($presentaCoincidente->variable->caracteristica->clasificacion === $elemento['caracteristica']) {
                    $estados['caracteristica'] = 'correcto';
                }
            } else {
                // Si no se requiere característica, cualquier valor es aceptable
                $estados['caracteristica'] = 'correcto';
            }

            // IMPORTANTE: Verificar si el parámetro es requerido
            $parametroRequerido = $presentaCoincidente->variable &&
                $presentaCoincidente->variable->parametro;

            // Verificar parámetro
            if ($parametroRequerido) {
                // Si se requiere parámetro pero no se especificó, es incorrecto
                if (!isset($elemento['parametro']) || !$elemento['parametro']) {
                    $estados['parametro'] = 'incorrecto';
                } else if ($presentaCoincidente->variable->parametro === $elemento['parametro']) {
                    $estados['parametro'] = 'correcto';
                }
            } else {
                // Si no se requiere parámetro, cualquier valor es aceptable
                $estados['parametro'] = 'correcto';
            }

            // IMPORTANTE: Verificar si la localización es requerida
            $localizacionRequerida = $presentaCoincidente->localizacion;

            // Verificar localización
            if ($localizacionRequerida) {
                // Si se requiere localización pero no se especificó, es incorrecta
                if (!isset($elemento['localizacion']) || !$elemento['localizacion']) {
                    $estados['localizacion'] = 'incorrecto';
                } else if ($presentaCoincidente->localizacion->nombre === $elemento['localizacion']) {
                    $estados['localizacion'] = 'correcto';
                }
            } else {
                // Si no se requiere localización, cualquier valor es aceptable
                $estados['localizacion'] = 'correcto';
            }
        }

        return $estados;
    }
    private function evaluarElementoClinico($elemento, $caso, $tipo)
    {
        foreach ($caso->presentas as $presenta) {
            if ($presenta->manifestacion->tipo !== $tipo) continue;

            $coincideManifestacion = $presenta->manifestacion->nombre === $elemento['manifestacion'];

            // Para síndromes solo verificamos la manifestación
            if ($tipo === 3 && $coincideManifestacion) {
                return true;
            }

            // Para signos y síntomas verificamos todos los campos
            if ($coincideManifestacion) {
                // Verificar característica si está presente
                $coincideCaracteristica = !isset($elemento['caracteristica']) || !$elemento['caracteristica'] ||
                    ($presenta->variable &&
                        $presenta->variable->caracteristica &&
                        $presenta->variable->caracteristica->clasificacion === $elemento['caracteristica']);

                // Verificar variable/parámetro si está presente
                $coincideParametro = !isset($elemento['parametro']) || !$elemento['parametro'] ||
                    ($presenta->variable && $presenta->variable->parametro === $elemento['parametro']);

                // Verificar localización si está presente
                $coincideLocalizacion = !isset($elemento['localizacion']) || !$elemento['localizacion'] ||
                    ($presenta->localizacion && $presenta->localizacion->nombre === $elemento['localizacion']);

                if ($coincideCaracteristica && $coincideParametro && $coincideLocalizacion) {
                    return true;
                }
            }
        }

        return false;
    }
    /**
     * Obtiene todas las resoluciones de un estudiante específico
     *
     * @param int $id ID del estudiante
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResolucionesByEstudiante($id)
    {
        try {
            // Verificar que el estudiante existe
            $estudiante = \App\Models\Estudiante::findOrFail($id);

            // Obtener todas las resoluciones del estudiante
            $resoluciones = Resolucion::where('estudiante_id', $id)
                ->with([
                    'caso:id,titulo',   // Solo necesitamos información básica del caso
                    'caso.configuraciones'  // Necesario para saber tipo de resolución
                ])
                ->get([
                    'id',
                    'caso_id',
                    'puntaje',
                    'fecha_resolucion',
                    'tipo'
                ]);

            // Transformar datos para simplificar la respuesta
            $response = $resoluciones->map(function ($resolucion) {
                return [
                    'id' => $resolucion->id,
                    'caso_id' => $resolucion->caso_id,
                    'caso_titulo' => $resolucion->caso->titulo ?? 'Caso sin título',
                    'puntaje' => $resolucion->puntaje,
                    'fecha_resolucion' => $resolucion->fecha_resolucion,
                    'tiempo_limite' => $resolucion->caso->configuraciones->first()->tiempo_resolucion ?? 0,
                    'estado' => $resolucion->puntaje > 0 ? 'completado' : 'intentado'
                ];
            });

            return response()->json($response);
        } catch (\Exception $e) {
            // Log del error para debug
            Log::error('Error obteniendo resoluciones del estudiante: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al obtener resoluciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getHistorialCasos()
    {
        try {
            $user = Auth::user();
            $estudiante = DB::table('estudiantes')->where('user_id', $user->id)->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no es un estudiante'
                ], 403);
            }

            // Obtener gestión activa
            $gestionActiva = Gestion::where('estado', 1)->first();

            if (!$gestionActiva) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            // Obtener historial de casos resueltos
            $historialCasos = DB::table('casos')
                ->select(
                    'casos.id',
                    'casos.titulo',
                    'casos.nivel_dificultad',
                    'casos.materia_id',
                    'resolucions.puntaje',
                    'resolucions.fecha_resolucion'
                )
                ->join('resolucions', 'casos.id', '=', 'resolucions.caso_id')
                ->where('resolucions.estudiante_id', $estudiante->id)
                ->where('resolucions.gestion_id', $gestionActiva->id)
                ->orderBy('resolucions.fecha_resolucion', 'desc')
                ->get();

            // Obtener información de materias para cada caso
            $materiasIds = $historialCasos->pluck('materia_id')->unique();
            $materias = DB::table('materias')
                ->whereIn('id', $materiasIds)
                ->get()
                ->keyBy('id');

            // Añadir nombre de materia a cada caso
            $historialCasos = $historialCasos->map(function ($caso) use ($materias) {
                $caso->materia_nombre = $materias[$caso->materia_id]->nombre ?? 'Sin materia';
                return $caso;
            });

            return response()->json([
                'success' => true,
                'data' => $historialCasos
            ]);
        } catch (\Exception $e) {
            Log::error('Error en historial de casos: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial de casos',
                'error' => $e->getMessage(),
                'trace' => env('APP_DEBUG', false) ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}
