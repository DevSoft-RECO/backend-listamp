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
        Schema::create('listas_mp', function (Blueprint $table) {
            $table->id('iddatos');
            $table->string('nombre');
            $table->string('tipo_identificacion')->nullable();
            $table->string('registro')->nullable();
            $table->string('cui')->nullable();
            $table->string('pasaporte')->nullable();
            $table->string('lugar_origen')->nullable();
            $table->date('fecha_respuesta');
            $table->string('nit')->nullable();
            $table->date('fecha_of')->nullable();
            $table->string('oficio')->nullable();
            $table->string('tipo_p')->nullable();
            $table->string('fiscalia')->nullable();
            $table->date('fecha_cooperativa')->nullable();
            $table->date('fecha_cumplimiento')->nullable();
            $table->enum('estado', ['0', '1'])->default('1')->comment('1: Activo, 0: Desactivado');
            $table->text('observacion_baja')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('nombre', 'idx_nombre');
            $table->index('cui', 'idx_cui');
            $table->index('pasaporte', 'idx_pasaporte');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listas_mp');
    }
};
