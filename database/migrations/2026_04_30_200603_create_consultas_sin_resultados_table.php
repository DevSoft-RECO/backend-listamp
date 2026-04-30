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
        Schema::create('consultas_sin_resultados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_buscado')->nullable()->index();
            $table->string('tipo_documento')->nullable();
            $table->string('numero_documento')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('agencia_id')->nullable();
            $table->string('tipo_reporte')->nullable()->index();
            $table->enum('verificacion', ['sin verificar', 'verificado'])->default('sin verificar')->index();
            $table->timestamp('fecha_consulta')->useCurrent()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultas_sin_resultados');
    }
};
