<?php

namespace App\Models\Vo2Max;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estatus extends Model
{
    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = 'piso.cat_estatus';
    protected $primaryKey = 'id';
}
