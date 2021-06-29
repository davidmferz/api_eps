<?php

namespace App\Models\Vo2Max;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FitnessTest extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'piso.persona_fitnes_test';
    protected $primaryKey = 'id';
}
