<?php

namespace App\Models\Deportiva;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstalacionActividadProgramada extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'deportiva.instalacionactividadprogramada';
    protected $primaryKey = 'idInstalacionActividad';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

}
