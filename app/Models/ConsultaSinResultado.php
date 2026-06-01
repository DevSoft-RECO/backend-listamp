<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultaSinResultado extends Model
{
    use HasFactory;

    protected $table = 'consultas_sin_resultados';

    protected $fillable = [
        'nombre_buscado',
        'tipo_documento',
        'numero_documento',
        'user_id',
        'agencia_id',
        'tipo_reporte',
        'destinatario',
        'fecha_consulta',
        'verificacion',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    protected static function booted()
    {
        static::created(function ($consulta) {
            self::notifyJefeAgencia($consulta->agencia_id);
        });

        static::updated(function ($consulta) {
            self::notifyJefeAgencia($consulta->agencia_id);
        });

        static::deleted(function ($consulta) {
            self::notifyJefeAgencia($consulta->agencia_id);
        });
    }

    public static function notifyJefeAgencia($agenciaId)
    {
        if (!$agenciaId) {
            return;
        }

        // 1. Obtener la cuenta de consultas sin resultado pendientes de verificación para esta agencia
        $pendientesCount = self::where('agencia_id', $agenciaId)
            ->where(function($query) {
                $query->where('verificacion', '!=', 'verificado')
                      ->orWhereNull('verificacion');
            })
            ->count();

        // 2. Encontrar todos los Jefes/Subjefes de Agencia (usuarios con el permiso 'consultas_ver_agencia')
        $jefes = \App\Models\User::where('id_agencia', $agenciaId)
            ->whereJsonContains('permissions_list', 'consultas_ver_agencia')
            ->get();

        if ($jefes->isEmpty()) {
            \Log::warning("No se encontró ningún usuario con permiso 'consultas_ver_agencia' en la agencia ID: {$agenciaId}");
            return;
        }

        // 3. Enviar la notificación a la App Madre para cada Jefe/Subjefe detectado
        $motherUrl = config('services.mother.api_url') ?? config('services.mother_app.url') ?? 'http://localhost:8000';
        $serviceToken = config('services.mother.service_token') ?? 'token_secreto_yamankutx_notificaciones';

        $titulo = "Consultas Pendientes";
        $mensaje = $pendientesCount > 0
            ? "Tienes {$pendientesCount} consultas sin resultado pendientes de verificación en tu agencia."
            : "Todas las consultas de tu agencia han sido verificadas.";

        foreach ($jefes as $jefe) {
            try {
                \Illuminate\Support\Facades\Http::withHeaders([
                    'X-SSO-Service-Token' => $serviceToken,
                    'Accept' => 'application/json'
                ])->timeout(2)->post("{$motherUrl}/api/sso/notifications/broadcast", [
                    'target_user_id' => $jefe->sso_id ?? $jefe->id,
                    'type' => 'direct', // Notificación directa/personal
                    'title' => $titulo,
                    'message' => $mensaje,
                    'app' => 'Buró Interno'
                ]);
            } catch (\Exception $e) {
                \Log::error("Error al enviar notificación de consultas pendientes al Jefe ID {$jefe->id}: " . $e->getMessage());
            }
        }
    }
}
