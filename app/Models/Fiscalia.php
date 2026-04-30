<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Fiscalia extends Model
{
    use HasFactory;

    protected $table = 'fiscalias';

    protected $fillable = [
        'nombre',
    ];
}
