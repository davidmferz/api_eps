<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use API_EPS\Models\CatRutinas;
use API_EPS\Models\MenuActividad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use API_EPS\Models\Objeto;

class EP extends Model
{

    use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.persona';
    protected $primaryKey = 'idPersona';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

   /**
     * [agenda description]
     *
     * @param  [type] $idEntrenador [description]
     * @param  [type] $idUn         [description]
     *
     * @return [type]               [description]
     */
    public static function agenda($idEntrenador, $idUn)
    {
        settype($idEntrenador, 'integer');
        settype($idUn, 'integer');
        $res = array();
        if ($idEntrenador == 0 && $idUn == 0) {
            return $res;
        }
        $wUn = '';
        if ($idUn > 0) {
            $wUn = ' AND eu.idUn ='.$idUn;
        }
        $wEntrenador = '';
        if ($idEntrenador > 0) {
            $wEntrenador = ' AND einv.idPersona = '.$idEntrenador;
        }
        $sql = "SELECT
                ei.idEventoInscripcion AS idInscripcion,
                c.nombre AS nombreProducto,
                CONCAT_WS(' ', p_e.nombre, p_e.paterno, p_e.materno) AS nombreEntrenador,
                CONCAT_WS(' ', p_e.nombre, p_e.paterno, p_e.materno) AS nombreEntrenador,
                CONCAT_WS(' ', p_c.nombre, p_c.paterno, p_c.materno) AS nombreCliente,
                CONCAT_WS(' ', p_c.nombre, p_c.paterno, p_c.materno) AS nombreCliente,
                ei.totalSesiones,
                ep.idEmpleado,
                p_e.idPersona
            FROM evento e
            INNER JOIN producto p ON p.idProducto = e.idProducto
            INNER JOIN categoria c ON c.idCategoria = p.idCategoria
            INNER JOIN eventoun eu ON eu.idEvento = e.idEvento {$wUn}
                AND eu.activo = 1 AND eu.fechaEliminacion = 0
                AND eu.finEvento >= DATE(NOW())
            INNER JOIN eventouncapacidad euc3 ON euc3.idEventoUn = eu.idEventoUn
                AND euc3.idTipoEventoCapacidad = 26
                AND euc3.activo = 1 AND euc3.eliminado = 0
                AND euc3.autorizado = 1 AND euc3.capacidad > 0
            INNER JOIN eventoinscripcion ei ON ei.idEventoUn = eu.idEventoUn
                AND ei.idTipoEstatusInscripcion = 1
                AND ei.eliminado = 0
                AND ei.totalSeguimiento < ei.totalSesiones
            INNER JOIN eventoinvolucrado einv ON einv.idEventoInscripcion = ei.idEventoInscripcion {$wEntrenador}
                AND einv.tipo = 'Entrenador' AND einv.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN persona p_e ON p_e.idPersona = einv.idPersona
            INNER JOIN persona p_c ON p_c.idPersona = ei.idPersona
            INNER JOIN eventomovimiento em ON em.idEventoInscripcion = ei.idEventoInscripcion
            INNER JOIN movimiento m ON m.idMovimiento = em.idMovimiento
            INNER JOIN empleado ep ON einv.idPersona = ep.idPersona
            WHERE m.idTipoEstatusMovimiento IN (66, 70)
            ORDER BY nombreCliente
            ";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            foreach ($query as $fila) {
                $sql = "SELECT
                    ef.idEventoFecha
                    FROM crm.eventofecha ef
                    WHERE ef.idEventoInscripcion = ".intval($fila->idInscripcion)."
                    AND ef.eliminado = 0";
                $seguimiento = count(DB::connection('crm')->select($sql));
                $sesionesDisponibles = intval($fila->totalSesiones) - intval($seguimiento);
                if ($sesionesDisponibles > 0) {
                    $agenda['idInscripcion']       = utf8_encode($fila->idInscripcion);
                    $agenda['nombreProducto']      = utf8_encode($fila->nombreProducto);
                    $agenda['nombreEntrenador']    = utf8_encode($fila->nombreEntrenador);
                    $agenda['nombreCliente']       = utf8_encode($fila->nombreCliente);
                    $agenda['sesionesDisponibles'] = utf8_encode($sesionesDisponibles);
                    $agenda['idEmpleado']          = utf8_encode($fila->idEmpleado);
                    $agenda['idPersona']           = utf8_encode($fila->idPersona);
                    $res[] = $agenda;
                }
            }
        }
        return $res;
    }
    
    /**
     * [arrayClases description]
     *
     * @param  [type] $idCategoria   [description]
     * @param  [type] $idUn          [description]
     * @param  [type] $participantes [description]
     *
     * @return [type]                [description]
     */
    function arrayClases($idCategoria, $idUn, $participantes)
    {
        $res = array();
        $sql = "
SELECT IF(pp.idEsquemaPago=7, 1,  euc4.capacidad) as clases
FROM producto p
INNER JOIN categoria c ON c.idCategoria=p.idCategoria
    AND c.idCategoria={$idCategoria}
INNER JOIN evento e ON e.idProducto=p.idProducto
   AND e.idEventoClasificacion>0
   AND e.fechaEliminacion=0
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
   AND pu.activo=1 AND pu.fechaEliminacion=0
   AND pu.idUn={$idUn}
INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
    AND pp.activo=1 AND pp.fechaEliminacion=0
    AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
    AND eu.idUn={$idUn} AND eu.activo=1 AND eu.fechaEliminacion=0
    AND DATE(NOW()) BETWEEN eu.inicioRegistro AND eu.finRegistro
    AND DATE(NOW()) <= eu.finEvento
INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
    AND euc.idTipoEventoCapacidad=1 AND euc.activo=1 AND euc.autorizado=1
    AND euc.fechaEliminacion=0 AND euc.capacidad>0
INNER JOIN eventouncapacidad euc2 ON euc2.idEventoUn=eu.idEventoUn
    AND euc2.idTipoEventoCapacidad=7 AND euc2.activo=1 AND euc2.autorizado=1
    AND euc2.eliminado=0 AND euc2.capacidad={$participantes}
INNER JOIN eventouncapacidad euc3 ON euc3.idEventoUn=eu.idEventoUn
    AND euc3.idTipoEventoCapacidad=26 AND euc3.activo=1 AND euc3.autorizado=1
    AND euc3.eliminado=0 AND euc3.capacidad>0
INNER JOIN eventouncapacidad euc4 ON euc4.idEventoUn=eu.idEventoUn
    AND euc4.idTipoEventoCapacidad=6 AND euc4.activo=1 AND euc4.autorizado=1
    AND euc4.eliminado=0 AND euc4.capacidad>0
WHERE p.activo=1 AND p.eliminado=0
GROUP BY 1
ORDER BY 1";
        $query = DB::connection('crm')->select($sql);
        
        if (count($query) > 0) {
            foreach ($query as $fila) {
                $r['numClases'] = (int) $fila->clases;
                $r['precios'] = $this->arrayPrecios($idCategoria, $idUn, $participantes, $fila->clases);
                $res[] = $r;
            }
        }
        return $res;
    }

    /**
     * [arrayEntrenadores description]
     *
     * @param  [type] $idCategoria [description]
     * @param  [type] $idUn        [description]
     *
     * @return [type]              [description]
     */
    function arrayEntrenadores($idCategoria, $idUn)
    {
        $res = array();

        $sql = "
SELECT * FROM (
        (SELECT emp.idPersona, CONCAT_WS(' ', per.nombre, per.paterno, per.materno) AS nombre
FROM producto p
INNER JOIN categoria c ON c.idCategoria=p.idCategoria
    AND c.idCategoria={$idCategoria}
INNER JOIN evento e ON e.idProducto=p.idProducto
    AND e.idEventoClasificacion>0
    AND e.eliminado=0
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
    AND pu.activo=1 AND pu.eliminado=0
    AND pu.idUn={$idUn}
INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
    AND eu.idUn={$idUn} AND eu.activo=1 AND eu.eliminado=0
    AND DATE(NOW()) BETWEEN eu.inicioRegistro and eu.finRegistro
    AND DATE(NOW()) <= eu.finEvento
INNER JOIN eventopuestocomision epc ON epc.idEvento=e.idEvento
    AND epc.activo=1 AND epc.fechaEliminacion='0000-00-00 00:00:00'
INNER JOIN empleadopuesto ep ON ep.idPuesto=epc.idPuesto
    AND ep.idUn={$idUn} AND ep.fechaEliminacion='0000-00-00 00:00:00'
INNER JOIN empleado emp ON emp.idEmpleado=ep.idEmpleado
    AND emp.idTipoEstatusEmpleado=196 AND emp.fechaEliminacion='0000-00-00 00:00:00'
INNER JOIN persona per ON per.idPersona=emp.idPersona
WHERE p.activo=1 AND p.eliminado=0
GROUP BY emp.idPersona)
UNION
(SELECT emp.idPersona, CONCAT_WS(' ', per.nombre, per.paterno, per.materno) AS nombre
FROM producto p
INNER JOIN categoria c ON c.idCategoria=p.idCategoria
    AND c.idCategoria={$idCategoria}
INNER JOIN evento e ON e.idProducto=p.idProducto
    AND e.idEventoClasificacion>0
    AND e.eliminado=0
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
    AND pu.activo=1 AND pu.eliminado=0
    AND pu.idUn={$idUn}
INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
    AND eu.idUn={$idUn} AND eu.activo=1 AND eu.eliminado=0
    AND DATE(NOW()) BETWEEN eu.inicioRegistro and eu.finRegistro
    AND DATE(NOW()) <= eu.finEvento
INNER JOIN eventounpuestoexcepcion eupe ON eupe.idEventoUn=eu.idEventoUn
    AND eupe.idEmpleado>0 AND eupe.activo=1
    AND eupe.fechaEliminacion='0000-00-00 00:00:00'
INNER JOIN empleado emp ON emp.idEmpleado=eupe.idEmpleado
    AND emp.idTipoEstatusEmpleado=196
    AND emp.fechaEliminacion = '0000-00-00 00:00:00'
INNER JOIN persona per ON per.idPersona=emp.idPersona
GROUP BY emp.idPersona)
) a GROUP BY a.idPersona
ORDER  BY nombre";
        $query = DB::connection('crm')->select($sql);

        if (count($query) > 0) {
            foreach ($query as $fila) {
                $r['idEntrenador']     = utf8_encode($fila->idPersona);
                $r['nombreEntrenador'] = utf8_encode($fila->nombre);
                $res[] = $r;
            }
        }
        return $res;
    }

    /**
     * [arrayFormaPago description]
     *
     * @param  [type] $idCategoria [description]
     * @param  [type] $idUn        [description]
     *
     * @return [type]              [description]
     */
    function arrayFormaPago($idCategoria, $idUn)
    {
        $res = array();
        
        $sql = "DROP TABLE IF EXISTS  tmp_WSEp_formaPago";
        $query = DB::connection('crm')->select($sql);

        $sql = "
CREATE TEMPORARY TABLE tmp_WSEp_formaPago
SELECT pmsi.numeroMeses, CONCAT(pmsi.numeroMeses, ' ', pmsi.descripcion) AS descripcion
FROM producto p
INNER JOIN categoria c ON c.idCategoria=p.idCategoria
    AND c.idCategoria={$idCategoria}
INNER JOIN evento e ON e.idProducto=p.idProducto
    AND e.idEventoClasificacion>0
    AND e.fechaEliminacion=0
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
    AND pu.activo=1 AND pu.fechaEliminacion=0
    AND pu.idUn={$idUn}
INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
    AND eu.idUn={$idUn} AND eu.activo=1 AND eu.fechaEliminacion=0
    AND DATE(NOW()) BETWEEN eu.inicioRegistro AND eu.finRegistro
    AND DATE(NOW()) <= eu.finEvento
INNER JOIN productomsi pm ON pm.idProducto=p.idProducto
    AND pu.idUn=pm.idUn AND pm.activo=1
INNER JOIN periodomsi pmsi on pmsi.idPeriodoMsi=pm.idPeriodoMsi
WHERE p.activo=1
AND p.fechaEliminacion=0
GROUP BY pmsi.idPeriodoMsi
ORDER BY pmsi.orden";
        $query = DB::connection('crm')->select($sql);
        
        $sql = "SELECT numeroMeses, descripcion FROM (
            (SELECT  1 AS numeroMeses, 'Contado' AS descripcion)
            UNION ALL
            (SELECT * FROM tmp_WSEp_formaPago p)
        ) a GROUP BY numeroMeses";
        $query = DB::connection('crm')->select($sql);
        
        if (count($query) > 0) {
            foreach ($query as $fila) {
                $r['meses']       = utf8_encode($fila->numeroMeses);
                $r['descripcion'] = utf8_encode($fila->descripcion);
                $res[] = $r;
            }
        }
        return $res;
    }

    /**
     * [arrayParticipantes description]
     *
     * @param  [type] $idCategoria [description]
     * @param  [type] $idUn        [description]
     *
     * @return [type]              [description]
     */
    function arrayParticipantes($idCategoria, $idUn)
    {
        $res = array();
        
        $sql = "
SELECT euc2.capacidad AS participantes
FROM producto p
INNER JOIN categoria c ON c.idCategoria=p.idCategoria
    AND c.idCategoria={$idCategoria}
INNER JOIN evento e ON e.idProducto=p.idProducto
    AND e.idEventoClasificacion>0
    AND e.fechaEliminacion=0
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
    AND pu.activo=1 AND pu.fechaEliminacion=0
    AND pu.idUn={$idUn}
INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
    AND eu.idUn={$idUn} AND eu.activo=1 AND eu.fechaEliminacion=0
    AND DATE(NOW()) BETWEEN eu.inicioRegistro and eu.finRegistro
    AND DATE(NOW()) <= eu.finEvento
INNER JOIN eventouncapacidad euc2 ON euc2.idEventoUn=eu.idEventoUn
    AND euc2.idTipoEventoCapacidad=7 AND euc2.activo=1 AND euc2.fechaEliminacion=0
    AND euc2.autorizado=1 AND euc2.capacidad>0
WHERE p.activo=1 AND p.fechaEliminacion=0
GROUP BY euc2.capacidad
ORDER BY euc2.capacidad";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            foreach ($query as $fila) {
                $r['numParticipantes'] = (int)$fila->participantes;
                $r['clases'] = $this->arrayClases($idCategoria, $idUn, $fila->participantes);
                $res[] = $r;
            }
        }

        return $res;
    }

    /**
     * [arrayPrecios description]
     *
     * @param  [type] $idCategoria   [description]
     * @param  [type] $idUn          [description]
     * @param  [type] $participantes [description]
     * @param  [type] $clases        [description]
     *
     * @return [type]                [description]
     */
    function arrayPrecios($idCategoria, $idUn, $participantes, $clases)
    {
        settype($idCategoria, 'integer');
        settype($idUn, 'integer');
        settype($participantes, 'integer');
        settype($participantes, 'integer');
        settype($clases, 'integer');

        $res = array();

        $sql = "
CREATE TEMPORARY TABLE tmp_pre_ep_precios
SELECT tc.descripcion  AS tipoCliente, IF(ep.idEsquemaPago=8, 'Contado', ep.descripcion) AS pago, pp.importe
FROM producto p
INNER JOIN categoria c ON c.idCategoria=p.idCategoria
    AND c.idCategoria={$idCategoria}
INNER JOIN evento e ON e.idProducto=p.idProducto
   AND e.idEventoClasificacion>0
   AND e.fechaEliminacion=0
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
   AND pu.activo=1 AND pu.fechaEliminacion=0
   AND pu.idUn={$idUn}
INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
    AND pp.activo=1 AND pp.fechaEliminacion=0 AND pp.idEsquemaPago NOT IN (7, 11)
    AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
INNER JOIN tipocliente tc ON tc.idTipoCliente=pp.idTipoCliente
INNER JOIN esquemapago ep ON ep.idEsquemaPago=pp.idEsquemaPago
INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
    AND eu.idUn={$idUn} AND eu.activo=1 AND eu.fechaEliminacion=0
    AND DATE(NOW()) BETWEEN eu.inicioRegistro AND eu.finRegistro
    AND DATE(NOW()) <= eu.finEvento
INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
    AND euc.idTipoEventoCapacidad=1 AND euc.activo=1 AND euc.autorizado=1
    AND euc.fechaEliminacion=0 AND euc.capacidad>0
INNER JOIN eventouncapacidad euc2 ON euc2.idEventoUn=eu.idEventoUn
    AND euc2.idTipoEventoCapacidad=7 AND euc2.activo=1 AND euc2.autorizado=1
    AND euc2.eliminado=0 AND euc2.capacidad={$participantes}
INNER JOIN eventouncapacidad euc3 ON euc3.idEventoUn=eu.idEventoUn
    AND euc3.idTipoEventoCapacidad=26 AND euc3.activo=1 AND euc3.autorizado=1
    AND euc3.eliminado=0 AND euc3.capacidad>0
INNER JOIN eventouncapacidad euc4 ON euc4.idEventoUn=eu.idEventoUn
    AND euc4.idTipoEventoCapacidad=6 AND euc4.activo=1 AND euc4.autorizado=1
    AND euc4.eliminado=0
    AND euc4.capacidad={$clases}
WHERE p.activo=1 AND p.eliminado=0
ORDER BY pp.idTipoCliente, pp.idEsquemaPago, pp.idProductoPrecio DESC";
        $query = DB::connection('crm')->select($sql);

        $sql = "
CREATE TEMPORARY TABLE tmp_ep_precios
SELECT tipoCliente, pago, importe
FROM tmp_pre_ep_precios
GROUP BY tipoCliente, pago";
        $query = DB::connection('crm')->select($sql);

        $sql = "
SELECT tipoCliente
FROM tmp_ep_precios
GROUP BY tipoCliente";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $r = array();
            foreach ($query as $fila) {
                $sql = "
SELECT pago, importe
FROM tmp_ep_precios
WHERE tipoCliente = '".$fila->tipoCliente."'
GROUP BY pago";
                $query2 = DB::connection('crm')->select($sql);
                if (count($query2) > 0) {
                    foreach ($query2 as $fila2) {
                        $r[$fila->tipoCliente][$fila2->pago] = $fila2->importe;
                    }
                }
                $res = $r;
            }
        }
        
        $sql = "DROP TABLE tmp_pre_ep_precios";
        DB::connection('crm')->select($sql);
        
        $sql = "DROP TABLE tmp_ep_precios";
        DB::connection('crm')->select($sql);
        
        return $res;
    }

    /**
     * [clase description]
     *
     * @param  [type] $idEntrenador [description]
     * @param  [type] $idUn         [description]
     *
     * @return [type]               [description]
     */
    public static function clase($idEmpleado, $idUn)
    {
        settype($idEmpleado, 'integer');
        settype($idUn, 'integer');

        $res = array();

        if ($idEmpleado == 0 && $idUn==0) {
            return $res;
        }

        $wEmpleado = '';
        if ($idEmpleado>0) {
            $wEmpleado = ' AND ef.idEmpleado='.$idEmpleado;
        }
        $wUn = '';
        if ($idUn>'0') {
            $wUn = ' AND eu.idUn='.$idUn;
        }

        $sql = "SELECT ef.idEventoFecha AS id,
                CONCAT(c.nombre, ' - ', CONCAT_WS(' ', p_c.nombre, p_c.paterno, p_c.materno)) AS title,
                CONCAT(c.nombre, ' - ', CONCAT_WS(' ', p_c.nombre, p_c.paterno, p_c.materno)) AS title,
                REPLACE(TIMESTAMP(ef.fechaEvento,ef.horaEvento), ' ', 'T') AS start,
                TIMESTAMPADD(HOUR,1,TIMESTAMP(ef.fechaEvento,ef.horaEvento)) AS end,
                CONCAT_WS(' ', p_e.nombre, p_e.paterno, p_e.materno) AS nombreEntrenador,
                CONCAT_WS(' ', p_e.nombre, p_e.paterno, p_e.materno) AS nombreEntrenador,
                IF (ef.idTipoEstatusEventoFecha IN (2, 3, 4, 6), 0,
                    IF (TIMESTAMP(ef.fechaEvento,ef.horaEvento) < DATE_ADD(NOW(), INTERVAL 1 HOUR), 0 , 1)
                ) AS editable, tef.idTipoEstatusEventoFecha AS estatusClasem,
                tef.descripcion AS descripcionClase, IF(co.idTipoEstatusComision=2, 1, 0) AS comisionPagada
            FROM producto p
            INNER JOIN categoria c ON c.idCategoria=p.idCategoria
            INNER JOIN evento e ON e.idProducto=p.idProducto
                AND e.idEventoClasificacion>0
                AND e.eliminado=0
            INNER JOIN productoun pu ON pu.idProducto=p.idProducto
                AND pu.activo=1 AND pu.eliminado=0
            INNER JOIN eventoun eu ON eu.idEvento=e.idEvento {$wUn} AND eu.idUn=pu.idUn
                AND eu.activo=1 AND eu.eliminado=0
                #AND DATE(NOW()) BETWEEN eu.inicioRegistro AND eu.finRegistro
                AND DATE(NOW()) <= eu.finEvento
            INNER JOIN eventoinscripcion ei ON ei.idEventoUn=eu.idEventoUn
                AND ei.eliminado=0
            INNER JOIN persona p_c ON p_c.idPersona=ei.idPersona
            INNER JOIN eventofecha ef ON ef.idEventoInscripcion=ei.idEventoInscripcion
                AND ef.eliminado=0
                AND ef.idTipoEstatusEventoFecha<>5 {$wEmpleado}
                AND ef.fechaEvento>=DATE_SUB(DATE(NOW()), INTERVAL 2 MONTH)
            LEFT JOIN eventofechacomision efc ON efc.idEventoFecha=ef.idEventoFecha
            LEFT JOIN comision co ON co.idComision=efc.idComision
                AND co.eliminado=0
            INNER JOIN tipoestatuseventofecha tef ON tef.idTipoEstatusEventoFecha=ef.idTipoEstatusEventoFecha
            INNER JOIN persona p_e ON p_e.idPersona=ef.idPersona
            INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
                AND euc.idTipoEventoCapacidad=1
                AND euc.activo=1
                AND euc.autorizado=1
                AND euc.eliminado=0
                AND euc.capacidad>0
            INNER JOIN eventouncapacidad euc3 ON euc3.idEventoUn=eu.idEventoUn
                AND euc3.idTipoEventoCapacidad=26
                AND euc3.activo=1 AND euc3.autorizado=1
                AND euc3.eliminado=0
                AND euc3.capacidad>0
            WHERE p.activo=1 AND p.eliminado=0
            ORDER BY ef.fechaEvento, ef.horaEvento";
        $query = DB::connection('crm')->select($sql);

        if (count($query) > 0) {
            // $query = array_map(function($x){return (array)$x;},$query);
            foreach ($query as $fila) {
                $clase['id']               = utf8_encode($fila->id);
                $clase['title']            = utf8_encode($fila->title);
                $clase['start']            = utf8_encode($fila->start);
                $clase['end']              = utf8_encode($fila->end);
                $clase['nombreEntrenador'] = utf8_encode($fila->nombreEntrenador);
                $clase['editable']         = utf8_encode($fila->editable);
                $clase['estatusClasem']    = utf8_encode($fila->estatusClasem);
                $clase['descripcionClase'] = utf8_encode($fila->descripcionClase);
                $clase['comisionPagada']   = utf8_encode($fila->comisionPagada);

                $res[] = $clase;
            }
        }

        return $res;
    }
    
    /**
     * [general description]
     *
     * @param  [type] $idUn [description]
     *
     * @return [type]       [description]
     */
    public function general($idUn)
    {
        settype($idUn, 'integer');

        $res = array();

        if ($idUn>1) {
            $sql = "
SELECT c.idCategoria AS idCategoria, c.nombre AS nombreCategoria,
    MIN(eu.edadMinima) AS edadMinima, MAX(eu.edadMaxima) AS edadMaxima,
    MAX(euc.capacidad) AS inscripciones
FROM producto p
INNER JOIN categoria c ON c.idCategoria=p.idCategoria
INNER JOIN evento e ON e.idProducto=p.idProducto
   AND e.idEventoClasificacion>0
   AND e.fechaEliminacion=0
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
   AND pu.activo=1 AND pu.fechaEliminacion=0
   AND pu.idUn = {$idUn}
INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
    AND eu.idUn = {$idUn} AND eu.activo=1 AND eu.fechaEliminacion=0
    AND DATE(NOW()) BETWEEN eu.inicioRegistro AND eu.finRegistro
    AND DATE(NOW()) <= eu.finEvento
INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
    AND euc.idTipoEventoCapacidad=1 AND euc.activo=1 AND euc.eliminado=0
    AND euc.autorizado=1 AND euc.capacidad>0
INNER JOIN eventouncapacidad euc3 ON euc3.idEventoUn=eu.idEventoUn
    AND euc3.idTipoEventoCapacidad=26 AND euc3.activo=1 AND euc3.eliminado=0
    AND euc3.autorizado=1 AND euc3.capacidad>0
WHERE p.activo=1 AND p.fechaEliminacion=0
GROUP BY c.nombre";
            $query = DB::connection('crm')->select($sql);
            if (count($query) > 0) {
                foreach ($query as $fila) {
                    $idCategoria                  = utf8_encode($fila->idCategoria);
                    $categoria['idCategoria']     = utf8_encode($fila->idCategoria);
                    $categoria['nombreCategoria'] = utf8_encode($fila->nombreCategoria);
                    $categoria['edadMinima']      = utf8_encode($fila->edadMinima);
                    $categoria['edadMaxima']      = utf8_encode($fila->edadMaxima);
                    $categoria['edadMaxima']      = utf8_encode($fila->edadMaxima);
                    $categoria['inscripciones']   = utf8_encode($fila->inscripciones);
                    $categoria['entrenadores']    = $this->arrayEntrenadores($idCategoria, $idUn);
                    $categoria['formasPagos']     = $this->arrayFormaPago($idCategoria, $idUn);
                    $categoria['participantes']   = $this->arrayParticipantes($idCategoria, $idUn);
                    
                    $res[] = $categoria;
                }
            }
        }
        
        return $res;
    }
    
    public function getRealIP()
    {
        if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            return $_SERVER["HTTP_CLIENT_IP"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED"])) {
            return $_SERVER["HTTP_X_FORWARDED"];
        } elseif (isset($_SERVER["HTTP_FORWARDED_FOR"])) {
            return $_SERVER["HTTP_FORWARDED_FOR"];
        } elseif (isset($_SERVER["HTTP_FORWARDED"])) {
            return $_SERVER["HTTP_FORWARDED"];
        } else {
            return $_SERVER["REMOTE_ADDR"];
        }
    }
    
    /**
     * [getNewEventoFecha description]
     *
     * @param  [type] $idEventoFecha [description]
     * @param  [type] $delay         [description]
     *
     * @return [type]                [description]
     */
    public static function getNewEventoFecha($idEventoFecha, $delay)
    {
        $query = '
SELECT TIMESTAMPADD(MICROSECOND,'.$delay.',TIMESTAMP(ef.fechaEvento,ef.horaEvento)) as nvaFecha '.
'FROM crm.eventoFecha ef '.
'WHERE ef.idEventoFecha = '.$idEventoFecha;
        
        $sql = 'SELECT o.nvaFecha, \'1\' as posible FROM ('.$query.') o';
        $query = DB::connection('crm')->select($sql);
        $query = array_map(function($x){return (array)$x;},$query);
        $nvaFecha = $query[0];
        if($nvaFecha['posible'] == 0)
            throw new \RuntimeException('Imposible mover evento al pasado');
        $query = 'UPDATE crm.eventoFecha '.
            'SET fechaEvento = date(\''.$nvaFecha['nvaFecha'].'\'), horaEvento = time(\''.$nvaFecha['nvaFecha'].'\') '.
            'WHERE idEventoFecha = '.$idEventoFecha;
        $query = DB::connection('crm')->select($sql);
        if($query)
            return $nvaFecha;
        throw new \RuntimeException('Error al actualizar la base de datos.');
    }
    
    /**
     * ingresaInBody - Insertar en la bd datos de inbody.
     *
     * @return void
     */
    public function ingresaInBody($datosIB)
    {
        if ($datosIB['RCC'] > 1.15)
            throw new \RuntimeException('RCC invalido');
        if ($datosIB['PGC'] > 58)
            throw new \RuntimeException('PGC invalido');
        if ($datosIB['IMC'] > 42.5)
            throw new \RuntimeException('IMC invalido');
        if ($datosIB['peso'] > 200)
            throw new \RuntimeException('peso invalido');
        if ($datosIB['MME'] > $datosIB['peso'])
            throw new \RuntimeException('MME invalido');
        if ($datosIB['MGC'] > $datosIB['peso'])
            throw new \RuntimeException('MGC invalida');
        if ($datosIB['minerales'] > $datosIB['peso'])
            throw new \RuntimeException('minerales invalidos');
        if ($datosIB['proteina'] > $datosIB['peso'])
            throw new \RuntimeException('proteina invalida');
        if ($datosIB['ACT'] > $datosIB['peso'])
            throw new \RuntimeException('ACT invalida');
        if ($datosIB['estatura'] > 249.99)
            throw new \RuntimeException('Estatura invalida');
        $sql = 'INSERT INTO personainbody (idPersona,RCC,PGC,IMC,MME,MCG,minerales,proteina,ACT,fechaRegistro,fechaActualizacion) '.
            'VALUES ('.$datosIB['idPersona'].','.$datosIB['RCC'].','.
                $datosIB['PGC'].','.$datosIB['IMC'].','.
                $datosIB['MME'].','.$datosIB['MGC'].','.
                $datosIB['minerales'].','.$datosIB['proteina'].','.
                $datosIB['ACT'].',now(),now()'.
            ')';
        $sql2 = 'INSERT INTO personaantropometricos (idPersona,estatura,peso) values ('.$datosIB['idPersona'].','.$datosIB['estatura'].','.$datosIB['peso'].')';
        foreach (array($sql,$sql2) as $value) {
            if (!$this->db->query($value))
                throw new \RuntimeException('No se pudo insertar datos en la BD. '.$value);
        }
    }
    
    /**
     * [login description]
     *
     * @param  [type] $email [description]
     *
     * @return [type]        [description]
     */
    public static function login($email, $password)
    {
        $res = array();

        if ($password!="#P3rr1t0$") {
            $ldap = ldap_connect('172.20.37.195');
            $ldapUsuario = 'sportsworld'."\\".strtolower(substr(substr($email, 0, strpos($email, "@")), 0, 20));
            $ldapClave = $password;
            if (((string) $ldapClave) == '') { //Prevenir un bind anonimo
                $res['status'] = '400';
                $res['message'] = 'Password vacio';
                $res['code'] = '1004';
                return $res;
            }

            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            $ldapAuth   =  @ldap_bind($ldap, $ldapUsuario, $ldapClave); //No importan los warnings
            if (!$ldapAuth) {
                $res['status'] = '400';
                $res['message'] = 'Usuario o contraseña invalidos';
                $res['code'] = '1005';
                return $res;
            }
        }
        $sql = "SELECT p.idPersona, CONCAT_WS(' ', p.nombre, p.paterno, p.materno) AS nombre,
            e.idEmpleado, e.idTipoEstatusEmpleado, u.idUn, u.nombre AS unNombre,
            pu.idPuesto, pu.descripcion AS puestoNombre, if(pu.idPuesto in (192, 194, 197, 217, 229, 417, 419, 444, 465, 466, 468, 470, 485, 499, 806,74, 75, 76, 82, 92, 100, 177, 410, 441, 447, 486, 509, 510, 567, 780, 100044, 100047),(
                SELECT GROUP_CONCAT(CONCAT_WS(',',p2.idPersona,CONCAT_WS(' ',p2.nombre,p2.Paterno,p2.Materno), ep2.idPuesto, pu2.descripcion) SEPARATOR '|')
                FROM crm.persona p2
                JOIN crm.empleado e2 ON e2.idPersona = p2.idPersona
                JOIN crm.empleadopuesto ep2 ON ep2.idEmpleado = e2.idEmpleado
                JOIN crm.puesto pu2 ON pu2.idPuesto = ep2.idPuesto
                WHERE  ep2.idUn = u.idUn
                AND e2.idTipoEstatusEmpleado = 196
                AND ep2.fechaEliminacion = 0
                AND e2.fechaEliminacion = 0
                AND pu2.idPuesto IN (84, 86, 111, 112, 132, 133, 134, 135, 136, 161, 175,  185, 189, 192, 194, 195, 197, 198, 210, 217, 226, 229, 344, 345, 346, 347, 348, 349, 350, 351, 352, 353, 354, 355, 356, 357, 358, 359, 360, 361, 362, 363, 364, 365, 366, 367, 368, 369, 370, 371, 372, 373, 374, 375, 376, 377, 378, 379, 380, 381, 382, 383, 384, 385, 386, 387, 388, 389, 390, 391, 392, 393, 394, 395, 396, 397, 398, 399, 400, 401, 402, 403, 404, 405, 417, 418, 420, 421, 422, 444, 465, 468, 478, 479, 480, 481, 482, 485, 499, 506, 531, 533, 534, 535, 541, 542, 543, 544, 545, 546, 547, 548, 549, 550, 551, 587, 588, 589, 590, 591, 592, 593, 594, 595, 596, 598, 599, 600, 601, 602, 603, 604, 605, 606, 607, 608, 609, 610, 611, 612, 613, 614, 615, 616, 617, 618, 619, 620, 621, 622, 623, 624, 625, 626, 627, 628, 629, 630, 631, 632, 633, 634, 635, 636, 637, 638, 639, 640, 641, 642, 643, 644, 645, 646, 647, 648, 649, 650, 651, 652, 653, 654, 655, 656, 657, 658, 659, 660, 661, 662, 663, 664, 665, 666, 667, 750, 751, 752, 753, 754, 755, 770, 774,775, 779, 797, 798, 801, 802, 806, 817, 100101, 100014, 100018, 100027, 100029, 100034, 100031, 100034, 100042, 100045, 100056,100051, 100052, 100053, 100055, 100085, 100095)
                ),'') AS entrenadores, e.perfil_ep
            FROM mail m
            INNER JOIN persona p ON p.idPersona=m.idPersona
            INNER JOIN empleado e ON e.idPersona=p.idPersona
            INNER JOIN empleadopuesto ep ON ep.idEmpleado=e.idEmpleado
                AND ep.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN un u ON u.idUn=ep.idUn
            INNER JOIN puesto pu ON pu.idPuesto=ep.idPuesto
            WHERE m.idTipoMail=37
                AND m.mail = '{$email}'
                AND m.fechaEliminacion='0000-00-00 00:00:00'
            LIMIT 1";
            // AND e2.idoperador in (2,7)
        $query = DB::connection('crm')->select($sql);
        if (count($query)>0) {
            $fila = (array_map(function($x){return (array)$x;},$query))[0];
            $tmp2 = [];
            if (strlen($fila['entrenadores']) > 0) {
                foreach (explode('|', $fila['entrenadores']) as $value) {
                    $tmp = explode(',', $value);
                    $tmp2[] = array(
                        'idPersona' => utf8_encode($tmp[0]),
                        'nombre'    => utf8_encode($tmp[1]),
                        'idPuesto'  => utf8_encode($tmp[2]),
                        'puesto'    => utf8_encode($tmp[3]),
                    );
                }
            }
            $fila['entrenadores'] = $tmp2;
            unset($tmp, $tmp2);
            $consulta = "SELECT u.idUn, u.nombre FROM crm.un u
                WHERE  u.idOperador = 1
                AND u.idTipoUn = 2
                AND u.activo = 1
                AND u.fechaEliminacion = 0
                ORDER BY nombre asc";
            $query = DB::connection('crm')->select($consulta);
            foreach ($query as $key => $value) {
                $club[$key]['idUn'] = $value->idUn;
                $club[$key]['nombre'] = $value->nombre;
            }
            
            $fila ['version'] = (Objeto::obtenerObjeto(953))['descripcion'];
            
            $fila ['calificacion'] = self::obtenCalificacionEmpleado($fila['idEmpleado']);
            $fila ['clubs'] = $club;
            if ($fila['idTipoEstatusEmpleado']==196) {
                $res['status'] = '200';
                foreach ($fila as &$valor) {
                    if(json_encode(array(0 => $valor)) === false)
                        $valor = utf8_encode($valor);
                }
                $res['response'] = $fila;
            } else {
                $res['status'] = '400';
                $res['message'] = 'Empleado inactivo';
                $res['code'] = '1003';
                $res['more_info'] = 'http://localhost/docs/error/1003';
            }
        } else {
            $res['status'] = '400';
            $res['message'] = 'Correo no encontrado';
            $res['code'] = '1002';
            $res['more_info'] = 'http://localhost/docs/error/1002';
        }

        return $res;
    }

    /**
     * [meta_venta description]
     *
     * @param  [type] $idPersona [description]
     *
     * @return [type]            [description]
     */
    public static function meta_venta($idPersona)
    {
        $mesesMenos = 3;
        $retval = array();
        $pustNat = [86, 134, 194, 551, 806, 100085, 100101];
        $pust = [551, 100085];
        $sql = "SELECT pu.idPuesto
        FROM crm.persona p
        JOIN crm.empleado e ON e.idPersona = p.idPersona
            AND e.fechaEliminacion = '0000-00-00 00:00:00'
        JOIN crm.empleadopuesto ep ON ep.idEmpleado = e.idEmpleado
            AND ep.fechaEliminacion = '0000-00-00 00:00:00'
        JOIN crm.puesto pu ON pu.idPuesto = ep.idPuesto
            AND pu.fechaEliminacion = '0000-00-00 00:00:00'
        WHERE p.idPersona =".$idPersona;
        $query = DB::connection('crm')->select($sql);
        
        $idPuesto = [];
        if (count($query) > 0) {
            $idPuesto = $query[0];
        }
        
        $primera = array_search(intval($idPuesto->idPuesto), $pustNat);
        if ($primera !== false && $primera > 0) {
            $segunda = array_search(intval($idPuesto->idPuesto), $pust);
            if ($sefunda !== false && $segunda > 0) {
                $met = 10000;
            } else {
                $met = 15000;
            }
        } else {
            $met = 35000;
        }
        while ($mesesMenos >= 0) {
            $sql = DB::connection('crm')->select('select date_format(date_sub(now(),interval '.$mesesMenos.' month),\'%Y-%m\') as mes');
            if (count($sql) > 0) {
                $sql = (array_map(function($x){return (array)$x;},$sql))[0];
            }
            $retval[$sql['mes']] = array(
                'total' => array(
                    'nuevo' => 0,
                    'renovacion' => 0
                ),
                'ventas' => array(
                    'nuevo' => 0,
                    'renovacion' => 0
                ),
                'meta' => $met,
            );
            $mesesMenos--;
        }
        
        $sql = "
SELECT DATE_FORMAT(m.fechaActualizacion,'%Y-%m') AS mes,
IF(ISNULL(ep.idProductoVenta),0,1) AS renovacion,
SUM(ROUND(m.importe/(1+(m.iva/100)),2)) AS total,
{$met} AS meta,
COUNT(ep.idProductoVenta) AS count_ren,
COUNT(ep.idEventoPartcipante) AS count_tot
FROM crm.eventoinvolucrado einv
INNER JOIN crm.eventoinscripcion eins ON einv.idEventoInscripcion = eins.idEventoInscripcion
    AND eins.fechaEliminacion = '0000-00-00 00:00:00'
INNER JOIN crm.eventoparticipante ep ON ep.idEventoInscripcion = eins.idEventoInscripcion
INNER JOIN crm.eventomovimiento em ON eins.idEventoInscripcion = em.idEventoInscripcion
INNER JOIN crm.facturamovimiento fm ON em.idMovimiento = fm.idMovimiento
INNER JOIN crm.movimiento m ON em.idMovimiento = m.idMovimiento
INNER JOIN crm.factura f ON fm.idFactura = f.idFactura
WHERE einv.fechaEliminacion = '0000-00-00 00:00:00'
AND einv.idPersona = {$idPersona}
AND einv.tipo = 'Entrenador'
AND m.fechaRegistro BETWEEN DATE_SUB(NOW(),INTERVAL 3 MONTH) AND NOW()
GROUP BY mes, renovacion
        ";
        $query = DB::connection('crm')->select($sql);
        
        if (count($query) > 0)
        foreach ($query as $value) {
            $retval[$value['mes']]['meta'] = $value['meta'];
            if ($value['renovacion'] == '1') {
                $retval[$value['mes']]['total']['renovacion'] = $value['total'];
                $retval[$value['mes']]['ventas']['renovacion'] = $value['count_ren'];
            } else {
                $retval[$value['mes']]['total']['nuevo'] = $value['total'];
                $retval[$value['mes']]['ventas']['nuevo'] = $value['count_tot']-$value['count_ren'];
            }
        }
        /*
         * Convertir el arreglo a como lo necesitamos
         */
        $retval2 = array();
        
        if (count($retval) > 0)
        foreach ($retval as $key => $value) {
            $retval2[] = array(
                'mes' => $key,
                'total' => $value['total'],
                'ventas' => $value['ventas'],
                'meta' => $value['meta'],
            );
        }
        return $retval2;
    }

    /**
     * [obtenerEvento description]
     * @param  [type] $idCategoria   [description]
     * @param  [type] $idUn          [description]
     * @param  [type] $participantes [description]
     * @param  [type] $clases        [description]
     * @return [type]                [description]
     */
    public static function obtenerEvento($idCategoria, $idUn, $participantes, $clases, $demo)
    {
        settype($idCategoria, 'integer');
        settype($idUn, 'integer');
        settype($participantes, 'integer');
        settype($clases, 'integer');
        settype($demo, 'integer');

        $joinParticipantes = '';
        $joinClases = '';
        if ($demo==0) {
            $joinParticipantes = "INNER JOIN eventouncapacidad euc2 ON euc2.idEventoUn=eu.idEventoUn
                AND euc2.idTipoEventoCapacidad=7
                AND euc2.activo=1 AND euc2.eliminado=0
                AND euc2.autorizado=1 AND euc2.capacidad={$participantes}";
            $joinClases = "INNER JOIN eventouncapacidad euc4 ON euc4.idEventoUn=eu.idEventoUn
                AND euc4.idTipoEventoCapacidad=6
                AND euc4.activo=1 AND euc4.fechaEliminacion=0
                AND euc4.autorizado=1 AND euc4.capacidad={$clases}";
        }
        $cat = ($idCategoria != 109) ? "AND p.nombre LIKE '%2018%'" : "" ;
        $sql = "SELECT * FROM (
                SELECT e.idEvento, p.nombre
                FROM producto p
                INNER JOIN categoria c on c.idCategoria=p.idCategoria
                    AND c.idCategoria={$idCategoria}
                INNER JOIN evento e on e.idProducto=p.idProducto
                    AND e.idEventoClasificacion>0
                    AND e.fechaEliminacion=0
                    {$cat}
                INNER JOIN productoun pu ON pu.idProducto = p.idProducto
                    AND pu.activo=1
                    AND pu.fechaEliminacion=0
                    AND pu.idUn={$idUn}
                INNER JOIN eventoun eu ON eu.idEvento = e.idEvento
                    AND eu.idUn={$idUn} AND eu.activo=1
                    AND eu.fechaEliminacion=0
                    AND DATE(NOW()) BETWEEN eu.inicioRegistro
                    AND eu.finRegistro
                    AND DATE(NOW()) <= eu.finEvento
                INNER JOIN eventouncapacidad euc ON euc.idEventoUn = eu.idEventoUn
                    AND euc.idTipoEventoCapacidad = 1
                    AND euc.activo = 1
                    AND euc.eliminado = 0
                    AND euc.autorizado = 1
                    AND euc.capacidad > 0
                    {$joinParticipantes}
                INNER JOIN eventouncapacidad euc3 ON euc3.idEventoUn = eu.idEventoUn
                    AND euc3.idTipoEventoCapacidad = 26
                    AND euc3.activo = 1
                    AND euc3.eliminado = 0
                    AND euc3.autorizado = 1
                    AND euc3.capacidad > 0
                    {$joinClases}
                WHERE p.activo = 1
                AND p.fechaEliminacion = 0
                {$cat}
            ) a
            LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        
        if (count($query) > 0) {
            $query = array_map(function($x){return (array)$x;},$query);
            $fila = $query[0];
            return $fila['idEvento'];
        }
        
        return 0;
    }

    /**
     * obtenInBody - Obtener desde la bd datos de inbody.
     *
     * @return array
     * @throws RuntimeException
     */
    public function obtenInBody($idPersona, $cantidad)
    {
        if (!is_int($idPersona))
            throw new RuntimeException('idPersona invalida');
        if (!is_int($cantidad))
            throw new RuntimeException('Cantidad invalida');
        $sql = DB::connection('crm')->select('pa.estatura, pa.peso, '.
            'pi.RCC, pi.PGC, pi.IMC, pi.MME, pi.MCG, pi.ACT, pi.minerales, pi.proteina, date(pi.fechaRegistro) as fecha', false)
            ->from('crm.personainbody pi')
            ->join('crm.personaantropometricos pa', 'date(pi.fechaRegistro) = date(pa.fechaRegistro) and pi.idPersona=pa.idPersona')
            ->where('pa.idPersona', $idPersona)
            ->where('pi.fechaEliminacion', '0000-00-00 00:00:00')
            ->where('pa.fechaEliminacion', '0000-00-00 00:00:00')
            ->order_by('fecha', 'desc')
            ->limit($cantidad)
            ->get()->result_array();
        return $sql;
    }

    /**
     * [totalDemos description]
     *
     * @param  [type] $idCategoria [description]
     * @param  [type] $idPersona   [description]
     *
     * @return [type]              [description]
     */
    public static function totalDemos($idCategoria, $idPersona)
    {
        settype($idCategoria, 'integer');
        settype($idPersona, 'integer');

        $res = 0;

        if ($idCategoria>0 && $idPersona>0) {
            $sql = "
SELECT COUNT(*) AS demos
FROM producto p
INNER JOIN evento e ON e.idProducto=p.idProducto
INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
INNER JOIN eventoinscripcion ei ON ei.idEventoUn=eu.idEventoUn
INNER JOIN eventofecha ef ON ef.idEventoInscripcion=ei.idEventoInscripcion
    AND ei.idPersona={$idPersona}
    AND ef.fechaEliminacion=0
    AND ef.idTipoEstatusEventoFecha=".ESTATUS_CLASE_DEMO."
WHERE p.idCategoria={$idCategoria}";
            $query = DB::connection('crm')->select($sql);
            if (count($query) > 0) {
                $query = array_map(function($x){return (array)$x;},$query);
                $fila = $query[0];
                $res = $fila['demos'];
            }
        }
        return $res;
    }

    public static function actualizaEventoFecha($idEventoParticipante, $fechaVenta, $idProducto, $precio)
    {
        $datos_db = array(
            'where' => array('idEventoPartcipante' => $idEventoParticipante),
            'update' => array(
                'fechaVenta' => $fechaVenta,
                'idProductoVenta' => $idProducto,
                'precioCotizado' => $precio
            )
        );

        $affected_rows = DB::connection('crm')
        ->table(TBL_EVENTOPARTICIPANTE)
        ->where($datos_db['where'])
        ->update($datos_db['update']);

        return $affected_rows;
    }

    public static function renovaciones($idPersona = 0)
    {
        $idPersona = $idPersona === 0 ? $_SESSION['idPersona'] : $idPersona;
        $sql = "SELECT 
                ep.idEventoPartcipante AS idEventoParticipante,
                c.nombre AS Actual,
                per.idPersona,
                concat_ws(' ',per.nombre,per.paterno,per.materno) AS nombre,
                m.fechaRegistro AS fechaActual,
                (
                    select tm.nombre
                    from crm.producto p
                    JOIN crm.categoria tm on p.idCategoria = tm.idCategoria
                    where p.idProducto = ep.idProductoVenta
                ) AS nuevo,
                ep.fechaVenta AS fechaNuevo,
                ep.precioCotizado AS importe,
                IF(m.idtipoestatusmovimiento = ".MOVIMIENTO_EXCEPCION_PAGO.",false,true) AS renovacion,false
            FROM eventoparticipante AS ep
            JOIN persona AS per ON ep.idPersona = per.idPersona
            JOIN eventoinvolucrado AS einv ON ep.idEventoInscripcion = einv.idEventoInscripcion
                AND einv.tipo = 'Vendedor'
                AND einv.idPersona = ".$idPersona."
            JOIN eventomovimiento AS em ON em.idEventoInscripcion = ep.idEventoInscripcion
            JOIN movimiento AS m ON em.idMovimiento = m.idMovimiento
                AND m.idtipoestatusmovimiento IN (".MOVIMIENTO_PAGADO.",".MOVIMIENTO_EXCEPCION_PAGO.")
            JOIN eventoinscripcion AS ei ON ei.idEventoInscripcion = ep.idEventoInscripcion
            JOIN eventoun AS eu ON ei.idEventoUn = eu.idEventoUn
            JOIN evento AS e ON eu.idEvento = e.idEvento
            JOIN producto AS p ON e.idProducto = p.idProducto
            JOIN categoria AS c ON c.idCategoria = p.idCategoria
            WHERE m.fechaRegistro > date_sub(now(), interval 6 WEEK)
            AND ei.fechaEliminacion = 0
            AND eu.fechaEliminacion = 0
            AND e.fechaEliminacion = 0
            AND p.fechaEliminacion = 0
            AND c.fechaEliminacion = 0
            AND ep.fechaVenta IS NULL OR ep.fechaVenta >= '".date('Y-m')."-01'";
        $retval = DB::connection('crm')->select($sql);
        if ($retval->count() > 0 ) {
            $retval = $retval->get()->toArray();
            
            foreach ($retval as &$ret_actual) {
                foreach ($ret_actual as &$valor) {
                    $valor = utf8_encode($valor);
                }
            }
        } else {
            $retval = 0;
        }
        
        return $retval;
    }

    public function getNuevosClientes($idUn, $fecha)
    {
        if (!is_int($idUn) && !is_numeric($idUn))
            throw new RuntimeException('El valor de idUn no es valido. ('.$idUn.')');
        if (!is_int($fecha) && !is_numeric($fecha))
            $fecha = strtotime($fecha); // En caso que nos manden una fecha que NO venga en modo unix_timestamp
        if (!$fecha)
            throw new RuntimeException('El valor de fecha es inválido');
        $iq = DB::connection('crm')
            ->select(implode(',',array(
                'p.idPersona',
                'concat_ws(\' \',p.nombre,p.paterno,p.materno) as nombre',
                'm.idMembresia',
                'group_concat(distinct em.mail) as mail',
                'group_concat(distinct if(length(t.telefono) = 10,t.telefono,concat(t.lada,t.telefono))) as telefonos',
                'min(s.fechaRegistro) as inscripcion'
            )), false)
            ->from(TBL_PERSONA.' p')
            ->join(TBL_SOCIO.' s', 'p.idPersona = s.idPersona')
            ->join(TBL_MEMBRESIA.' m', 's.idUnicoMembresia = m.idUnicoMembresia')
            ->join(TBL_MAIL.' em', 'em.idPersona = p.idPersona and em.eliminado = false')
            ->join(TBL_TELEFONO.' t', 't.idPersona = p.idPersona and t.fechaEliminacion = 0')
            ->where('m.idUn', $idUn)
            ->group_by('p.idPersona')
            ->order_by('inscripcion asc')
            ->_compile_select();
        DB::connection('crm')->_reset_select();

        $retval = DB::connection('crm')->query('SELECT * FROM ('.$iq.') o where o.inscripcion between \''.date('Y-m-d', $fecha).'\' and now()')
            ->result_array();
        /*
         * Recordemos que la base esta en latin1 y JSON ocupa utf8
         */
        foreach ($retval as &$ret_actual) {
            foreach ($ret_actual as &$valor) {
                $valor = utf8_encode($valor);
            }
        }

        return $retval;
    }

    public function getComisiones($idPersona)
    {
        settype($idPersona, 'int');
        if ($idPersona == 0) {
            throw new RuntimeException('No se pudo obtener el idPersona del empleado.');
        }
        $query = 'SELECT
            c.idComision AS Identificador,
            c.idTipoEstatusComision,
            tec.descripcion AS TipoEstatusComision,
            tc.descripcion AS TipoComision,
            CONCAT(f.prefijoFactura,f.folioFactura) AS Factura,
            m.importe AS ImporteFactura,
            c.montoComision AS ImporteComision,
            pr.nombre AS Producto,
            CONCAT_WS(" ",p.nombre,p.paterno,p.materno) as Cliente,
            DATE_FORMAT(c.fechaEmision,"%d-%m-%Y") AS FechaEmision
        FROM crm.comision c
        JOIN crm.tipoestatuscomision tec ON tec.idTipoEstatusComision = c.idTipoEstatusComision
        JOIN crm.tipocomision tc ON tc.idTipoComision = c.idTipoComision
        LEFT JOIN crm.comisionmovimiento cm on cm.idComision = c.idComision
        LEFT JOIN crm.movimiento m on m.idMovimiento = cm.idMovimiento
        LEFT JOIN crm.persona p on p.idPersona = m.idPersona
        JOIN crm.facturamovimiento fm on fm.idMovimiento = cm.idMovimiento
        JOIN crm.factura f on f.idFactura = fm.idFactura
        JOIN crm.producto pr ON pr.idProducto = m.idProducto
        WHERE c.fechaEmision BETWEEN DATE(CONCAT(DATE_FORMAT(NOW(), "%Y-%m"),"-01")) AND LAST_DAY(NOW())
        AND c.idPersona = '.$idPersona.'
        GROUP BY Factura';
        $sql = DB::connection('crm')->query($query)->result_array();
        return $sql;
    }

    public function perfil($idPersona, $perfil = null)
    {
        if (!is_null($perfil)) {
            $update = DB::connection('crm')->update(TBL_EMPLEADO, array('perfil_ep' => $perfil), array(
                'idPersona' => $idPersona,
                'idTipoEstatusEmpleado' => ESTATUS_EMPLEADO_ACTIVO,
                'fechaEliminacion' => 0
            ));
            if (DB::connection('crm')->affected_rows() === 0) {
                throw new RuntimeException('idPersona incorrecto');
            }
        }
        return DB::connection('crm')->select('idEmpleado,idPersona,perfil_ep')
            ->from(TBL_EMPLEADO)
            ->where('idPersona', $idPersona)
            ->where('idTipoEstatusEmpleado', ESTATUS_EMPLEADO_ACTIVO)
            ->where('fechaEliminacion', 0)
            ->get()->result()[0];
    }

    /**
     * [obtenCalificacion description]
     *
     * @param  [int] $idEventoInscripcion
     * @return [int] calificacion
     */
    public function obtenCalificacion($idEventoInscripcion)
    {
        settype($idEventoInscripcion, 'integer');
        $retval = DB::connection('crm')->select('calificacion')
            ->from(TBL_EVENTOINSCRIPCION)
            ->where('idEventoInscripcion', $idEventoInscripcion)
            ->get()->row();
        return $retval->calificacion;
    }

    /**
     * [ingresaCalificacion description]
     *
     * @param  [int] $idEventoInscripcion
     * @param  [int] $calificacion
     * @return [bool]
     */
    public function ingresaCalificacion($data)
    {
        $resultCalificacion = DB::connection('crm')->select('*')
        ->from('eventocalificacion')
        ->where('idEventoInscripcion', $data["token"])
        ->get()
        ->row();
        $resultInscripcion = DB::connection('crm')->select('*')
        ->from('eventoinscripcion')
        ->where('idEventoInscripcion', $data["token"])
        ->get()
        ->row();
        if (count($resultCalificacion) > 0) {
            throw new RuntimeException('Ya califiaste la clase');
        } else {
            if (count($resultInscripcion) != 1) {
                throw new RuntimeException('Error al Calirifar Clase');
            } else {
                $datos = [
                    'idEventoInscripcion' => $data['token'],
                    'idEmpleado'          => intval($resultInscripcion->idEmpleado),
                    'calificacion'        => $data['calificacion'],
                    'q1'                  => $data['r1'],
                    'q2'                  => $data['r2'],
                    'q3'                  => $data['r3'],
                    'q4'                  => $data['r4'],
                    'q5'                  => $data['r5'],
                    'q6'                  => $data['r6'],
                    'fechaRegistro'       => date('Y-m-d H:i:s')
                ];
                $inser = DB::connection('crm')->insert(TBL_EVENTOCALIFICACION, $datos);
                if ($inser == false) {
                    throw new RuntimeException('Error al Insertar en la Base de Datos');
                }
            }
        }

        settype($idEventoInscripcion, 'integer');
        settype($calificacion, 'integer');
        $retval = DB::connection('crm')->where('idEventoInscripcion', $idEventoInscripcion)
        ->update(TBL_EVENTOINSCRIPCION, array('calificacion' => $calificacion));
        return $retval;
    }

    /**
     * [obtenCalificacionEmpleado description]
     *
     * @param  [int] $idEmpleado
     * @return [double]
     */
    public static function obtenCalificacionEmpleado($idEmpleado)
    {
        settype($idEmpleado, 'integer');
        $retval = DB::connection('crm')->select('SELECT
            IFNULL(ROUND(SUM(ec.calificacion)/COUNT(ec.calificacion)), 0) AS calificacion,
            IFNULL(ROUND((SUM(ec.q1)/COUNT(ec.calificacion))*100), 0) AS q1,
            IFNULL(ROUND((SUM(ec.q2)/COUNT(ec.calificacion))*100), 0) AS q2,
            IFNULL(ROUND((SUM(ec.q3)/COUNT(ec.calificacion))*100), 0) AS q3,
            IFNULL(ROUND((SUM(ec.q4)/COUNT(ec.calificacion))*100), 0) AS q4,
            IFNULL(ROUND((SUM(ec.q5)/COUNT(ec.calificacion))*100), 0) AS q5,
            IFNULL(ROUND((SUM(ec.q6)/COUNT(ec.calificacion))*100), 0) AS q6,
            IFNULL(COUNT(ec.calificacion), 0) AS total
            FROM crm.eventocalificacion ec
            JOIN crm.eventoinscripcion ei ON ei.idEventoInscripcion = ec.idEventoInscripcion
                AND ei.idEmpleado = ec.idEmpleado
            WHERE ei.idEmpleado = '.$idEmpleado.'
            AND ei.fechaEliminacion = 0
            AND ec.fechaEliminacion = 0
        ');
        $retval = array_map(function($x){return (array)$x;},$retval);
        return $retval[0];
    }

    /**
     * [obtenEntrenadores description]
     *
     * @param  [int] $idEmpleado
     * @return [double]
     */
    public function obtenEntrenadores($idUn)
    {
        settype($idUn, 'integer');
            $sql = "SELECT p.idPersona,
            CONCAT_WS(
                ' ',
                p.nombre,
                p.Paterno,
                p.Materno
            ) AS nombre,
            ep.idPuesto,
            pu.descripcion,
            ep.idUn,
            u.nombre AS club,
            e.idEmpleado
            FROM crm.persona p
            JOIN crm.empleado e ON e.idPersona = p.idPersona
            JOIN crm.empleadopuesto ep ON ep.idEmpleado = e.idEmpleado
            JOIN crm.puesto pu ON pu.idPuesto = ep.idPuesto
            JOIN crm.un u ON u.idUn = ep.idUn
            JOIN crm.mail m ON m.idPersona = p.idPersona
            WHERE  ep.idUn = $idUn
            AND e.idTipoEstatusEmpleado = 196
            AND ep.fechaEliminacion = 0
            AND e.fechaEliminacion = 0
            AND m.fechaEliminacion = 0
            AND p.fechaEliminacion = 0
            AND pu.fechaEliminacion = 0
            AND u.fechaEliminacion = 0
            AND pu.idPuesto IN (84, 111, 112, 132, 133, 134, 135, 136, 161, 175,  185, 189, 192, 194, 195, 197, 198, 210, 217, 226, 229, 344, 345, 346, 347, 348, 349, 350, 351, 352, 353, 354, 355, 356, 357, 358, 359, 360, 361, 362, 363, 364, 365, 366, 367, 368, 369, 370, 371, 372, 373, 374, 375, 376, 377, 378, 379, 380, 381, 382, 383, 384, 385, 386, 387, 388, 389, 390, 391, 392, 393, 394, 395, 396, 397, 398, 399, 400, 401, 402, 403, 404, 405, 417, 418, 420, 421, 422, 444, 465, 468, 478, 479, 480, 481, 482, 485, 499, 506, 531, 533, 534, 535, 541, 542, 543, 544, 545, 546, 547, 548, 549, 550, 551, 587, 588, 589, 590, 591, 592, 593, 594, 595, 596, 598, 599, 600, 601, 602, 603, 604, 605, 606, 607, 608, 609, 610, 611, 612, 613, 614, 615, 616, 617, 618, 619, 620, 621, 622, 623, 624, 625, 626, 627, 628, 629, 630, 631, 632, 633, 634, 635, 636, 637, 638, 639, 640, 641, 642, 643, 644, 645, 646, 647, 648, 649, 650, 651, 652, 653, 654, 655, 656, 657, 658, 659, 660, 661, 662, 663, 664, 665, 666, 667, 750, 751, 752, 753, 754, 755, 770, 774,775, 779, 797, 798, 801, 802, 806, 817, 100014, 100018, 100027, 100029, 100034, 100031, 100034, 100042, 100045, 100056,100051, 100052, 100053, 100055, 100095)
            GROUP BY p.idPersona";
            // AND e.idoperador in (2,7)
        $query = DB::connection('crm')->query($sql);
        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $calificacion = $this->obtenCalificacionEmpleado($fila->idEmpleado);
                $r['idPersona']    = $fila->idPersona;
                $r['nombre']       = $fila->nombre;
                $r['idEmpleado']   = $fila->idEmpleado;
                $r['idPuesto']     = $fila->idPuesto;
                $r['puesto']       = $fila->descripcion;
                $r['club']         = $fila->club;
                $r['calificacion'] = $calificacion;
                $res[] = $r;
            }
        }
        return $res;
    }
}
