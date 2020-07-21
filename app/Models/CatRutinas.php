<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CatRutinas extends Model
{

    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = "piso.cat_rutinas";
    protected $primaryKey = "id";

    const CREATED_AT  = 'fechaRegistro';
    const UPDATED_AT  = 'fechaActualizacion';
    const DELETED_AT  = 'fechaEliminacion';
    protected $hidden = [
        'fechaRegistro', 'fechaActualizacion', 'fechaEliminacion',
    ];
    public static function read_rutina_circuitos($rutina_id)
    {

        $sql = "SELECT rc.id ,rc.num_dia,che.id as idCircuito,che.ejercicio_id,che.orden
        FROM piso.rutina_circuitos AS rc
        INNER JOIN piso.circuitos_has_ejercicios AS che ON rc.id = che.circuito_id
        WHERE rc.rutina_id = {$rutina_id}
        ORDER BY rc.num_dia,orden";
        $res = DB::connection('aws')->select($sql);
        if (count($res) > 0) {
            $rutina = [];
            foreach ($res as $circuito) {
                if (isset($rutina[$circuito->id])) {
                    $rutina[$circuito->id]['ejercicios'][] = [
                        'id'           => $circuito->idCircuito,
                        'ejercicio_id' => $circuito->ejercicio_id,
                        'orden'        => $circuito->orden,
                    ];
                } else {

                    $rutina[$circuito->id] = [
                        'id'         => $circuito->id,
                        'num_dia'    => $circuito->num_dia,
                        'ejercicios' => [
                            [
                                'id'           => $circuito->idCircuito,
                                'ejercicio_id' => $circuito->ejercicio_id,
                                'orden'        => $circuito->orden,
                            ],
                        ],
                    ];

                }
            }

        }
        $rutina = array_values($rutina);
        return $rutina;
    }
    public static function read_rutina_cardios($rutina_id)
    {
        $res = DB::connection('aws')->table('piso.rutina_cardios')
            ->select('id', 'num_semana', 'num_semana', 'equipo_id', 'tiempo', 'intensidad')
            ->where('rutina_id', '=', $rutina_id)->get();

        $arr_return = [];
        if (count($res) > 0) {
            // Se obtiene detalle de circuitos
            $res_arr1 = $res->toArray();
            $res_arr1 = array_map(function ($x) {return (array) $x;}, $res_arr1);
            $arr_return = $res_arr1;
        }
        return $arr_return;
    }
    public static function getMiRutina($idPersona)
    {
        $sql = "SELECT
                        m.id as idRutina,cr.nivel,cr.rutina, m.fecha_inicio,m.Fecha_fin
                    FROM piso.menu as m
                    JOIN  piso.cat_rutinas as cr on m.idRutina=cr.id
                    WHERE
                        m.idPersona = {$idPersona}
                            AND (m.fechaEliminacion = '0000-00-00 00:00:00' || m.fechaEliminacion IS NULL)
                            AND m.fecha_fin > NOW()
                            AND m.fechaCancelacion IS NULL
                    ORDER BY m.fechaRegistro DESC LIMIT 1";
        $res = DB::connection('aws')->select($sql);

        if (count($res) > 0) {
            return ['estatus' => true, 'data' => $res[0]];
        } else {
            return ['estatus' => false, 'mensaje' => 'Acércate a un entrenador para que genere tu rutina'];
        }
    }

}
