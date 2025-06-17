<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Caso;
use App\Models\Estudiante;
use App\Models\Resolucion;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\EvaluacionCaso;
use App\Models\Grupo;
use App\Models\Gestion;
use App\Models\Materia;
use App\Models\Docente;

class ReportesController extends Controller
{
    /**
     * Obtener reporte de usuarios (estudiantes y/o docentes) con filtros
     */
    public function usuarios(Request $request)
    {
        try {
            // Obtener y validar parámetros
            $tipo = $request->input('tipo', 'todos'); // 'estudiantes', 'docentes' o 'todos'
            $gestionId = $request->input('gestion_id');
            $materiaId = $request->input('materia_id');
            $grupoId = $request->input('grupo_id');

            // Array para almacenar resultados
            $usuarios = [];

            // Consultar estudiantes si se solicitó
            if ($tipo === 'estudiantes' || $tipo === 'todos') {
                $estudiantesQuery = DB::table('estudiantes')
                    ->join('users', 'estudiantes.user_id', '=', 'users.id')
                    ->select(
                        'estudiantes.id',
                        'estudiantes.nombres',
                        'estudiantes.apellido1',
                        'estudiantes.apellido2',
                        'estudiantes.correo',
                        'estudiantes.telefono',
                        'estudiantes.estado',
                        DB::raw("'Estudiante' as tipo")
                    );

                // Aplicar filtro por grupo
                if ($grupoId) {
                    $estudiantesQuery->join('inscripcions', 'estudiantes.id', '=', 'inscripcions.estudiante_id')
                        ->where('inscripcions.grupo_id', $grupoId);
                }
                // Aplicar filtro por materia y gestión
                else if ($materiaId && $gestionId) {
                    $estudiantesQuery->join('inscripcions', 'estudiantes.id', '=', 'inscripcions.estudiante_id')
                        ->join('grupos', 'inscripcions.grupo_id', '=', 'grupos.id')
                        ->where('grupos.materia_id', $materiaId)
                        ->where('grupos.gestion_id', $gestionId);
                }
                // Aplicar filtro solo por gestión
                else if ($gestionId) {
                    $estudiantesQuery->join('inscripcions', 'estudiantes.id', '=', 'inscripcions.estudiante_id')
                        ->join('grupos', 'inscripcions.grupo_id', '=', 'grupos.id')
                        ->where('grupos.gestion_id', $gestionId);
                }

                $estudiantes = $estudiantesQuery->get();

                // Para cada estudiante, obtener grupos y materias inscritas
                foreach ($estudiantes as $estudiante) {
                    // Obtener grupos del estudiante
                    $gruposQuery = DB::table('inscripcions')
                        ->join('grupos', 'inscripcions.grupo_id', '=', 'grupos.id')
                        ->join('materias', 'grupos.materia_id', '=', 'materias.id')
                        ->where('inscripcions.estudiante_id', $estudiante->id);

                    // Aplicar filtro por gestión si existe
                    if ($gestionId) {
                        $gruposQuery->where('grupos.gestion_id', $gestionId);
                    }

                    $grupos = $gruposQuery->select(
                        'grupos.nombre as grupo_nombre',
                        'materias.nombre as materia_nombre'
                    )->get();

                    // Formatear grupos y materias como texto
                    $gruposTexto = $grupos->pluck('grupo_nombre')->implode(', ');
                    $materiasTexto = $grupos->pluck('materia_nombre')->unique()->implode(', ');

                    // Añadir información adicional
                    $estudiante->grupos = $gruposTexto;
                    $estudiante->materias = $materiasTexto;

                    // Añadir a la lista de usuarios
                    $usuarios[] = $estudiante;
                }
            }

            // Consultar docentes si se solicitó
            if ($tipo === 'docentes' || $tipo === 'todos') {
                $docentesQuery = DB::table('docentes')
                    ->join('users', 'docentes.user_id', '=', 'users.id')
                    ->select(
                        'docentes.id',
                        'docentes.nombres',
                        'docentes.apellidos',
                        'docentes.correo',
                        'docentes.telefono',
                        'docentes.estado',
                        DB::raw("'Docente' as tipo")
                    );

                // Aplicar filtro por grupo
                if ($grupoId) {
                    $docentesQuery->join('grupos', 'docentes.id', '=', 'grupos.docente_id')
                        ->where('grupos.id', $grupoId);
                }
                // Aplicar filtro por materia y gestión
                else if ($materiaId && $gestionId) {
                    $docentesQuery->join('grupos', 'docentes.id', '=', 'grupos.docente_id')
                        ->where('grupos.materia_id', $materiaId)
                        ->where('grupos.gestion_id', $gestionId);
                }
                // Aplicar filtro solo por gestión
                else if ($gestionId) {
                    $docentesQuery->join('grupos', 'docentes.id', '=', 'grupos.docente_id')
                        ->where('grupos.gestion_id', $gestionId);
                }

                $docentes = $docentesQuery->get();

                // Para cada docente, obtener grupos y materias asignadas
                foreach ($docentes as $docente) {
                    // Obtener grupos del docente
                    $gruposQuery = DB::table('grupos')
                        ->join('materias', 'grupos.materia_id', '=', 'materias.id')
                        ->where('grupos.docente_id', $docente->id);

                    // Aplicar filtro por gestión si existe
                    if ($gestionId) {
                        $gruposQuery->where('grupos.gestion_id', $gestionId);
                    }

                    $grupos = $gruposQuery->select(
                        'grupos.nombre as grupo_nombre',
                        'materias.nombre as materia_nombre'
                    )->get();

                    // Formatear grupos y materias como texto
                    $gruposTexto = $grupos->pluck('grupo_nombre')->implode(', ');
                    $materiasTexto = $grupos->pluck('materia_nombre')->unique()->implode(', ');

                    // Añadir información adicional
                    $docente->grupos = $gruposTexto;
                    $docente->materias = $materiasTexto;

                    // Añadir a la lista de usuarios
                    $usuarios[] = $docente;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $usuarios
            ]);
        } catch (\Exception $e) {
            Log::error('Error en reporte de usuarios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener reporte de actividad del sistema
     */
    public function actividadSistema(Request $request)
    {
        try {
            // Obtener y validar parámetros
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'gestion_id' => 'nullable|exists:gestions,id',
                'tipo_actividad' => 'nullable|in:todas,practica,evaluacion',
                'agrupacion' => 'required|in:dia,semana,mes',
            ]);

            $fechaInicio = $request->fecha_inicio;
            $fechaFin = $request->fecha_fin;
            $gestionId = $request->gestion_id;
            $tipoActividad = $request->tipo_actividad ?? 'todas';
            $agrupacion = $request->agrupacion;

            // Consulta base para resoluciones
            $resolucionesQuery = DB::table('resolucions')
                ->join('estudiantes', 'resolucions.estudiante_id', '=', 'estudiantes.id')
                ->join('casos', 'resolucions.caso_id', '=', 'casos.id')
                ->join('materias', 'casos.materia_id', '=', 'materias.id')
                ->whereDate('resolucions.fecha_resolucion', '>=', $fechaInicio)
                ->whereDate('resolucions.fecha_resolucion', '<=', $fechaFin);

            // Aplicar filtro por gestión
            if ($gestionId) {
                $resolucionesQuery->where('resolucions.gestion_id', $gestionId);
            }

            // Aplicar filtro por tipo de actividad
            if ($tipoActividad !== 'todas') {
                $tipoValor = ($tipoActividad === 'practica') ? 0 : 1;
                $resolucionesQuery->where('resolucions.tipo', $tipoValor);
            }

            // Obtener datos para resumen
            $totalResoluciones = $resolucionesQuery->count();
            $totalEstudiantes = $resolucionesQuery->distinct('resolucions.estudiante_id')->count('resolucions.estudiante_id');
            $casosUtilizados = $resolucionesQuery->distinct('resolucions.caso_id')->count('resolucions.caso_id');
            $promedioRendimiento = round($resolucionesQuery->avg('resolucions.puntaje') ?? 0);

            // Consulta para actividad por tiempo (día, semana, mes)
            $actividadPorTiempo = [];

            if ($agrupacion === 'dia') {
                $actividadQuery = DB::table('resolucions')
                    ->select(
                        DB::raw('DATE(fecha_resolucion) as periodo'),
                        DB::raw('COUNT(*) as cantidad')
                    )
                    ->whereDate('fecha_resolucion', '>=', $fechaInicio)
                    ->whereDate('fecha_resolucion', '<=', $fechaFin);

                // Aplicar filtros adicionales
                if ($gestionId) {
                    $actividadQuery->where('gestion_id', $gestionId);
                }

                if ($tipoActividad !== 'todas') {
                    $tipoValor = ($tipoActividad === 'practica') ? 0 : 1;
                    $actividadQuery->where('tipo', $tipoValor);
                }

                $actividadPorTiempo = $actividadQuery->groupBy('periodo')
                    ->orderBy('periodo')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'periodo' => $item->periodo,
                            'etiqueta' => date('d/m/Y', strtotime($item->periodo)),
                            'cantidad' => $item->cantidad
                        ];
                    });
            } else if ($agrupacion === 'semana') {
                $actividadQuery = DB::table('resolucions')
                    ->select(
                        DB::raw('YEARWEEK(fecha_resolucion, 1) as periodo'),
                        DB::raw('MIN(fecha_resolucion) as fecha_inicio_semana'),
                        DB::raw('COUNT(*) as cantidad')
                    )
                    ->whereDate('fecha_resolucion', '>=', $fechaInicio)
                    ->whereDate('fecha_resolucion', '<=', $fechaFin);

                // Aplicar filtros adicionales
                if ($gestionId) {
                    $actividadQuery->where('gestion_id', $gestionId);
                }

                if ($tipoActividad !== 'todas') {
                    $tipoValor = ($tipoActividad === 'practica') ? 0 : 1;
                    $actividadQuery->where('tipo', $tipoValor);
                }

                $actividadPorTiempo = $actividadQuery->groupBy('periodo')
                    ->orderBy('periodo')
                    ->get()
                    ->map(function ($item) {
                        $fechaInicio = date('d/m/Y', strtotime($item->fecha_inicio_semana));
                        $fechaFin = date('d/m/Y', strtotime($item->fecha_inicio_semana . ' +6 days'));
                        return [
                            'periodo' => $item->periodo,
                            'etiqueta' => "Semana del {$fechaInicio}",
                            'cantidad' => $item->cantidad
                        ];
                    });
            } else { // mes
                $actividadQuery = DB::table('resolucions')
                    ->select(
                        DB::raw('DATE_FORMAT(fecha_resolucion, "%Y-%m") as periodo'),
                        DB::raw('COUNT(*) as cantidad')
                    )
                    ->whereDate('fecha_resolucion', '>=', $fechaInicio)
                    ->whereDate('fecha_resolucion', '<=', $fechaFin);

                // Aplicar filtros adicionales
                if ($gestionId) {
                    $actividadQuery->where('gestion_id', $gestionId);
                }

                if ($tipoActividad !== 'todas') {
                    $tipoValor = ($tipoActividad === 'practica') ? 0 : 1;
                    $actividadQuery->where('tipo', $tipoValor);
                }

                $actividadPorTiempo = $actividadQuery->groupBy('periodo')
                    ->orderBy('periodo')
                    ->get()
                    ->map(function ($item) {
                        $fecha = \DateTime::createFromFormat('Y-m', $item->periodo);
                        return [
                            'periodo' => $item->periodo,
                            'etiqueta' => $fecha->format('M Y'),
                            'cantidad' => $item->cantidad
                        ];
                    });
            }

