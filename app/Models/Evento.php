<?php

namespace App\Models;

use App\Models\Empleado;
use App\Models\EventoInscripcion;
use App\Models\Permiso;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Evento extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.evento';
    protected $primaryKey = 'idEvento';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    public static function inscripcionV2($idUn, $idCategoria, $idPersona, $idPersonaRespVta, $idPersonaEntrenador, $idTipoCliente, $demo = 0, $idProducto = 0, $cantidad = 1,
        $importe = 0, $idEsquemaPago = 1) {

        $sql = "SELECT
                p.nombre AS productoNombre,
                e.idEvento,
                e.idTipoEvento,
                eu.idEventoUn,
                numClases.capacidad AS clases,
                participantes.capacidad AS numParticipantes,
                pp.importe,
                tc.descripcion as tipoCliente,
                ep.descripcion  as esquemaPago,
                cp.cuentaProducto,
                cc.numCuenta
            FROM producto AS p
            JOIN evento AS e ON p.idProducto=e.idProducto AND e.eliminado=0
            JOIN eventoUn AS eu ON eu.idEvento=e.idEvento

            INNER JOIN productoun pu ON pu.idProducto=p.idProducto
                    AND pu.activo=1
                    AND pu.eliminado=0
                    AND pu.idUn=eu.idUn
            INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
                AND pp.activo=1
                AND pp.fechaEliminacion=0
                -- AND pp.eliminado=0
                AND pp.idEsquemaPago NOT IN (7, 11)
                AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
            INNER JOIN tipocliente tc ON tc.idTipoCliente=pp.idTipoCliente
            INNER JOIN esquemapago ep ON ep.idEsquemaPago=pp.idEsquemaPago

            INNER JOIN cuentacontable cc ON cc.idCuentaContable=pp.idCuentaContable
            INNER JOIN cuentaproducto cp ON cp.idCuentaProducto=pp.idCuentaProducto

            INNER JOIN eventouncapacidad numClases ON numClases.idEventoUn=eu.idEventoUn
            AND numClases.idTipoEventoCapacidad=6
            AND numClases.activo=1
            AND numClases.eliminado=0
            AND numClases.autorizado=1
            AND numClases.capacidad>0

            INNER JOIN eventouncapacidad participantes ON participantes.idEventoUn=eu.idEventoUn
            AND participantes.idTipoEventoCapacidad=7
            AND participantes.activo=1
            AND participantes.eliminado=0
            AND participantes.autorizado=1
            AND participantes.capacidad>0

            WHERE idCategoria={$idCategoria}
            AND now() BETWEEN p.inicioVigencia AND p.finVigencia
            AND p.activo=1
            AND p.eliminado=0
            AND eu.idUn={$idUn}
            AND tc.idTipoCliente={$idTipoCliente}
            AND p.idProducto={$idProducto}
            AND ep.idEsquemaPago = '{$idEsquemaPago}'

            order by numClases.capacidad desc, pp.idProductoPrecio desc
            limit 1
            ";

        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            if ($demo) {
                $monto         = 0;
                $pagado        = 0;
                $cantidad      = 1;
                $totalSesion   = 1;
                $participantes = 1;
            } else {
                $monto = $query[0]->importe * $cantidad;
                if ($monto != $importe) {
                    return [
                        'estatus' => false,
                        'mensaje' => 'el precio no coincide',

                    ];
                }

                $totalSesion   = $query[0]->clases * $cantidad;
                $participantes = $query[0]->numParticipantes;
            }
            $idEmpleado = Empleado::obtenIdEmpleado($idPersonaRespVta, 1);

            $reg = [
                'idEventoUn'               => $query[0]->idEventoUn,
                'idPersona'                => $idPersona,
                'idUn'                     => $idUn,
                'idEmpleado'               => $idEmpleado,
                'idTipoEstatusInscripcion' => 1,
                'monto'                    => $monto,
                'pagado'                   => 0,
                'cantidad'                 => $cantidad,
                'totalSesiones'            => $totalSesion,
                'idTipoCliente'            => $idTipoCliente,
                'descQuincenas'            => 1,
                'informativo'              => 0,
                'participantes'            => $participantes,
                'visa'                     => 0,
            ];
            $eventoInscripcion = EventoInscripcion::create($reg);

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
                'productoNombre'      => $query[0]->productoNombre,
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

    public function arrayClases($idPersona, $dias)
    {
        settype($idPersona, 'integer');
        $res = array();

        $fecha = '';

        foreach ($dias as $key => $value) {
            if ($fecha == '') {
                $fecha = "'" . $value . "'";
            } else {
                $fecha .= ",'" . $value . "'";
            }
        }

        $sql = "SELECT
                ef.idEventoFecha,
                UPPER(pr.nombre) AS evento,
                CONCAT(ef.fechaEvento,' ',ef.horaEvento) AS fechaInicio,
                DATE_ADD(CONCAT(ef.fechaEvento,' ',ef.horaEvento), INTERVAL 50 MINUTE) AS fechaFin,
                ef.idTipoEstatusEventoFecha,
                teef.descripcion AS estatusEvento,
                UPPER(GROUP_CONCAT(CONCAT_WS(' ', p.nombre, p.paterno, p.materno))) AS participantes,
                UPPER(GROUP_CONCAT(CONCAT_WS(' ', p2.nombre, p2.paterno, p2.materno))) AS entrenador
            FROM eventofecha ef
            INNER JOIN tipoestatuseventofecha teef ON teef.idTipoEstatusEventoFecha=ef.idTipoEstatusEventoFecha
            INNER JOIN eventoinscripcion ei ON ei.idEventoInscripcion=ef.idEventoInscripcion
            INNER JOIN eventoun eu ON eu.idEventoUn=ei.idEventoUn
            INNER JOIN evento e ON e.idEvento=eu.idEvento
                AND e.idTipoEvento=3
            INNER JOIN producto pr ON pr.idProducto=e.idProducto
            INNER JOIN eventoparticipante ep ON ep.idEventoInscripcion=ef.idEventoInscripcion
                AND ep.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN persona p ON p.idPersona=ep.idPersona
            INNER JOIN persona p2 ON p2.idPersona=ef.idPersona
            WHERE ef.idPersona=$idPersona
                AND ef.fechaEvento IN ($fecha)
                AND ef.fechaEliminacion='0000-00-00 00:00:00'
            GROUP BY ef.idEventoFecha
            ORDER BY ef.fechaEvento, ef.horaEvento";
        $query = DB::connection('crm')->query($sql);
        if ($query->num_rows) {
            $res = $query->result_array();
        }
        return $res;
    }

    /**
     * Regresa la capacidad disponible de lugares del evento en el club solicitidado
     *
     * @param integer $idEvento Identificador del evento
     * @param integer $idUn     Identificador del club
     */
    public function capacidadEvento($idEvento, $idUn, $tipo)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        if ($idEvento == 0 || $idUn == 0) {
            return -1;
        }

        $query = DB::connection('crm')->table(TBL_EVENTOUNCAPACIDAD . ' ec')
            ->select('ec.capacidad')
            ->join(TBL_EVENTOUN . ' eu', 'eu.idEventoUn=ec.idEventoUn AND eu.fechaEliminacion=\'0000-00-00 00:00:00\'', 'INNER')
            ->where('eu.idEvento', $idEvento)
            ->where('eu.idUn', $idUn)
            ->where('ec.idTipoEventoCapacidad', $tipo)
            ->where('eu.activo', 1)
            ->where('ec.autorizado', 1)
            ->where('ec.activo', 1)
            ->where('ec.fechaEliminacion', '0000-00-00 00:00:00')
            ->orderBy('ec.idEventoUnCapacidad', 'desc');

        if ($query->count() > 0) {
            $query = array_map(function ($x) {return (array) $x;}, $query);
            $fila      = $query[0];
            $capacidad = $fila['capacidad'];
            return $capacidad;
        } else {
            return false;
        }
    }

    /**
     * [ctaContable description]
     *
     * @param  [type] $idEvento [description]
     * @param  [type] $idun     [description]
     *
     * @return [type]           [description]
     */
    public static function ctaContable($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $ctaContable = '';

        $sql = "
SELECT cc.numCuenta
FROM evento e
INNER JOIN producto p ON p.idProducto=e.idProducto
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
    and pu.idUn={$idUn} and pu.activo=1
    and pu.fechaEliminacion='0000-00-00 00:00:00'
INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
    AND pp.activo=1 AND pp.fechaEliminacion='0000-00-00 00:00:00'
    AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
INNER JOIN cuentacontable cc ON cc.idCuentaContable=pp.idCuentaContable
WHERE e.idEvento={$idEvento}
ORDER BY pp.idProductoPrecio DESC
LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $query = array_map(function ($x) {return (array) $x;}, $query);
            $fila        = $query[0];
            $ctaContable = $fila['numCuenta'];
        }
        return $ctaContable;
    }

    /**
     * [ctaContable description]
     *
     * @param  [type] $idEvento [description]
     * @param  [type] $idun     [description]
     *
     * @return [type]           [description]
     */
    public static function ctaProducto($idEvento, $idun)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $ctaProducto = '';

        $sql = "
