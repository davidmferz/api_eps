<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;

class UsuarioPlanActividad extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.USUARIO_PLAN_ACTIVIDAD";
    protected $primaryKey = "ID_USUARIO_PLAN_ACTIVIDAD";
    public $timestamps    = false;
}
