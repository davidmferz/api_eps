<?php

namespace App\Models\BD_APP\CLASES;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActividadAsistencia extends Model
{
    use SoftDeletes;
    protected $connection = 'app';
    protected $table      = 'CLASES.actividadAsistencia';
    protected $primaryKey = 'idActividadAsistencia';

}
