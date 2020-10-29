<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemoClientes extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.demo_clientes';
    protected $primaryKey = 'id';

}
