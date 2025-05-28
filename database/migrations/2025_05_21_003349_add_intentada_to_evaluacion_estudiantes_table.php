<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIntentadaToEvaluacionEstudiantesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('evaluacion_estudiantes', function (Blueprint $table) {
            // Añadir campo intentada (boolean) con valor por defecto false
            $table->boolean('intentada')->default(false)->after('completado');

            // Añadir campo fecha_intento (timestamp) que puede ser nulo
            $table->timestamp('fecha_intento')->nullable()->after('intentada');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('evaluacion_estudiantes', function (Blueprint $table) {
            // Eliminar los campos en caso de rollback
            $table->dropColumn('intentada');
            $table->dropColumn('fecha_intento');
        });
    }
}
