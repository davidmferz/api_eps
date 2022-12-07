<?php

namespace App\Models\Deportiva;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EpsPuestosCrm2 extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'deportiva.eps_puestos_crm2';
    protected $primaryKey = 'idPuesto';
    protected $hidden     = [
        'created_at', 'update_at', 'deleted_at',
    ];
}
