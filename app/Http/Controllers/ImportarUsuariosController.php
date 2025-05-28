<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Role;
use App\Models\Estudiante;
use App\Models\Docente;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ImportarUsuariosController extends Controller
{
    /**
     * Genera una vista previa del archivo Excel subido
     */
    public function preview(Request $request)
    {
        // Validar la solicitud
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls',
            'tipo' => 'required|in:estudiantes,docentes',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo inválido. Debe ser un archivo Excel (.xlsx, .xls)',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $tipo = $request->tipo;

            // Leer el archivo Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Verificar si hay encabezados en la primera fila
            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo está vacío o no tiene datos suficientes.',
                ], 422);
            }

            // Obtener los encabezados
            $headers = $rows[0];

            // Verificar encabezados según el tipo
            if ($tipo === 'estudiantes') {
                $requiredHeaders = ['codigo', 'nombres', 'apellido1', 'apellido2', 'correo', 'telefono'];
                $missingHeaders = array_diff($requiredHeaders, $headers);

                if (!empty($missingHeaders)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Faltan columnas requeridas: ' . implode(', ', $missingHeaders),
                    ], 422);
                }
            } else { // docentes
                $requiredHeaders = ['ci', 'nombres', 'apellidos', 'correo', 'telefono'];
                $missingHeaders = array_diff($requiredHeaders, $headers);

                if (!empty($missingHeaders)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Faltan columnas requeridas: ' . implode(', ', $missingHeaders),
                    ], 422);
                }
            }

            // Obtener hasta 5 filas para la vista previa (excluyendo encabezados)
            $preview = [];
            $maxPreviewRows = 5;

            for ($i = 1; $i <= min(count($rows) - 1, $maxPreviewRows); $i++) {
                $row = $rows[$i];
                $rowData = [];

                foreach ($headers as $index => $header) {
                    $rowData[$header] = $row[$index] ?? '';
                }

                $preview[] = $rowData;
            }

            return response()->json([
                'success' => true,
                'headers' => $headers,
                'preview' => $preview,
                'total_rows' => count($rows) - 1, // Excluir encabezados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa el archivo Excel e importa los usuarios
     */
    public function import(Request $request)
    {
        // Validar la solicitud
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls',
            'tipo' => 'required|in:estudiantes,docentes',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo inválido. Debe ser un archivo Excel (.xlsx, .xls)',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $tipo = $request->tipo;

            // Leer el archivo Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Verificar si hay encabezados en la primera fila
            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo está vacío o no tiene datos suficientes.',
                ], 422);
            }

            // Obtener los encabezados
            $headers = $rows[0];

            // Verificar encabezados según el tipo
            if ($tipo === 'estudiantes') {
                $requiredHeaders = ['codigo', 'nombres', 'apellido1', 'apellido2', 'correo', 'telefono'];
                $headerIndexes = $this->getHeaderIndexes($headers, $requiredHeaders);

                if (count($headerIndexes) !== count($requiredHeaders)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Faltan columnas requeridas. Verifica el formato del archivo.',
                    ], 422);
                }

                // Obtener el rol de estudiante
                $rolEstudiante = Role::where('nombre', 'ESTUDIANTE')->first();
                if (!$rolEstudiante) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró el rol de ESTUDIANTE en la base de datos.',
                    ], 500);
                }

                $resultado = $this->procesarEstudiantes($rows, $headerIndexes, $rolEstudiante);

                return response()->json([
                    'success' => true,
                    'message' => 'Importación completada. Se han procesado ' . count($resultado) . ' registros.',
                    'resultados' => $resultado,
                ]);
            } else { // docentes
                $requiredHeaders = ['ci', 'nombres', 'apellidos', 'correo', 'telefono'];
                $headerIndexes = $this->getHeaderIndexes($headers, $requiredHeaders);

                if (count($headerIndexes) !== count($requiredHeaders)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Faltan columnas requeridas. Verifica el formato del archivo.',
                    ], 422);
                }

                // Obtener el rol de docente
                $rolDocente = Role::where('nombre', 'DOCENTE')->first();
                if (!$rolDocente) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró el rol de DOCENTE en la base de datos.',
                    ], 500);
                }

                $resultado = $this->procesarDocentes($rows, $headerIndexes, $rolDocente);

                return response()->json([
                    'success' => true,
                    'message' => 'Importación completada. Se han procesado ' . count($resultado) . ' registros.',
                    'resultados' => $resultado,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa las filas de estudiantes y crea los registros correspondientes
     */
    private function procesarEstudiantes($rows, $headerIndexes, $rolEstudiante)
    {
        $resultados = [];

        // Empezar desde la segunda fila (índice 1) para omitir los encabezados
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Obtener los datos del estudiante
            $codigo = trim($row[$headerIndexes['codigo']] ?? '');
            $nombres = trim($row[$headerIndexes['nombres']] ?? '');
            $apellido1 = trim($row[$headerIndexes['apellido1']] ?? '');
            $apellido2 = trim($row[$headerIndexes['apellido2']] ?? '');
            $correo = trim($row[$headerIndexes['correo']] ?? '');
            $telefono = trim($row[$headerIndexes['telefono']] ?? '');

            // Validar datos mínimos requeridos (solo código, nombres y apellido1 son obligatorios)
            if (empty($codigo) || empty($nombres) || empty($apellido1)) {
                $resultados[] = [
                    'exito' => false,
                    'mensaje' => "Fila {$i}: Falta información necesaria (código, nombres o apellido1)",
                ];
                continue;
            }

            // Verificar si ya existe el usuario con ese código
            $usuarioExistente = User::where('name', $codigo)->first();
            if ($usuarioExistente) {
                $resultados[] = [
                    'exito' => false,
                    'mensaje' => "Fila {$i}: Ya existe un usuario con el código '{$codigo}'",
                ];
                continue;
            }

            // Verificar unicidad de correo y teléfono solo si no están vacíos
            if (!empty($correo)) {
                $correoExistente = Estudiante::where('correo', $correo)->first();
                if ($correoExistente) {
                    $resultados[] = [
                        'exito' => false,
                        'mensaje' => "Fila {$i}: Ya existe un estudiante con el correo '{$correo}'",
                    ];
                    continue;
                }
            }

            if (!empty($telefono)) {
                $telefonoExistente = Estudiante::where('telefono', $telefono)->first();
                if ($telefonoExistente) {
                    $resultados[] = [
                        'exito' => false,
                        'mensaje' => "Fila {$i}: Ya existe un estudiante con el teléfono '{$telefono}'",
                    ];
                    continue;
                }
            }

            try {
                // Iniciar transacción para garantizar la integridad de los datos
                DB::beginTransaction();

                // Crear el usuario
                $usuario = User::create([
                    'name' => $codigo,
                    'password' => Hash::make($codigo), // La contraseña es el mismo código
                    'role_id' => $rolEstudiante->id,
                    'estado' => 1,
                ]);

                // Crear el estudiante
                Estudiante::create([
                    'nombres' => $nombres,
                    'apellido1' => $apellido1,
                    'apellido2' => $apellido2 ?: null, // Permitir null si está vacío
                    'correo' => $correo ?: null, // Permitir null si está vacío
                    'telefono' => $telefono ?: null, // Permitir null si está vacío
                    'estado' => 1,
                    'user_id' => $usuario->id,
                ]);

                // Confirmar la transacción
                DB::commit();

                $resultados[] = [
                    'exito' => true,
                    'mensaje' => "Fila {$i}: Estudiante '{$nombres} {$apellido1}' (código: {$codigo}) importado correctamente",
                ];
            } catch (\Exception $e) {
                // Revertir la transacción en caso de error
                DB::rollBack();

                $resultados[] = [
                    'exito' => false,
                    'mensaje' => "Fila {$i}: Error al importar - " . $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    /**
     * Procesa las filas de docentes y crea los registros correspondientes
     */
    private function procesarDocentes($rows, $headerIndexes, $rolDocente)
    {
        $resultados = [];

        // Empezar desde la segunda fila (índice 1) para omitir los encabezados
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Obtener los datos del docente
            $ci = trim($row[$headerIndexes['ci']] ?? '');
            $nombres = trim($row[$headerIndexes['nombres']] ?? '');
            $apellidos = trim($row[$headerIndexes['apellidos']] ?? '');
            $correo = trim($row[$headerIndexes['correo']] ?? '');
            $telefono = trim($row[$headerIndexes['telefono']] ?? '');

            // Validar datos mínimos requeridos (solo CI, nombres y apellidos son obligatorios)
            if (empty($ci) || empty($nombres) || empty($apellidos)) {
                $resultados[] = [
                    'exito' => false,
                    'mensaje' => "Fila {$i}: Falta información necesaria (CI, nombres o apellidos)",
                ];
                continue;
            }

            // Verificar si ya existe el usuario con ese CI
            $usuarioExistente = User::where('name', $ci)->first();
            if ($usuarioExistente) {
                $resultados[] = [
                    'exito' => false,
                    'mensaje' => "Fila {$i}: Ya existe un usuario con la CI '{$ci}'",
                ];
                continue;
            }

            // Verificar unicidad de correo y teléfono solo si no están vacíos
            if (!empty($correo)) {
                $correoExistente = Docente::where('correo', $correo)->first();
                if ($correoExistente) {
                    $resultados[] = [
                        'exito' => false,
                        'mensaje' => "Fila {$i}: Ya existe un docente con el correo '{$correo}'",
                    ];
                    continue;
                }
            }

            if (!empty($telefono)) {
                $telefonoExistente = Docente::where('telefono', $telefono)->first();
                if ($telefonoExistente) {
                    $resultados[] = [
                        'exito' => false,
                        'mensaje' => "Fila {$i}: Ya existe un docente con el teléfono '{$telefono}'",
                    ];
                    continue;
                }
            }

            try {
                // Iniciar transacción para garantizar la integridad de los datos
                DB::beginTransaction();

                // Crear el usuario
                $usuario = User::create([
                    'name' => $ci,
                    'password' => Hash::make($ci), // La contraseña es el mismo CI
                    'role_id' => $rolDocente->id,
                    'estado' => 1,
                ]);

                // Crear el docente
                Docente::create([
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'correo' => $correo ?: null, // Permitir null si está vacío
                    'telefono' => $telefono ?: null, // Permitir null si está vacío
                    'estado' => 1,
                    'user_id' => $usuario->id,
                ]);

                // Confirmar la transacción
                DB::commit();

                $resultados[] = [
                    'exito' => true,
                    'mensaje' => "Fila {$i}: Docente '{$nombres} {$apellidos}' (CI: {$ci}) importado correctamente",
                ];
            } catch (\Exception $e) {
                // Revertir la transacción en caso de error
                DB::rollBack();

                $resultados[] = [
                    'exito' => false,
                    'mensaje' => "Fila {$i}: Error al importar - " . $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    /**
     * Obtiene los índices de las columnas según los encabezados
     */
    private function getHeaderIndexes($headers, $requiredHeaders)
    {
        $indexes = [];

        foreach ($requiredHeaders as $requiredHeader) {
            $index = array_search($requiredHeader, $headers);
            if ($index !== false) {
                $indexes[$requiredHeader] = $index;
            }
        }

        return $indexes;
    }

    /**
     * Genera y descarga una plantilla de Excel para importar usuarios
     */
    public function descargarPlantilla($tipo)
    {
        return $this->generarPlantilla($tipo);
    }

    /**
     * Versión pública de la descarga de plantillas sin requerir autenticación
     */
    public function descargarPlantillaPublica($tipo)
    {
        return $this->generarPlantilla($tipo);
    }

    /**
     * Método compartido para generar las plantillas
     */
    private function generarPlantilla($tipo)
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            if ($tipo === 'estudiantes') {
                // Encabezados para estudiantes
                $headers = ['codigo', 'nombres', 'apellido1', 'apellido2', 'correo', 'telefono'];
                $sheet->fromArray($headers, NULL, 'A1');

                // Ejemplo de datos
                $ejemplos = [
                    ['20202020', 'Juan Carlos', 'Perez', 'Gomez', 'jperez@mail.com', '70707070'],
                    ['20203030', 'Ana Maria', 'Rodriguez', 'Lopez', 'arodriguez@mail.com', '71717171'],
                ];
                $sheet->fromArray($ejemplos, NULL, 'A2');

                $filename = 'plantilla_estudiantes.xlsx';
            } else {
                // Encabezados para docentes
                $headers = ['ci', 'nombres', 'apellidos', 'correo', 'telefono'];
                $sheet->fromArray($headers, NULL, 'A1');

                // Ejemplo de datos
                $ejemplos = [
                    ['1234567', 'Roberto', 'Ramirez Fuentes', 'rramirez@mail.com', '72727272'],
                    ['7654321', 'Carla Patricia', 'Mendoza Suarez', 'cmendoza@mail.com', '73737373'],
                ];
                $sheet->fromArray($ejemplos, NULL, 'A2');

                $filename = 'plantilla_docentes.xlsx';
            }

            // Dar formato a la plantilla
            foreach (range('A', chr(64 + count($headers))) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Estilo para encabezados
            $styleArray = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => '4A148C', // Deep Purple
                    ],
                ],
            ];

            $sheet->getStyle('A1:' . chr(64 + count($headers)) . '1')->applyFromArray($styleArray);

            // Crear el archivo Excel en memoria
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $tempFile = tempnam(sys_get_temp_dir(), 'plantilla_');
            $writer->save($tempFile);

            // Añadir encabezados para evitar caché
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
                'Pragma' => 'public'
            ];

            // Descargar el archivo
            return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la plantilla: ' . $e->getMessage(),
            ], 500);
        }
    }
}
