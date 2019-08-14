<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class PromocionVisa extends Model
{
    protected $connection = 'crm';
    protected $table = 'crm.promocionvisa';
    protected $primaryKey = 'id';

    public function scopeValidaCliente($query, $idPersona)
    {
        $valid = $query
        ->select(DB::raw('promocionvisa.*'))
        ->join('crm.membresia', function ($join) use ($idPersona) {
            $join->on('promocionvisa.idUnicoMembresia', '=', 'membresia.idUnicoMembresia')
            ->where('membresia.fechaEliminacion', '=', '0000-00-00 00:00:00')
            ->where('membresia.idPersona', '=', $idPersona);
        })
        ->where('promocionvisa.eps', '=', 1)
        ->first();
        return $valid;
    }

    public static function getFechas($tipo)
    {
        switch ($tipo) {
            case 'Infinite':
                $days = 7;
                $dias = 1;
                break;
            case 'Platinum':
                $days = 60;
                $dias = 2;
                break;
        }
        $firstday = Carbon::now()->subDays($days)->format('Y-m-d');
        $lastday = Carbon::now()->format('Y-m-d');
        return ['firstday' => $firstday,'lastday' => $lastday, 'days' => $dias];
    }

    public function scopeValidaEvento($query, $idPersona, $fechas, $inicio, $final)
    {
        $result =  $query
        ->select('*')
        ->join('crm.membresia', function ($join) {
            $join->on('membresia.idUnicoMembresia', '=', 'promocionvisa.idUnicoMembresia')
            ->where('membresia.fechaEliminacion', '=', '0000-00-00 00:00:00');
        })
        ->join('crm.eventoinscripcion', function ($join) use ($fechas) {
            $join->on('eventoinscripcion.idPersona', '=', 'membresia.idPersona')
            ->where('eventoinscripcion.fechaEliminacion', '=', '0000-00-00 00:00:00')
            ->whereBetween('eventoinscripcion.fechaRegistro',[$fechas['firstday'], $fechas['lastday']]);
        })
        ->where('membresia.idPersona', '=', $idPersona)
        ->where('eventoinscripcion.visa', '=', 1)
        ->count();
        return $result;
    }
}
