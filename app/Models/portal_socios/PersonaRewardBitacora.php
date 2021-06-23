<?php

namespace App\Http\Models\portal_socios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaRewardBitacora extends Model
{
    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = "portal_socios.persona_reward_bitacora";
    protected $primaryKey = "id";

    public static function validaEstatusReward($idPersona)
    {
        return PersonaRewardBitacora::where('completado', 0)
            ->where('idPersona', $idPersona)
            ->orderBy('id', 'DESC')->first();

    }

}
