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
        Schema::create('configuracions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tiempo_resolucion'); // en minutos
            $table->unsignedInteger('tiempo_vista_enunciado');
            $table->integer('tipo')->default(1);  // 1) oficial impuesto por el docente

            $table->unsignedBigInteger('caso_id');
            $table->foreign('caso_id')->references('id')->on('casos')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracions');
    }
};
