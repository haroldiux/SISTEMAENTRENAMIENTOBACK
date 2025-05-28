<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AllowNullInResolucionManifestacions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('resolucion_manifestacions', function (Blueprint $table) {
            $table->unsignedBigInteger('variable_id')->nullable()->change();
            $table->unsignedBigInteger('localizacion_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('resolucion_manifestacions', function (Blueprint $table) {
            $table->unsignedBigInteger('variable_id')->nullable(false)->change();
            $table->unsignedBigInteger('localizacion_id')->nullable(false)->change();
        });
    }
}
