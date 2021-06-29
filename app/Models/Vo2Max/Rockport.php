<?php

namespace App\Models\Vo2Max;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rockport extends Model
{
    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = 'piso.cat_rockport';
    protected $primaryKey = 'id';
}
