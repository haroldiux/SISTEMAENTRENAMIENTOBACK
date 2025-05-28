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
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->integer('filtro_nivel_dificultad'); // Cambio de nombre para clarificar propósito
            $table->enum('modo_seleccion', ['aleatorio', 'manual']);
            $table->integer('limite_casos')->nullable();
            $table->foreignId('materia_id')->constrained()->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade');
            $table->foreignId('gestion_id')->constrained('gestions')->onDelete('cascade');
            $table->foreignId('docente_id')->constrained('docentes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};
