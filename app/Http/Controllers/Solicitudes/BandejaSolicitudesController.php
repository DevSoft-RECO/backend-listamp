<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Models\SolicitudAutorizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class BandejaSolicitudesController extends Controller
{
    /**
     * Obtener listado de solicitudes según el estado
     */
    public function getSolicitudes(Request $request)
    {
        $user = Auth::user();
        $tipo = $request->input('tipo', 'pendientes'); // pendientes, autorizadas, rechazadas
        $destinatario = $request->input('destinatario');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = SolicitudAutorizacion::with(['usuario', 'agencia']);

        // 1. Filtrar por tipo (estado)
        if ($tipo === 'pendientes') {
            $query->where(function ($q) {
                $q->where(function($sq) {
                    $sq->whereNull('estado_cumplimiento')->orWhere('estado_cumplimiento', 'pendiente');
                })
                ->orWhere(function($sq) {
                    $sq->whereNull('estado_jefatura')->orWhere('estado_jefatura', 'pendiente');
                });
            });
        } elseif ($tipo === 'autorizadas') {
            $query->where('estado_cumplimiento', 'autorizado')
                  ->where('estado_jefatura', 'autorizado');
        } elseif ($tipo === 'rechazadas') {
            $query->where(function ($q) {
                // Aparece aquí si ambos ya decidieron Y al menos uno rechazó
                $q->where(function($sq) {
                    $sq->whereNotNull('estado_cumplimiento')->where('estado_cumplimiento', '!=', 'pendiente');
                })
                ->where(function($sq) {
                    $sq->whereNotNull('estado_jefatura')->where('estado_jefatura', '!=', 'pendiente');
                })
                ->where(function ($sq) {
                    $sq->where('estado_cumplimiento', 'rechazado')
                      ->orWhere('estado_jefatura', 'rechazado');
                });
            });
        }

        // 2. Filtros opcionales
        if (!empty($destinatario) && $destinatario !== 'todos') {
            $query->where('destinatario', $destinatario);
        }

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('created_at', [
                Carbon::parse($fechaInicio)->startOfDay(),
                Carbon::parse($fechaFin)->endOfDay()
            ]);
        }

        // 3. Permisos y Roles (Seguridad - Usando helpers personalizados en modelo User)
        if (!$user->hasRole('Super Admin')) {
            // Si es Jefe de Agencia, solo ve las de su propia agencia
            if ($user->hasRole('Jefe de Agencia')) {
                $query->where('agencia_id', $user->id_agencia);
                $query->whereIn('destinatario', ['jefatura', 'ambos']);
            } 
            // Si es Cumplimiento
            elseif ($user->hasRole('Cumplimiento')) {
                $query->whereIn('destinatario', ['cumplimiento', 'ambos']);
            }
        }

        $solicitudes = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($solicitudes);
    }

    /**
     * Ver detalle de una solicitud
     */
    public function show($id)
    {
        $solicitud = SolicitudAutorizacion::with(['usuario', 'agencia'])->findOrFail($id);
        $user = Auth::user();

        // Validación de seguridad básica
        if (!$user->hasRole('Super Admin')) {
            if ($user->hasRole('Jefe de Agencia') && $solicitud->agencia_id != $user->id_agencia) {
                return response()->json(['message' => 'No tiene permiso para ver esta solicitud.'], 403);
            }
        }

        return response()->json($solicitud);
    }

    /**
     * Actualizar estado (Autorizar/Rechazar)
     */
    public function actualizarEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:autorizado,rechazado',
            'comentario' => 'required|string|min:10|max:2000',
            'perfil' => 'required|in:cumplimiento,jefatura'
        ]);

        $solicitud = SolicitudAutorizacion::findOrFail($id);
        $user = Auth::user();
        $perfil = $request->perfil;

        // Validar que el usuario tenga el rol/permiso adecuado
        if ($perfil === 'cumplimiento' && !$user->hasRole(['Super Admin', 'Cumplimiento'])) {
            return response()->json(['message' => 'No tiene permisos de Cumplimiento.'], 403);
        }
        if ($perfil === 'jefatura' && !$user->hasRole(['Super Admin', 'Jefe de Agencia'])) {
            return response()->json(['message' => 'No tiene permisos de Jefe de Agencia.'], 403);
        }

        if ($perfil === 'cumplimiento') {
            if ($request->estado === 'autorizado') {
                // REGLA: Si el otro ya rechazó en AMBOS, no se puede autorizar
                if ($solicitud->destinatario === 'ambos' && $solicitud->estado_jefatura === 'rechazado') {
                    return response()->json(['message' => 'No se puede autorizar. El Jefe de Agencia ya rechazó esta solicitud; usted también debe rechazar para cerrar el flujo.'], 422);
                }
                $solicitud->mensaje_autorizacionC = $request->comentario;
            } else {
                $solicitud->mensaje_rechazadoC = $request->comentario;
            }
            $solicitud->estado_cumplimiento = $request->estado;
            
            if ($solicitud->destinatario === 'cumplimiento') {
                $solicitud->estado_jefatura = $request->estado;
            }
        } else {
            if ($request->estado === 'autorizado') {
                // REGLA: Si el otro ya rechazó en AMBOS, no se puede autorizar
                if ($solicitud->destinatario === 'ambos' && $solicitud->estado_cumplimiento === 'rechazado') {
                    return response()->json(['message' => 'No se puede autorizar. Cumplimiento ya rechazó esta solicitud; usted también debe rechazar para cerrar el flujo.'], 422);
                }
                $solicitud->mensaje_autorizacionJ = $request->comentario;
            } else {
                $solicitud->mensaje_rechazadoJ = $request->comentario;
            }
            $solicitud->estado_jefatura = $request->estado;

            if ($solicitud->destinatario === 'jefatura') {
                $solicitud->estado_cumplimiento = $request->estado;
            }
        }

        $solicitud->autorizacion_completa = (
            $solicitud->estado_cumplimiento === 'autorizado' && 
            $solicitud->estado_jefatura === 'autorizado'
        );

        $solicitud->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Estado actualizado correctamente.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * Descargar el PDF asociado
     */
    public function descargarPDF($id)
    {
        $solicitud = SolicitudAutorizacion::findOrFail($id);
        $rutaCompleta = public_path($solicitud->pdf_path);

        if (!File::exists($rutaCompleta)) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        return response()->download($rutaCompleta);
    }
}
