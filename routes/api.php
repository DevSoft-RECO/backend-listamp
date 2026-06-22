<?php

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SSOController;

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
Route::middleware('sso')->group(function () {
    
    // 📊 Dashboard Stats
    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'getStats']);

    // 🧠 Sincronización JIT (Ecosistema Madre)
    Route::get('/me', [SSOController::class, 'me']);

    // 📋 Listas MP
    Route::get('listas-mp/export', [\App\Http\Controllers\ListaMpController::class, 'exportCSV']);
    Route::post('listas-mp/{id}/baja', [\App\Http\Controllers\ListaMpController::class, 'darBaja']);
    Route::apiResource('listas-mp', \App\Http\Controllers\ListaMpController::class);
    Route::apiResource('fiscalias', \App\Http\Controllers\FiscaliaController::class);
    
    // 💳 Lista Crédito
    Route::get('lista-credito/export', [\App\Http\Controllers\ListaCreditoController::class, 'exportCSV']);
    Route::apiResource('lista-credito', \App\Http\Controllers\ListaCreditoController::class);

    // 📄 Reportes Lista MP
    Route::prefix('reportes/lista-mp')->group(function () {
        Route::get('/data-filtrada', [\App\Http\Controllers\Reportes\ReporteListaMPController::class, 'dataFiltrada']);
        Route::post('/generar-pdf', [\App\Http\Controllers\Reportes\ReporteListaMPController::class, 'generarPDF']);
        Route::post('/registrar-solicitud', [\App\Http\Controllers\Reportes\ReporteListaMPController::class, 'registrarSolicitud']);
    });

    // 📄 Reportes Consolidado (MP + Créditos)
    Route::prefix('reportes/lista-consolidada')->group(function () {
        Route::get('/data-filtrada', [\App\Http\Controllers\Reportes\ReporteConsolidadoController::class, 'buscarDataFiltrada']);
        Route::post('/generar-pdf', [\App\Http\Controllers\Reportes\ReporteConsolidadoController::class, 'generarPdf']);
        Route::post('/registrar-solicitud', [\App\Http\Controllers\Reportes\ReporteConsolidadoController::class, 'registrarSolicitud']);
    });

    // 📥 Bandeja de Solicitudes (Autorizaciones)
    Route::prefix('solicitudes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Solicitudes\BandejaSolicitudesController::class, 'getSolicitudes']);
        Route::get('/exportar', [\App\Http\Controllers\Solicitudes\BandejaSolicitudesController::class, 'exportarReporte']);
        Route::get('/{id}', [\App\Http\Controllers\Solicitudes\BandejaSolicitudesController::class, 'show']);
        Route::post('/{id}/actualizar-estado', [\App\Http\Controllers\Solicitudes\BandejaSolicitudesController::class, 'actualizarEstado']);
        Route::get('/{id}/descargar-pdf', [\App\Http\Controllers\Solicitudes\BandejaSolicitudesController::class, 'descargarPDF']);
        Route::delete('/{id}', [\App\Http\Controllers\Solicitudes\BandejaSolicitudesController::class, 'destroy']);
    });

    // 🔍 Consultas Sin Coincidencias
    Route::prefix('consultas-sin-resultado')->group(function () {
        Route::get('/', [\App\Http\Controllers\Solicitudes\ConsultaSinResultadoController::class, 'index']);
        Route::get('/export-csv', [\App\Http\Controllers\Solicitudes\ConsultaSinResultadoController::class, 'exportCSV']);
        Route::post('/{id}/verificar', [\App\Http\Controllers\Solicitudes\ConsultaSinResultadoController::class, 'verificar']);
        Route::get('/{id}/regenerar-pdf', [\App\Http\Controllers\Solicitudes\ConsultaSinResultadoController::class, 'regenerarPdf']);
        Route::delete('/{id}', [\App\Http\Controllers\Solicitudes\ConsultaSinResultadoController::class, 'destroy']);
    });

});

// ==========================================
// === BACKUP SYSTEM ===
// Endpoints internos para el sistema de respaldos de la Madre
// ==========================================
Route::post('/internal/backup', [\App\Http\Controllers\InternalBackupController::class, 'generate']);
Route::get('/internal/download-backup', [\App\Http\Controllers\InternalBackupController::class, 'download']);
Route::delete('/internal/backup', [\App\Http\Controllers\InternalBackupController::class, 'deleteFile']);
