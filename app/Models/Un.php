<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Un extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.Un';
    protected $primaryKey = 'idUn';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    public function scopeGetClubsRegiones($query)
    {
        $where = [
            ['r.activo', '=', 1],
            ['un.activo', '=', 1],
            ['eliminado', '=', 0],
            ['un.idtipoUn', '=', 2],
            ['r.idRegion', '<>', 0],
        ];

        $datos = $query->select('r.idRegion', 'r.descripcion', 'idUn', 'nombre')
            ->join('crm.region as r', 'un.idRegion', '=', 'r.idRegion')
            ->where($where)
            ->orderBy('nombre', 'ASC')
            ->get()
            ->toArray();
        $regiones[]  = ['value' => 0, 'label' => 'Todos'];
        $regionesAux = [];
        foreach ($datos as $key => $value) {
            $clubs[$value['idRegion']][] = ['value' => $value['idUn'], 'label' => $value['nombre']];

            $regionesAux[$value['idRegion']] = ['idRegion' => $value['idRegion'], 'descripcion' => $value['descripcion']];
        }
        foreach ($regionesAux as $key => $value) {
            $regiones[] = ['value' => $value['idRegion'], 'label' => $value['descripcion']];
        }
        usort($regiones, function ($a, $b) {
            return $a['value'] <=> $b['value'];
        });
        return ['regiones' => $regiones, 'clubs' => $clubs];
    }

}
