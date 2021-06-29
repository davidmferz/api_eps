<?php

namespace App\Models\Deportiva;

use Illuminate\Database\Eloquent\Model;

class AuxSyncPersona extends Model
{
    protected $connection = 'aws';
    protected $table      = 'deportiva.aux_sync_persona';
    protected $primaryKey = 'id';
    public $timestamps    = false;

}