            // Distribución por tipo de actividad
            $distribucionTipo = DB::table('resolucions')
                ->select(
                    'tipo',
                    DB::raw('COUNT(*) as cantidad')
                )
                ->whereDate('fecha_resolucion', '>=', $fechaInicio)
                ->whereDate('fecha_resolucion', '<=', $fechaFin);

            // Aplicar filtro por gestión
            if ($gestionId) {
                $distribucionTipo->where('gestion_id', $gestionId);
            }

            $distribucionTipo = $distribucionTipo->groupBy('tipo')
                ->get()
                ->map(function ($item) {
                    return [
                        'tipo' => $item->tipo == 0 ? 'Práctica' : 'Evaluación',
                        'cantidad' => $item->cantidad
                    ];
                });

            // Actividad por hora del día
            $actividadPorHora = DB::table('resolucions')
                ->select(
                    DB::raw('HOUR(fecha_resolucion) as hora'),
                    DB::raw('COUNT(*) as cantidad')
                )
                ->whereDate('fecha_resolucion', '>=', $fechaInicio)
                ->whereDate('fecha_resolucion', '<=', $fechaFin);

            // Aplicar filtros adicionales
            if ($gestionId) {
                $actividadPorHora->where('gestion_id', $gestionId);
            }

            if ($tipoActividad !== 'todas') {
                $tipoValor = ($tipoActividad === 'practica') ? 0 : 1;
                $actividadPorHora->where('tipo', $tipoValor);
            }

