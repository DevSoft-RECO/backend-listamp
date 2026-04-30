<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudAutorizacion extends Model
{
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'agencia_id',
        'destinatario',
        'observacion_cumplimiento',
        'observacion_jefatura',
        'mensaje_autorizacionC',
        'mensaje_rechazadoC',
        'mensaje_autorizacionJ',
        'mensaje_rechazadoJ',
        'pdf_path',
        'estado_cumplimiento',
        'estado_jefatura',
        'autorizacion_completa',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function getPuedeDescargarAttribute()
    {
        return $this->autorizacion_completa === true;
    }
}
