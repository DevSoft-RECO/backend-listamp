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
        Schema::table('listas_mp', function (Blueprint $table) {
            $table->string('documento_baja')->nullable()->after('observacion_baja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listas_mp', function (Blueprint $table) {
            $table->dropColumn('documento_baja');
        });
    }
};
