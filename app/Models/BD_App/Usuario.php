<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.USUARIO";
    protected $primaryKey = "ID_USUARIO";
    public $timestamps    = false;

}
