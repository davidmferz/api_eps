<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenEncuestas extends Model
{
    protected $connection = 'aws';
    protected $table      = 'piso.token_encuestas_entrenador';
    protected $primaryKey = 'id';
    public $timestamps    = false;

}
