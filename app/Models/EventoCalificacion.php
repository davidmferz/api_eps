<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EventoCalificacion extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.eventocalificacion';
    protected $primaryKey = 'idEventoCalificacion';
    public $timestamps    = false;

    public function scopeGetCalificaciones($scope, $entrenadores)
    {

        $strIds = implode(",", $entrenadores);
        $sql    = "SELECT
            ei.idEmpleado,
            TRUNCATE(SUM(ec.calificacion)/COUNT(ec.calificacion),2) AS calificacion,
            TRUNCATE((SUM(ec.q1)/COUNT(ec.calificacion))*100,2) AS q1,
            TRUNCATE((SUM(ec.q2)/COUNT(ec.calificacion))*100,2) AS q2,
            TRUNCATE((SUM(ec.q3)/COUNT(ec.calificacion))*100,2) AS q3,
            TRUNCATE((SUM(ec.q4)/COUNT(ec.calificacion))*100,2) AS q4,
            TRUNCATE((SUM(ec.q5)/COUNT(ec.calificacion))*100,2) AS q5,
            TRUNCATE((SUM(ec.q6)/COUNT(ec.calificacion))*100,2) AS q6,
            COUNT(ec.calificacion) AS total
            FROM crm.eventocalificacion ec
            JOIN crm.eventoinscripcion ei ON ei.idEventoInscripcion = ec.idEventoInscripcion
                AND ei.idEmpleado = ec.idEmpleado
            JOIN crm.empleado as e ON e.idempleado=ei.idEmpleado  AND idTipoEstatusEmpleado=196
            JOIN crm.persona as p ON p.idPersona=ei.idPersona
            WHERE ei.idEmpleado IN ({$strIds})

            AND ei.fechaEliminacion = 0
            AND ec.fechaEliminacion = 0
            GROUP BY ei.idEmpleado";
        $retval = DB::connection('crm')->select($sql);
        $retval = array_map(function ($x) {return (array) $x;}, $retval);
        return $retval;
    }

}
