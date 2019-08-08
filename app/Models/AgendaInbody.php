<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public static function getDatosMailAgendainbody($idAgenda)
    {
        $sql = "SELECT
                    CONCAT(p.nombre,' ',p.paterno,' ',p.materno) as  nombreSocio,
                    CONCAT(pe.nombre,' ',pe.paterno,' ',pe.materno) as  nombreEmpleado,
                    u.nombre as nombreClub,
                    e.idPersona as idPersona_empleado
                from    piso.agenda_inbody as ai
                JOIN deportiva.persona as p ON p.idPersona=ai.idPersona
                JOIN deportiva.empleado as e ON ai.idEmpleado=e.idEmpleado
                JOIN deportiva.persona as pe ON pe.idPersona=e.idPersona
                JOIN deportiva.un as u ON u.idUn = ai.idUn
                where idAgenda= {$idAgenda}";
        $query = DB::connection('aws')->select($sql);

        return $query[0];
    }

    /**
     * Consulta Inbody por Empleado
     *
     * @version 1.0.0
     * @param $data
     * @return JSON
     */
    public static function scopeConsultaInbodyEmpleado($query, $idEmpleado, $idUn, $fecha = '')
    {
        $sql = "SELECT  CONCAT_WS(' ', persona.nombre, persona.paterno, persona.materno) AS nombre
        from persona
        join empleado ON persona.idPersona = empleado.idPersona
        where idEmpleado = {$idEmpleado}";

        $query = DB::connection('crm')->select($sql);

        $nombreEntrenador = $query[0]->nombre;
        //dd($nombreEmpleado);
        $now = Carbon::now();
        if ($fecha != '') {
            $before         = new Carbon($fecha);
            $before->minute = 0;

            $after         = new Carbon($fecha);
            $after->minute = 0;
            $after->addHour(1);

        } else {
            $before = Carbon::now()->subMonths(1);
            $after  = Carbon::now()->addMonth(2);

        }

        $res = self::select(DB::connection('aws')->raw("un.nombre ,
            idAgenda as id ,
            persona.idPersona,
            CONCAT('InBody', ' - ', CONCAT_WS(' ', persona.nombre, persona.paterno, persona.materno)) AS title,
            horario , fechaSolicitud as start ,
            if(`fechaConfirmacion`<>'0000-00-00 00:00:00' OR fechaCancelacion <> '0000-00-00 00:00:00',0,1)  as editable ,
            0 as comisionPagada,
            'InBody' as descripcionClase,
            '{$nombreEntrenador}' as nombreEntrenador,
            if(`fechaConfirmacion`<>'0000-00-00 00:00:00',1,0) as confirmado,
            if(`fechaCancelacion`<>'0000-00-00 00:00:00',1,0) as cancelado,
            0 as  nuevo
            "))
            ->join('deportiva.persona', function ($join) {
                $join->on('persona.idPersona', '=', 'agenda_inbody.idPersona');
            })
            ->join('deportiva.un', function ($join) {
                $join->on('un.idUn', '=', 'agenda_inbody.idUn');
            })
            ->where('agenda_inbody.fechaEliminacion', '=', '0000-00-00 00:00:00')
            ->where('agenda_inbody.idUn', '=', $idUn)
            ->where('agenda_inbody.idEmpleado', '=', $idEmpleado);

        $res->whereBetween('fechaSolicitud', [$before, $after]);

        $res = $res->orderBy('agenda_inbody.idUn', 'asc')
            ->orderBy('fechaSolicitud', 'asc')
            ->get()
            ->toArray();
/*
$addSlashes = str_replace('?', "'?'", $res->toSql());
$sq         = vsprintf(str_replace('?', '%s', $addSlashes), $res->getBindings());
dd($sq);
 */
        if (count($res) > 0) {

            $idPersonas    = array_unique(array_column($res, 'idPersona'));
            $ids           = implode(',', $idPersonas);
            $intervaloDias = 700;
            $sql           = "SELECT  CAST(so.idPersona AS char(50)) as idPersona
                            from socio as so
                            LEFT join membresiareactivacion as mem ON  so.idUnicoMembresia=mem.idUnicoMembresia AND mem.fechaEliminacion='0000-00-00 00:00:00'

                            where so.eliminado=0
                            AND so.idPersona IN ({$ids})
                            AND IF(DATEDIFF(NOW(),mem.fechaRegistro )<{$intervaloDias} OR  DATEDIFF(NOW(),so.fechaRegistro )<{$intervaloDias},1,0) = 1
                            order by mem.idMembresiaReactivacion DESC
                            ";

            $elemts = DB::connection('crm')->select($sql);
            $finIds = array_column($elemts, 'idPersona');
            foreach ($res as $key => $value) {
                if (in_array((string) $value['idPersona'], $finIds)) {
                    $res[$key]['nuevo'] = 1;

                }
            }

            return $res;

        }

        return [];

    }
}
