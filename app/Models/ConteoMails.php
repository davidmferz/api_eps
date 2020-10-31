<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConteoMails extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'piso.conteo_mail_encuestas';
    protected $primaryKey = 'id';
}