SELECT cc.cuentaProducto
FROM evento e
INNER JOIN producto p ON p.idProducto=e.idProducto
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
    and pu.idUn={$idUn} and pu.activo=1
    and pu.fechaEliminacion='0000-00-00 00:00:00'
INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
    AND pp.activo=1 AND pp.fechaEliminacion='0000-00-00 00:00:00'
    AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
INNER JOIN cuentaproducto cc ON cc.idCuentaProducto=pp.idCuentaProducto
WHERE e.idEvento={$idEvento}
ORDER BY pp.idProductoPrecio DESC
LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $query = array_map(function ($x) {return (array) $x;}, $query);
            $fila        = $query[0];
            $ctaProducto = $fila['cuentaProducto'];
        }
        return $ctaProducto;
    }

    /**
     * Obtiene los generales del evento solicitado dentro del club indicado
     *
     * @param integer $idEvento Identificador del evento
     * @param integer $idUn     Identificador del club
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public static function datosGenerales($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        $datos = array();

        if ($idEvento == 0 || $idUn == 0) {
            return $datos;
        }

        /*$sql = "SELECT c.idCategoria
        FROM crm.evento e
        JOIN crm.producto p ON p.idProducto = e.idProducto
        AND p.eliminado = '0000-00-00 00:00:00'
        JOIN crm.categoria c ON c.idCategoria = p.idCategoria
        WHERE e.idEvento = ".$idEvento;
        $categoria = DB::connection('crm')->query($sql)->row_array();

        $cat = (intval($categoria["idCategoria"]) != 109) ? " and p.nombre LIKE '%2018%'" : "";
         */

        $cat = '';

        $query = DB::connection('crm')->table(TBL_EVENTO . ' AS e')
            ->select('p.idProducto', 'p.nombre', 'eu.idEventoUn', 'e.idTipoEvento', 'eu.inicioRegistro', 'eu.finRegistro', 'eu.inicioEvento', 'eu.finEvento', 'eu.reservarInstalacion', 'eu.anticipo', 'eu.edadMinima', 'eu.edadMaxima')
            ->join(TBL_PRODUCTO . ' AS p', function ($join) {
                $join->on('p.idProducto', '=', 'e.idProducto')
                    ->where('p.eliminado', '=', 0);
                // ->where('p.fechaEliminacion', '=', '0000-00-00 00:00:00');
            })
            ->join(TBL_TIPOEVENTO . ' AS te', 'te.idTipoEvento', '=', 'e.idTipoEvento')
            ->join(TBL_EVENTOUN . ' AS eu', function ($join) {
                $join->on('eu.idEvento', '=', 'e.idEvento')
                    ->where('eu.eliminado', '=', 0);
                // ->where('eu.fechaEliminacion', '=', '0000-00-00 00:00:00');
            })
            ->where('e.idEvento', $idEvento)
            ->where('e.eliminado', '=', 0)
        // ->where('e.fechaEliminacion', '0000-00-00 00:00:00')
            ->where('eu.idUn', $idUn)
            ->get()
            ->toArray();

        if (count($query) > 0) {
            $fila                    = $query[0];
            $datos['idProducto']     = $fila->idProducto;
            $datos['nombre']         = $fila->nombre;
            $datos['idEventoUn']     = $fila->idEventoUn;
            $datos['tipoEvento']     = $fila->idTipoEvento;
            $datos['inicioRegistro'] = $fila->inicioRegistro;
            $datos['finRegistro']    = $fila->finRegistro;
            $datos['inicioEvento']   = $fila->inicioEvento;
            $datos['finEvento']      = $fila->finEvento;
            $datos['reservar']       = $fila->reservarInstalacion;
            $datos['anticipo']       = $fila->anticipo;
            $datos['minina']         = $fila->edadMinima;
            $datos['maxima']         = $fila->edadMaxima;
        }
        return $datos;
    }

    public static function descuentoAnualidad($idProducto, $idUn)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        $res = 0;

        if ($idProducto <= 0 || $idUn <= 1) {
            return $res;
        }

        $sql = "SELECT euc.capacidad
            FROM producto p
            INNER JOIN evento e ON e.idProducto=p.idProducto
                AND e.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
                AND eu.idUn=$idUn AND eu.activo=1 AND eu.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
                AND euc.idTipoEventoCapacidad=29 AND euc.activo=1
                AND euc.autorizado=1 AND euc.capacidad>0
            WHERE p.idProducto=$idProducto AND p.activo=1 AND p.fechaEliminacion='0000-00-00 00:00:00'
            ORDER BY euc.idEventoUnCapacidad DESC
            LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $res = $query[0]->capacidad;
        }
        return $res;
    }

    /**
     * Regresa el listado de eventos disponibles
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function disponibles($un, $tipo = 0, $titulo = "")
    {
        settype($un, 'integer');
        settype($tipo, 'integer');

        $datos = array();

        if ($un == 0) {
            return $datos;
        }

        if ($titulo != "") {
            $datos[0] = $titulo;
        }
        $w_clasificacion = '';
        if ($this->session->userdata('idEmpresaGrupo') == 1) {
            $w_clasificacion = ' AND e.idEventoClasificacion>0 ';
        }

        $sql = 'SELECT e.idEvento, UPPER(p.nombre) AS nombre FROM ' . TBL_EVENTO . ' e ' .
        'INNER JOIN ' . TBL_PRODUCTO . ' p ON p.idProducto = e.idProducto AND p.activo=1 AND p.fechaEliminacion=\'0000-00-00 00:00:00\' ' .
        'INNER JOIN ' . TBL_PRODUCTOUN . ' pu ON pu.idProducto = p.idProducto AND pu.activo=1 AND pu.fechaEliminacion=\'0000-00-00 00:00:00\' AND idUn=' . $un . ' ' .
        'INNER JOIN ' . TBL_EVENTOUN . ' eu ON eu.idEvento = e.idEvento AND eu.activo=1 AND eu.fechaEliminacion=\'0000-00-00 00:00:00\' ' .
        'LEFT JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn ' .
        'AND euc.idTipoEventoCapacidad=26 ' .
        'AND euc.activo=1 AND euc.autorizado=1 AND euc.fechaEliminacion=\'0000-00-00 00:00:00\' ' .
        'WHERE e.bloqueoVenta=0 AND e.fechaEliminacion=\'0000-00-00 00:00:00\' AND \'' . date('Y-m-d') . '\' BETWEEN ' .
            'eu.inicioRegistro AND eu.finRegistro AND euc.idEventoUnCapacidad IS NULL AND eu.idUn=' . $un . $w_clasificacion;
        if ($tipo > 0) {
            $sql .= ' AND e.idTipoEvento=' . $tipo;
        }
        $sql .= ' GROUP BY e.idEvento';
        $sql .= ' ORDER BY p.nombre';

        $query = DB::connection('crm')->query($sql);
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idEvento] = $fila->nombre;
            }
        }
        return $datos;
    }

    /**
     * Elimina el evento indicado de manera logica en el sistema
     *
     * @param integer $id Id de la categoria a eliminar
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function eliminar($id)
    {
        settype($id, 'integer');

        if ($id == 0) {
            return false;
        }
        $datos = array('fechaEliminacion' => date("Y-m-d H:i:s"));

        DB::connection('crm')->where('idEvento', $id);
        DB::connection('crm')->where('fechaEliminacion', '0000-00-00 00:00:00');
        DB::connection('crm')->update(TBL_EVENTO, $datos);

        $total = DB::connection('crm')->affected_rows();
        if ($total == 0) {
            return false;
        }
        $this->permisos_model->log('Se elimino el evento con ID ' . $id, LOG_EVENTO);

        return true;
    }

    /**
     * [eliminarClase description]
     *
     * @param  [type] $idEventoFecha [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]                [description]
     */
    public static function eliminarClase($idEventoFecha)
    {
        settype($idEventoFecha, 'integer');
        $res = false;
        if ($idEventoFecha > 0) {
            $query = DB::connection('crm')->table(TBL_EVENTOFECHA)
                ->select('idEventoInscripcion')
                ->where('idEventoFecha', $idEventoFecha);

            if ($query->count() == 0) {
                return $res;
            }

            $fila = $query->get()->toArray();
            $fila = (array_map(function ($x) {return (array) $x;}, $fila))[0];

            $idEventoInscripcion = $fila['idEventoInscripcion'];
            if ($idEventoInscripcion > 0) {
                $datos = array('fechaEliminacion' => date("Y-m-d H:i:s"));

                $affected_rows = DB::connection('crm')->table(TBL_EVENTOFECHA)
                    ->where('idEventoFecha', $idEventoFecha)
                    ->where('fechaEliminacion', '0000-00-00 00:00:00')
                    ->where('idTipoEstatusEventoFecha', '<>', 2)
                    ->where('idEventoFecha', $idEventoFecha)
                    ->where('fechaEvento', '>', date('Y-m-d'))
                    ->update($datos);
                $total = $affected_rows;

                if ($total > 0) {
                    Permiso::log('Se elimino clase para la inscripcion (' . $idEventoInscripcion . ')', LOG_EVENTO);
                    $res = true;
                }
            }
        }
        return $res;
    }

    /**
     * Valida si se debe generar comision por venta en base a la configuracion
     *
     * @param  integer $idEvento [description]
     * @param  integer $idUn     [description]
     *
     * @return boolean
     */
    public static function generarComisionVenta($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        $res = false;

        if ($idEvento == 0 || $idUn == 0) {
            return $res;
        }

        $sql = "SELECT eu.idEventoUn
            FROM eventoun eu
            INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
                AND euc.idTipoEventoCapacidad IN (8, 10, 13) AND euc.eliminado=0
                AND euc.activo=1 AND euc.autorizado=1
            WHERE eu.eliminado=0 AND eu.activo=1
                AND eu.idEvento=$idEvento AND eu.idUn=$idUn";
        Log::debug($sql);
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $res = true;
        }

        return $res;
    }

    /**
     * Valida si un movimiento va a ser devengado
     *
     * @param integer $idMovimiento Identificador de movimientos
     *
     * @author Ivan Mendoza
     *
     * @return Object  Activo y Autorizado de EventoUnCapacidad
     */
    public static function movientoDevengado($idMovimiento)
    {
        $query = DB::connection('crm')->table(TBL_MOVIMIENTO . ' `mov`')
            ->select(DB::connection('crm')->raw('eucap.activo AS activo, eucap.autorizado AS autorizado'))
            ->from(TBL_MOVIMIENTO . ' AS mov')
            ->join('crm.movimientoctacontable AS mcc', 'mov.idMovimiento', '=', 'mcc.idMovimiento')
            ->join('crm.eventomovimiento AS emov', 'mov.idMovimiento', '=', 'emov.idMovimiento')
            ->join('crm.eventoinscripcion AS eins', 'emov.idEventoInscripcion', '=', 'eins.idEventoInscripcion')
            ->join('crm.eventoun AS eun', 'eun.idEventoUn', '=', 'eins.idEventoUn')
            ->join('crm.eventouncapacidad AS eucap', 'eun.idEventoUn', '=', 'eucap.idEventoUn')
            ->join('crm.tipoeventocapacidad AS tec', 'tec.idTipoEventoCapacidad', '=', 'eucap.idTipoEventoCapacidad')
            ->where('eucap.idTipoEventoCapacidad', 30)
            ->where('mov.idMovimiento', $idMovimiento)
            ->distinct()
            ->first();

        return $query;
    }

    /**
     * Aplica el Devengado 60 20 20 Al Movimiento Contable
     *
     * @param integer $idMovimiento Identificador de movimientos
     *
     * @author Ivan Mendoza
     *
     * @return  Object con Id´s de Movimientos contables Insertados y Fechas de Aplicacion
     */
    public static function devengarMovimientoContable($idMovimiento)
    {
        $queryMovimiento = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
            ->where('idMovimiento', $idMovimiento);

        //Obtenemos datos de Movimiento
        if ($queryMovimiento->count() > 0) {
            $queryMovimiento       = $queryMovimiento->get()->toArray();
            $movimientoctacontable = $queryMovimiento[0];
        }

        //Actualizamos Registro Contable del 60
        $whereCtaContable = array(
            'idMovimiento' => $idMovimiento,
        );
        $set = array(
            'importe' => ($movimientoctacontable->importe * 0.60),
        );

        $result = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
            ->where($whereCtaContable)
            ->update($set);

        if ($result) {
            $insertTblDevengado = [
                'idMovimientoCtaContable' => $movimientoctacontable->idMovimientoCtaContable,
                'idTipoDevengadoProducto' => '668',
                'idTipoDevengado'         => '4',
                'numeroAplicaciones'      => '1',
            ];
            //Insert a Tabla Movimientos Devengados
            // DB::connection('crm')->insert(TBL_MOVIMIENTODEVENGADO, $insertTblDevengado); No es Necesario
            $datos['60'] = "Movimientyo Actualizado Correctamente al 60 $" . ($movimientoctacontable->importe * 0.60);
        }
        //Iniciamilamos Arreglo para el Primer Insert
        $nuevafecha     = strtotime('+1 month', strtotime($movimientoctacontable->fechaAplica));
        $nuevafecha     = date('Y-m-j', $nuevafecha);
        $datosDevengado = array(
            'idMovimiento'        => $idMovimiento,
            'idTipoMovimiento'    => $movimientoctacontable->idTipoMovimiento,
            'idUn'                => $movimientoctacontable->idUn,
            'numeroCuenta'        => $movimientoctacontable->numeroCuenta,
            'cuentaProducto'      => $movimientoctacontable->cuentaProducto,
            'fechaAplica'         => $nuevafecha,
            'importe'             => ($movimientoctacontable->importe * 0.20),
            'cveProductoServicio' => $movimientoctacontable->cveProductoServicio,
            'cveUnidad'           => $movimientoctacontable->cveUnidad,
            'cantidad'            => $movimientoctacontable->cantidad,
        );

        $idMovimientoCuentaCont = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
            ->insertGetId($datosDevengado);
        $idMovimientoCuentaCont = DB::connection('crm')->insert_id();

        if ($idMovimientoCuentaCont > 0) {
            $sql = "UPDATE `movimientoctacontable` SET fechaAplica = adddate(last_day('" . $movimientoctacontable->fechaAplica . "'), 1) WHERE `idMovimientoCtaContable` =" . $idMovimientoCuentaCont;
            DB::connection('crm')->select($sql);

            $insertTblDevengado['idMovimientoCtaContable'] = $idMovimientoCuentaCont;
            //Insert a Tabla Movimientos Devengados
            DB::connection('crm')->table(TBL_MOVIMIENTODEVENGADO)
                ->insert($insertTblDevengado);
            $datos['80'] = "Movimientyo Creado Correctamente al 20 2o Mes $" . ($movimientoctacontable->importe * 0.20);
        }
        //Inicializamos Arreglo para segundo Insert
        $nuevafecha             = strtotime('+1 month', strtotime($movimientoctacontable->fechaAplica));
        $nuevafecha             = date('Y-m-j', $nuevafecha);
        $idMovimientoCuentaCont = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)->insertGetId($datosDevengado);
        $devengadoSegundoMes    = $idMovimientoCuentaCont;
        if ($devengadoSegundoMes > 0) {
            $sql = "UPDATE `movimientoctacontable` SET fechaAplica = adddate(last_day('" . $nuevafecha . "'), 1) WHERE `idMovimientoCtaContable` =" . $idMovimientoCuentaCont;
            DB::connection('crm')->select($sql);
            $insertTblDevengado['idMovimientoCtaContable'] = $idMovimientoCuentaCont;
            //Insert a Tabla Movimientos Devengados
            DB::connection('crm')->table(TBL_MOVIMIENTODEVENGADO)->insert($insertTblDevengado);
            $datos['100'] = "Movimientyo Ceado Correctamente al  20 3er Mes $" . ($movimientoctacontable->importe * 0.20);
        }
        //Actualizamos Log
        Permiso::log(utf8_decode("Actualiza importe del Movimiento Contable 60 20 20 (" . $idMovimiento . ")"), LOG_SISTEMAS);
        return $datos;
    }

    /**
     * Inserta inscripcion al evento envaido
     *
     * @param integer $idProd  Producto a ser utlizado
     *
     * @return boolean
     */
    public function guardaParticipante($idInscripcion, $idPersona, $idEvento = 0)
    {
        settype($idInscripcion, 'integer');
        settype($idPersona, 'integer');
        settype($idEvento, 'integer');

        if (!$idInscripcion or !$idPersona) {
            return 0;
        }

        $idCategoria = 0;
        if ($idEvento > 0) {
            $idCategoria = $this->obtenIdCategoria($idEvento);
        }
        if ($idCategoria == CATEGORIA_CARRERAS) {
            $sql = "
SELECT IF(MAX(ep.numfolio) IS NULL,0, MAX(ep.numfolio)) AS ultimoFolio
FROM crm.eventoparticipante  AS ep
INNER JOIN crm.eventoinscripcion AS ei ON ei.idEventoInscripcion=ep.idEventoInscripcion AND ei.monto=ei.pagado
INNER JOIN crm.eventoun AS eu ON eu.idEventoUn=ei.idEventoUn
INNER JOIN crm.evento AS e ON e.idEvento=eu.idEvento AND e.idEvento IN (" . $idEvento . ")
WHERE ep.fechaEliminacion='0000-00-00 00:00:00'
            ";
            $query = DB::connection('crm')->select($sql);
            $query = array_map(function ($x) {return (array) $x;}, $query);
            $row         = $query[0];
            $ultimoFolio = $row->ultimoFolio;
            $ultimoFolio = 0;

            $set = array(
                'idEventoInscripcion' => $idInscripcion,
                'idPersona'           => $idPersona,
                'numFolio'            => $ultimoFolio,
            );
        } else {
            $set = array(
                'idEventoInscripcion' => $idInscripcion,
                'idPersona'           => $idPersona,
            );
        }

        $id      = DB::connection('crm')->table(TBL_EVENTOPARTICIPANTE)->insertGetId($set);
        $permiso = new Permiso;
        $permiso->log('Se asigna persona al evento (' . $idPersona . ')', LOG_EVENTO);

        return $id;
    }

    /**
     * Genera una inscripcion para el evento solicitado
     *
     * @param  integer  $idEvento         Identificador del evento
     * @param  integer  $idUn             Identificador del club
     * @param  integer  $idPersona        Identificador de la persona
     * @param  integer $idPersonaRespVta  Identificador de persona responsable de la venta
     * @param  float $monto               Monto por cuota de inscripcion
     * @param  float $pagado              Monto pagado de la cuota de inscripcion
     * @param  integer $membresia         [description]
     * @param  integer $cantidad          [description]
     * @param  integer $totalSesion       [description]
     * @param  integer $idTipoCliente     [description]
     * @param  integer $descQuincenas     [description]
     * @param  integer $informativo       [description]
     *
     * @author Jorge Cruz
     *
     * @return integer                    [description]
     */
    public static function inscripcion($idEvento, $idUn, $idPersona, $idPersonaRespVta = 0, $monto = 0,
        $pagado = 0, $membresia = 0, $cantidad = 0, $totalSesion = 0, $idTipoCliente = TIPO_CLIENTEEXTERNO,
        $descQuincenas = 1, $informativo = 0, $participantes = 0, $idPersonaRespVta1 = 0, $visa = 0) {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        settype($idPersona, 'integer');
        settype($membresia, 'integer');
        settype($monto, 'float');
        settype($pagado, 'float');
        settype($cantidad, 'float');
        settype($totalSesion, 'float');
        settype($idTipoCliente, 'integer');
        settype($descQuincenas, 'integer');
        settype($informativo, 'integer');
        settype($visa, 'integer');

        if ($idEvento == 0 || $idUn == 0 || $idPersona == 0 or $idPersonaRespVta == 0) {
            return 0;
        }

        $query = DB::connection('crm')->table(TBL_EVENTOUN)
            ->select('idEventoUn', 'edadMinima', 'edadMaxima')
            ->where('idUn', $idUn)
            ->where('idEvento', $idEvento)
            ->where('activo', 1)
            ->where('fechaEliminacion', '0000-00-00 00:00:00')
            ->get()
            ->toArray();
        if (count($query) > 0) {
            $fila       = $query[0];
            $idEventoUn = $fila->idEventoUn;
            $edadMinima = $fila->edadMinima;
            $edadMaxima = $fila->edadMaxima;
        } else {
            return (-1);
        }

        $query = DB::connection('crm')->table(TBL_EVENTO . ' AS  e')
            ->select('p.nombre')
            ->join(TBL_PRODUCTO . ' AS p', 'e.idProducto', '=', 'p.idProducto')
            ->where('e.idEvento', $idEvento)->get()->toArray();

        if (count($query) > 0) {
            $fila   = $query[0];
            $nombre = $fila->nombre;
        } else {
            $nombre = '';
        }

        $aux         = new self;
        $idCategoria = $aux->obtenIdCategoria($idEvento);
        if ($participantes == 0) {
            $participantes = $aux->capacidadEvento($idEvento, $idUn, TIPO_NUMERO_PARTICIPANTES);
        }

        $unSession = (int) $_SESSION['idUn'];
        if ($unSession == 0) {
            $unSession = $idUn;
        }

        $empleadoSession = (int) $_SESSION['idEmpleado'];
        if ($empleadoSession == 0) {
            $empleadoSession = Empleado::obtenIdEmpleado($idPersonaRespVta);

        }

        $reg = array(
            'idEventoUn'               => $idEventoUn,
            'idPersona'                => $idPersona,
            'idUn'                     => $unSession,
            'idEmpleado'               => $empleadoSession,
            'idTipoEstatusInscripcion' => 1,
            'monto'                    => $monto,
            'pagado'                   => $pagado,
            'cantidad'                 => $cantidad,
            'totalSesiones'            => $totalSesion,
            'idTipoCliente'            => $idTipoCliente,
            'descQuincenas'            => $descQuincenas,
            'informativo'              => $informativo,
            'participantes'            => $participantes,
            'visa'                     => $visa,
        );

        $inscripcion = DB::connection('crm')->table(TBL_EVENTOINSCRIPCION)->insertGetId($reg);
        $permiso     = new Permiso;
        $permiso->log(
            'Se realiza incripcion al evento ' . $nombre . ' (Num. Inscripcion ' . $inscripcion . ')',
            LOG_EVENTO,
            $membresia,
            $idPersona
        );

        $edad = Persona::edad($idPersona);

        if ((($edadMinima == 0 && $edadMaxima == 0) || ($edad >= $edadMinima && $edad <= $edadMaxima)) && $inscripcion > 0) {
            if ($idCategoria == CATEGORIA_CARRERAS) {
                $aux->guardaParticipante($inscripcion, $idPersona, $idEvento);
            } else {
                if ($idCategoria != CATEGORIA_SUMMERCAMP) {
                    $aux->guardaParticipante($inscripcion, $idPersona);
                }
            }
        }

        $set = array(
            'idEventoInscripcion' => $inscripcion,
            'idPersona'           => $_SESSION['idPersona'],
            'tipo'                => 1,
        );
        $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);

        if ($res) {
            $set = array(
                'idEventoInscripcion' => $inscripcion,
                'idPersona'           => $idPersonaRespVta,
                'tipo'                => 2,
            );
            $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);

            if ($idPersonaRespVta1 > 0) {
                $set = array(
                    'idEventoInscripcion' => $inscripcion,
                    'idPersona'           => $idPersonaRespVta1,
                    'tipo'                => 3,
                );
                $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);
            }
        }
        return $inscripcion;
    }

    /**
     * Vincula el movimiento indicado con la inscripcion
     *
     * @param integer $idInscripcion Identificador de la inscripcion
     * @param integer $idMovimiento  Identificador del movimiento
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public static function inscripcionMovimiento($idInscripcion, $idMovimiento)
    {
        settype($idInscripcion, 'integer');
        settype($idMovimiento, 'integer');

        if ($idInscripcion == 0 || $idMovimiento == 0) {
            return 0;
        }

        $reg = array(
            'idEventoInscripcion' => $idInscripcion,
            'idMovimiento'        => $idMovimiento,
        );
        $id = DB::connection('crm')->table(TBL_EVENTOMOVIMIENTO)
            ->insertGetId($reg);
        $permiso = new Permiso;
        $permiso->log('Se vincula evento al movimiento (' . $idMovimiento . ')', LOG_EVENTO);

        return $id;
    }

    /**
     * [insertaClase description]
     *
     * @param  [type] $idEventoInscripcion [description]
     * @param  [type] $idEmpleado          [description]
     * @param  [type] $idPersona           [description]
     * @param  [type] $fecha               [description]
     * @param  [type] $hora                [description]
     *
     * @return [type]                      [description]
     */
    public static function insertaClase($idEventoInscripcion, $idEmpleado, $idPersona, $fecha, $hora, $demo = 0)
    {
        settype($idEventoInscripcion, 'integer');
        settype($idEmpleado, 'integer');
        settype($idPersona, 'integer');

        $res = 0;

        if ($idEventoInscripcion > 0 && $idPersona > 0) {
            $sql = "select totalSesiones from " . TBL_EVENTOINSCRIPCION . "
            where idEventoInscripcion = '{$idEventoInscripcion}'
            and fechaEliminacion = '0000-00-00 00:00:00' ";
            $query = DB::connection('crm')->select($sql);

            $query = DB::connection('crm')
                ->table(TBL_EVENTOINSCRIPCION)
                ->select('totalSesiones')
                ->where('idEventoInscripcion', $idEventoInscripcion)
                ->where('fechaEliminacion', '0000-00-00 00:00:00')
                ->get()->toArray();

            $query = array_map(function ($x) {return (array) $x;}, $query);
            $fila          = $query[0];
            $totalSesiones = $fila['totalSesiones'];
            $query         = DB::connection('crm')
                ->table(TBL_EVENTOFECHA)
                ->select('idEventoFecha')
                ->where('idEventoInscripcion', $idEventoInscripcion)
                ->where('fechaEliminacion', '0000-00-00 00:00:00');
            $totalClase = $query->count();

            $estatusClase = ESTATUS_CLASE_ASIGNADO;
            if ($demo == 1) {
                $estatusClase = ESTATUS_CLASE_DEMO;
            }
            if ($totalClase - 1 < $totalSesiones) {
                $reg = array(
                    'idEventoInscripcion'      => $idEventoInscripcion,
                    'idTipoEstatusEventoFecha' => $estatusClase,
                    'idEmpleado'               => $idEmpleado,
                    'idPersona'                => $idPersona,
                    'idUnInstalacion'          => 0,
                    'fechaEvento'              => $fecha,
                    'horaEvento'               => $hora,
                );
                $res     = DB::connection('crm')->table(TBL_EVENTOFECHA)->insertGetId($reg);
                $permiso = new Permiso;
                $permiso->log('Se agenda clase para la inscripcion (' . $idEventoInscripcion . ')', LOG_EVENTO);

                $totalClase = $totalClase + 1;
                $set        = array('totalSeguimiento' => $totalClase);
                $where      = array('idEventoInscripcion' => $idEventoInscripcion);
                DB::connection('crm')->table(TBL_EVENTOINSCRIPCION)->where($where)->update($set);
            }
        }

        return $res;
    }

    /**
     * Inserta relacion eventouncategoria
     *
     * @param integer $idEventoUn Identificador de eventoun
     * @param integer $idTalla    Identificador de talla
     *
     * @author Antonio Sixtos
     *
     * @return int
     */
    public function insertaConfigCategoria($idEventoUn, $idEventoCategoria)
    {
        settype($idEventoUn, 'integer');
        settype($idEventoCategoria, 'integer');

        $set = array(
            'idEventoUn'        => $idEventoUn,
            'idEventoCategoria' => $idEventoCategoria,
            'fechaRegistro'     => date('Y-m-d H:i:s'),
            'activo'            => 0,
        );
        if (!$idEventoUn or !$idEventoCategoria) {
            $set['idEventoUnCategoria'] = 0;
            return $set;
        }
        if (DB::connection('crm')->insert(TBL_EVENTOUNCATEGORIA, $set)) {
            $set['idEventoUnCategoria'] = DB::connection('crm')->insert_id();
        }
        return $set;
    }
    /**
     * Inserta un nuevo evento
     *
     * @param integer $idProducto   Identificador de producto
     * @param integer $idTipoEvento Identificador de tipoevento
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function insertaEvento($idProducto, $idTipoEvento)
    {
        settype($idProducto, 'integer');
        settype($idTipoEvento, 'integer');
        $idEvento = 0;

        if (!$idProducto or !$idTipoEvento) {
            return $idEvento;
        }
        $set = array(
            'idProducto'   => $idProducto,
            'idTipoEvento' => $idTipoEvento,
        );

        DB::connection('crm')->insert(TBL_EVENTO, $set);
        $id = DB::connection('crm')->insert_id();
        $this->permisos_model->log('Se inserta nuevo evento', LOG_EVENTO);

        return $id;
    }

    /**
     * Inserta capacidad a evento
     *
     * @param integer $idTipoEventoCapacidad Identificador de tipoeventocapacidad
     * @param integer $idEventoUn            Identificador de eventoun
     * @param integer $activo                Bandera de estatus de eventouncapacidad
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function insertaEventoCapacidadUn($idTipoEventoCapacidad, $idEventoUn, $activo = 0)
    {
        settype($idTipoEventoCapacidad, 'integer');
        settype($idEventoUn, 'integer');
        $idEventoUnCapacidad = 0;

        if (!$idTipoEventoCapacidad or !$idEventoUn) {
            return $idEventoUnCapacidad;
        }
        $set = array(
            'idTipoEventoCapacidad' => $idTipoEventoCapacidad,
            'idEventoUn'            => $idEventoUn,
            'activo'                => $activo,
        );
        DB::connection('crm')->insert(TBL_EVENTOUNCAPACIDAD, $set);
        $id = DB::connection('crm')->insert_id();
        $this->permisos_model->log('Se inserta capacidad del evento', LOG_EVENTO);

        return $id;
    }

    /**
     *
     * @param <type> $evento
     * @param <type> $club
     * @param <type> $empresa
     * @param <type> $totales
     * @param <type> $orden
     * @param <type> <1posicion></1posicion>
     * @param <type> $registros
     * @param <type> $direction
     *
     * @return <type>
     */
    public function lista($evento = '', $club = 0, $empresa = 0, $totales = false, $orden = 'pr.nombre', $posicion = null, $registros = null, $direction = 'ASC')
    {
        settype($club, 'integer');

        $datos = array();

        $idPuesto = $this->session->userdata('idPuesto');

        $ci = &get_instance();
        $ci->load->model('empleados_model');
        $puesto = $ci->empleados_model->obtienePuesto($idPuesto);

        $filtro = '';
        $pos    = strrpos($puesto, 'KIDZ');
        if ($pos !== false) {
            $filtro = ' AND p.idCategoria=' . CATEGORIA_SUMMERCAMP;
        }

        $sql = "SELECT UPPER(p.nombre) AS producto, eu.inicioEvento, u.nombre AS club, e.idProducto, eu.idEvento, eu.idUn
            FROM " . TBL_EVENTOUN . " eu
            INNER JOIN " . TBL_EVENTOINSCRIPCION . " ei ON ei.idEventoUn=eu.idEventoUn AND ei.fechaEliminacion='0000-00-00 00:00:00'
                AND ei.idTipoEstatusInscripcion=1 AND ei.informativo=0
            INNER JOIN " . TBL_UN . " u ON u.idUn=eu.idUn
            INNER JOIN " . TBL_EVENTO . " e ON e.idEvento=eu.idEvento AND e.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN " . TBL_PRODUCTO . " p ON p.idProducto=e.idProducto AND p.fechaEliminacion='0000-00-00 00:00:00' $filtro
            WHERE eu.idUn=$club AND eu.fechaEliminacion='0000-00-00 00:00:00' AND eu.activo=1
                AND ( DATE(NOW()) BETWEEN eu.inicioRegistro AND DATE_ADD(eu.finRegistro, INTERVAL 6 MONTH)  )
                AND p.activo=1
            GROUP BY p.idProducto
            ORDER BY p.nombre";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows) {
            if ($totales) {
                $datos = $query->num_rows;
            } else {
                $datos = $query->result_array();
            }
        }
        return $datos;
    }

    /**
     * [modificaMonto description]
     *
     * @param  [type] $idEventoInscripcion [description]
     * @param  [type] $importe             [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]                      [description]
     */
    public static function modificaMonto($idEventoInscripcion, $importe)
    {
        settype($idEventoInscripcion, 'integer');
        settype($importe, 'float');

        if ($idEventoInscripcion == 0) {
            return false;
        }

        $set   = array('monto' => $importe);
        $where = array(
            'idEventoInscripcion' => $idEventoInscripcion,
            'eliminado'           => 0,
        );
        $res = DB::connection('crm')->table(TBL_EVENTOINSCRIPCION)
            ->where($where)
            ->update($set);

        return true;
    }

    /**
     * Obtiene capacidades del evento
     *
     * @param integer $idEventoUn Identificador de eventoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenCapacidad($idEventoUn, $permisoModificaTodo)
    {
        settype($idEventoUn, 'integer');
        $datos = array('error' => 1, 'mensaje' => 'Error al recibir datos', 'capacidad' => array());

        if (!$idEventoUn) {
            return $datos;
        }
        $tiposCapacidad = $this->obtenTiposEventoCapacidad();
        foreach ($tiposCapacidad as $idRow => $capacidad) {
            $idEventoUnCapacidad = 0;
            $infoCapacidad       = $this->validaEventoCapacidadUn($capacidad['idTipoEventoCapacidad'], $idEventoUn);
            if (!$infoCapacidad) {
                $idEventoUnCapacidad = $this->insertaEventoCapacidadUn($capacidad['idTipoEventoCapacidad'], $idEventoUn);
            } else {
                $idEventoUnCapacidad = $infoCapacidad['idEventoUnCapacidad'];
            }
            if (!$idEventoUnCapacidad) {
                $datos['error']   = 2;
                $datos['mensaje'] = 'Error al insertar en eventouncapacidad';
                return $datos;
            }
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $where = array(
            'tec.activo'           => 1,
            'euc.idEventoUn'       => $idEventoUn,
            'euc.fechaEliminacion' => '0000-00-00 00:00:00',
        );
        if (!$permisoModificaTodo) {
            $where['tec.requierePermiso'] = 0;
        }
        DB::connection('crm')->join(TBL_EVENTOUNCAPACIDAD . " euc", "tec.idTipoEventoCapacidad = euc.idTipoEventoCapacidad", "inner");
        DB::connection('crm')->select(
            "euc.idEventoUnCapacidad, tec.idTipoEventoCapacidad,
            tec.descripcion AS tipoEventoCapacidad, euc.capacidad, euc.activo, euc.autorizado, idCategoriaEventoCapacidad", false
        );
        DB::connection('crm')->order_by('tec.orden');
        $query = DB::connection('crm')->get_where(TBL_TIPOEVENTOCAPACIDAD . " tec", $where);

        if ($query->num_rows) {
            $datos['capacidad'] = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene categorias
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenCategorias()
    {
        $datos = array();

        $query = DB::connection('crm')->select("
            idEventoCategoria, descripcion AS categoria, activo", false
        )->get_where(TBL_EVENTOCATEGORIA); #->get_where(TBL_TALLA, $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene la categoria del evento
     *
     * @param integer $idEvento Identificador del evento
     *
     * @return array
     */
    public function obtenIdCategoria($idEvento)
    {
        settype($idEvento, 'integer');

        $sql = "
SELECT pr.idCategoria
FROM  evento e
INNER JOIN producto pr ON pr.idProducto=e.idProducto
WHERE e.idEvento IN (" . $idEvento . ")
        ";
        $query = DB::connection('crm')->select($sql);
        $row   = $query[0];
        return $row->idCategoria;
    }

    /**
     * Obtiene tipo de capacidades del evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTiposEventoCapacidad()
    {
        $datos = array();
        $where = array('activo' => 1);
        $query = DB::connection('crm')->select(
            "idTipoEventoCapacidad, descripcion AS tipoEventoCapacidad"
        )->get_where(TBL_TIPOEVENTOCAPACIDAD, $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * [precioPrimerSemana description]
     *
     * @param  [type] $idProducto [description]
     * @param  [type] $idUn       [description]
     *
     * @return [type]             [description]
     */
    public static function precioPrimerSemana($idProducto, $idUn)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        $res = 0;

        if ($idProducto <= 0 || $idUn <= 1) {
            return $res;
        }

        $sql = "SELECT euc.capacidad
            FROM producto p
            INNER JOIN evento e ON e.idProducto=p.idProducto
                AND e.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
                AND eu.idUn=$idUn AND eu.activo=1 AND eu.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
                AND euc.idTipoEventoCapacidad=28 AND euc.activo=1
                AND euc.autorizado=1 AND euc.capacidad>0
            WHERE p.idProducto=$idProducto AND p.activo=1 AND p.fechaEliminacion='0000-00-00 00:00:00'
            ORDER BY euc.idEventoUnCapacidad DESC
            LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $res = $query[0]->capacidad;
        }

        return $res;
    }

    /**
     * Valida si existe registro de capacidad en evento
     *
     * @param integer $idTipoEventoCapacidad Identificador de tipoeventocapacidad
     * @param integer $idEventoUn            Identificador de eventoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaEventoCapacidadUn($idTipoEventoCapacidad, $idEventoUn)
    {
        settype($idTipoEventoCapacidad, 'integer');
        settype($idEventoUn, 'integer');
        $datos = array();

        if (!$idTipoEventoCapacidad or !$idEventoUn) {
            return $datos;
        }
        $where = array(
            'idTipoEventoCapacidad' => $idTipoEventoCapacidad,
            'idEventoUn'            => $idEventoUn,
            'fechaEliminacion'      => '0000-00-00 00:00:00',
        );
        $query = DB::connection('crm')->select(
            "idEventoUnCapacidad, activo", false
        )->get_where(TBL_EVENTOUNCAPACIDAD, $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

}
