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
        Schema::create('resolucion_tratamient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolucion_id')->constrained('resolucions')->onDelete('cascade');
            $table->foreignId('medicamento_id')->constrained('medicamentos')->onDelete('cascade');
            $table->foreignId('dosis_id')->constrained('dosis')->onDelete('cascade');
            $table->foreignId('frecuencia_id')->constrained('frecuencias')->onDelete('cascade');
            $table->foreignId('duracion_id')->constrained('duraciones')->onDelete('cascade');
            $table->boolean('respuesta')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resolucion_tratamient');
    }
};
