<?php

namespace App\Models\Vo2Max;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatFcr extends Model
{
    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = 'piso.cat_fcr';
    protected $primaryKey = 'id';
}
