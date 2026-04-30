<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\ConsultaSinResultado;
use App\Models\ListaMp;
use App\Models\SolicitudAutorizacion;
use App\Traits\ValidacionDocumentos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ReporteListaMPController extends Controller
{
    use ValidacionDocumentos;

    /**
     * Búsqueda asíncrona para obtener datos filtrados.
     */
    public function dataFiltrada(Request $request)
    {
        $searchValue = trim($request->input('search', ''));

        if (!$searchValue || mb_strlen($searchValue) < 6) {
            return response()->json(['registros' => []]);
        }

        $registros = ListaMp::active()
            ->searchFilter($searchValue)
            ->limit(50)
            ->get();

        return response()->json(['registros' => $registros]);
    }

    /**
     * Generar PDF o retornar estado de autorización requerida.
     */
    public function generarPDF(Request $request)
    {
        $request->validate([
            'nombre_buscado' => 'nullable|string',
            'registros' => 'nullable|string',
            'documento' => 'nullable|string',
            'tipo_identificacion' => 'nullable|string',
        ]);

        $usuario = Auth::user();
        $agencia_usuario = $usuario->agencia->nombre ?? 'N/A';

        $registrosVisibles = $request->registros
            ? collect(json_decode($request->registros, true))
            : collect();

        $documentoIngresado = $request->documento
            ? preg_replace('/[\s-]+/', '', $request->documento)
            : null;

        $tipo_identificacion = $request->tipo_identificacion;

        $coincidencias = collect();
        $mensajeExtra = '';
        $cui_consultado = null;
        $pasaporte_consultado = null;
        $nit_consultado = null;

        // Lógica de Validación
        if ($documentoIngresado && $tipo_identificacion === 'CUI') {
            [$coincidencias, $mensajeExtra, $cui_consultado, $pasaporte_consultado, $nit_consultado] =
                $this->validarPorCUI($documentoIngresado, $registrosVisibles);
        } elseif ($documentoIngresado && $tipo_identificacion === 'Pasaporte') {
            [$coincidencias, $mensajeExtra, $cui_consultado, $pasaporte_consultado, $nit_consultado] =
                $this->validarPorPasaporte($documentoIngresado, $registrosVisibles);
        } elseif ($documentoIngresado && $tipo_identificacion === 'Nit') {
            [$coincidencias, $mensajeExtra, $cui_consultado, $pasaporte_consultado, $nit_consultado] =
                $this->validarPorNIT($documentoIngresado, $registrosVisibles);
        } else {
            // Caso "Sin Documento" o búsqueda por nombre únicamente
            $nombre_buscado = trim($request->nombre_buscado ?? '');

            if ($nombre_buscado && $registrosVisibles->isNotEmpty()) {
                $coincidenciaExacta = $registrosVisibles->first(function ($item) use ($nombre_buscado) {
                    $nombre = is_array($item) ? $item['nombre'] : $item->nombre;
                    return mb_strtolower(trim($nombre)) === mb_strtolower(trim($nombre_buscado));
                });

                if ($coincidenciaExacta) {
                    $coincidencias = collect([$coincidenciaExacta]);
                    $mensajeExtra = "Coincidencia exacta con el nombre '" . (is_array($coincidenciaExacta) ? $coincidenciaExacta['nombre'] : $coincidenciaExacta->nombre) . "'.";
                } else {
                    $coincidencias = $registrosVisibles;
                    $mensajeExtra = "No hay coincidencia exacta, se muestran los registros posibles encontrados.";
                }
            } else {
                $coincidencias = collect();
                $mensajeExtra = "No se encontraron registros relacionados con la persona buscada.";
            }
        }

        // Si se buscó con documento y no hubo coincidencia exacta, pero hay registros visibles
        if (($documentoIngresado && in_array($tipo_identificacion, ['CUI', 'Pasaporte', 'Nit'])) && $coincidencias->isEmpty()) {
            $coincidencias = $registrosVisibles;
            $mensajeExtra = "No se encontró coincidencia con la identificación ingresada. Se muestran registros similares por nombre.";
        }

        $data = [
            'titulo' => 'REPORTE LISTA MP',
            'nombre_consultado' => $request->nombre_buscado ?? 'N/A',
            'tipo_identificacion' => $tipo_identificacion ?? 'Sin Documento',
            'documento_ingresado' => $request->documento,
            'cui_consultado' => $cui_consultado,
            'pasaporte_consultado' => $pasaporte_consultado,
            'nit_consultado' => $nit_consultado,
            'nombre_usuario' => $usuario->name ?? 'N/A',
            'tipo_usuario' => $usuario->puesto ?? 'N/A',
            'agencia_usuario' => $usuario->agencia->nombre ?? 'N/A',
            'fecha_consulta' => now()->format('d/m/Y'),
            'hora_consulta' => now()->format('H:i:s'),
            'coincidencias' => $coincidencias,
            'hay_resultados' => $coincidencias->isNotEmpty(),
            'mensaje_extra' => $mensajeExtra,
        ];

        // Decisión: Autorización o Descarga Directa
        if ($coincidencias->isNotEmpty()) {
            // Requerir Autorización
            $pdf = Pdf::loadView('reportes.reporte_lista_mp', $data)->setPaper('a4', 'portrait');

            $documentoSlug = $documentoIngresado ?? preg_replace('/[^A-Za-z0-9]/', '', $request->nombre_buscado);
            $nombreArchivo = 'reporte_mp_' . time() . '_' . $documentoSlug . '.pdf';
            $directorio = 'uploads/reportes_autorizacion';

            if (!File::exists(public_path($directorio))) {
                File::makeDirectory(public_path($directorio), 0755, true);
            }

            $rutaCompleta = public_path($directorio . '/' . $nombreArchivo);
            $pdf->save($rutaCompleta);
            $pdfPath = $directorio . '/' . $nombreArchivo;

            return response()->json([
                'status' => 'autorizacion_requerida',
                'mensaje' => 'Se detectaron coincidencias que requieren autorización.',
                'pdf_path' => $pdfPath,
                'nombre_consultado' => $request->nombre_buscado,
                'documento' => $request->documento,
                'tipo_identificacion' => $tipo_identificacion,
            ]);
        } else {
            // Descarga Directa y Registro de Búsqueda sin Resultados
            $this->registrarBusquedaSinCoincidencias(
                $request->nombre_buscado,
                $tipo_identificacion,
                $request->documento
            );

            $pdf = Pdf::loadView('reportes.reporte_lista_mp', $data)->setPaper('a4', 'portrait');
            
            return $pdf->stream('reporte_lista_mp.pdf');
        }
    }

    /**
     * Registrar solicitud de autorización.
     */
    public function registrarSolicitud(Request $request)
    {
        $request->validate([
            'nombre_consultado' => 'required|string',
            'documento' => 'nullable|string',
            'pdf_path' => 'required|string',
            'observacion_cumplimiento' => 'nullable|string',
            'tipo_identificacion' => 'required|string',
        ]);

        $usuario = Auth::user();

        try {
            $solicitud = SolicitudAutorizacion::create([
                'usuario_id' => $usuario->id,
                'agencia_id' => $usuario->id_agencia ?? null,
                'destinatario' => 'cumplimiento',
                'observacion_cumplimiento' => $request->observacion_cumplimiento,
                'pdf_path' => $request->pdf_path,
                'estado_cumplimiento' => 'pendiente',
                'estado_jefatura' => 'autorizado', // En este flujo solo cumple cumplimiento
                'autorizacion_completa' => false,
            ]);

            return response()->json([
                'status' => 'success_solicitud',
                'solicitud_id' => $solicitud->id,
                'mensaje' => 'Solicitud de autorización enviada correctamente.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al registrar solicitud: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al registrar la solicitud.',
            ], 500);
        }
    }

    /**
     * Registrar búsqueda sin coincidencias.
     */
    private function registrarBusquedaSinCoincidencias($nombreBuscado, $tipoDocumento, $numeroDocumento)
    {
        $usuario = Auth::user();

        ConsultaSinResultado::create([
            'nombre_buscado' => $nombreBuscado ?? 'N/A',
            'tipo_documento' => $tipoDocumento ?? 'Sin Documento',
            'numero_documento' => $numeroDocumento ?? 'N/A',
            'user_id' => $usuario->id ?? null,
            'agencia_id' => $usuario->id_agencia ?? null,
            'tipo_reporte' => 'Lista MP',
            'fecha_consulta' => now(),
        ]);
    }
}
