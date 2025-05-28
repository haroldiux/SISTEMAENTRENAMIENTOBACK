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
        Schema::create('manifestacions', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->integer('tipo'); // 1) SIGNOS, 2) SINTOMAS, 3) SINDROMES
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifestacions');
    }
};