            $actividadPorHora = $actividadPorHora->groupBy('hora')
                ->orderBy('hora')
                ->get();

            // Rendimiento por materia
            $rendimientoPorMateria = DB::table('resolucions')
                ->join('casos', 'resolucions.caso_id', '=', 'casos.id')
                ->join('materias', 'casos.materia_id', '=', 'materias.id')
                ->select(
                    'materias.id',
                    'materias.nombre as materia',
                    DB::raw('AVG(resolucions.puntaje) as promedio')
                )
                ->whereDate('resolucions.fecha_resolucion', '>=', $fechaInicio)
                ->whereDate('resolucions.fecha_resolucion', '<=', $fechaFin);

            // Aplicar filtros adicionales
            if ($gestionId) {
                $rendimientoPorMateria->where('resolucions.gestion_id', $gestionId);
            }

            if ($tipoActividad !== 'todas') {
                $tipoValor = ($tipoActividad === 'practica') ? 0 : 1;
                $rendimientoPorMateria->where('resolucions.tipo', $tipoValor);
            }

            $rendimientoPorMateria = $rendimientoPorMateria->groupBy('materias.id', 'materias.nombre')
                ->orderBy('promedio', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'materia' => $item->materia,
                        'promedio' => round($item->promedio)
                    ];
                });

            // Obtener detalle de actividad
            $detalleQuery = $resolucionesQuery->select(
                'resolucions.id',
                'resolucions.fecha_resolucion',
                'resolucions.tipo',
                'resolucions.puntaje',
                'estudiantes.nombres',
                'estudiantes.apellido1',
                'estudiantes.apellido2',
                'casos.titulo as caso_titulo',
                'materias.nombre as materia_nombre'
            )
                ->orderBy('resolucions.fecha_resolucion', 'desc')
                ->limit(1000); // Limitamos a 1000 registros para no sobrecargar

            $detalle = $detalleQuery->get()->map(function ($item) {
                $fechaHora = new \DateTime($item->fecha_resolucion);
                return [
                    'id' => $item->id,
                    'fecha' => $fechaHora->format('Y-m-d'),
                    'hora' => $fechaHora->format('H:i'),
                    'estudiante' => "{$item->nombres} {$item->apellido1} {$item->apellido2}",
                    'caso' => $item->caso_titulo,
                    'materia' => $item->materia_nombre,
                    'tipo' => $item->tipo == 0 ? 'Práctica' : 'Evaluación',
                    'puntaje' => $item->puntaje
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen' => [
                        'total_resoluciones' => $totalResoluciones,
                        'total_estudiantes' => $totalEstudiantes,
                        'casos_utilizados' => $casosUtilizados,
                        'promedio_rendimiento' => $promedioRendimiento
                    ],
                    'actividad_por_tiempo' => $actividadPorTiempo,
                    'distribucion_tipo' => $distribucionTipo,
                    'actividad_por_hora' => $actividadPorHora,
                    'rendimiento_por_materia' => $rendimientoPorMateria,
                    'detalle_actividad' => $detalle
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en reporte de actividad del sistema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de actividad del sistema: ' . $e->getMessage()
            ], 500);
        }
    }
}
