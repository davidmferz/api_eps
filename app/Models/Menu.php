<?php

namespace App\Models;

use App\Models\CatRutinas;
use App\Models\PersonaInbody;
use App\Models\portal_socios\PersonaRewardBitacora;
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
            ->whereNull('menu.fechaEliminacion')
            ->get();
        
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

                    ORDER BY mnu.fechaRegistro desc
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
    public static function insertMenu($idUn, $idPersona, $idRutina, $fechaInicio, $fechaFin, $observaciones, $actividades, $idEmpleado)
    {
        try {
            $conn_01 = DB::connection('aws');
            $conn_01->beginTransaction();
            $hoy = Carbon::now();
            // self::where('idPersona', $idPersona)->update(['fechaEliminacion' => $hoy]);
            $menu = new self();

            $menu->idPersona  = $idPersona;
            $menu->idEmpleado = $idEmpleado;
            $menu->idUn       = $idUn;
            // $menu->idRutina      = $idRutina;
            $menu->fecha_inicio  = $fechaInicio;
            $menu->fecha_fin     = $fechaFin;
            $menu->observaciones = $observaciones;
            $menu->save();

            $bitacora = PersonaRewardBitacora::validaEstatusReward($idPersona);
            if ($bitacora != null) {
                if ($bitacora->idMenu1 == null) {
                    $bitacora->idMenu1 = $menu->id;
                } elseif ($bitacora->idMenu2 == null) {
                    $bitacora->idMenu2 = $menu->id;
                } else {
                    $bitacora->idMenu3 = $menu->id;
                }
                $bitacora->save();
            }
            // $res = self::insertMenuActividad($idRutina, $menu->id, $actividades, $fechaInicio);
            $conn_01->commit();
            return $menu->id;
        } catch (\Illuminate\Database\QueryException $ex) {
            $conn_01->rollback();

            return ['estatus' => false, 'mensaje' => $ex->getMessage()];
        } catch (\Exception $ex) {
            $conn_01->rollback();
            return ['estatus' => false, 'mensaje' => $ex->getMessage()];
        }

    }

    private static function insertMenuActividad($idRutina, $menu_id, $actividades, $fechaInicio)
    {

        $arr_circuito = CatRutinas::read_rutina_circuitos($idRutina);
        $arr_cardio   = CatRutinas::read_rutina_cardios($idRutina);
        $fechaInicio  = Carbon::parse($fechaInicio);
        $idx_circuito = 0;
        $auxCount     = 0;
        $insert       = '';
        foreach ($actividades as $dia => $act) {
            if ($act['fuerza'] === true || $act['fuerza'] === "true") {
                $auxCount = $idx_circuito % count($arr_circuito);
                $circuito = $arr_circuito[$auxCount];
                if (count($circuito) == 0) {
                    $circuito = ['id' => 0];
                } else {
                    $idx_circuito++;
                }
            } else {
                $circuito = ['id' => 0];
            }

            if ($act['cardio'] === true || $act['cardio'] === "true") {
                $diaAux = Carbon::parse($dia);
                // Se calcula número de semana según fecha proporcionada
                $diffdays   = $fechaInicio->diffInDays($diaAux);
                $num_semana = intval($diffdays / 7) + 1;
                $cardio     = [];
                if ($num_semana <= 4) {
                    // Se devuelve arreglo según número de semana calculada
                    foreach ($arr_cardio as $arr) {
                        if ($arr["num_semana"] == $num_semana) {
                            $cardio = $arr;
                        }
                    }
                }

                if (count($cardio) == 0) {
                    $cardio = ['id' => 0];
                }
            } else {
                $cardio = ['id' => 0];
            }
            if ($act['clases'] === true || $act['clases'] === "true") {
                $clase_id = 10000; // Lo define porteriormente el socio
            } else {
                $clase_id = 0;
            }
            if ($act['opcionales'] === true || $act['opcionales'] === "true") {
                $optativa_id = 10000; // Lo define porteriormente el socio
            } else {
                $optativa_id = 0;
            }
            $insert .= "({$menu_id},'{$dia}',{$circuito['id']},{$cardio['id']},{$clase_id},{$optativa_id}),";

        }
        if ($insert != '') {
            $insert    = substr($insert, 0, -1);
            $sqlInsert = "INSERT INTO piso.menu_actividad (menu_id,dia,circuito_id,cardio_id,clase_id,optativa_id) VALUES {$insert};";
            $res       = DB::connection('aws')->select($sqlInsert);
        }
        return $res;
    }

    public static function getCardioDia($idRutina, $idPersona, $dia, $menuId, $numSemana)
    {

        // Obtención de la Frecuencia Cardiaca en Reposo
        $res = PersonaInbody::select('fcresp')
            ->where('idPersona', '=', $idPersona)
            ->orderBy('fechaRegistro', 'desc')
            ->get();
        if (count($res) > 0) {
            $fcresp = $res->first()->fcresp;
        } else {
            $fcresp = 0;
        }
        // Obtención de edad del socio
        $sql = "SELECT per.fechaNacimiento
                FROM persona per
                WHERE per.fechaNacimiento <> '0000-00-00'
                AND per.fechaEliminacion = '0000-00-00 00:00:00'
                AND per.idPersona = {$idPersona}
                LIMIT 1
                ";
        $res = DB::connection('crm')->select($sql);

        if (count($res) > 0) {
            $fechaNacimiento = $res[0]->fechaNacimiento;

            $fechaNacimiento_tmp            = Carbon::now();
            $fechaNacimiento_tmp->timestamp = strtotime($fechaNacimiento);
            $edad                           = $fechaNacimiento_tmp->diffInYears(Carbon::now());
        } else {
            $fechaNacimiento = null;
        }

        // Se obtienen cardios por menu_id
        $res = DB::connection('aws')->table('piso.rutina_cardios as rc')
            ->select(DB::raw("CONCAT({$menuId},'-',rc.id) as clave"),
                'eq.nombre as equipo', 'eq.imagen', 'rc.tiempo', 'rc.intensidad')
            ->join('piso.cat_equipos as eq', 'rc.equipo_id', '=', 'eq.id')
            ->where('rc.rutina_id', '=', $idRutina)
            ->where('rc.num_semana', '=', $numSemana)
            ->get();

        $res_arr = [];
        if (count($res) > 0) {
            $cardio  = $res->first();
            $res_arr = array_map(function ($x) {return (array) $x;}, $res_arr);

            $intensidad     = $cardio->intensidad;
            $intensidad     = str_replace('%', '', $intensidad);
            $arr_intensidad = explode('-', $intensidad);

            // Obtención de la Recuencia Cardiaca Máxima
            // $fcmax = intval(220 – $edad); // Fórmula Clásica
            if ($fechaNacimiento != null) {

                $fcmax = intval(208 - (0.7 * $edad)); // Fórmula Tanaka

                // Fórmula Karvonen para (Target Heart Rate THR)
                $fc_objetivo = intval((($fcmax - $fcresp) * $arr_intensidad[0] / 100) + $fcresp);

                $cardio->intensidad = $fc_objetivo;
            }
        }
        return [$cardio];

    }

    public static function getOptativaDia($idRutina, $dia, $menuId)
    {
        $sql = "SELECT CONCAT({$menuId},'-',co.id) AS clave, co.nombre, co.imagen
                FROM piso.rutinas_has_optativas AS rho
                INNER JOIN piso.cat_optativas AS co ON rho.optativa_id = co.id
                WHERE rho.rutina_id = {$idRutina}
                UNION
                select CONCAT({$menuId},'-',co.id) AS clave, co.nombre, co.imagen
                FROM piso.cat_optativas AS co WHERE co.nombre='OTRO'
                ";
        $res = DB::connection('aws')->select($sql);
        if (count($res) > 0) {
            return $res;
        } else {
            return [];
        }
    }

    public static function getClaseDia($idRutina, $dia, $menuId)
    {
        $sql = "SELECT CONCAT(11933,'-',cc.id) AS clave, cc.nombre, cc.imagen
                FROM piso.rutinas_has_clases AS rhc
                INNER JOIN piso.cat_clases AS cc ON rhc.clase_id = cc.id
                WHERE rhc.rutina_id = {$idRutina}

                UNION
                SELECT  CONCAT(11933,'-',cc.id) AS clave, cc.nombre, cc.imagen
                from piso.cat_clases AS cc
                WHERE cc.nombre='OTRO'
                ";
        $res = DB::connection('aws')->select($sql);
        if (count($res) > 0) {
            return $res;
        } else {
            return [];
        }
    }

    public static function getEjerciciosFuerzaDia($idPersona, $dia, $menuId, $numSemana)
    {
        $sql = "SELECT concat(m.id,'-',ce.circuito_id,'-',ce.ejercicio_id) clave,
        ce.orden, eje.nombre, eje.video, eje.imagen, prg.series, prg.repeticiones,
        -- IF(rc.tipo='circuito',prg.descanso,0) descanso,
        rc.tipo,
        prg.descanso,
        IF(IFNULL(mec.completado,0)=0,false,true) completado
        FROM piso.menu m
        INNER JOIN piso.menu_actividad ma ON ma.menu_id = m.id AND m.fechaEliminacion IS NULL
        INNER JOIN piso.rutina_circuitos rc ON rc.id = circuito_id
        INNER JOIN piso.circuitos_has_ejercicios ce ON ce.circuito_id = ma.circuito_id
        INNER JOIN piso.cat_ejercicios eje ON eje.id = ce.ejercicio_id
        INNER JOIN piso.rutina_progresiones prg ON prg.rutina_id = m.idRutina
        LEFT JOIN piso.menu_ejercicio_completado mec ON mec.menu_id = ma.menu_id
            AND mec.circuito_id = ce.circuito_id
            AND mec.ejercicio_id = ce.ejercicio_id
            AND mec.fechaCompletado = ma.dia
        WHERE m.idPersona = {$idPersona}
        AND ma.dia = '{$dia}'
        AND m.id = {$menuId}
        AND prg.num_semana = {$numSemana}
        AND ma.circuito_id <> 0
        ORDER BY ce.orden ";
        $res = DB::connection('aws')->select($sql);
        // Se convierte resultado en arreglo
        if (count($res) > 0) {
            $res = array_map(function ($x) {
                //$video = explode("'", $x->video);
                return (array) [
                    'clave'        => $x->clave,
                    'orden'        => $x->orden,
                    'nombre'       => $x->nombre,
                    'video'        => $x->video,
                    'imagen'       => $x->imagen,
                    'series'       => $x->series,
                    'repeticiones' => $x->repeticiones,
                    'tipo'         => $x->tipo,
                    'descanso'     => $x->descanso,
                ];
            }, $res);
            return $res;
        } else {
            return [];
        }

    }
    public static function obtenRutinaDia($idPersona, $dia, $idRutina, $fechaInicio, $menuId)
    {
        // Tiene Cardio como Actividad ?
        $res = DB::connection('aws')->table('piso.menu_actividad')
            ->where('menu_id', '=', $menuId)
            ->where('dia', '=', $dia)
            ->first();
        if ($res == null) {
            return ['estatus' => false, 'mensaje' => "Acércate a un entrenador para que genere tu rutina"];

        }
        if ($res->cardio_id == 0 &&
            $res->optativa_id == 0 &&
            $res->clase_id == 0 &&
            $res->circuito_id == 0) {
            return ['estatus' => false, 'mensaje' => "Día de recuperación"];

        }

        $cardio    = [];
        $optativas = [];
        $clases    = [];
        $fuerza    = [];

        // Se calcula número de semana según fecha proporcionada
        $hoy       = Carbon::now();
        $diffdays  = $hoy->diffInDays($dia);
        $numSemana = intval($diffdays / 7) + 1;
        if ($res != null) {
            if ($res->cardio_id > 0) {
                $cardio = self::getCardioDia($idRutina, $idPersona, $dia, $menuId, $numSemana);
            } else {
                $cardio = [];
            }
            if ($res->optativa_id > 0) {
                $optativas = self::getOptativaDia($idRutina, $dia, $menuId);
            }
            if ($res->clase_id > 0) {
                $clases = self::getClaseDia($idRutina, $dia, $menuId);
            }
            if ($res->circuito_id > 0) {
                $fuerza = self::getEjerciciosFuerzaDia($idPersona, $dia, $menuId, $numSemana);
            }

            $salida_obj              = [];
            $salida_obj['Fuerza']    = $fuerza;
            $salida_obj['Cardio']    = $cardio;
            $salida_obj['Clases']    = $clases;
            $salida_obj['Optativas'] = $optativas;
            return ['estatus' => true, 'data' => $salida_obj];
        } else {

            return ['estatus' => false, 'mensaje' => "Día de recuperación"];
        }

    }
    public static function scopeReadMenu($query, $idPersona, $dia)
    {
        $sql = "SELECT crut.id, mnu.fecha_inicio, mnu.id menu_id
                FROM piso.menu mnu
                INNER JOIN piso.cat_rutinas crut ON crut.id = mnu.idRutina
                WHERE mnu.idPersona = {$idPersona}
                AND mnu.fechaEliminacion IS NULL
                AND mnu.fecha_inicio <= '{$dia}'
                AND mnu.fecha_fin >= '{$dia}'
                ORDER BY mnu.fechaRegistro desc
                LIMIT 1 ";

        $res = DB::connection('aws')->select($sql);

        if (count($res) > 0) {
            $rutina_id        = $res[0]->id;
            $fecha_inicio_sql = $res[0]->fecha_inicio;
            $menu_id          = $res[0]->menu_id;
        } else {
            return ['estatus' => false, 'mensaje' => "Día de recuperación"];
        }

        // Se calcula los días de antigüedad de la rutina
        $fecha_hoy    = new Carbon;
        $fecha_inicio = new Carbon;
        $fecha_inicio->timestamp(strtotime($fecha_inicio_sql . ' 00:00:00'));
        $diff = $fecha_hoy->diffinDays($fecha_inicio);

        // Si han pasado más de MAX_DIAS_ANTIGUEDAD_RUTINA entonces caduca la rutina de esa idPersona
        if ($diff > self::MAX_DIAS_ANTIGUEDAD_RUTINA) {
            return ['estatus' => false, 'mensaje' => "Última rutina ha caducado"];
        }

        $dia_act        = $dia;
        $dia            = new Carbon();
        $dia->timestamp = strtotime($dia_act);

        $idRutina = $res[0]->id;
        // $arr_cardio = CatRutinas::read_rutina_cardios($idRutina);

        // Obtención de la Frecuencia Cardiaca en Reposo
        $res = PersonaInbody::select('fcresp')
            ->where('idPersona', '=', $idPersona)
            ->orderBy('fechaRegistro', 'desc')
            ->get();
        if (count($res) > 0) {
            $fcresp = $res->first()->fcresp;
        } else {
            $fcresp = 0;
        }

        // Obtención de edad del socio
        $sql = "
                SELECT per.fechaNacimiento
                FROM persona per
                WHERE per.fechaNacimiento <> '0000-00-00'
                AND per.fechaEliminacion = '0000-00-00 00:00:00'
                AND per.idPersona = {$idPersona}
                LIMIT 1
            ";
        $res = DB::connection('crm')->select($sql);

        if (count($res) > 0) {
            $fechaNacimiento = $res[0]->fechaNacimiento;
        } else {
            $fechaNacimiento = null;
            throw new \Exception("No se pudo recuperar la edad del socio, rectifique sus datos en el club más cercano.");
        }
        $fechaNacimiento_tmp            = Carbon::now();
        $fechaNacimiento_tmp->timestamp = strtotime($fechaNacimiento);
        $edad                           = $fechaNacimiento_tmp->diffInYears(Carbon::now());

        // Se calcula número de semana según fecha proporcionada
        $diffdays   = $fecha_inicio->diffInDays($dia);
        $num_semana = intval($diffdays / 7) + 1;
        if ($num_semana > 4) {
            throw new \Exception("La fecha {$dia_act} está fuera de las cuatro semanas de la rutina", 400);
        }

        // Se obtiene menu_id por idPersona
        $res = self::select('id', 'porcentaje')
            ->where('idPersona', '=', $idPersona)
            ->where('fechaEliminacion', '=', '0000-00-00 00:00:00')
            ->orderBy('fechaRegistro', 'DESC')
            ->get();

        $menu_id = 0;
        if (count($res) == 0) {
            throw new \Exception("No existen rutinas definidas", 400);
        } else {
            $menu_id    = $res[0]->id;
            $porcentaje = $res[0]->porcentaje;
        }

        // Tiene Cardio como Actividad ?
        $res = DB::connection('aws')->table('piso.menu_actividad')
            ->where('menu_id', '=', $menu_id)
            ->where('dia', '=', $dia)
            ->select('cardio_id')
            ->get();

        $cardios = [];
        if (count($res) > 0 && $res[0]->cardio_id > 0) {
            // Se obtienen cardios por menu_id
            $res = DB::connection('aws')->table('piso.rutina_cardios as rc')
                ->select(DB::raw("CONCAT({$menu_id},'-',rc.id) as clave"),
                    'eq.nombre as equipo', 'eq.imagen', 'rc.tiempo', 'rc.intensidad',
                    DB::raw('IF(IFNULL(mcc.completado,0)=0,false,true) completado'))
                ->join('piso.cat_equipos as eq', 'rc.equipo_id', '=', 'eq.id')
                ->leftJoin('piso.menu_cardio_completado as mcc', function ($join) use ($menu_id, $dia) {
                    $join->on('rc.id', '=', 'mcc.cardio_id')
                        ->where('mcc.menu_id', '=', $menu_id)
                        ->where('mcc.fechaCompletado', '=', $dia);
                })
                ->where('rc.rutina_id', '=', $idRutina)
                ->where('rc.num_semana', '=', $num_semana)
                ->get();

            $res_arr = [];
            if (count($res) > 0) {
                $res_arr = $res->toArray();
                $res_arr = array_map(function ($x) {return (array) $x;}, $res_arr);

                $intensidad     = $res_arr[0]['intensidad'];
                $intensidad     = str_replace('%', '', $intensidad);
                $arr_intensidad = explode('-', $intensidad);

                // Obtención de la Recuencia Cardiaca Máxima
                // $fcmax = intval(220 – $edad); // Fórmula Clásica
                $fcmax = intval(208 - (0.7 * $edad)); // Fórmula Tanaka

                // Fórmula Karvonen para (Target Heart Rate THR)
                $fc_objetivo = intval((($fcmax - $fcresp) * $arr_intensidad[0] / 100) + $fcresp);

                $res_arr[0]['intensidad'] = $fc_objetivo;
            }

            // Se obtiene la bandera completado de cardio
            if (count($res) > 0) {
                $res_arr = $res->toArray();
                $res_arr = array_map(function ($x) {return (array) $x;}, $res_arr);
                $res_arr[0]['intensidad'] = $fc_objetivo;

                foreach ($res_arr as $arr1) {
                    $aa1 = [];
                    foreach ($arr1 as $k2 => $v2) {
                        if ($k2 == 'completado') {
                            if ($v2 == 0) {
                                $v2 = false;
                            } else {
                                $v2 = true;
                            }
                        }
                        $aa1[$k2] = $v2;
                    }
                    $cardios[] = $aa1;
                }
            }
        }

        // Tiene Clase como Actividad ?
        $res = DB::connection('aws')->table('piso.menu_actividad')
            ->where('menu_id', '=', $menu_id)
            ->where('dia', '=', $dia)
            ->select('clase_id')
            ->get();

        $clases = [];
        if (count($res) > 0 && $res[0]->clase_id > 0) {
            // Se obtienen clases por menu_id
            $res = DB::connection('aws')->table('piso.rutinas_has_clases as rhc')
                ->select(DB::raw("CONCAT({$menu_id},'-',cc.id) as clave"),
                    'cc.nombre', 'cc.imagen')
                ->join('piso.cat_clases as cc', 'rhc.clase_id', '=', 'cc.id')
                ->where('rhc.rutina_id', '=', $idRutina)
                ->get();

            // Se obtiene la bandera completado de clases
            if (count($res) > 0) {
                $res_arr = $res->toArray();
                $res_arr = array_map(function ($x) {return (array) $x;}, $res_arr);

                foreach ($res_arr as $arr1) {
                    $aa1 = [];
                    foreach ($arr1 as $k2 => $v2) {
                        if ($k2 == 'completado') {
                            if ($v2 == 0) {
                                $v2 = false;
                            } else {
                                $v2 = true;
                            }
                        }
                        $aa1[$k2] = $v2;
                    }
                    $clases[] = $aa1;
                }
            }

            // Se obtiene el id de la clase OTRO
            $res_clases = DB::connection('aws')->table('piso.cat_clases as cc')
                ->where('cc.nombre', '=', 'OTRO')
                ->get();
            $id_otro = $res_clases[0]->id;

            $res_otro = DB::connection('aws')->table('piso.cat_clases as cat')
                ->where('cat.nombre', '=', 'OTRO')
                ->select(DB::raw("IFNULL(cat.imagen,'') imagen"))
                ->get();
            $imagen_otro = $res_otro[0]->imagen;

            // Se obtienen clases por menu_id OTRO
            $res_otro = DB::connection('aws')->table('piso.menu_clase_completado as mcc')
                ->select(DB::raw("CONCAT({$menu_id},'-',mcc.clase_id) as clave"),
                    DB::raw('IF(IFNULL(mcc.completado,0)=0,false,true) completado'))
                ->leftJoin('piso.cat_clases as cc', function ($join) use ($id_otro) {
                    $join->on('mcc.clase_id', '=', 'cc.id')
                        ->where('cc.id', '=', $id_otro);
                })
                ->where('mcc.fechaCompletado', '=', $dia)
                ->where('mcc.menu_id', '=', $menu_id)
                ->get();

            // Se obtiene la bandera completado de clases OTRO
            if (count($res_otro) > 0) {
                $res_arr = $res_otro->toArray();
                $res_arr = array_map(function ($x) {return (array) $x;}, $res_arr);

                $completado = true;
            } else {
                $completado = false;
            }
            $clases[] = ["clave" => $menu_id . "-" . $id_otro, "nombre" => "OTRO", "imagen" => $imagen_otro, "completado" => $completado];
        }

        // Tiene Optativa como Actividad ?
        $res = DB::connection('aws')->table('piso.menu_actividad')
            ->where('menu_id', '=', $menu_id)
            ->where('dia', '=', $dia)
            ->select('optativa_id')
            ->get();

        $optativas = [];
        if (count($res) > 0 && $res[0]->optativa_id > 0) {
            // Se obtienen optativa por menu_id
            $res = DB::connection('aws')->table('piso.rutinas_has_optativas as rho')
                ->select(DB::raw("CONCAT({$menu_id},'-',co.id) as clave"),
                    'co.nombre', 'co.imagen',
                    DB::raw('IF(IFNULL(moc.completado,0)=0,false,true) completado'))
                ->join('piso.cat_optativas as co', 'rho.optativa_id', '=', 'co.id')
                ->leftJoin('piso.menu_optativa_completado as moc', function ($join) use ($menu_id, $dia) {
                    $join->on('co.id', '=', 'moc.optativa_id')
                        ->where('moc.menu_id', '=', $menu_id)
                        ->where('moc.fechaCompletado', '=', $dia);
                })
                ->where('rho.rutina_id', '=', $idRutina)
                ->get();

            // Se obtiene la bandera completado de optativas
            if (count($res) > 0) {
                $res_arr = $res->toArray();
                $res_arr = array_map(function ($x) {return (array) $x;}, $res_arr);

                foreach ($res_arr as $arr1) {
                    $aa1 = [];
                    foreach ($arr1 as $k2 => $v2) {
                        if ($k2 == 'completado') {
                            if ($v2 == 0) {
                                $v2 = false;
                            } else {
                                $v2 = true;
                            }
                        }
                        $aa1[$k2] = $v2;
                    }
                    $optativas[] = $aa1;
                }
            }

            // Se obtiene el id de la optativa OTRO
            $res_optativa = DB::connection('aws')->table('piso.cat_optativas as co')
                ->where('co.nombre', '=', 'OTRO')
                ->get();
            $id_otro = $res_optativa[0]->id;

            $res_otro = DB::connection('aws')->table('piso.cat_optativas as cat')
                ->where('cat.nombre', '=', 'OTRO')
                ->select(DB::raw("IFNULL(cat.imagen,'') imagen"))
                ->get();
            $imagen_otro = $res_otro[0]->imagen;

            // Se obtienen optativas por menu_id OTRO
            $res_otro = DB::connection('aws')->table('piso.menu_optativa_completado as moc')
                ->select(DB::raw("CONCAT({$menu_id},'-',moc.optativa_id) as clave"),
                    DB::raw('IF(IFNULL(moc.completado,0)=0,false,true) completado'))
                ->leftJoin('piso.cat_optativas as co', function ($join) use ($id_otro) {
                    $join->on('moc.optativa_id', '=', 'co.id')
                        ->where('co.id', '=', $id_otro);
                })
                ->where('moc.fechaCompletado', '=', $dia)
                ->where('moc.menu_id', '=', $menu_id)
                ->get();

            // Se obtiene la bandera completado de optativas OTRO
            if (count($res_otro) > 0) {
                $res_arr = $res_otro->toArray();
                $res_arr = array_map(function ($x) {return (array) $x;}, $res_arr);

                $completado = true;
            } else {
                $completado = false;
            }
            $optativas[] = ["clave" => $menu_id . "-" . $id_otro, "nombre" => "OTRO", "imagen" => $imagen_otro, "completado" => $completado];
        }

        $sql = "SELECT concat(m.id,'-',ce.circuito_id,'-',ce.ejercicio_id) clave,
                ce.orden, eje.nombre, eje.video, eje.imagen, prg.series, prg.repeticiones,
                -- IF(rc.tipo='circuito',prg.descanso,0) descanso,
                rc.tipo,
                prg.descanso,
                IF(IFNULL(mec.completado,0)=0,false,true) completado
                FROM piso.menu m
                INNER JOIN piso.menu_actividad ma ON ma.menu_id = m.id AND m.fechaEliminacion IS NULL
                INNER JOIN piso.rutina_circuitos rc ON rc.id = circuito_id
                INNER JOIN piso.circuitos_has_ejercicios ce ON ce.circuito_id = ma.circuito_id
                INNER JOIN piso.cat_ejercicios eje ON eje.id = ce.ejercicio_id
                INNER JOIN piso.rutina_progresiones prg ON prg.rutina_id = m.idRutina
                LEFT JOIN piso.menu_ejercicio_completado mec ON mec.menu_id = ma.menu_id
                    AND mec.circuito_id = ce.circuito_id
                    AND mec.ejercicio_id = ce.ejercicio_id
                    AND mec.fechaCompletado = ma.dia
                WHERE m.idPersona = {$idPersona}
                AND ma.dia = '{$dia}'
                AND m.id = {$menu_id}
                AND prg.num_semana = {$num_semana}
                AND ma.circuito_id <> 0
                ORDER BY ce.orden ";
        $res = DB::connection('aws')->select($sql);

        // Se convierte resultado en arreglo
        if (count($res) > 0) {
            $res = array_map(function ($x) {

                $video = explode("'", $x->video);

                return (array) [
                    'clave'        => $x->clave,
                    'orden'        => $x->orden,
                    'nombre'       => $x->nombre,
                    'video'        => $video[1],
                    'imagen'       => $x->imagen,
                    'series'       => $x->series,
                    'repeticiones' => $x->repeticiones,
                    'tipo'         => $x->tipo,
                    'descanso'     => $x->descanso,
                ];
            }, $res);
        }

        $fuerzas = [];
        foreach ($res as $arr1) {
            $aa1 = [];
            foreach ($arr1 as $k2 => $v2) {
                if ($k2 == 'completado') {
                    if ($v2 == 0) {
                        $v2 = false;
                    } else {
                        $v2 = true;
                    }
                }
                $aa1[$k2] = $v2;
            }
            $fuerzas[] = $aa1;
        }

        // Corrección de descansos
        $switch_descanso = true;
        foreach ($fuerzas as &$fuerza) {
            switch ($fuerza['tipo']) {
                case 'circuito':$fuerza['descanso'] = 0;
                    break;
                case 'biserie':
                    if ($switch_descanso) {
                        $fuerza['descanso'] = 0;
                        $switch_descanso    = false;
                    } else {
                        $switch_descanso = true;
                    }
                    break;
                case 'estacion':break; // Se conservan los descansos
            }
        }

        $salida_obj              = [];
        $salida_obj['Fuerza']    = $fuerzas;
        $salida_obj['Cardio']    = $cardios;
        $salida_obj['Clases']    = $clases;
        $salida_obj['Optativas'] = $optativas;
        if (count($fuerzas) == 0
            && count($cardios) == 0
            && count($clases) == 0
            && count($optativas) == 0
        ) {
            $descanso_completado = false;

            $sql = "SELECT COUNT(*) conteo
                    FROM piso.menu_descanso_completado
                    WHERE 1 = 1
                    AND menu_id = {$menu_id}
                    AND completado = 1
                    AND fechaCompletado = '{$dia}' ";
            $res                 = DB::connection('aws')->select($sql);
            $descanso_completado = $res[0]->conteo > 0 ? true : false;

            $salida_obj['Descanso'][] = [
                "clave"      => "{$menu_id}",
                "nombre"     => "DIA DE DESCANSO",
                "completado" => $descanso_completado,
                "imagen"     => self::IMAGEN_ACTIVIDAD_POR_DEFECTO,
            ];
        } else {
            $salida_obj['Descanso'] = [];
        }

        return ['estatus' => true, 'data' => $salida_obj];

    }

}
