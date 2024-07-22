<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coberturas extends Model
{
    use HasFactory;

    protected $fillable = ['estatus', 'ĺtds_id'];
}
