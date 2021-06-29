<?php

namespace App\Models\Vo2Max;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatImc extends Model
{
    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = 'piso.cat_imc';
    protected $primaryKey = 'id';
}
