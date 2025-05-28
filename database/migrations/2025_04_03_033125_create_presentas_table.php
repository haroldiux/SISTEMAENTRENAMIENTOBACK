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
        Schema::create('presentas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            
            $table->unsignedBigInteger('caso_id');
            $table->foreign('caso_id')->references('id')->on('casos')->onDelete('cascade');
            $table->unsignedBigInteger('manifestacion_id')->nullable();
            $table->foreign('manifestacion_id')->references('id')->on('manifestacions')->onDelete('cascade');
            $table->unsignedBigInteger('variable_id')->nullable();
            $table->foreign('variable_id')->references('id')->on('variables')->onDelete('cascade');
            $table->unsignedBigInteger('localizacion_id')->nullable();
            $table->foreign('localizacion_id')->references('id')->on('localizacions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentas');
    }
};
