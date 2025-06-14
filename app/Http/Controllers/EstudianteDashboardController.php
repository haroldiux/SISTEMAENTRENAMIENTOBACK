<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\Estudiante;
use App\Models\Materia;
use App\Models\Resolucion;
use App\Models\ExamenComplementario;
use App\Models\Gestion;
use App\Models\Examen;
use App\Models\Inscripcion;
use App\Models\User;
use App\Models\EvaluacionCaso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EstudianteDashboardController extends Controller
{
    public function index()
    {
        try {
            // Inicializar array para depuración
            $debug = [
                'logs' => [],
                'queries' => []
            ];

            $debug['logs'][] = 'Iniciando obtención de dashboard';

            // Obtener el usuario autenticado
            $user = Auth::user();
            $debug['logs'][] = 'Usuario autenticado: ID=' . ($user ? $user->id : 'null');

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Obtener el rol del usuario
            $debug['logs'][] = 'Rol del usuario: ' . $user->role->nombre;

            // Obtener el estudiante asociado al usuario
            $estudiante = Estudiante::where('user_id', $user->id)->first();
            $debug['logs'][] = 'Estudiante encontrado: ' . ($estudiante ? 'Sí, ID=' . $estudiante->id : 'No');

            if (!$estudiante) {
                // Si no se encuentra el estudiante, intentamos buscarlo manualmente
                $debug['logs'][] = 'Buscando estudiante de forma manual';
                $estudiantes = Estudiante::all();
                $debug['logs'][] = 'Total de estudiantes en la BD: ' . $estudiantes->count();

                // Verificar usuarios disponibles
                $usuarios = User::all();
                $debug['logs'][] = 'Total de usuarios en la BD: ' . $usuarios->count();

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no es un estudiante',
                    'debug' => $debug
                ], 403);
            }

            // Verificar datos del estudiante
            $debug['logs'][] = 'Datos del estudiante: Nombres=' . $estudiante->nombres . ', Apellido1=' . $estudiante->apellido1;

            // Obtener gestión activa
            $gestionActiva = Gestion::where('estado', 1)->first();
            $debug['logs'][] = 'Gestión activa encontrada: ' . ($gestionActiva ? 'Sí, ID=' . $gestionActiva->id . ', Nombre=' . $gestionActiva->nombre : 'No');

            // Si no hay gestión activa, verificar todas las gestiones
            if (!$gestionActiva) {
                $todasGestiones = Gestion::all();
                $debug['logs'][] = 'Total de gestiones en la BD: ' . $todasGestiones->count();

                if ($todasGestiones->count() > 0) {
                    // Usar la primera gestión disponible
                    $gestionActiva = $todasGestiones->first();
                    $debug['logs'][] = 'Usando primera gestión disponible: ID=' . $gestionActiva->id . ', Nombre=' . $gestionActiva->nombre;
                } else {
                    $debug['logs'][] = 'No hay gestiones disponibles, creando una';
                    // Crear una gestión por defecto
                    $gestionActiva = Gestion::create([
                        'nombre' => 'Gestión Actual',
                        'anio' => date('Y'),
                        'estado' => 1
                    ]);
                    $debug['logs'][] = 'Gestión creada: ID=' . $gestionActiva->id;
                }
            }

            // Estadísticas generales - Casos resueltos
            $casosResueltos = Resolucion::where('estudiante_id', $estudiante->id);
            if ($gestionActiva) {
                $casosResueltos = $casosResueltos->where('gestion_id', $gestionActiva->id);
            }
            $casosResueltosCount = $casosResueltos->count();
            $debug['logs'][] = 'Casos resueltos: ' . $casosResueltosCount;

            // Verificar todas las resoluciones
            $todasResoluciones = Resolucion::all();
            $debug['logs'][] = 'Total de resoluciones en la BD: ' . $todasResoluciones->count();

            // Analizar resoluciones disponibles
            $resolucionesData = [];
            foreach ($todasResoluciones as $res) {
                $resolucionesData[] = [
                    'id' => $res->id,
                    'estudiante_id' => $res->estudiante_id,
                    'caso_id' => $res->caso_id,
                    'gestion_id' => $res->gestion_id,
                    'puntaje' => $res->puntaje
                ];
            }
            $debug['queries']['resoluciones'] = $resolucionesData;

            // Promedio general de puntajes
            $promedio = Resolucion::where('estudiante_id', $estudiante->id);
            if ($gestionActiva) {
                $promedio = $promedio->where('gestion_id', $gestionActiva->id);
            }
            $promedioGeneral = $promedio->avg('puntaje') ?: 0;
            $debug['logs'][] = 'Promedio general: ' . $promedioGeneral;

            // Evaluaciones pendientes - Obtenemos los grupos del estudiante
            $inscripciones = Inscripcion::where('estudiante_id', $estudiante->id)->get();
            $debug['logs'][] = 'Inscripciones encontradas: ' . $inscripciones->count();

            $gruposIds = $inscripciones->pluck('grupo_id')->toArray();
            $debug['logs'][] = 'IDs de grupos: ' . implode(', ', $gruposIds);

            // Verificar todos los grupos
            $todosGrupos = DB::table('grupos')->get();
            $debug['logs'][] = 'Total de grupos en la BD: ' . $todosGrupos->count();

            // Evaluaciones pendientes
            $evaluacionesPendientes = collect([]);
            if (count($gruposIds) > 0) {
                $evaluacionesPendientes = Evaluacion::whereIn('grupo_id', $gruposIds)
                    ->where('fecha_fin', '>=', now())
                    ->get();
                $debug['logs'][] = 'Evaluaciones pendientes encontradas: ' . $evaluacionesPendientes->count();
            } else {
                $debug['logs'][] = 'No hay grupos para buscar evaluaciones pendientes';
            }

            // Verificar todas las evaluaciones
            $todasEvaluaciones = Evaluacion::all();
            $debug['logs'][] = 'Total de evaluaciones en la BD: ' . $todasEvaluaciones->count();

            // Analizar evaluaciones
            $evaluacionesData = [];
            foreach ($todasEvaluaciones as $eval) {
                $evaluacionesData[] = [
                    'id' => $eval->id,
                    'titulo' => $eval->titulo,
                    'grupo_id' => $eval->grupo_id,
                    'fecha_fin' => $eval->fecha_fin
                ];
            }
            $debug['queries']['evaluaciones'] = $evaluacionesData;

            // Filtrar solo las evaluaciones no completadas por el estudiante
            $evaluacionesPendientesData = [];
            foreach ($evaluacionesPendientes as $evaluacion) {
                $evaluacionCasos = EvaluacionCaso::where('evaluacion_id', $evaluacion->id)->get();
                $debug['logs'][] = 'Evaluación ' . $evaluacion->id . ': ' . $evaluacionCasos->count() . ' casos asociados';

                if ($evaluacionCasos->isEmpty()) {
                    continue;
                }

                $pendiente = false;
                foreach ($evaluacionCasos as $evaluacionCaso) {
                    $evaluacionEstudiante = EvaluacionEstudiante::where('evaluacion_caso_id', $evaluacionCaso->id)
                        ->where('estudiante_id', $estudiante->id)
                        ->first();

                    $debug['logs'][] = 'Evaluación caso ' . $evaluacionCaso->id . ': ' .
                        ($evaluacionEstudiante ? 'Existe para estudiante, completado=' . ($evaluacionEstudiante->completado ? 'Sí' : 'No') : 'No existe para estudiante');

                    if (!$evaluacionEstudiante || !$evaluacionEstudiante->completado) {
                        $pendiente = true;
                        break;
                    }
                }

                if ($pendiente) {
                    $evaluacionesPendientesData[] = $evaluacion;
                }
            }
            $debug['logs'][] = 'Evaluaciones pendientes filtradas: ' . count($evaluacionesPendientesData);

            // Historial de casos
            $historialCasos = collect([]);
            if ($casosResueltosCount > 0) {
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
                    ->orderBy('resolucions.fecha_resolucion', 'desc')
                    ->get();
                $debug['logs'][] = 'Historial de casos encontrados: ' . $historialCasos->count();
            } else {
                $debug['logs'][] = 'No hay resoluciones para obtener historial de casos';
            }

            // Verificar todos los casos
            $todosCasos = Caso::all();
            $debug['logs'][] = 'Total de casos en la BD: ' . $todosCasos->count();

            // Datos para gráficos
            $misCalificaciones = [];
            foreach ($historialCasos as $caso) {
                $misCalificaciones[] = [
                    'caso' => $caso->titulo,
                    'puntaje' => $caso->puntaje
                ];
            }

            // Obtener todas las materias
            $materias = Materia::all();
            $debug['logs'][] = 'Total de materias en la BD: ' . $materias->count();

            // Verificar si hay materias, si no, crear algunas por defecto
            if ($materias->count() == 0) {
                $debug['logs'][] = 'No hay materias, creando algunas por defecto';
                $materiasDefecto = [
                    ['nombre' => 'Cardiología'],
                    ['nombre' => 'Neurología'],
                    ['nombre' => 'Gastroenterología'],
                    ['nombre' => 'Dermatología']
                ];

                foreach ($materiasDefecto as $materia) {
                    Materia::create($materia);
                }

                $materias = Materia::all();
                $debug['logs'][] = 'Materias creadas: ' . $materias->count();
            }

            // Progreso por áreas (materias)
            $progresoAreas = [];
            if ($historialCasos->count() > 0) {
                $materiasIds = $historialCasos->pluck('materia_id')->unique();

                foreach ($materiasIds as $materiaId) {
                    $materia = $materias->firstWhere('id', $materiaId);
                    if ($materia) {
                        $promedio = $historialCasos->where('materia_id', $materiaId)->avg('puntaje') ?: 0;
                        $progresoAreas[] = [
                            'materia' => $materia->nombre,
                            'promedio' => round($promedio)
                        ];
                    }
                }
            }
            $debug['logs'][] = 'Progreso por áreas calculado: ' . count($progresoAreas) . ' áreas';

            // Progreso detallado por materias
            $progresoMaterias = [];
            foreach ($materias as $materia) {
                $promedio = 0;
                if ($historialCasos->count() > 0) {
                    $casosDeLaMateria = $historialCasos->where('materia_id', $materia->id);
                    if ($casosDeLaMateria->count() > 0) {
                        $promedio = $casosDeLaMateria->avg('puntaje') ?: 0;
                    }
                }

                $progresoMaterias[] = [
                    'id' => $materia->id,
                    'nombre' => $materia->nombre,
                    'promedio' => round($promedio)
                ];
            }
            $debug['logs'][] = 'Progreso por materias calculado para ' . count($progresoMaterias) . ' materias';

            // Exámenes complementarios
            $examenesComplementarios = [];
            if ($historialCasos->count() > 0) {
                $casosResueltoIds = $historialCasos->pluck('id')->toArray();

                if (count($casosResueltoIds) > 0) {
                    $examenes = DB::table('examen_complementarios')
                        ->select(
                            'examen_complementarios.id',
                            'examen_complementarios.caso_id',
                            'examens.nombre',
                            'examen_complementarios.archivo'
                        )
                        ->join('examens', 'examen_complementarios.examen_id', '=', 'examens.id')
                        ->whereIn('examen_complementarios.caso_id', $casosResueltoIds)
                        ->get();

                    $examenesComplementarios = $examenes->toArray();
                    $debug['logs'][] = 'Exámenes complementarios encontrados: ' . count($examenesComplementarios);
                } else {
                    $debug['logs'][] = 'No hay casos resueltos para buscar exámenes complementarios';
                }
            } else {
                $debug['logs'][] = 'No hay historial de casos para buscar exámenes complementarios';
            }

            // Verificar todos los exámenes
            $todosExamenes = Examen::all();
            $debug['logs'][] = 'Total de exámenes en la BD: ' . $todosExamenes->count();

            // Verificar todos los exámenes complementarios
            $todosExamenesComp = ExamenComplementario::all();
            $debug['logs'][] = 'Total de exámenes complementarios en la BD: ' . $todosExamenesComp->count();

            // Obtener los títulos de los casos
            $casosInfo = [];
            if ($historialCasos->count() > 0) {
                $casosIds = $historialCasos->pluck('id')->toArray();
                if (count($casosIds) > 0) {
                    $casosInfo = Caso::whereIn('id', $casosIds)->select('id', 'titulo')->get();
                }
            }
            $debug['logs'][] = 'Información de casos obtenida: ' . count($casosInfo);

            $debug['logs'][] = 'Dashboard generado exitosamente';

            return response()->json([
                'success' => true,
                'data' => [
                    'estadisticas_generales' => [
                        'casos_resueltos' => $casosResueltosCount,
                        'promedio_general' => round($promedioGeneral)
                    ],
                    'evaluaciones_pendientes' => $evaluacionesPendientesData,
                    'historial_casos' => $historialCasos,
                    'mis_calificaciones' => $misCalificaciones,
                    'progreso_areas' => $progresoAreas,
                    'datos_usuario' => $estudiante,
                    'gestion_actual' => $gestionActiva,
                    'materias' => $materias,
                    'progreso_materias' => $progresoMaterias,
                    'examenes_complementarios' => $examenesComplementarios,
                    'casos' => $casosInfo,
                    'debug' => $debug
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en dashboard estudiante: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del dashboard',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => env('APP_DEBUG', false) ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}
