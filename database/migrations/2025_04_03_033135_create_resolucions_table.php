<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resolucions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estudiante_id');
            $table->unsignedBigInteger('caso_id');
            $table->unsignedBigInteger('gestion_id');
            $table->boolean('tipo'); // 0) evaluativa o 1) entrenamiento

            $table->integer('puntaje')->default(0);
            $table->dateTime('fecha_resolucion')->nullable(); // para ranking y control de cuando

            $table->timestamps();

            // $table->unsignedBigInteger('distribucionEvaluacion_id');
            // $table->foreign('distribucionEvaluacion_id')->references('id')->on('distribucionEvaluacions');
            $table->foreign('estudiante_id')->references('id')->on('estudiantes')->onDelete('cascade');
            $table->foreign('caso_id')->references('id')->on('casos')->onDelete('cascade');
            $table->foreign('gestion_id')->references('id')->on('gestions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resolucions');
    }
};
