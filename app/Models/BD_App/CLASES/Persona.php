<?php

namespace App\Models\BD_App\CLASES;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $connection = 'app';
    protected $table      = 'CLASES.persona';
    protected $primaryKey = 'persona_id';
    public $timestamps    = false;

    public static function updatePersona($value)
    {
        $persona = Persona::find($value->persona_id);
        if ($persona == null) {
            $persona             = new Persona();
            $persona->persona_id = $value->persona_id;
        }
        $persona->nombre                     = $value->nombre;
        $persona->primer_apellido            = $value->primer_apellido;
        $persona->segundo_apellido           = $value->segundo_apellido;
        $persona->sexo                       = $value->sexo;
        $persona->eliminado                  = $value->eliminado;
        $persona->tipo                       = $value->tipo;
        $persona->fecha_nacimiento           = $value->fecha_nacimiento;
        $persona->fecha_eliminacion          = $value->fecha_eliminacion;
        $persona->estado_civil               = $value->estado_civil;
        $persona->convenio                   = $value->convenio;
        $persona->empresa                    = $value->empresa;
        $persona->created_by                 = $value->created_by;
        $persona->created_date               = $value->created_date;
        $persona->updated_by                 = $value->updated_by;
        $persona->updated_date               = $value->updated_date;
        $persona->club                       = $value->club;
        $persona->es_extranjero              = $value->es_extranjero;
        $persona->huella                     = $value->huella;
        $persona->huella_status              = $value->huella_status;
        $persona->foto_storage_id            = $value->foto_storage_id;
        $persona->foto_status                = $value->foto_status;
        $persona->foto_content_type          = $value->foto_content_type;
        $persona->registro_facial_status     = $value->registro_facial_status;
        $persona->registro_torniquete_status = $value->registro_torniquete_status;
        $persona->numero_empleado            = $value->numero_empleado;
        $persona->vetado                     = $value->vetado;
        $persona->es_invitado_gratis         = $value->es_invitado_gratis;
        $persona->id_auth_user               = $value->id_auth_user;
        $persona->persona_crm1_id            = $value->persona_crm1_id;
        $persona->nombre_completo            = $value->nombre_completo;
        $persona->save();
    }

}
