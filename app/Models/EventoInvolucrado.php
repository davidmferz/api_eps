<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;

class EventoInvolucrado extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.eventoinvolucrado';
    protected $primaryKey = 'idEventoInvolucrado';

    const CREATED_AT    = 'fechaRegistro';
    const UPDATED_AT    = 'fechaActualizacion';
    const DELETED_AT    = 'fechaEliminacion';
    protected $fillable = [
        'idEventoInscripcion',
        'idPersona',
        'tipo',
    ];

}
