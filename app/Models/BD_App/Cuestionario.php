<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;

class Cuestionario extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.CUESTIONARIO";
    protected $primaryKey = "ID_CUESTIONARIO";
    public $timestamps    = false;
}
