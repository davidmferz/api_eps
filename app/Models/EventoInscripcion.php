<?php

namespace App\Models;

use App\Models\Permiso;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EventoInscripcion extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.eventoinscripcion';
    protected $primaryKey = 'idEventoInscripcion';

    const CREATED_AT    = 'fechaRegistro';
    const UPDATED_AT    = 'fechaActualizacion';
    const DELETED_AT    = 'fechaEliminacion';
    protected $fillable = [
        'idEventoUn',
        'idPersona',
        'idUn',
        'idEmpleado',
        'idTipoEstatusInscripcion',
        'monto',
        'pagado',
        'cantidad',
        'totalSesiones',
        'idTipoCliente',
        'descQuincenas',
        'informativo',
        'participantes',
        'visa',
    ];

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
        return $query->select('ef.idEventoInscripcion')
            ->distinct()
            ->join('crm.eventofecha as ef', 'ef.idEventoInscripcion', '=', 'eventoinscripcion.idEventoInscripcion')
            ->leftJoin('crm.eventocalificacion as ec', 'ec.idEventoInscripcion', '=', 'ef.idEventoInscripcion')
            ->whereRaw('eventoinscripcion.eliminado= 0')
            ->whereRaw('eventoinscripcion.totalSesiones = eventoinscripcion.totalSeguimiento')
            ->where('ef.fechaEvento', $fechaIni->format('Y-m-d'))
            ->where('ef.horaEvento', '>=', $fechaIni->format('H:i:s'))
            ->where('ef.horaEvento', '<', $fechaFin->format('H:i:s'))
            ->whereNull('ec.idEventoInscripcion')
            ->get()
            ->toArray();
        /*$addSlashes = str_replace('?', "'?'", $query->toSql());
    $sq         = vsprintf(str_replace('?', '%s', $addSlashes), $query->getBindings());
    dd($sq);*/
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

    public static function insertaProgramaDeportivo($idUn, $idProducto, $idPersona, $idPersonaEntrenador, $idPersonaRespVta, $importe, $cantidad, $idTipoCliente)
    {
        $empleado = Empleado::where('idPersona', $idPersonaEntrenador)->where('idTipoEstatusEmpleado', 196)->first();
        $sql      = "SELECT eu.idEventoUn, p.nombre as productoNombre
                    FROM producto AS p
                    JOIN evento AS e ON e.idProducto=p.idProducto
                    JOIN eventoun AS eu ON eu.idEvento=e.idEvento
                    WHERE p.idProducto={$idProducto} AND eu.idUn={$idUn}";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $idEventoUn                                  = $query[0]->idEnventoUn;
            $eventoInscripcion                           = new self();
            $eventoInscripcion->idEventoun               = $idEventoUn;
            $eventoInscripcion->idPersona                = $idPersona;
            $eventoInscripcion->idUn                     = $idUn;
            $eventoInscripcion->idEmpleado               = $idEmpleado;
            $eventoInscripcion->idTipoEstatusInscripcion = 1;
            $eventoInscripcion->monto                    = $importe;
            $eventoInscripcion->pagado                   = 0.00;
            $eventoInscripcion->cantidad                 = $cantidad;
            $eventoInscripcion->totalSesiones            = 1;
            $eventoInscripcion->idTipoCliente            = $idTipoCliente;
            $eventoInscripcion->descQuincenas            = 1;
            $eventoInscripcion->informativo              = 0;
            $eventoInscripcion->participantes            = 1;
            $eventoInscripcion->visa                     = 0;
            $eventoInscripcion->save();

            $permiso = new Permiso;
            $permiso->log(
                'Se realiza incripcion al evento ' . $query[0]->productoNombre . ' (Num. Inscripcion ' . $eventoInscripcion->idEventoInscripcion . ')',
                LOG_EVENTO,
                0,
                $idPersona
            );

            $datos = [
                'idEventoInscripcion' => $eventoInscripcion->idEventoInscripcion,
                'idPersona'           => $idPersona,
                'tipo'                => 1,
            ];
            $eventoInscripcion = EventoInvolucrado::create($datos);

            $datos = [
                'idEventoInscripcion' => $eventoInscripcion->idEventoInscripcion,
                'idPersona'           => $idPersonaRespVta,
                'tipo'                => 2,
            ];
            $eventoInscripcion = EventoInvolucrado::create($datos);

            $datos = [
                'idEventoInscripcion' => $eventoInscripcion->idEventoInscripcion,
                'idPersona'           => $idPersonaEntrenador,
                'tipo'                => 3,
            ];
            $eventoInscripcion = EventoInvolucrado::create($datos);

            return [
                'estatus'             => true,
                'idEventoInscripcion' => $eventoInscripcion->idEventoInscripcion,
                'cuentaProducto'      => $query[0]->cuentaProducto,
                'numCuenta'           => $query[0]->numCuenta,
                'idTipoEvento'        => $query[0]->idTipoEvento,
                'idEvento'            => $query[0]->idEvento,
            ];

        } else {
            return [
                'estatus' => false,
                'mensaje' => 'Evento no encontrado',

            ];

        }
    }

}
