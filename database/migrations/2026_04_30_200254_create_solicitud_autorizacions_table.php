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
        Schema::create('solicitud_autorizacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('agencia_id')->nullable()->constrained('agencias')->onDelete('set null');

            // Destinatario de la solicitud
            $table->enum('destinatario', ['cumplimiento', 'jefatura', 'ambos']);

            // Comentarios o excepciones específicas de cada destinatario
            $table->text('observacion_cumplimiento')->nullable();
            $table->text('observacion_jefatura')->nullable();
            $table->text('mensaje_autorizacionC')->nullable();
            $table->text('mensaje_rechazadoC')->nullable();
            $table->text('mensaje_autorizacionJ')->nullable();
            $table->text('mensaje_rechazadoJ')->nullable();

            // PDF generado
            $table->string('pdf_path');

            // Estados de autorización
            $table->enum('estado_cumplimiento', ['pendiente', 'autorizado', 'rechazado'])->default('pendiente');
            $table->enum('estado_jefatura', ['pendiente', 'autorizado', 'rechazado'])->default('pendiente');

            // Indica si ya fue completamente autorizado
            $table->boolean('autorizacion_completa')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_autorizacions');
    }
};
