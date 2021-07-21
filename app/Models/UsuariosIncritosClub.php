<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsuariosIncritosClub extends Model
{
    protected $connection = 'aws';
    protected $table      = 'app.usuarios_incritos_club';
    protected $primaryKey = 'id';
    use SoftDeletes;

}
