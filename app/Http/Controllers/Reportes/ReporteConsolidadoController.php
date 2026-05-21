<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\ConsultaSinResultado;
use App\Models\ListaMp;
use App\Models\ListaCredito;
use App\Models\SolicitudAutorizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ReporteConsolidadoController extends Controller
{
    /**
     * Búsqueda Preliminar en ambas listas
     */
    public function buscarDataFiltrada(Request $request)
    {
        $searchValue = $request->input('search');

        if (!$searchValue || strlen($searchValue) < 10) {
            return response()->json(['registros' => []], 200);
        }

        // Búsqueda en Lista MP
        $registrosMP = ListaMp::where('estado', '1')
            ->select('iddatos as id', 'nombre', 'cui as documento', 'pasaporte', 'nit')
            ->where(function($q) use ($searchValue) {
                $q->where('nombre', 'like', "%{$searchValue}%")
                  ->orWhere('cui', 'like', "%{$searchValue}%")
                  ->orWhere('nit', 'like', "%{$searchValue}%")
                  ->orWhere('pasaporte', 'like', "%{$searchValue}%");
            })
            ->get()
            ->map(function ($item) {
                $item->source = 'MP';
                $item->cui = $item->documento;
                return $item;
            });

        // Búsqueda en Lista Crédito
        $registrosCredito = ListaCredito::select('id', 'nombre', 'dpi as documento', 'motivo', 'descripcion')
            ->where(function($q) use ($searchValue) {
                $q->where('nombre', 'like', "%{$searchValue}%")
                  ->orWhere('dpi', 'like', "%{$searchValue}%");
            })
            ->get()
            ->map(function ($item) {
                $item->source = 'CREDITOS';
                $item->cui = $item->documento;
                return $item;
            });

        // Consolidar
        $registros = $registrosMP->concat($registrosCredito);

        return response()->json(['registros' => $registros]);
    }

    /**
     * Validación y Generación de PDF
     */
    public function generarPdf(Request $request)
    {
        $request->validate([
            'documento' => 'required|string',
            'nombre_buscado' => 'required|string',
            'tipo_identificacion' => 'required|string',
            'registros' => 'nullable|string', // JSON string from frontend
        ]);

        $documentoIngresado = trim($request->documento ?? '');
        $tipo_identificacion = strtoupper($request->tipo_identificacion ?? 'CUI');
        $nombreBuscado = trim($request->nombre_buscado ?? 'N/A');
        $usuario = Auth::user();

        // 🔹 Separar registros que venían del frontend
        $todosLosRegistros = collect(json_decode($request->registros, true) ?? []);
        
        $registrosMPBusqueda = $todosLosRegistros->filter(fn($item) => $item['source'] === 'MP')->values();
        $registrosCreditosBusqueda = $todosLosRegistros->filter(fn($item) => $item['source'] === 'CREDITOS')->values();

        // Limpiar documento para comparar
        $docIngresadoLimpio = strtoupper(preg_replace('/[\s-]+/', '', $documentoIngresado));

        // Filtrar coincidencias exactas en base al documento validado
        $registrosMPFinal = collect();
        $registrosCreditosFinal = collect();
        $mensajeValidacion = "";

        if ($documentoIngresado && $tipo_identificacion === 'CUI') {
            // Verificar exactos en MP por CUI
            $registrosMPExactos = $registrosMPBusqueda->filter(function($item) use ($docIngresadoLimpio) {
                $docLimpio = strtoupper(preg_replace('/[\s-]+/', '', $item['cui'] ?? $item['documento'] ?? ''));
                return $docLimpio === $docIngresadoLimpio;
            })->values();

            if ($registrosMPExactos->isEmpty()) {
                $dbMp = ListaMp::where('estado', '1')
                    ->whereRaw("REPLACE(REPLACE(cui, ' ', ''), '-', '') = ?", [$docIngresadoLimpio])
                    ->get()
                    ->map(function ($item) {
                        return [
                            'id' => $item->iddatos, 'nombre' => $item->nombre, 'documento' => $item->cui,
                            'cui' => $item->cui, 'pasaporte' => $item->pasaporte, 'nit' => $item->nit, 'source' => 'MP'
                        ];
                    });
                if ($dbMp->isNotEmpty()) { $registrosMPExactos = $dbMp; }
            }

            // Verificar exactos en Creditos por DPI
            $registrosCreditosExactos = $registrosCreditosBusqueda->filter(function($item) use ($docIngresadoLimpio) {
                $docLimpio = strtoupper(preg_replace('/[\s-]+/', '', $item['documento'] ?? ''));
                return $docLimpio === $docIngresadoLimpio;
            })->values();

            if ($registrosCreditosExactos->isEmpty()) {
                $dbCredito = ListaCredito::whereRaw("REPLACE(REPLACE(dpi, ' ', ''), '-', '') = ?", [$docIngresadoLimpio])
                    ->get()
                    ->map(function ($item) {
                        return [
                            'id' => $item->id, 'nombre' => $item->nombre, 'documento' => $item->dpi,
                            'motivo' => $item->motivo, 'descripcion' => $item->descripcion, 'source' => 'CREDITOS'
                        ];
                    });
                if ($dbCredito->isNotEmpty()) { $registrosCreditosExactos = $dbCredito; }
            }

            $existeMP = $registrosMPExactos->isNotEmpty();
            $existeCredito = $registrosCreditosExactos->isNotEmpty();

            // Aplicar Regla de Compliance (Homónimos)
            if ($existeMP && $existeCredito) {
                $registrosMPFinal = $registrosMPExactos;
                $registrosCreditosFinal = $registrosCreditosExactos;
            } elseif ($existeMP && !$existeCredito) {
                $registrosMPFinal = $registrosMPExactos;
                $registrosCreditosFinal = collect(); // Créditos se perdona
            } elseif (!$existeMP && $existeCredito) {
                $registrosMPFinal = $registrosMPBusqueda; // MP NO se perdona (Sospecha por Nombre)
                $registrosCreditosFinal = $registrosCreditosExactos;
            } else {
                $registrosMPFinal = $registrosMPBusqueda; // MP NO se perdona
                $registrosCreditosFinal = collect(); // Créditos se perdona
            }

            $mensajeValidacion = "Validación consolidada para el documento '{$documentoIngresado}'. Revise el detalle de coincidencias (exactas o por nombre) en las siguientes tablas.";

        } elseif ($documentoIngresado && in_array($tipo_identificacion, ['PASAPORTE', 'NIT'])) {
            $campoId = $tipo_identificacion === 'PASAPORTE' ? 'pasaporte' : 'nit';

            $registrosMPExactos = $registrosMPBusqueda->filter(function($item) use ($docIngresadoLimpio, $campoId) {
                $docLimpio = strtoupper(preg_replace('/[\s-]+/', '', $item[$campoId] ?? ''));
                return $docLimpio === $docIngresadoLimpio;
            })->values();

            if ($registrosMPExactos->isEmpty()) {
                $dbMp = ListaMp::where('estado', '1')
                    ->whereRaw("UPPER(REPLACE(REPLACE({$campoId}, ' ', ''), '-', '')) = ?", [$docIngresadoLimpio])
                    ->get()->map(function ($item) {
                        return [
                            'id' => $item->iddatos, 'nombre' => $item->nombre, 'documento' => $item->cui,
                            'cui' => $item->cui, 'pasaporte' => $item->pasaporte, 'nit' => $item->nit, 'source' => 'MP'
                        ];
                    });
                if ($dbMp->isNotEmpty()) { $registrosMPExactos = $dbMp; }
            }

            // Aplicar Regla de Compliance (Homónimos) para PASAPORTE/NIT
            if ($registrosMPExactos->isNotEmpty()) {
                $registrosMPFinal = $registrosMPExactos;
            } else {
                $registrosMPFinal = $registrosMPBusqueda; // MP NO se perdona
            }
            $registrosCreditosFinal = collect(); // Créditos no aplica para Pasaporte/NIT
            $mensajeValidacion = "Validación consolidada para el documento '{$documentoIngresado}'. Revise el detalle de coincidencias en las siguientes tablas.";
        } else {
            // Validación por Nombre
            $registrosMPFinal = $registrosMPBusqueda;
            $registrosCreditosFinal = collect();
            $mensajeValidacion = "Validación ejecutada por coincidencia de nombre. Revise el detalle de coincidencias en las siguientes tablas.";
        }

        $hayCoincidenciasMP = $registrosMPFinal->isNotEmpty();
        $hayCoincidenciasCreditos = $registrosCreditosFinal->isNotEmpty();
        $estadoFinal = ($hayCoincidenciasMP || $hayCoincidenciasCreditos) ? 'NO APTO' : 'APTO';

        // Determinar destinatario
        $destinatario = 'ninguno';
        if ($hayCoincidenciasMP && $hayCoincidenciasCreditos) {
            $destinatario = 'ambos';
        } elseif ($hayCoincidenciasMP) {
            $destinatario = 'cumplimiento';
        } elseif ($hayCoincidenciasCreditos) {
            $destinatario = 'jefatura';
        }

        // Si hay coincidencias y es la validación inicial (no forzar excepcion)
        if ($estadoFinal === 'NO APTO') {
            return response()->json([
                'status' => 'autorizacion_requerida',
                'mensaje' => 'Se detectaron coincidencias que requieren autorización.',
                'destinatario' => $destinatario,
                'nombre_consultado' => $nombreBuscado,
                'documento' => $documentoIngresado,
                'tipo_identificacion' => $tipo_identificacion,
                'coincidencias_mp' => $registrosMPFinal,
                'coincidencias_creditos' => $registrosCreditosFinal,
                'mensaje_validacion' => $mensajeValidacion,
            ], 200);
        }

        // Si es APTO (sin coincidencias)
        $this->registrarBusquedaSinCoincidencias($nombreBuscado, $tipo_identificacion, $documentoIngresado);

        $data = $this->prepararDatosPDF($nombreBuscado, $documentoIngresado, $usuario, $estadoFinal, $mensajeValidacion, $registrosMPFinal, $registrosCreditosFinal, false, $destinatario, '', '');

        $pdf = PDF::loadView('reportes.reporte_consolidado', $data)->setPaper('letter', 'portrait');
        return $pdf->stream('reporte_consolidado.pdf');
    }

    /**
     * Registrar solicitud forzada (Autorización Excepcional)
     */
    public function registrarSolicitud(Request $request)
    {
        $request->validate([
            'nombre_consultado' => 'required|string',
            'documento' => 'nullable|string',
            'tipo_identificacion' => 'required|string',
            'destinatario' => 'required|string',
            'observacion_cumplimiento' => 'nullable|string',
            'observacion_jefatura' => 'nullable|string',
            'coincidencias_mp' => 'nullable|array',
            'coincidencias_creditos' => 'nullable|array',
            'mensaje_validacion' => 'nullable|string',
        ]);

        $usuario = Auth::user();
        
        // Validar observaciones según destinatario
        if (($request->destinatario === 'cumplimiento' || $request->destinatario === 'ambos') && empty($request->observacion_cumplimiento)) {
            return response()->json(['status' => 'error', 'mensaje' => 'Falta justificación para Cumplimiento.'], 422);
        }
        if (($request->destinatario === 'jefatura' || $request->destinatario === 'ambos') && empty($request->observacion_jefatura)) {
            return response()->json(['status' => 'error', 'mensaje' => 'Falta justificación para Jefe de Agencia.'], 422);
        }

        $registrosMPFinal = collect($request->coincidencias_mp ?? []);
        $registrosCreditosFinal = collect($request->coincidencias_creditos ?? []);

        // Generar PDF
        $data = $this->prepararDatosPDF(
            $request->nombre_consultado, $request->documento, $usuario, 'NO APTO', $request->mensaje_validacion ?? '', 
            $registrosMPFinal, $registrosCreditosFinal, true, $request->destinatario, 
            $request->observacion_cumplimiento, $request->observacion_jefatura
        );

        $pdf = PDF::loadView('reportes.reporte_consolidado', $data)->setPaper('letter', 'portrait');

        // Guardar PDF
        $nombreArchivo = 'reporte_consolidado_'.time().'_'.Str::slug($request->nombre_consultado).'.pdf';
        $directorio = 'uploads/reportes_autorizacion';
        if (! File::exists(public_path($directorio))) {
            File::makeDirectory(public_path($directorio), 0755, true);
        }
        $pdfPath = $directorio.'/'.$nombreArchivo;
        $pdf->save(public_path($pdfPath));

        // Crear solicitud
        try {
            $estadoCumplimiento = $request->destinatario === 'jefatura' ? 'autorizado' : 'pendiente';
            $estadoJefatura = $request->destinatario === 'cumplimiento' ? 'autorizado' : 'pendiente';

            $solicitud = SolicitudAutorizacion::create([
                'usuario_id' => $usuario->id,
                'agencia_id' => $usuario->id_agencia ?? 1, // Fix if id_agencia is null
                'destinatario' => $request->destinatario,
                'observacion_cumplimiento' => $request->observacion_cumplimiento,
                'observacion_jefatura' => $request->observacion_jefatura,
                'pdf_path' => $pdfPath,
                'estado_cumplimiento' => $estadoCumplimiento,
                'estado_jefatura' => $estadoJefatura,
                'autorizacion_completa' => false,
            ]);

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Solicitud de autorización enviada correctamente.',
                'solicitud_id' => $solicitud->id
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error al registrar solicitud: '.$e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al registrar la solicitud.',
            ], 500);
        }
    }

    private function prepararDatosPDF($nombreBuscado, $documentoIngresado, $usuario, $estadoFinal, $mensajeValidacion, $registrosMPFinal, $registrosCreditosFinal, $forzarExcepcion, $destinatario, $obsCump, $obsJef)
    {
        return [
            'titulo' => 'REPORTE CONSOLIDADO DE RIESGO',
            'nombre_consultado' => $nombreBuscado,
            'documento' => $documentoIngresado,
            'usuario' => [
                'name' => $usuario->name ?? 'N/A',
                'role' => $usuario->puesto ?? 'N/A', // Usamos puesto
                'agency' => $usuario->agencia->nombre ?? 'N/A' // Obtener la agencia del usuario actual
            ],
            'fecha_consulta' => now()->format('d/m/Y H:i:s'),
            'estado_final' => $estadoFinal,
            'mensaje_extra' => $mensajeValidacion,
            'registrosMP' => $registrosMPFinal,
            'registrosCreditos' => $registrosCreditosFinal,
            'requiere_autorizacion' => $estadoFinal === 'NO APTO',
            'destinatario' => $destinatario,
            'forzar_excepcion' => $forzarExcepcion,
            'observacion_cumplimiento' => $obsCump,
            'observacion_jefatura' => $obsJef,
        ];
    }

    private function registrarBusquedaSinCoincidencias($nombreBuscado, $tipoDocumento, $numeroDocumento)
    {
        $usuario = Auth::user();
        ConsultaSinResultado::create([
            'nombre_buscado' => $nombreBuscado ?? 'N/A',
            'tipo_documento' => $tipoDocumento ?? 'N/A',
            'numero_documento' => $numeroDocumento ?? 'N/A',
            'user_id' => $usuario->id ?? null,
            'agencia_id' => $usuario->id_agencia ?? null,
            'tipo_reporte' => 'Lista Mixta',
            'fecha_consulta' => now(),
        ]);
    }
}
