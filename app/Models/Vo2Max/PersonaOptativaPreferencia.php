<?php

namespace App\Models\Vo2Max;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaOptativaPreferencia extends Model
{
    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = 'piso.persona_optativa_preferencia';
    protected $primaryKey = 'idPersona';
}
