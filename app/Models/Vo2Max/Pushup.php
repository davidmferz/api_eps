<?php

namespace App\Models\Vo2Max;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pushup extends Model
{
    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = 'piso.cat_pushup';
    protected $primaryKey = 'id';
}
