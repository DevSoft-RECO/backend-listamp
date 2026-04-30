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

});


