<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Models\ConsultaSinResultado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ConsultaSinResultadoController extends Controller
{
    /**
     * Listado de consultas verificadas sin coincidencias
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tipoReporte = $request->input('tipo_reporte', 'todas');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = ConsultaSinResultado::with(['usuario', 'agencia']);

        // 1. Filtros
        if ($tipoReporte !== 'todas') {
            $query->where('tipo_reporte', $tipoReporte);
        }

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('created_at', [
                Carbon::parse($fechaInicio)->startOfDay(),
                Carbon::parse($fechaFin)->endOfDay()
            ]);
        }

        // 2. Seguridad y Roles (Híbrido: Super Admin o Permiso de Auditoría)
        if (!$user->hasRole('Super Admin') && !$user->hasPermission('consultas_ver_todo')) {
            if ($user->hasPermission('consultas_ver_agencia')) {
                $query->where('agencia_id', $user->id_agencia);
            } elseif ($user->hasPermission('consultas_ver_propias')) {
                $query->where('user_id', $user->id);
            } else {
                // Por defecto solo ve lo suyo
                $query->where('user_id', $user->id);
            }
        }

        $consultas = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($consultas);
    }

    /**
     * Marcar como verificado
     */
    public function verificar($id)
    {
        $registro = ConsultaSinResultado::findOrFail($id);
        
        if ($registro->verificacion === 'verificado') {
            return response()->json(['message' => 'Este registro ya fue verificado.'], 422);
        }

        $registro->update(['verificacion' => 'verificado']);

        return response()->json([
            'status' => 'success',
            'message' => 'Consulta verificada correctamente.'
        ]);
    }

    /**
     * Regenerar el PDF original
     */
    public function regenerarPdf($id)
    {
        $registro = ConsultaSinResultado::with(['usuario', 'agencia'])->findOrFail($id);

        // CASO A: REPORTE CONSOLIDADO
        if ($registro->tipo_reporte === 'Lista Mixta' || $registro->tipo_reporte === 'Lista Consolidada') {
            
            $data = [
                'titulo'            => 'REPORTE CONSOLIDADO DE RIESGO',
                'nombre_consultado' => $registro->nombre_buscado ?? 'N/A',
                'documento'         => $registro->numero_documento ?? '', 
                'usuario' => [
                    'name'   => $registro->usuario->name ?? 'Usuario Sistema',
                    'role'   => $registro->usuario->puesto ?? 'Colaborador', 
                    'agency' => $registro->agencia->nombre ?? 'N/A',
                ],
                'fecha_consulta'    => Carbon::parse($registro->fecha_consulta)->format('d/m/Y H:i:s'),
                'estado_final'      => 'APTO',
                'mensaje_extra'     => "Validación: No se encontraron coincidencias en ninguna lista (MP o Créditos).",
                'registrosMP'       => collect([]),
                'registrosCreditos' => collect([]),
                'requiere_autorizacion' => false,
                'forzar_excepcion'      => false,
                'destinatario'          => null,
                'observacion_cumplimiento' => null,
                'observacion_jefatura'     => null,
            ];

            $pdf = Pdf::loadView('reportes.reporte_consolidado', $data)
                ->setPaper('letter', 'portrait');

            return $pdf->stream('Reporte_Consolidado_Limpio.pdf');
        }

        // CASO B: REPORTE LISTA MP
        else {
            $tipoIdentificacion = $registro->tipo_documento;
            $documentoIngresado = $registro->numero_documento;

            $data = [
                'titulo'               => 'REPORTE LISTA MP',
                'nombre_consultado'    => $registro->nombre_buscado ?? 'N/A',
                'tipo_identificacion'  => $tipoIdentificacion ?? 'Nombre/CUI',
                'documento_ingresado'  => $documentoIngresado,
                'cui_consultado'       => ($tipoIdentificacion === 'CUI') ? $documentoIngresado : null,
                'pasaporte_consultado' => ($tipoIdentificacion === 'Pasaporte') ? $documentoIngresado : null,
                'nit_consultado'       => ($tipoIdentificacion === 'Nit') ? $documentoIngresado : null,
                'nombre_usuario'       => $registro->usuario->name ?? 'Usuario Sistema',
                'tipo_usuario'         => $registro->usuario->puesto ?? 'Colaborador',
                'agencia_usuario'      => $registro->agencia->nombre ?? 'N/A',
                'fecha_consulta'       => Carbon::parse($registro->fecha_consulta)->format('d/m/Y'),
                'hora_consulta'        => Carbon::parse($registro->fecha_consulta)->format('H:i:s'),
                'coincidencias'        => collect([]),
                'hay_resultados'       => false,
                'mensaje_extra'        => "No se encontraron registros relacionados con la persona buscada.",
            ];

            $pdf = Pdf::loadView('reportes.reporte_lista_mp', $data)
                ->setPaper('a4', 'portrait');

            return $pdf->stream('Reporte_MP_Limpio.pdf');
        }
    }

    /**
     * Exportar a CSV
     */
    public function exportCSV(Request $request)
    {
        $user = Auth::user();
        $tipoReporte = $request->input('tipo_reporte', 'todas');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = ConsultaSinResultado::with(['usuario', 'agencia']);

        // Mismos filtros que el index
        if ($tipoReporte !== 'todas') {
            $query->where('tipo_reporte', $tipoReporte);
        }

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('created_at', [
                Carbon::parse($fechaInicio)->startOfDay(),
                Carbon::parse($fechaFin)->endOfDay()
            ]);
        }

        // Seguridad
        if (!$user->hasRole('Super Admin') && !$user->hasPermission('consultas_ver_todo')) {
            if ($user->hasPermission('consultas_ver_agencia')) {
                $query->where('agencia_id', $user->id_agencia);
            } elseif ($user->hasPermission('consultas_ver_propias')) {
                $query->where('user_id', $user->id);
            } else {
                $query->where('user_id', $user->id);
            }
        }

        $registros = $query->latest()->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=historial_consultas_limpias.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID', 'Nombre Consultado', 'Tipo Doc', 'Documento', 'Tipo Reporte', 'Fecha Consulta', 'Verificacion', 'Usuario (Username)', 'Agencia (ID)'];

        $callback = function() use($registros, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($registros as $r) {
                fputcsv($file, [
                    $r->id,
                    $r->nombre_buscado,
                    $r->tipo_documento,
                    $r->numero_documento,
                    $r->tipo_reporte,
                    $r->fecha_consulta,
                    $r->verificacion,
                    $r->usuario->username ?? 'N/A',
                    $r->agencia_id
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Eliminar registro
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $registro = ConsultaSinResultado::findOrFail($id);
        
        if (!$user->hasRole('Super Admin') && !$user->hasPermission('consultas_eliminar')) {
            return response()->json(['message' => 'No tiene permisos para eliminar registros.'], 403);
        }

        $registro->delete();

        return response()->json(['status' => 'success', 'message' => 'Registro eliminado correctamente.']);
    }
}
