<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Menu extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'piso.menu';
    protected $primaryKey = 'id';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';
    public static function historicoCliente($idPersona)
    {
        $sql = "SELECT  concat_ws(' ',p.nombre,p.paterno,p.materno) AS entrenador,u.nombre as club ,cr.nivel,cr.rutina,m.fecha_inicio,m.fecha_fin
                FROM piso.menu as m
                JOIN deportiva.persona as p ON m.idEmpleado =p.idPersona
                JOIN deportiva.un as u ON u.idUn=m.idUn
                JOIN piso.cat_rutinas as cr ON cr.id=m.idRutina
                where m.idPersona ={$idPersona}
                group By m.fecha_inicio
                order by m.id desc
                limit 10";
        $query = DB::connection('aws')->select($sql);
        if (count($query) > 0) {
            return $query;
        } else {
            return [];

        }
    }

    public function scopeGetConteoRutinasPorFecha($query, $fechaInicio, $fechaFin)
    {
        return $query->selectRaw('idUn, count(*) as total')
            ->whereBetween('fechaRegistro', [$fechaInicio, $fechaFin])
            ->get();
    }

    /**
     * Regresa un array con los tipo de cliente
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function scopeRutinasEntrenadores($query, $ids)
    {
        $fecha      = Carbon::now();
        $fecha->day = 1;
        $ini        = $fecha->format('Y-m-d');
        $fin        = $fecha->endOfMonth()->format('Y-m-d');

        return $query->selectRaw("p.idPersona,CONCAT(p.nombre,' ',p.paterno,' ',p.materno) nombre_socio, cr.rutina, cr.nivel,menu.observaciones, menu.idEmpleado, date(menu.fechaRegistro) as fechaRegistro ")
            ->join('piso.cat_rutinas as cr', 'cr.id', '=', 'menu.idRutina')
            ->Join('deportiva.persona as p', 'p.idPersona', '=', 'menu.idPersona')
            ->join('deportiva.empleado as e', 'e.idPersona', '=', 'menu.idEmpleado')
            ->whereIn('menu.idEmpleado', $ids)
            ->whereRaw("menu.fechaRegistro  between  '{$ini}' AND  '{$fin}'")
            ->where('menu.fechaEliminacion', '0000-00-00 00:00:00')
            ->get();
        $addSlashes = str_replace('?', "'?'", $query->toSql());
        $sq         = vsprintf(str_replace('?', '%s', $addSlashes), $query->getBindings());
        dd($sq);

    }

    public function scopeRutinasClubConteo($query, $fechaIni, $fechaFin)
    {

        $query->selectRaw("CONCAT(p.nombre,' ',p.paterno,' ',p.materno) nombre_socio, cr.rutina, cr.nivel,menu.observaciones, menu.idEmpleado")
            ->join('piso.cat_rutinas as cr', 'cr.id', '=', 'menu.idRutina')
            ->Join('deportiva.persona as p', 'p.idPersona', '=', 'menu.idPersona')
            ->join('deportiva.empleado as e', 'e.idPersona', '=', 'menu.idEmpleado')
            ->whereRaw("menu.fechaRegistro  between  '{$fechaIni}' AND  '{$fechaFin}'")
            ->where('menu.fechaEliminacion', '0000-00-00 00:00:00');
        // ->get();
        $addSlashes = str_replace('?', "'?'", $query->toSql());
        $sq         = vsprintf(str_replace('?', '%s', $addSlashes), $query->getBindings());
        dd($sq);
    }

    public static function getConteoRutinasClub($idsClubs)
    {
        $sql = "SELECT
                DATE_FORMAT(fechaRegistro, '%Y-%m') date2,
                idUn,
                count(*) as numRutinas
                FROM piso.menu
                where DATE_FORMAT(fechaRegistro, '%Y-%m')  > DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 6 MONTH) ,'%Y-%m')
                AND idUn IN ({$idsClubs})
                group by date2 ,idUn
                order by date2,idUn";
        $rows  = DB::connection('aws')->select($sql);
        $datos = [];
        foreach ($rows as $key => $value) {
            $datos[$value->idUn][] = ['mes' => $value->date2, 'num' => $value->numRutinas];

        }
        return $datos;
    }

    public static function getConteoRutinasRegion($clubs)
    {
        $sql = "SELECT
                DATE_FORMAT(fechaRegistro, '%Y-%m') date2,
                idUn,
                count(*) as numRutinas

                FROM piso.menu
                where DATE_FORMAT(fechaRegistro, '%Y-%m')  > DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 6 MONTH) ,'%Y-%m')
                group by date2 ,idUn
                order by date2,idUn";

        $rows = DB::connection('aws')->select($sql);

        $total = [];
        $datos = [];
        foreach ($rows as $key => $value) {
            if (isset($total[$value->date2])) {
                $total[$value->date2] += $value->numRutinas;
            } else {
                $total[$value->date2] = $value->numRutinas;
            }
            foreach ($clubs as $idRegion => $idClubs) {
                if (in_array($value->idUn, $idClubs)) {
                    if (isset($datos[$idRegion][$value->date2])) {
                        $datos[$idRegion][$value->date2] += $value->numRutinas;
                    } else {
                        $datos[$idRegion][$value->date2] = $value->numRutinas;
                    }
                }
            }
        }
        $datos[0] = $total;
        ksort($datos);
        return $datos;
    }
    public static function getConteoRutinasEntrenadores($idUn)
    {
        $sql = "SELECT
        DATE_FORMAT(m.fechaRegistro, '%Y-%m') date2,

        count(*) as numRutinas,
        m.idEmpleado,
        concat(p.nombre,' ',p.paterno) as nombreCompleto

        FROM piso.menu as m
        join deportiva.persona as p ON p.idPersona=m.idEmpleado
        where DATE_FORMAT(m.fechaRegistro, '%Y-%m')  > DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 6 MONTH) ,'%Y-%m')
        AND m.idUn={$idUn}
        group by date2 ,m.idEmpleado
        order by date2;
        ";

        $rows = DB::connection('aws')->select($sql);

        $total = [];
        $datos = [];
        foreach ($rows as $key => $value) {
            $datos[$value->idEmpleado][] = ['mes' => $value->date2, 'num' => $value->numRutinas, 'nombre' => strtolower($value->nombreCompleto)];

        }
        ksort($datos);
        return $datos;
    }
    public static function getHistoryRutinas($idPersonaEmpleado)
    {
        $sql = "SELECT  p.idPersona, concat_ws(' ',p.nombre,p.paterno,p.materno) AS nombre
                FROM piso.menu as m
                JOIN deportiva.persona as p ON m.idPersona =p.idPersona
                where m.idEmpleado={$idPersonaEmpleado}
                order by m.id desc
                limit 5;";
        $query = DB::connection('aws')->select($sql);
        if (count($query) > 0) {
            return $query;
        } else {
            return [];

        }
    }

    public static function scopeReadMenuActividad($query, $idPersona)
    {
        $sql = "SELECT crut.id, mnu.id as menu_id
                    FROM piso.menu mnu
                    INNER JOIN piso.cat_rutinas crut ON crut.id = mnu.idRutina
                    WHERE mnu.idPersona = {$idPersona}

                    ORDER BY mnu.fechaActualizacion desc
                    LIMIT 1 ";
        $res = DB::connection('aws')->select($sql);
        if (count($res) > 0) {
            $rutina_id = $res[0]->id;
            $menu_id   = $res[0]->menu_id;
        } else {
            $rutina_id = 0;
            $menu_id   = 0;
        }
        $arr_avance = self::calculaAvance($rutina_id, $menu_id);

        // Se obtiene la fecha de inicio de la rutina y el idRutina
        $sql = "SELECT mnua.circuito_id, mnua.cardio_id, mnua.clase_id, mnua.optativa_id, mnua.dia, crut.nivel, mnu.porcentaje, crut.rutina, mnu.observaciones
                    FROM piso.menu mnu
                    INNER JOIN piso.menu_actividad mnua ON mnua.menu_id = mnu.id
                    INNER JOIN piso.cat_rutinas crut ON crut.id = mnu.idRutina
                    WHERE mnu.idPersona = {$idPersona}

                    AND crut.id = {$rutina_id}
                    GROUP BY mnua.dia
                    ORDER BY dia ASC
            ";

        $res = DB::connection('aws')->select($sql);
        if (count($res) > 0) {
            $res = array_map(function ($x) {return (array) $x;}, $res);
        } else {
            return [
                'estatus' => false,
                'message' => true,
            ];
        }

        $arr                  = [];
        $arr['idPersona']     = $idPersona;
        $arr['nivel']         = $res[0]['nivel'];
        $arr['avance']        = $res[0]['porcentaje']; // $arr_avance['porcentaje_avance'];
        $arr['rutina']        = $res[0]['rutina'];
        $arr['observaciones'] = $res[0]['observaciones'];

        $arr['dias'] = [];
        foreach ($res as $v1) {
            unset($v1['nivel']);
            unset($v1['rutina']);
            unset($v1['observaciones']);
            unset($v1['porcentaje']);
            $arr2 = [];
            foreach ($v1 as $k2 => $v2) {
                if ($k2 != 'dia') {
                    if ($v2 > 0) {
                        $v2 = true;
                    } else {
                        $v2 = false;
                    }
                }
                switch ($k2) {
                    case 'circuito_id':$k2 = 'fuerza';
                        break;
                    case 'cardio_id':$k2 = 'cardio';
                        break;
                    case 'clase_id':$k2 = 'clase';
                        break;
                    case 'optativa_id':$k2 = 'optativa';
                        break;
                    case 'dia':$k2 = 'fecha';
                        break;
                }
                $arr2[$k2] = $v2;

            }

            if ($arr2['fuerza'] == false && $arr2['cardio'] == false && $arr2['clase'] == false && $arr2['optativa'] == false) {
                $arr2['descanso'] = true;
            } else {
                $arr2['descanso'] = false;
            }

            $arr['dias'][] = $arr2;
        }

        return [
            'estatus' => true,
            'data'    => $arr,
        ];
    }

    private static function calculaAvance($rutina_id, $menu_id)
    {
        // Obtiene cuantos ejercicios existen para una rutina
        $sql = "SELECT * FROM
                (SELECT
                COUNT(*) as ejercicios
                FROM piso.cat_rutinas crut
                INNER JOIN piso.rutina_circuitos rc ON rc.rutina_id = crut.id
                INNER JOIN piso.circuitos_has_ejercicios che ON che.circuito_id = rc.id
                WHERE rc.rutina_id = {$rutina_id}) ejercicios
                INNER JOIN
                (SELECT
                COUNT(*) as cardios
                FROM piso.cat_rutinas crut
                INNER JOIN piso.rutina_cardios rc ON rc.rutina_id = crut.id
                WHERE rc.rutina_id = {$rutina_id}) cardios ON 1 = 1
                INNER JOIN
                (SELECT
                COUNT(*) as clases
                FROM piso.cat_rutinas crut
                INNER JOIN piso.rutinas_has_clases rh ON rh.rutina_id = crut.id
                WHERE rh.rutina_id = {$rutina_id}) clases ON 1 = 1
                INNER JOIN
                (SELECT
                COUNT(*) as optativas
                FROM piso.cat_rutinas crut
                INNER JOIN piso.rutinas_has_optativas rh ON rh.rutina_id = crut.id
                WHERE rh.rutina_id = {$rutina_id}) optativas ON 1 = 1 ";
        $res = DB::connection('aws')->select($sql);

        $arr_out           = [];
        $porcentaje_avance = 0; // 0-100%
        $arr_ponderacion   = [];
        if (count($res) > 0) {
            $conteo_actividades = array_map(function ($x) {return (array) $x;}, $res);
            $conteo_actividades = $conteo_actividades[0];

            // Obtiene cuantos ejercicios ha completado para idPersona - rutina_id
            $arr_respuesta = [];

            $sql = "SELECT * FROM
                    (SELECT COUNT(*) as ejercicios
                    FROM piso.menu_ejercicio_completado mec
                    WHERE menu_id = {$menu_id}
                    AND mec.completado = 1) ejercicios
                    INNER JOIN
                    (SELECT COUNT(*) as cardios
                    FROM piso.menu_cardio_completado mec
                    WHERE menu_id = {$menu_id}
                    AND mec.completado = 1) cardios ON 1 = 1
                    INNER JOIN
                    (SELECT COUNT(*) as clases
                    FROM piso.menu_clase_completado mec
                    WHERE menu_id = {$menu_id}
                    AND mec.completado = 1) clases ON 1 = 1
                    INNER JOIN
                    (SELECT COUNT(*) as optativas
                    FROM piso.menu_optativa_completado mec
                    WHERE menu_id = {$menu_id}
                    AND mec.completado = 1) optativas ON 1 = 1 ";
            $res = DB::connection('aws')->select($sql);
            if (count($res) > 0) {
                $conteo_completadas = array_map(function ($x) {return (array) $x;}, $res);
                $conteo_completadas = $conteo_completadas[0];
                $total_actividades  = array_sum($conteo_actividades);

                foreach ($conteo_actividades as $nom_act_a => $conteo_actividad) {
                    foreach ($conteo_completadas as $nom_act_c => $conteo_completada) {
                        if ($nom_act_a == $nom_act_c) {
                            if (intval($conteo_actividad) == 0) {
                                Log:info("División por cero, la actividad {$nom_act_a}");
                                //throw new \Exception('Falló interno del sistema', 500);
                                continue;
                            }
                            $porcentaje_avance += round(33.33 * intval($conteo_completada) / intval($conteo_actividad), 2);
                            $arr_ponderacion[$nom_act_a]      = round(33.33 / intval($conteo_actividad), 2);
                            $arr_conteo_actividad[$nom_act_a] = intval($conteo_actividad);
                            // $porcentaje_avance += round(100*intval($conteo_completada) / intval($total_actividades),2);
                            // $arr_ponderacion[$nom_act_a] = round(100 / intval($total_actividades),2);
                        }
                    }
                }
                $arr_ponderacion['optativas'] = 0;
            }
            $arr_out = [
                'porcentaje_avance' => $porcentaje_avance,
                'ponderacion'       => $arr_ponderacion,
                // 'conteo_actividad' => $arr_conteo_actividad,
            ];
        }
        return $arr_out;
    }

    public static function rakingEntrenadores()
    {
        $inicio       = Carbon::now();
        $fin          = $inicio->daysInMonth;
        $inicio->day  = 1;
        $inicioFormat = $inicio->format('Y-m-d');
        $inicio->day  = $fin;
        $finFormat    = $inicio->format('Y-m-d');
        $sql          = "SELECT   count(*) as num,p.nombre,p.paterno,m.idUn
                        FROM piso.menu as m
                        JOIN deportiva.persona as p ON m.idEmpleado =p.idPersona
                        where m.fechaRegistro between  '{$inicioFormat}' AND '{$finFormat}'
                        AND idEmpleado IS NOT NULL
                        group by idEmpleado,m.idUn
                        order by num desc";

        $res = DB::connection('aws')->select($sql);
        if (count($res) > 0) {
            $etiquetas = [];
            $valor     = [];
            $idUn      = [];
            foreach ($res as $key => $value) {
                $etiquetas[] = $value->nombre . ' ' . $value->paterno;
                $valor[]     = $value->num;
                $idUn[]      = $value->idUn;
            }
            return [
                "etiquetas" => $etiquetas,
                "valor"     => $valor,
                "clubs"     => $idUn,
            ];

        } else {
            return [];
        }

    }

}
