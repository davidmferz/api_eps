<?php

namespace App\Models\BD_App\CLASES;

use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    protected $connection = 'app';
    protected $table      = 'CLASES.club';
    protected $primaryKey = 'club_id';
    public $timestamps    = false;

    public static function updateClub($values)
    {
        $club = Club::find($values->club_id);
        if ($club == null) {
            $club          = new Club();
            $club->club_id = $values->club_id;
        }
        $club->clave_corta          = $values->clave_corta;
        $club->nombre               = $values->nombre;
        $club->tipo                 = $values->tipo;
        $club->responsable_apertura = $values->responsable_apertura;
        $club->fecha_apertura       = $values->fecha_apertura;
        $club->fecha_preventa       = $values->fecha_preventa;
        $club->activo               = $values->activo;
        $club->created_by           = $values->created_by;
        $club->created_date         = $values->created_date;
        $club->updated_by           = $values->updated_by;
        $club->updated_date         = $values->updated_date;
        $club->empresa              = $values->empresa;
        $club->id_cp_sucursal       = $values->id_cp_sucursal;
        $club->id_cp_buzon          = $values->id_cp_buzon;
        $club->id_cp_sucursal_alt   = $values->id_cp_sucursal_alt;
        $club->id_cp_buzon_alt      = $values->id_cp_buzon_alt;
        $club->region_operacion     = $values->region_operacion;
        $club->region_venta         = $values->region_venta;
        $club->programa_referidos   = $values->programa_referidos;
        $club->timezone_id          = $values->timezone_id;
        $club->migrado_crm2         = $values->migrado_crm2;
        $club->activo_proceso_cat   = $values->activo_proceso_cat;
        $club->save();
    }
}
