<?php

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SSOController;

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
Route::middleware('sso')->group(function () {
    
    // 🧠 Sincronización JIT (Ecosistema Madre)
    Route::get('/me', [SSOController::class, 'me']);

    // 📋 Listas MP
    Route::apiResource('listas-mp', \App\Http\Controllers\ListaMpController::class);
    Route::apiResource('fiscalias', \App\Http\Controllers\FiscaliaController::class);
    Route::apiResource('lista-credito', \App\Http\Controllers\ListaCreditoController::class);

    // 📄 Reportes Lista MP
    Route::prefix('reportes/lista-mp')->group(function () {
        Route::get('/data-filtrada', [\App\Http\Controllers\Reportes\ReporteListaMPController::class, 'dataFiltrada']);
        Route::post('/generar-pdf', [\App\Http\Controllers\Reportes\ReporteListaMPController::class, 'generarPDF']);
        Route::post('/registrar-solicitud', [\App\Http\Controllers\Reportes\ReporteListaMPController::class, 'registrarSolicitud']);
    });

});


