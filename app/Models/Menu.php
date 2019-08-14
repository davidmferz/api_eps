<?php

namespace API_EPS\Models;

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

    /**
     * Regresa un array con los tipo de cliente
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function scopeRutinasEntrenadores($query, $ids)
    {
        return $query->selectRaw("CONCAT(p.nombre,' ',p.paterno,' ',p.materno) nombre_socio, cr.rutina, cr.nivel,menu.observaciones, menu.idEmpleado")
            ->join('piso.cat_rutinas as cr', 'cr.id', '=', 'menu.idRutina')
            ->Join('deportiva.persona as p', 'p.idPersona', '=', 'menu.idPersona')
            ->join('deportiva.empleado as e', 'e.idPersona', '=', 'menu.idEmpleado')
            ->whereIn('menu.idEmpleado', $ids)
            ->whereRaw('NOW()  between menu.fecha_inicio AND  menu.fecha_fin')
            ->where('menu.fechaEliminacion', '0000-00-00 00:00:00')
            ->get();

    }

}
