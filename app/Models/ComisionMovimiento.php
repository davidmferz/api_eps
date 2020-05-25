<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComisionMovimiento extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.comisionmovimiento';
    protected $primaryKey = 'idComisionMovimiento';

//    public $timestamps = false;

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = null;
    const DELETED_AT = 'fechaEliminacion';
}
