<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use App\Models\User;
use App\Models\Role;

class ImportarUsuariosSeparadosCSV extends Command
{
    protected $signature = 'usuarios:importar-csv {tipo : estudiantes|docentes|admin}';
    protected $description = 'Importa usuarios desde un CSV separado por tipo (estudiantes, docentes, admin)';

    public function handle()
    {
        $tipo = strtolower($this->argument('tipo'));

        $rolesValidos = [
            'admin' => 'ADMINISTRADOR',
            'docentes' => 'DOCENTE',
            'estudiantes' => 'ESTUDIANTE'
        ];

        if (!array_key_exists($tipo, $rolesValidos)) {
            $this->error("Tipo inválido. Usa: estudiantes, docentes o admin.");
            return 1;
        }

        $roleName = $rolesValidos[$tipo];
        $role = Role::where('nombre', $roleName)->first();

        if (!$role) {
            $this->error("Rol '$roleName' no existe. Créalo primero en la base de datos.");
            return 1;
        }

        $path = storage_path("app/usuarios/{$tipo}.csv");

        if (!file_exists($path)) {
            $this->error("Archivo no encontrado: $path");
            return 1;
        }

        $usuarios = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($usuarios as $name) {
            $name = trim($name);

            if (User::where('name', $name)->exists()) {
                $this->warn("Ya existe el usuario '$name'. Omitido.");
                continue;
            }

            User::create([
                'name' => $name,
                'password' => Hash::make($name),
                'role_id' => $role->id,
                'estado' => 1
            ]);

            $this->info("✔ Usuario '$name' creado como $roleName.");
        }

        $this->info('✅ Importación finalizada correctamente.');
        return 0;
    }
}
