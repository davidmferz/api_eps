<?php

namespace App\Models\Deportiva;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaDeportiva extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'deportiva.persona';
    protected $primaryKey = 'idPersona';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

}
