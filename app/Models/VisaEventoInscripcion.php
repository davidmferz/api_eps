<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class VisaEventoInscripcion extends Model
{
    protected $connection = 'crm';
    protected $table = 'crm.visaeventoinscripcion';
    protected $primaryKey = 'id';

    public function scopeValidaEvento($query, $idPersona, $fechas, $inicio, $final)
    {
        $result =  $query
        ->select('*')
        ->join('crm.eventofecha', function ($join) use ($idPersona, $fechas, $inicio, $final) {
            $join->on('eventofecha.idEventoInscripcion', '=', 'visaeventoinscripcion.idEventoInscripcion')
            ->where('eventofecha.fechaEliminacion', '=', '0000-00-00 00:00:00')
            // ->where('eventofecha.idEventoInscripcion', '=', $idEventoInscripcion)
            ->where('visaeventoinscripcion.idPersona', '=', $idPersona)
            ->whereBetween('eventofecha.fechaEvento',[$fechas['firstday'], $fechas['lastday']])
            // ->whereBetween('eventofecha.fechaEvento',[$inicio, $final])
            ;
        })
        ->count()
        ;
        return $result;
    }
}
