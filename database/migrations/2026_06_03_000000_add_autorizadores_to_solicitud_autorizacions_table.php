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
        Schema::table('solicitud_autorizacions', function (Blueprint $table) {
            $table->foreignId('user_cumplimiento_id')->nullable()->after('estado_cumplimiento')->constrained('users')->onDelete('set null');
            $table->foreignId('user_jefatura_id')->nullable()->after('estado_jefatura')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_autorizacions', function (Blueprint $table) {
            $table->dropForeign(['user_cumplimiento_id']);
            $table->dropColumn('user_cumplimiento_id');
            
            $table->dropForeign(['user_jefatura_id']);
            $table->dropColumn('user_jefatura_id');
        });
    }
};
