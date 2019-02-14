<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class AgendaInbody extends Model
{
    protected $connection = 'aws';
    protected $table      = 'piso.agenda_inbody';
    protected $primaryKey = 'idAgenda';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    protected $fillable = [
        'idPersona',
        'idUn',
        'horario',
        'fechaSolicitud',
        'fechaAsistencia',
        'fechaConfirmacion',
    ];

    const HORAS_INICIA           = 8;
    const HORAS_DESCANSO_INICIA  = 13;
    const HORAS_DESCANSO_TERMINA = 15;
    const HORAS_TERMINA          = 20;
    const HORAS_ANTICIPACION     = 36;
    const MINUTOS_INBODY         = 30;
    const MAX_CAPACIDAD_CLUB     = 20;
    const NUM_DIAS_A_MOSTRAR     = 60;

    const FITWEEK_ACTIVO = true;
    const FITWEEK_INICIO = 5;
    const FITWEEK_FINAL  = 12;

    /**
     * Consulta Inbody por Empleado
     *
     * @version 1.0.0
     * @param $data
     * @return JSON
     */
    public static function scopeConsultaInbodyEmpleado($query, $idUn, $idEmpleado)
    {
        $sql = "SELECT  CONCAT_WS(' ', persona.nombre, persona.paterno, persona.materno) AS nombre
        from persona
        join empleado ON persona.idPersona = empleado.idPersona
        where idEmpleado = {$idEmpleado}";

        $query = DB::connection('crm')->select($sql);

        $nombreEntrenador = $query[0]->nombre;
        //dd($nombreEmpleado);
        $now    = Carbon::now();
        $after  = Carbon::now()->addMonth();
        $before = Carbon::now()->subMonths(2);

        $res = self::select(DB::connection('aws')->raw("un.nombre ,
            idAgenda as id ,
            CONCAT('InBody', ' - ', CONCAT_WS(' ', persona.nombre, persona.paterno, persona.materno)) AS title,
            horario , fechaSolicitud as start ,
            if(`fechaConfirmacion`<>'0000-00-00 00:00:00' OR fechaCancelacion <> '0000-00-00 00:00:00',0,1)  as editable ,
            0 as comisionPagada,
            'InBody' as descripcionClase,
            '{$nombreEntrenador}' as nombreEntrenador,
            if(`fechaConfirmacion`<>'0000-00-00 00:00:00',1,0) as confirmado,
            if(`fechaCancelacion`<>'0000-00-00 00:00:00',1,0) as cancelado
            "))
            ->join('deportiva.persona', function ($join) {
                $join->on('persona.idPersona', '=', 'agenda_inbody.idPersona');
            })

            ->join('deportiva.un', function ($join) {
                $join->on('un.idUn', '=', 'agenda_inbody.idUn');
            })
            ->where('agenda_inbody.fechaEliminacion', '=', '0000-00-00 00:00:00')
            ->whereBetween('fechaSolicitud', [$before, $after])
            ->where('agenda_inbody.idUn', '=', $idUn)
            ->where('agenda_inbody.idEmpleado', '=', $idEmpleado);

        $res = $res->orderBy('agenda_inbody.idUn', 'asc')
            ->orderBy('fechaSolicitud', 'asc')
            ->get()
            ->toArray();

        /*   $addSlashes = str_replace('?', "'?'", $res->toSql());
        $sq= vsprintf(str_replace('?', '%s', $addSlashes), $res->getBindings());
        dd($sq);
        /*
         */
        if (count($res) > 0) {

            return $res;

        }

        return [];

    }
}
