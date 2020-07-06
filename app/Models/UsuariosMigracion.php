<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsuariosMigracion extends Model
{
    use SoftDeletes;

    protected $connection = 'crm';
    protected $table      = "socios.usuarios_migracion";
    protected $primaryKey = "idPersona";
    protected $fillable   = [];

}
