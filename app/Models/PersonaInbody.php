<?php
namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;

class PersonaInbody extends Model
{
    protected $connection = 'aws';
    protected $table      = 'piso.personainbody';
    protected $primaryKey = 'idPersonaInBody';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    protected $fillable = [
        'RCC',
        'PGC',
        'IMC',
        'MME',
        'MCG',
        'ACT',
        'minerales',
        'proteina',
        'peso',
        'estatura',
        'fcresp',
    ];
    protected $hidden = ['idPersonaEmpleado', 'fechaActualizacion', 'fechaEliminacion'];
}
