<?php

namespace App\Http\Controllers;

use App\Models\ListaMp;
use App\Models\ListaCredito;
use App\Models\SolicitudAutorizacion;
use App\Models\ConsultaSinResultado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats()
    {
        // 1. Conteo de Listas Negras
        $totalListaMp = ListaMp::count();
        $totalListaCreditos = ListaCredito::count();

        // 2. Estado de Solicitudes de Autorización
        // Pendientes: No están completas y no han sido rechazadas
        $pendientes = SolicitudAutorizacion::where('autorizacion_completa', false)
            ->where(function($query) {
                $query->where('estado_cumplimiento', 'pendiente')
                      ->orWhere('estado_jefatura', 'pendiente');
            })
            ->where('estado_cumplimiento', '!=', 'rechazado')
            ->where('estado_jefatura', '!=', 'rechazado')
            ->count();

        $autorizadas = SolicitudAutorizacion::where('autorizacion_completa', true)->count();
        
        $rechazadas = SolicitudAutorizacion::where('estado_cumplimiento', 'rechazado')
            ->orWhere('estado_jefatura', 'rechazado')
            ->count();

        // 3. Consultas Sin Resultados / Sin Coincidencias
        $totalConsultasSinResultado = ConsultaSinResultado::count();
        
        // Consultas sin coincidencias que aún no han sido verificadas
        $sinCoincidenciasNoVerificadas = ConsultaSinResultado::where(function($query) {
            $query->where('verificacion', '!=', 'verificado')
                  ->orWhereNull('verificacion');
        })->count();

        return response()->json([
            'listas' => [
                'mp' => $totalListaMp,
                'creditos' => $totalListaCreditos,
            ],
            'solicitudes' => [
                'pendientes' => $pendientes,
                'autorizadas' => $autorizadas,
                'rechazadas' => $rechazadas,
            ],
            'consultas' => [
                'total' => $totalConsultasSinResultado,
                'no_verificadas' => $sinCoincidenciasNoVerificadas,
            ],
            'recent_activity' => $this->getRecentActivity()
        ]);
    }

    private function getRecentActivity()
    {
        // Obtener las últimas 5 acciones relevantes
        $solicitudes = SolicitudAutorizacion::with(['usuario', 'agencia'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function($s) {
                return [
                    'tipo' => 'solicitud',
                    'mensaje' => "Nueva solicitud de {$s->usuario->name}",
                    'fecha' => $s->created_at,
                    'estado' => $s->autorizacion_completa ? 'completada' : 'procesando'
                ];
            });

        return $solicitudes;
    }
}
