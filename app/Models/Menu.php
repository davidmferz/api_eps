<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

        return $query->selectRaw("CONCAT(p.nombre,' ',p.paterno,' ',p.materno) nombre_socio, cr.rutina, cr.nivel,menu.observaciones, menu.idEmpleado")
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

}
