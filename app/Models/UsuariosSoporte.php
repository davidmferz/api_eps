<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;

class UsuariosSoporte extends Model
{

    protected $connection = 'aws';
    protected $table      = 'deportiva.usuarios_soporte';
    protected $primaryKey = 'id';

    public $timestamps = false;

}
