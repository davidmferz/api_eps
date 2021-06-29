<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CalificacionEntrenador extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'piso.calificacion_entrenador';

    protected $primaryKey = 'idEmpleado';

    public static function getCalificacionesAws($entrenadores)
    {
        if (count($entrenadores) <= 0) {
            return [];
        }
        $strIds = implode(",", $entrenadores);
        $sql    = "SELECT
            idEmpleado,
             calificacion,
             promedio_p1 as q1,
             promedio_p2 as q2,
             promedio_p3 as q3,
             promedio_p4 as q4,
             0 as q5,
             0 as q6,
            'x' AS total
            FROM piso.calificacion_entrenador
            WHERE idEmpleado IN ({$strIds})
            ";
        $retval = DB::connection('aws')->select($sql);
        //dd($retval);

        $retval = array_map(function ($x) {return (array) $x;}, $retval);
        return $retval;
    }

    public function scopeGuardaPromedio($query, $idEmpleado, $columna)
    {

        $empleado = $query->where('idEmpleado', $idEmpleado)->first();
        if ($empleado == null) {
            $empleado              = new self();
            $empleado->promedio_p1 = 0;
            $empleado->promedio_p2 = 0;
            $empleado->promedio_p3 = 0;
            $empleado->promedio_p4 = 0;
            //$empleado->promedio_p1=0;
        }

        $sql = "SELECT
                    e.idEmpleado,
                    TRUNCATE(SUM(ec.calificacion)/COUNT(ec.calificacion),2) AS calificacion,
                    AVG(IF(ec.{$columna} >0 ,ec.{$columna} ,NULL)) as promedio
                    FROM crm.eventocalificacion ec
                    JOIN crm.eventoinvolucrado ei ON ei.idEventoInscripcion = ec.idEventoInscripcion AND tipo='Entrenador'
                    JOIN crm.empleado as e ON e.idPersona=ei.idPersona AND idTipoEstatusEmpleado=196
                    JOIN crm.persona as p ON p.idPersona=ei.idPersona
                    WHERE e.idEmpleado IN ({$idEmpleado})
                    AND ei.fechaEliminacion = 0
                    AND ec.fechaEliminacion = 0
                    GROUP BY e.idEmpleado
                   ";
        $calificacion = DB::connection('crm')->select($sql);
        if (count($calificacion) > 0) {
            $calificacion           = $calificacion[0];
            $empleado->idEmpleado   = $idEmpleado;
            $empleado->calificacion = $calificacion->calificacion;
            switch ($columna) {
                case 'q1':
                    $empleado->promedio_p1 = $calificacion->promedio;
                    break;
                case 'q2':
                    $empleado->promedio_p2 = $calificacion->promedio;
                    # code...
                    break;
                case 'q3':
                    $empleado->promedio_p3 = $calificacion->promedio;
                    # code...
                    break;
                case 'q4':
                    $empleado->promedio_p4 = $calificacion->promedio;
                    # code...
                    break;

                default:
                    return false;
                    break;
            }

            $empleado->save();
            return true;

        } else {
            return false;
        }

    }

}
