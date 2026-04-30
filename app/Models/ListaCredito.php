<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ListaCredito extends Model
{
    use HasFactory;

    protected $table = 'lista_credito';

    protected $fillable = [
        'id_usuario',
        'dpi',
        'nombre',
        'motivo',
        'descripcion',
    ];
}
