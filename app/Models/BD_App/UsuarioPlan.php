<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;

class UsuarioPlan extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.USUARIO_PLAN";
    protected $primaryKey = "ID_USUARIO_PLAN";

    public function actividades()
    {
        return $this->hasMany(UsuarioPlanActividad::class, 'ID_USUARIO_PLAN', 'ID_USUARIO_PLAN');
    }
}
