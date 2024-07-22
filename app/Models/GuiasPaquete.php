<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuiasPaquete extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $fillable = ['peso', 'largo','ancho', 'alto', 'precio_unitario', 'empresa_id', 'guia_id'];
}
