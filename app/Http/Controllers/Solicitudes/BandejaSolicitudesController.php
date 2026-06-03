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

                // Auditores Globales o Super Admin (Ven todo lo pendiente)
                if ($user->hasPermission('solicitudes_ver_todo') || $user->hasRole('Super Admin')) {
                    $q->orWhereRaw('1 = 1');
                }
            });
        } elseif ($tipo === 'agencia') {
            // Vista de Monitoreo: Solo para quienes tienen permiso de ver agencia
            if (!$user->hasRole('Super Admin') && !$user->hasPermission('solicitudes_ver_agencia')) {
                return response()->json(['message' => 'No tiene permiso para ver el monitoreo de agencia.'], 403);
            }
            if (!$user->hasRole('Super Admin')) {
                $query->where('agencia_id', $user->id_agencia);
            }
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
            $solicitud->user_cumplimiento_id = $user->id;

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
            $solicitud->user_jefatura_id = $user->id;

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
    public function descargarPDF(Request $request, $id)
    {
        $user = Auth::user();
        $solicitud = SolicitudAutorizacion::with(['userCumplimiento', 'userJefatura'])->findOrFail($id);

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
        if ($request->has('debug')) {
            return response()->json([
                'id' => $solicitud->id,
                'autorizacion_completa' => $solicitud->autorizacion_completa,
                'pdf_path' => $solicitud->pdf_path,
                'exists' => File::exists(public_path($solicitud->pdf_path)),
                'class_fpdi_exists' => class_exists('setasign\Fpdi\Fpdi'),
                'class_fpdf_exists' => class_exists('Fpdf\Fpdf'),
            ]);
        }

        $rutaCompleta = public_path($solicitud->pdf_path);

        if (!File::exists($rutaCompleta)) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        \Illuminate\Support\Facades\Log::info("descargarPDF Solicitud: " . $solicitud->id . ", autorizacion_completa: " . json_encode($solicitud->autorizacion_completa));

        // Si la autorización está completa, generamos el PDF con el dictamen estampado en una nueva página
        if ($solicitud->autorizacion_completa) {
            try {
                if (!class_exists('FPDF') && class_exists('Fpdf\Fpdf')) {
                    class_alias('Fpdf\Fpdf', 'FPDF');
                }
                $pdf = new \setasign\Fpdi\Fpdi();
                $pdf->SetAutoPageBreak(false);
                $pageCount = $pdf->setSourceFile($rutaCompleta);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);

                    // Si es la última página, estampamos los dictámenes al final de esta página (arriba del footer)
                    if ($pageNo === $pageCount) {
                        $boxHeight = 30;
                        $currentY = $size['height'] - 78; // 78 mm desde el fondo, para quedar arriba del footer

                        $leftMargin = 12;
                        $rightMargin = 12;
                        $gap = 6;
                        $destinatario = $solicitud->destinatario;

                        // Nombre de quien autorizó
                        $userC = $solicitud->userCumplimiento->name ?? 'Auditor de Cumplimiento';
                        $msgC = $solicitud->mensaje_autorizacionC ?? 'Autorizado sin comentarios adicionales.';

                        $userJ = $solicitud->userJefatura->name ?? 'Jefe de Agencia';
                        $msgJ = $solicitud->mensaje_autorizacionJ ?? 'Autorizado sin comentarios adicionales.';

                        if ($destinatario === 'ambos') {
                            $width = ($size['width'] - $leftMargin - $rightMargin - $gap) / 2;

                            // 1. Caja Cumplimiento (Izquierda)
                            $pdf->SetDrawColor(229, 231, 235); // Borde gris claro (#e5e7eb)
                            $pdf->SetFillColor(249, 250, 251); // Fondo suave (#f9fafb)
                            $pdf->Rect($leftMargin, $currentY, $width, $boxHeight, 'DF');

                            // Barra lateral verde
                            $pdf->SetFillColor(16, 185, 129); // Verde
                            $pdf->Rect($leftMargin, $currentY, 3, $boxHeight, 'F');

                            $pdf->SetFont('Helvetica', 'B', 8);
                            $pdf->SetTextColor(16, 185, 129);
                            $pdf->SetXY($leftMargin + 5, $currentY + 3);
                            $pdf->Cell($width - 8, 4, utf8_decode("CUMPLIMIENTO - AUTORIZADO"), 0, 1, 'L');

                            $pdf->SetFont('Helvetica', 'B', 7);
                            $pdf->SetTextColor(75, 85, 99);
                            $pdf->SetXY($leftMargin + 5, $currentY + 8);
                            $pdf->Cell($width - 8, 4, utf8_decode("Autorizado por: " . $userC), 0, 1, 'L');

                            $pdf->SetFont('Helvetica', '', 7);
                            $pdf->SetTextColor(31, 41, 55);
                            $pdf->SetXY($leftMargin + 5, $currentY + 13);
                            $pdf->MultiCell($width - 8, 3.5, utf8_decode($msgC), 0, 'L');

                            // 2. Caja Jefatura (Derecha)
                            $pdf->SetDrawColor(229, 231, 235);
                            $pdf->SetFillColor(249, 250, 251);
                            $pdf->Rect($leftMargin + $width + $gap, $currentY, $width, $boxHeight, 'DF');

                            // Barra lateral azul
                            $pdf->SetFillColor(1, 61, 123); // Azul Cope (#013d7b)
                            $pdf->Rect($leftMargin + $width + $gap, $currentY, 3, $boxHeight, 'F');

                            $pdf->SetFont('Helvetica', 'B', 8);
                            $pdf->SetTextColor(1, 61, 123);
                            $pdf->SetXY($leftMargin + $width + $gap + 5, $currentY + 3);
                            $pdf->Cell($width - 8, 4, utf8_decode("JEFE DE AGENCIA - AUTORIZADO"), 0, 1, 'L');

                            $pdf->SetFont('Helvetica', 'B', 7);
                            $pdf->SetTextColor(75, 85, 99);
                            $pdf->SetXY($leftMargin + $width + $gap + 5, $currentY + 8);
                            $pdf->Cell($width - 8, 4, utf8_decode("Autorizado por: " . $userJ), 0, 1, 'L');

                            $pdf->SetFont('Helvetica', '', 7);
                            $pdf->SetTextColor(31, 41, 55);
                            $pdf->SetXY($leftMargin + $width + $gap + 5, $currentY + 13);
                            $pdf->MultiCell($width - 8, 3.5, utf8_decode($msgJ), 0, 'L');

                        } else {
                            // Un solo bloque con todo el ancho disponible
                            $width = $size['width'] - $leftMargin - $rightMargin;

                            $pdf->SetDrawColor(229, 231, 235);
                            $pdf->SetFillColor(249, 250, 251);
                            $pdf->Rect($leftMargin, $currentY, $width, $boxHeight, 'DF');

                            if ($destinatario === 'cumplimiento') {
                                // Barra lateral verde
                                $pdf->SetFillColor(16, 185, 129);
                                $pdf->Rect($leftMargin, $currentY, 3, $boxHeight, 'F');

                                $pdf->SetFont('Helvetica', 'B', 8);
                                $pdf->SetTextColor(16, 185, 129);
                                $pdf->SetXY($leftMargin + 5, $currentY + 3);
                                $pdf->Cell($width - 8, 4, utf8_decode("CUMPLIMIENTO - AUTORIZADO"), 0, 1, 'L');

                                $pdf->SetFont('Helvetica', 'B', 7);
                                $pdf->SetTextColor(75, 85, 99);
                                $pdf->SetXY($leftMargin + 5, $currentY + 8);
                                $pdf->Cell($width - 8, 4, utf8_decode("Autorizado por: " . $userC), 0, 1, 'L');

                                $pdf->SetFont('Helvetica', '', 7);
                                $pdf->SetTextColor(31, 41, 55);
                                $pdf->SetXY($leftMargin + 5, $currentY + 13);
                                $pdf->MultiCell($width - 8, 3.5, utf8_decode($msgC), 0, 'L');
                            } else {
                                // Barra lateral azul
                                $pdf->SetFillColor(1, 61, 123);
                                $pdf->Rect($leftMargin, $currentY, 3, $boxHeight, 'F');

                                $pdf->SetFont('Helvetica', 'B', 8);
                                $pdf->SetTextColor(1, 61, 123);
                                $pdf->SetXY($leftMargin + 5, $currentY + 3);
                                $pdf->Cell($width - 8, 4, utf8_decode("JEFATURA DE AGENCIA - AUTORIZADO"), 0, 1, 'L');

                                $pdf->SetFont('Helvetica', 'B', 7);
                                $pdf->SetTextColor(75, 85, 99);
                                $pdf->SetXY($leftMargin + 5, $currentY + 8);
                                $pdf->Cell($width - 8, 4, utf8_decode("Autorizado por: " . $userJ), 0, 1, 'L');

                                $pdf->SetFont('Helvetica', '', 7);
                                $pdf->SetTextColor(31, 41, 55);
                                $pdf->SetXY($leftMargin + 5, $currentY + 13);
                                $pdf->MultiCell($width - 8, 3.5, utf8_decode($msgJ), 0, 'L');
                            }
                        }

                        // Sello o pie de página discreto
                        $pdf->SetFont('Helvetica', 'I', 6);
                        $pdf->SetTextColor(156, 163, 175);
                        $pdf->SetXY($leftMargin, $currentY + $boxHeight + 1.5);
                        $pdf->Cell($size['width'] - $leftMargin - $rightMargin, 5, utf8_decode("Resolución de autorización de riesgo #" . $solicitud->id . " vinculada a este expediente."), 0, 0, 'C');
                    }
                }

                return response($pdf->Output('S'), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="Autorizacion_' . $id . '.pdf"'
                ]);
            } catch (\Exception $e) {
                // Si falla por algún motivo FPDI, retornamos el archivo original para que no se bloquee el flujo
                \Illuminate\Support\Facades\Log::error("Error al estampar PDF: " . $e->getMessage());
                return response()->download($rutaCompleta);
            }
        }

        return response()->download($rutaCompleta);
    }

    /**
     * Eliminar solicitud y su archivo PDF adjunto
     */
    public function destroy($id)
    {
        $solicitud = SolicitudAutorizacion::findOrFail($id);

        // Borrar el archivo si existe
        if ($solicitud->pdf_path) {
            $rutaArchivo = public_path($solicitud->pdf_path);
            if (File::exists($rutaArchivo)) {
                File::delete($rutaArchivo);
            }
        }

        $solicitud->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Solicitud eliminada y archivo borrado correctamente.'
        ]);
    }
}
