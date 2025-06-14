<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\CambioContrasenaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
use App\Http\Controllers\{
    MateriaController,
    MotivoController,
    ManifestacionController,
    CaracteristicaController,
    LocalizacionController,
    ExamenController,
    ResolucionController,
    VariableController,
    GestionController,
    GrupoController,
    ImportarUsuariosController,
    EvaluacionController,
    MedicamentoController,
    DosisController,
    FrecuenciaController,
    DuracionController,
    CasoTratamientoController,
    ResolucionTratamientoController,
    DashboardController,
    AdminDashboardController,
    DocenteDashboardController,
    EstudianteDashboardController,
    ExamenComplementarioController,
    EvaluacionEstudianteController
};

// ====== RUTAS DEL DASHBOARD ======
Route::middleware('auth:sanctum')->group(function () {
    // Dashboard general (funciona para cualquier usuario autenticado)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Dashboard específico para administradores
    Route::get('/dashboard/admin', [AdminDashboardController::class, 'index'])
        ->middleware('role:ADMINISTRADOR');

    // Dashboard específico para docentes
    Route::get('/dashboard/docente', [DocenteDashboardController::class, 'index'])
        ->middleware('role:DOCENTE');
    Route::get('/dashboard/estudiante', [EstudianteDashboardController::class, 'index'])
        ->middleware('role:ESTUDIANTE');
});

//RUTAS PARA EL MENU CREAR CASO
Route::apiResource('materias', MateriaController::class)->only(['index', 'store', 'update']);
Route::apiResource('motivos', MotivoController::class)->only(['index', 'store', 'update']);
Route::apiResource('manifestaciones', ManifestacionController::class)->only(['index', 'store', 'update']);
Route::apiResource('caracteristicas', CaracteristicaController::class)->only(['index', 'store', 'update']);
Route::apiResource('variables', VariableController::class)->only(['index', 'store', 'update']);
Route::apiResource('localizaciones', LocalizacionController::class)->only(['index', 'store', 'update']);
Route::apiResource('examenes', ExamenController::class)->only(['index', 'store', 'update']);

//RUTA PARA OBTENER LAS VARIABLES DADO LA MANIFESTACION Y LA CARACTERISTICA
Route::get('/variables/filtrar', [VariableController::class, 'filtrarPorManifestacionYCaracteristica']);

//LISTAR CASO
use App\Http\Controllers\CasoController;

Route::get('/casos', [CasoController::class, 'index']);
Route::post('/casos', [CasoController::class, 'store']);
Route::put('/casos/{caso}', [CasoController::class, 'update']);

//PARA MOSTRAR DURANTE LA RESOLUCION
Route::get('/casos/{caso}/detalle-completo', [CasoController::class, 'detalleCompleto']);
Route::post('casos/{caso}/solicitar-examenes', [CasoController::class, 'solicitarExamenes']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/resoluciones', [ResolucionController::class, 'store']);

Route::post('/registro-estudiante', [AuthController::class, 'registroEstudiante']);
Route::post('/registro-docente', [AuthController::class, 'registroDocente']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Nueva ruta para cambiar contraseña en primer inicio de sesión
    Route::post('/cambiar-contrasena-inicial', [CambioContrasenaController::class, 'cambiarContrasenaInicial']);

    Route::post('/completar-perfil-docente', [DocenteController::class, 'completarPerfil']);
    Route::post('/completar-perfil-estudiante', [EstudianteController::class, 'completarPerfil']);

    Route::get('/casos/disponibles', [EvaluacionController::class, 'getCasosDisponibles']);
    Route::post('/evaluaciones', [EvaluacionController::class, 'store']);
    Route::get('/evaluaciones/pendientes', [EvaluacionController::class, 'getPendientesEstudiante']);
    Route::get('/evaluaciones/{evaluacion}/caso', [EvaluacionController::class, 'getCasoEstudiante']);
    Route::post('/evaluaciones/{evaluacion}/completar', [EvaluacionController::class, 'marcarCompletada']);
    Route::get('/evaluaciones/{id}/caso-estudiante', [EvaluacionController::class, 'getCasoEvaluacionEstudiante']);
    Route::get('/casos/{caso}/diagnosticos', [CasoController::class, 'getDiagnosticosAleatorios']);

    // Rutas para importar usuarios desde Excel (solo para administradores)
    Route::middleware('role:ADMINISTRADOR')->group(function () {
        Route::post('/importar-usuarios/preview', [ImportarUsuariosController::class, 'preview']);
        Route::post('/importar-usuarios/import', [ImportarUsuariosController::class, 'import']);
        Route::get('/importar-usuarios/plantilla/{tipo}', [ImportarUsuariosController::class, 'descargarPlantilla'])
            ->where('tipo', 'estudiantes|docentes');
    });

    // Rutas públicas para descargar plantillas (sin middleware de autenticación)
    Route::get('/plantillas/download/{tipo}', [ImportarUsuariosController::class, 'descargarPlantillaPublica'])
        ->where('tipo', 'estudiantes|docentes');
});

// Rutas para docentes
Route::get('/docentes', [DocenteController::class, 'index']);

