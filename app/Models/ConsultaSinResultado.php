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
}
