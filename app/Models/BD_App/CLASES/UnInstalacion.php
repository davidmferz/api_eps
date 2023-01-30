<?php

namespace App\Models\BD_App\CLASES;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnInstalacion extends Model
{
    use SoftDeletes;
    protected $connection = 'app';
    protected $table      = 'CLASES.unInstalacion';
    protected $primaryKey = 'idUnInstalacion';

}
