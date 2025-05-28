<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModificarCamposNullableEnUsuarios extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Modificar tabla de estudiantes
        Schema::table('estudiantes', function (Blueprint $table) {
            // Primero eliminar las restricciones UNIQUE existentes
            $table->dropUnique(['correo']);
            $table->dropUnique(['telefono']);

            // Modificar las columnas para permitir NULL
            $table->string('correo')->nullable()->change();
            $table->string('telefono')->nullable()->change();

            // Recrear las restricciones UNIQUE que permiten NULL
            $table->unique(['correo']);
            $table->unique(['telefono']);
        });

        // Modificar tabla de docentes
        Schema::table('docentes', function (Blueprint $table) {
            // Primero eliminar las restricciones UNIQUE existentes
            $table->dropUnique(['correo']);
            $table->dropUnique(['telefono']);

            // Modificar las columnas para permitir NULL
            $table->string('correo')->nullable()->change();
            $table->string('telefono')->nullable()->change();

            // Recrear las restricciones UNIQUE que permiten NULL
            $table->unique(['correo']);
            $table->unique(['telefono']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revertir cambios en la tabla de estudiantes
        Schema::table('estudiantes', function (Blueprint $table) {
            // Eliminar las restricciones únicas que permiten NULL
            $table->dropUnique(['correo']);
            $table->dropUnique(['telefono']);

            // Modificar las columnas para NO permitir NULL
            $table->string('correo')->nullable(false)->change();
            $table->string('telefono')->nullable(false)->change();

            // Recrear las restricciones UNIQUE estándar
            $table->unique(['correo']);
            $table->unique(['telefono']);
        });

        // Revertir cambios en la tabla de docentes
        Schema::table('docentes', function (Blueprint $table) {
            // Eliminar las restricciones únicas que permiten NULL
            $table->dropUnique(['correo']);
            $table->dropUnique(['telefono']);

            // Modificar las columnas para NO permitir NULL
            $table->string('correo')->nullable(false)->change();
            $table->string('telefono')->nullable(false)->change();

            // Recrear las restricciones UNIQUE estándar
            $table->unique(['correo']);
            $table->unique(['telefono']);
        });
    }
}
