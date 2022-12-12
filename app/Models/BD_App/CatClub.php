<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;

class CatClub extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.CAT_CLUB";
    protected $primaryKey = "ID_CLUB";
    public $timestamps    = false;

}
