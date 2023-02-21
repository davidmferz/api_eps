<?php
namespace App\Models\CRM2\msclub;

use Illuminate\Database\Eloquent\Model;

class Club extends Model
{

    protected $connection = 'crm2';
    protected $table      = 'msclub.club';
    protected $primaryKey = 'club_id';
    public $timestamps    = false;

}
