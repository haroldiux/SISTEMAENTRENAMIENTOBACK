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
        Schema::create('resolucion_manifestacions', function (Blueprint $table) {
            $table->id();
            $table->boolean('respuesta')->default(0);
            $table->unsignedBigInteger('resolucion_id');
            $table->foreign('resolucion_id')->references('id')->on('resolucions')->onDelete('cascade');
            $table->unsignedBigInteger('localizacion_id');
            $table->foreign('localizacion_id')->references('id')->on('localizacions')->onDelete('cascade');
            $table->unsignedBigInteger('manifestacion_id')->nullable();
            $table->foreign('manifestacion_id')->references('id')->on('manifestacions')->onDelete('cascade');
            $table->unsignedBigInteger('variable_id')->nullable();
            $table->foreign('variable_id')->references('id')->on('variables')->onDelete('cascade');
            $table->timestamps();

            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resolucion_manifestacions');
    }
};
