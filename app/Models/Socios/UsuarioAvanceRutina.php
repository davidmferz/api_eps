<?php

namespace App\Models\Socios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsuarioAvanceRutina extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'socios.usuario_avance_rutina';
    protected $primaryKey = 'idPlan';

}
