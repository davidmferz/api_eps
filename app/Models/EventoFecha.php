<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoFecha extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.eventoFecha';
    protected $primaryKey = 'idEventoFecha';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';
    //

    public function scopeValidaHorario($query, $idEmpleado, $fecha, $hora)
    {
        return $query
            ->where('eliminado', 0)
            ->where('fechaEvento', $fecha)
            ->where('horaEvento', $hora)
            ->where('idEmpleado', $idEmpleado)
            ->count();
    }
}
