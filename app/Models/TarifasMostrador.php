<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifasMostrador extends Model
{
    use HasFactory;

    protected $fillable = ['estatus', 'ĺtd_id'];
}
