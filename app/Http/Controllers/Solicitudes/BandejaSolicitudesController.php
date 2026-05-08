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
            // Regla de Oro: En pendientes SOLO debe haber cosas que NO estén terminadas (al menos uno en pendiente)
            $query->where(function ($q) {
                $q->where(function($sq) {
                    $sq->whereNull('estado_cumplimiento')->orWhere('estado_cumplimiento', 'pendiente');
                })
                ->orWhere(function($sq) {
                    $sq->whereNull('estado_jefatura')->orWhere('estado_jefatura', 'pendiente');
                });
            });

            // Ahora aplicamos quién puede ver qué de esos pendientes
            $query->where(function ($q) use ($user) {
                // Tareas de Cumplimiento
                if ($user->hasPermission('solicitudes_autorizar_cumplimiento') || $user->hasRole('Super Admin')) {
                    $q->orWhereIn('destinatario', ['cumplimiento', 'ambos']);
                }

                // Tareas de Jefatura / Monitoreo Agencia
                if ($user->hasPermission('solicitudes_autorizar_jefatura') || $user->hasPermission('solicitudes_ver_agencia') || $user->hasRole('Super Admin')) {
                    $q->orWhere('agencia_id', $user->id_agencia);
                }

                // Auditores Globales
                if ($user->hasPermission('solicitudes_ver_todo')) {
                    $q->orWhereRaw('1 = 1');
                }
            });
        } elseif ($tipo === 'agencia') {
            // Vista de Monitoreo: Solo para quienes tienen permiso de ver agencia
            if (!$user->hasRole('Super Admin') && !$user->hasPermission('solicitudes_ver_agencia')) {
                return response()->json(['message' => 'No tiene permiso para ver el monitoreo de agencia.'], 403);
            }
            $query->where('agencia_id', $user->id_agencia);
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

        // 3. Permisos y Roles (Híbrido: Super Admin o Ver Todo tienen acceso total)
        if (!$user->hasRole('Super Admin') && !$user->hasPermission('solicitudes_ver_todo')) {
            $query->where(function($q) use ($user) {
                $hasVisibility = false;

                // Opción A: Ver su agencia (Jefaturas)
                if ($user->hasPermission('solicitudes_ver_agencia')) {
                    $q->orWhere('agencia_id', $user->id_agencia);
                    $hasVisibility = true;
                }
                
                // Opción B: Ver cumplimiento
                if ($user->hasPermission('solicitudes_ver_cumplimiento')) {
                    $q->orWhereIn('destinatario', ['cumplimiento', 'ambos']);
                    $hasVisibility = true;
                }
                
                // Si no tiene ningún permiso de visualización, forzar resultado vacío
                if (!$hasVisibility) {
                    $q->whereRaw('1 = 0');
                }
            });
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

        // Validación de seguridad de acceso al detalle
        if (!$user->hasRole('Super Admin') && !$user->hasPermission('solicitudes_ver_todo')) {
            $canSeeAgencia = $user->hasPermission('solicitudes_ver_agencia') && 
                            $solicitud->agencia_id == $user->id_agencia;
                            
            $canSeeCumplimiento = $user->hasPermission('solicitudes_ver_cumplimiento') && 
                                 in_array($solicitud->destinatario, ['cumplimiento', 'ambos']);
                                 
            if (!$canSeeAgencia && !$canSeeCumplimiento) {
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

        // Validar que el usuario tenga el permiso granular adecuado (Bypass para Super Admin)
        if ($perfil === 'cumplimiento' && !$user->hasRole('Super Admin') && !$user->hasPermission('solicitudes_autorizar_cumplimiento')) {
            return response()->json(['message' => 'No tiene permisos para autorizar como Cumplimiento.'], 403);
        }
        if ($perfil === 'jefatura' && !$user->hasRole('Super Admin') && !$user->hasPermission('solicitudes_autorizar_jefatura')) {
            return response()->json(['message' => 'No tiene permisos para autorizar como Jefe de Agencia.'], 403);
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
        $user = Auth::user();
        $solicitud = SolicitudAutorizacion::findOrFail($id);

        // Permitir si es Super Admin, tiene permiso de descarga O tiene permiso de ver la solicitud
        $hasDownloadPerm = $user->hasPermission('solicitudes_descargar_pdf');
        $canSeeTodo = $user->hasPermission('solicitudes_ver_todo');
        $canSeeAgencia = $user->hasPermission('solicitudes_ver_agencia') && 
                         $solicitud->agencia_id == $user->id_agencia;
        $canSeeCumplimiento = $user->hasPermission('solicitudes_ver_cumplimiento') && 
                              in_array($solicitud->destinatario, ['cumplimiento', 'ambos']);

        if (!$user->hasRole('Super Admin') && !$hasDownloadPerm && !$canSeeTodo && !$canSeeAgencia && !$canSeeCumplimiento) {
            return response()->json(['message' => 'No tiene permisos para acceder a este documento.'], 403);
        }

        $rutaCompleta = public_path($solicitud->pdf_path);

        if (!File::exists($rutaCompleta)) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        return response()->download($rutaCompleta);
    }
}
