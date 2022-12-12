<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;

class CatDisciplina extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.CAT_DISCIPLINA";
    protected $primaryKey = "ID_DISCIPLINA";
    public $timestamps    = false;

}
