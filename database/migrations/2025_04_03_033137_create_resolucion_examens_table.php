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
        Schema::create('resolucion_examens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resolucion_id');
            $table->unsignedBigInteger('examen_id'); // lo que el estudiante pidió
            $table->boolean('respuesta')->default(0);
            $table->timestamps();

            $table->foreign('resolucion_id')->references('id')->on('resolucions')->onDelete('cascade');
            $table->foreign('examen_id')->references('id')->on('examens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resolucion_examens');
    }
};
