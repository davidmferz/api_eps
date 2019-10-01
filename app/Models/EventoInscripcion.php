<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EventoInscripcion extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.eventoinscripcion';
    protected $primaryKey = 'idEventoInscripcion';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    public function scopeinfoEvento($query, $idEventoInscripciones)
    {
        return $query->selectRaw("CONCAT(pe.nombre,' ',pe.paterno,' ',pe.materno) nombre_entrenador, pro.nombre,e.idEmpleado")
            ->join('eventoinvolucrado as ei', 'ei.idEventoInscripcion', '=', 'eventoinscripcion.idEventoInscripcion')
            ->join('empleado as e', 'e.idPersona', '=', 'ei.idPersona')
            ->join('persona as pe', 'pe.idPersona', '=', 'e.idPersona')
            ->join('eventoun as eu', 'eu.idEventoUn', '=', 'eventoinscripcion.idEventoUn')
            ->join('evento as ev', 'ev.idEvento', '=', 'eu.idEvento')
            ->join('producto as pro', 'pro.idProducto', '=', 'ev.idProducto')
            ->where('eventoinscripcion.idEventoInscripcion', $idEventoInscripciones)
            ->where('ei.tipo', 'Entrenador')
            ->get()
            ->toArray();
    }

    public function scopeFindClasesTerminadas($query)
    {

        $fechaIni = Carbon::now()->minute(0)->second(0);

        $fechaFin = Carbon::now()->addHour()->minute(0)->second(0);
        $query->select('ef.idEventoInscripcion')
            ->join('crm.eventofecha as ef', 'ef.idEventoInscripcion', '=', 'eventoinscripcion.idEventoInscripcion')
            ->whereRaw('eventoinscripcion.eliminado= 0')
            ->whereRaw('eventoinscripcion.totalSesiones = eventoinscripcion.totalSeguimiento')
            ->where('ef.fechaEvento', $fechaIni->format('Y-m-d'))
            ->where('ef.horaEvento', '>=', $fechaIni->format('H:i:s'))
            ->where('ef.horaEvento', '<', $fechaFin->format('H:i:s'))
            ->get()
            ->toArray();
        $addSlashes = str_replace('?', "'?'", $query->toSql());
        $sq         = vsprintf(str_replace('?', '%s', $addSlashes), $query->getBindings());
        dd($sq);
    }

    public static function getNombresEmail($idEventoInscripciones)
    {

        $strIds = '';
        foreach ($idEventoInscripciones as $key => $value) {
            if ($key == 0) {
                $strIds .= $value['idEventoInscripcion'];
            } else {
                $strIds .= ',' . $value['idEventoInscripcion'];

            }
        }

        $sql = "SELECT
        ei.idEventoInscripcion ,
        CONCAT(p.nombre,' ',p.paterno,' ',p.materno) nombre_socio,
         CONCAT(pe.nombre,' ',pe.paterno,' ',pe.materno) nombre_entrenador,
         (
         select m2.mail
       from crm.mail as m2
       where  m2.idPersona=ei.idPersona
       and m2.idTipoMail IN (34,35,37)
       order by m2.idTipoMail
       limit 1
       ) as mail
       FROM crm.eventoinscripcion as ei
       JOIN crm.persona as p ON p.idPersona=ei.idPersona
       JOIN crm.eventoinvolucrado eie ON ei.idEventoInscripcion=eie.idEventoInscripcion
       JOIN crm.persona as pe ON pe.idPersona=eie.idPersona

        where ei.idEventoInscripcion IN ({$strIds})
        AND eie.tipo='Entrenador'
       ;";
        $query = DB::connection('crm')->select($sql);
        return $query;
    }

}
