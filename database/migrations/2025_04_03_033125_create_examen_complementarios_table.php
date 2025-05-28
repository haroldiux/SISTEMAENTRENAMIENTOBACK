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
        Schema::create('examen_complementarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caso_id');
            $table->unsignedBigInteger('examen_id');
            $table->string('archivo'); // nombre o path del archivo
            $table->timestamps();

            $table->foreign('caso_id')->references('id')->on('casos')->onDelete('cascade');
            $table->foreign('examen_id')->references('id')->on('examens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examen_complementarios');
    }
};
