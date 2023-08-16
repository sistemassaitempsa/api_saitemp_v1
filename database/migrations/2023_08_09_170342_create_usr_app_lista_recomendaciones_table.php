<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsrAppListaRecomendacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usr_app_lista_recomendaciones', function (Blueprint $table) {
            $table->id();
            $table->string('recomendacion1',1000)->nullable();
            $table->string('recomendacion2',1000)->nullable();
            $table->unsignedBigInteger('subcategoria_cargo_id');
            $table->foreign('subcategoria_cargo_id')->references('id')->on('usr_app_subcategoria_cargos')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usr_app_lista_recomendaciones');
    }
}
