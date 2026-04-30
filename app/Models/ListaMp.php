<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListaMp extends Model
{
    use HasFactory;

    protected $table = 'listas_mp';
    protected $primaryKey = 'iddatos';

    protected $fillable = [
        'nombre',
        'tipo_identificacion',
        'registro',
        'cui',
        'pasaporte',
        'lugar_origen',
        'fecha_respuesta',
        'nit',
        'fecha_of',
        'oficio',
        'tipo_p',
        'fiscalia',
        'fecha_cooperativa',
        'fecha_cumplimiento',
        'estado',
        'observacion_baja'
    ];

    protected $casts = [
        'fecha_respuesta' => 'date',
        'fecha_of' => 'date',
        'fecha_cooperativa' => 'date',
        'fecha_cumplimiento' => 'date',
    ];

    /**
     * Scope a query to only include active records.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', '1');
    }

    public function scopeSearchFilter($query, $searchValue)
    {
        if (!$searchValue || strlen(trim($searchValue)) < 6) {
            return $query;
        }

        $searchValue = trim($searchValue);
        $cleanSearchValue = str_replace([' ', '-'], '', $searchValue);

        return $query->where(function ($q) use ($searchValue, $cleanSearchValue) {
            $q->where('nombre', 'LIKE', "%{$searchValue}%")
              ->orWhereRaw("REPLACE(REPLACE(cui, ' ', ''), '-', '') LIKE ?", ["%{$cleanSearchValue}%"])
              ->orWhereRaw("REPLACE(REPLACE(pasaporte, ' ', ''), '-', '') LIKE ?", ["%{$cleanSearchValue}%"])
              ->orWhereRaw("REPLACE(REPLACE(nit, ' ', ''), '-', '') LIKE ?", ["%{$cleanSearchValue}%"]);
        });
    }
}
