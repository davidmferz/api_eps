<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Menu extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'piso.menu';
    protected $primaryKey = 'id';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

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

        return $query->selectRaw("CONCAT(p.nombre,' ',p.paterno,' ',p.materno) nombre_socio, cr.rutina, cr.nivel,menu.observaciones, menu.idEmpleado, date(menu.fechaRegistro) as fechaRegistro ")
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

}
