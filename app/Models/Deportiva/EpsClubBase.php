<?php

namespace App\Models\Deportiva;

use Illuminate\Database\Eloquent\Model;

class EpsClubBase extends Model
{
    protected $connection = 'aws';
    protected $table      = 'deportiva.eps_club_base';
    protected $primaryKey = ['idUsuario', 'idClub'];
    public $incrementing  = false;
    public $timestamps    = false;
}