// Rutas para gestiones académicas
Route::get('/gestiones', [GestionController::class, 'index']);
Route::get('/gestiones/activa', [GestionController::class, 'getActiva']);
Route::post('/gestiones', [GestionController::class, 'store']);
Route::put('/gestiones/{gestion}/estado', [GestionController::class, 'cambiarEstado']);
Route::delete('/gestiones/{gestion}', [GestionController::class, 'destroy']);
Route::put('/gestiones/{gestion}', [GestionController::class, 'update']);

// Rutas para grupos
Route::get('/materias/{materia}/grupos', [GrupoController::class, 'getPorMateria']);
Route::post('/grupos/generar', [GrupoController::class, 'generarGrupos']);
Route::put('/grupos/{grupo}', [GrupoController::class, 'update']); // Ruta para actualizar el nombre del grupo
Route::put('/grupos/{grupo}/docente', [GrupoController::class, 'asignarDocente']);
Route::delete('/grupos/{grupo}', [GrupoController::class, 'destroy']);

// Rutas para administración de estudiantes en grupos
Route::get('/grupos/{grupo}/estudiantes', [GrupoController::class, 'getEstudiantes']);
Route::get('/estudiantes/disponibles', [EstudianteController::class, 'getDisponibles']);
Route::post('/grupos/{grupo}/asignar-estudiantes', [GrupoController::class, 'asignarEstudiantes']);
Route::post('/grupos/{grupo}/remover-estudiantes', [GrupoController::class, 'removerEstudiantes']);

Route::get('/estudiantes/{id}/resoluciones', [ResolucionController::class, 'getResolucionesByEstudiante']);
Route::get('/evaluaciones/vencidas', [EvaluacionController::class, 'getEvaluacionesVencidas'])->middleware('auth:sanctum');
Route::get('/evaluaciones/{id}/caso', [EvaluacionController::class, 'getCasoEvaluacion'])->middleware('auth:sanctum');

// Rutas para evaluaciones
Route::prefix('evaluaciones')->group(function () {
    // Ruta para obtener una evaluación específica por ID
    Route::get('/{id}', [EvaluacionController::class, 'show']);

    // Ruta para marcar evaluación como intentada
    Route::post('/{id}/marcar-intentada', [EvaluacionController::class, 'marcarIntentada']);

    // Ruta para obtener caso asignado al estudiante en evaluación
    Route::get('/{id}/caso-estudiante', [EvaluacionController::class, 'getCasoEvaluacionEstudiante']);
});

// Rutas para medicamentos
Route::apiResource('medicamentos', MedicamentoController::class);

// Rutas para dosis
Route::apiResource('dosis', DosisController::class);

// Rutas para frecuencias
Route::apiResource('frecuencias', FrecuenciaController::class);

// Rutas para duraciones
Route::apiResource('duraciones', DuracionController::class);

// Rutas para tratamientos de casos
Route::apiResource('caso-tratamientos', CasoTratamientoController::class);
Route::get('casos/{caso}/tratamientos', [CasoTratamientoController::class, 'index']);

// Rutas para tratamientos de resolución
Route::apiResource('resolucion-tratamientos', ResolucionTratamientoController::class);

// Rutas adicionales específicas
Route::prefix('tratamientos')->group(function () {
    // Obtener todas las opciones para formularios
    Route::get('/opciones', function () {
        return response()->json([
            'medicamentos' => \App\Models\Medicamento::activos()->get(),
            'dosis' => \App\Models\Dosis::activos()->get(),
            'frecuencias' => \App\Models\Frecuencia::activos()->get(),
            'duraciones' => \App\Models\Duracion::activos()->get(),
        ]);
    });

    // Buscar medicamentos
    Route::get('/medicamentos/buscar', function (Request $request) {
        $query = $request->get('q', '');
        $medicamentos = \App\Models\Medicamento::where('nombre', 'LIKE', "%{$query}%")
            ->activos()
            ->limit(10)
            ->get();
        return response()->json($medicamentos);
    });

    // Estadísticas de uso
    Route::get('/estadisticas', function () {
        return response()->json([
            'medicamentos_mas_usados' => \App\Models\Medicamento::withCount('casoTratamientos')
                ->orderBy('caso_tratamientos_count', 'desc')
                ->limit(10)
                ->get(),
            'dosis_mas_usadas' => \App\Models\Dosis::withCount('casoTratamientos')
                ->orderBy('caso_tratamientos_count', 'desc')
                ->limit(10)
                ->get(),
        ]);
    });

    // ====== RUTAS PARA EL DASHBOARD DE ESTUDIANTE ======
    Route::middleware(['auth:sanctum', 'role:ESTUDIANTE'])->prefix('estudiante')->group(function () {
        // Dashboard específico para estudiantes (ya debe existir si tienes un composable general)
        // Rutas adicionales específicas para el dashboard de estudiante
        Route::get('/evaluaciones/pendientes', [EvaluacionEstudianteController::class, 'getPendientes']);
        Route::get('/casos/historial', [ResolucionController::class, 'getHistorialCasos']);
        Route::get('/examenes-complementarios', [ExamenComplementarioController::class, 'getExamenesEstudiante']);

        // Ver detalles de un examen complementario específico
        Route::get('/examenes-complementarios/{id}', [ExamenComplementarioController::class, 'show']);
    });
});
