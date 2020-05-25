<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Producto extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.producto';
    protected $primaryKey = 'idProducto';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    public static function getPaquetes($idUn)
    {
        $sql = "SELECT
                p.idProducto,
                p.idCategoria,
                p.nombre AS producto,
                c.nombre AS categoria,
                eu.idEventoUn,
                numClases.capacidad AS clases,
                numInsc.capacidad AS inscripciones,
                participantes.capacidad AS numParticipantes,
                pp.importe,
                tc.descripcion as tipoCliente,
                tc.idTipoCliente,
                ep.idEsquemaPago,
                ep.descripcion  as esquemaPago
                FROM producto p
                INNER JOIN categoria c ON c.idCategoria=p.idCategoria
                INNER JOIN evento e ON e.idProducto=p.idProducto
                    AND e.idEventoClasificacion>0
                    AND e.eliminado=0
                INNER JOIN productoun pu ON pu.idProducto=p.idProducto
                    AND pu.activo=1
                    AND pu.eliminado=0
                    AND pu.idUn={$idUn}
            INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
                AND pp.activo=1
                AND pp.fechaEliminacion=0
                -- AND pp.eliminado=0
                AND pp.idEsquemaPago NOT IN (7, 11)
                AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
            INNER JOIN tipocliente tc ON tc.idTipoCliente=pp.idTipoCliente
            INNER JOIN esquemapago ep ON ep.idEsquemaPago=pp.idEsquemaPago

            INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
            AND eu.idUn=pu.idUn
            AND eu.activo=1
            AND eu.eliminado=0
            AND DATE(NOW()) BETWEEN eu.inicioRegistro and eu.finRegistro
            AND DATE(NOW()) <= eu.finEvento
            INNER JOIN eventouncapacidad numClases ON numClases.idEventoUn=eu.idEventoUn
            AND numClases.idTipoEventoCapacidad=6
            AND numClases.activo=1
            AND numClases.eliminado=0
            AND numClases.autorizado=1
            AND numClases.capacidad>0

            INNER JOIN eventouncapacidad numInsc ON numInsc.idEventoUn=eu.idEventoUn
            AND numInsc.idTipoEventoCapacidad=1
            AND numInsc.activo=1
            AND numInsc.eliminado=0
            AND numInsc.autorizado=1
            AND numInsc.capacidad>0


            INNER JOIN eventouncapacidad appEps ON appEps.idEventoUn=eu.idEventoUn
            AND appEps.idTipoEventoCapacidad=26
            AND appEps.activo=1
            AND appEps.eliminado=0
            AND appEps.autorizado=1
            AND appEps.capacidad>0


            INNER JOIN eventouncapacidad participantes ON participantes.idEventoUn=eu.idEventoUn
            AND participantes.idTipoEventoCapacidad=7
            AND participantes.activo=1
            AND participantes.eliminado=0
            AND participantes.autorizado=1
            AND participantes.capacidad>0
            WHERE p.activo=1
            AND p.eliminado=0
            AND p.idProducto <> 4732
            ORDER BY p.nombre
        ";
        $resultado  = DB::connection('crm')->select($sql);
        $categorias = [];
        $paquetes   = [];
        $productos  = [];
        if (count($resultado) > 0) {
            $idsCategorias = implode(',', array_unique(array_column($resultado, 'idCategoria')));
            $idsProductos  = implode(',', array_unique(array_column($resultado, 'idProducto')));

            $sql = " SELECT p.idProducto, pmsi.numeroMeses, CONCAT(pmsi.numeroMeses, ' ', pmsi.descripcion) AS descripcion
            FROM producto p
            INNER JOIN categoria c ON c.idCategoria=p.idCategoria
                 AND c.idCategoria in ({$idsCategorias})
            INNER JOIN evento e ON e.idProducto=p.idProducto
                AND e.idEventoClasificacion>0
                AND e.eliminado=0
            INNER JOIN productoun pu ON pu.idProducto=p.idProducto
                AND pu.activo=1
                AND pu.eliminado=0
                AND pu.idUn={$idUn}
            INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
                AND eu.idUn=pu.idUn
                AND eu.activo=1
                AND eu.eliminado=0
                AND DATE(NOW()) BETWEEN eu.inicioRegistro AND eu.finRegistro
                AND DATE(NOW()) <= eu.finEvento
            INNER JOIN productomsi pm ON pm.idProducto=p.idProducto
                AND pu.idUn=pm.idUn
                AND pm.activo=1
            INNER JOIN periodomsi pmsi ON pmsi.idPeriodoMsi=pm.idPeriodoMsi
                AND pmsi.numeroMeses <> 1
            WHERE p.activo=1
            AND p.idProducto IN({$idsProductos})
            AND p.eliminado=0";
            $msi        = DB::connection('crm')->select($sql);
            $formasPago = [];
            if (count($msi) > 0) {
                $formasPago = [];
                foreach ($msi as $key => $value) {
                    $formasPago[$value->idProducto][$value->numeroMeses] = $value->descripcion;
                }
            }
            foreach ($resultado as $key => $value) {
                $categorias[$value->idCategoria]                    = $value->categoria;
                $productos[$value->idCategoria][$value->idProducto] = $value->producto;
                $tiposPagos                                         = [];
                $aux                                                = [];
                if (isset($formasPago[$value->idProducto])) {

                    $aux[] = ['id' => 1, 'value' => 'contado'];
                    foreach ($formasPago[$value->idProducto] as $keyPago => $tipoPago) {

                        $aux[] = ['id' => $keyPago, 'value' => $tipoPago];
                    }

                    $tiposPagos = $aux;
                } else {
                    $tiposPagos[] = ['id' => 1, 'value' => 'contado'];
                }

                $paquetes[$value->idCategoria][$value->idProducto][$value->tipoCliente][$value->esquemaPago] = [
                    'idCategoria'   => $value->idCategoria,
                    'categoria'     => $value->categoria,
                    'idProducto'    => $value->idProducto,
                    'producto'      => $value->producto,
                    'clases'        => $value->clases,
                    'participantes' => $value->numParticipantes,
                    'precio'        => $value->importe,
                    'tipoUsuario'   => $value->tipoCliente,
                    'esquemaPago'   => $value->esquemaPago,
                    'idTipoCliente' => $value->idTipoCliente,
                    'idEsquemaPago' => $value->idEsquemaPago,
                    'tiposPagos'    => $tiposPagos,
                ];

            }
            return [
                'productos'     => $productos,
                'categorias'    => $categorias,
                'paquetes'      => $paquetes,
                'idsCategorias' => $idsCategorias,
            ];
        } else {
            return false;
        }

    }
    public static function precio($idProducto, $idUn, $idTipoRolCliente = ROL_CLIENTE_NINGUNO, $idEsquemaPago = ESQUEMA_PAGO_CONTADO

    ) {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');

        $datos                      = array();
        $datos['monto']             = '0.00';
        $datos['id']                = 0;
        $datos['idCta']             = 0;
        $datos['idCtaProd']         = 0;
        $datos['cuenta']            = '';
        $datos['cuentaProducto']    = '';
        $datos['error']             = 0;
        $datos['mensaje']           = '';
        $datos['numCuenta']         = 0;
        $datos['numCuentaProducto'] = 0;
        $datos['activo']            = 0;
        $datos['query']             = array();
        //$fechaVigencia            = ($fechaVigencia == '') ? date('Y-m-d') : $fechaVigencia;

        if (!$idProducto or !$idUn) {
            $datos['error']   = 1;
            $datos['mensaje'] = 'Faltan datos para consulta';

            return $datos;
        }

        $sql = "SELECT pp.importe, pp.idProductoPrecio AS id, pp.idCuentaContable, CONCAT('(', cc.numCuenta, ') ', cc.descripcion) AS cuenta,cc.numCuenta, cp.idCuentaProducto, CONCAT('(', cp.cuentaProducto, ') ', cp.descripcion) AS cuentaProducto, IFNULL(cp.cuentaProducto, '') AS numCuentaProducto, pp.activo
            from productoprecio as pp
            JOIN productoUn as  pu ON pu.idProductoUn = pp.idProductoUn
                AND pu.idProductoGrupo = pp.idProductoGrupo
                AND pu.eliminado = 0
                AND pu.activo = 1
                AND pu.idProducto       = {$idProducto}
                AND pu.idUn             = {$idUn}
            JOIN un as u ON u.idUn = pu.idUn
                -- AND u.fechaEliminacion = '0000-00-00 00:00:00'
                AND u.eliminado = 0
                AND u.activo = 1
            JOIN cuentacontable AS cc ON pp.idCuentaContable = cc.idCuentaContable
            JOIN cuentaproducto AS cp ON pp.idCuentaProducto = cp.idCuentaProducto
            WHERE 1 = 1
            AND pp.eliminado        = 0
            AND pp.activo=1
            AND pp.idTipoRolCliente = {$idTipoRolCliente}
            AND pp.idEsquemaPago    = {$idEsquemaPago}
            AND pp.idTipoMembresia  = 0
            AND pp.unidades         = 1
            order by pp.idProductoPrecio DESC ";
        $query = DB::connection('crm')->select($sql);

        if (count($query) > 0) {
            $fila                       = $query[0];
            $datos['monto']             = number_format($fila->importe, 2, '.', '');
            $datos['id']                = $fila->id;
            $datos['idCta']             = $fila->idCuentaContable;
            $datos['idCtaProd']         = $fila->idCuentaProducto;
            $datos['cuenta']            = $fila->cuenta;
            $datos['cuentaProducto']    = $fila->cuentaProducto;
            $datos['numCuenta']         = $fila->numCuenta;
            $datos['numCuentaProducto'] = $fila->numCuentaProducto;
            $datos['activo']            = $fila->activo;
        }

        return $datos;
    }

    /**
     * [cveProducto description]
     *
     * @param  integer $idProducto Identificador de producto
     *
     * @return string
     */
    public static function cveProducto($idProducto)
    {
        settype($idProducto, 'integer');

        $cve   = '';
        $query = DB::connection('crm')->table(TBL_PRODUCTO)
            ->select('cveProductoServicio')
            ->where('idProducto', $idProducto)->get()->toArray();

        if (count($query) > 0) {
            $fila = $query[0];
            $cve  = $fila->cveProductoServicio;
        }
        return $cve;
    }

    /**
     * [cveUnidad description]
     *
     * @param  integer $idProducto Identificador de producto
     *
     * @return string
     */
    public static function cveUnidad($idProducto)
    {
        settype($idProducto, 'integer');

        $cve   = '';
        $query = DB::connection('crm')->table(TBL_PRODUCTO)
            ->select('cveUnidad')
            ->where('idProducto', $idProducto)->get()->toArray();
        if (count($query) > 0) {
            $fila = $query[0];
            $cve  = $fila->cveUnidad;
        }
        return $cve;
    }
    /**
     * Regresa el string con la clave de producto
     *
     * @param  integer $idProducto Identificador de producto
     * @param  integer $idUn       Identificador de club
     *
     * @return string
     */
    public static function ctaProducto($idProducto, $idUn)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');

        $res = '';

        if ($idProducto == 0 || $idUn == 0) {
            return $res;
        }

        $sql = "
            SELECT cp.cuentaProducto
            FROM producto p
            INNER JOIN productoun pu ON pu.idProducto=p.idProducto
                AND pu.activo=1 AND pu.eliminado=0 AND pu.idUn={$idUn}
            INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
                AND pp.activo=1 AND pp.eliminado=0
                AND pp.idCuentaProducto>0
                AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
            INNER JOIN cuentaproducto cp ON cp.idCuentaProducto=pp.idCuentaProducto
            WHERE p.idProducto={$idProducto} AND p.activo=1 AND p.eliminado=0
            ORDER BY pp.idProductoPrecio DESC
            LIMIT 1
        ";
        $query = DB::connection('crm')->select($sql);

        $res = [];
        if (count($query) > 0) {
            $fila = $query[0];
            $res  = $fila->cuentaProducto;
        }
        return $res;
    }

}
