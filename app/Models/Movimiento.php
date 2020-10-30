<?php

namespace App\Models;

use App\Models\Permiso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Movimiento extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.movimiento';
    protected $primaryKey = 'idMovimiento';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * Inserta un registro dentro de la tabla de moviminetos si la operacion es correcta regresa el identeficador de movimiento
     * de lo contrario algunos de los siguientes codigos de error:
     *      -1 Tipo de movimiento invalido
     *      -2 Descripcion de movimiento nula
     *      -3 Iva invalido
     *      -4 Fecha nula o invalida
     *      -5 Eror al insertar movimiento
     *      -6 Identificador de persona invalido
     *      -7 Error al inserta cuenta contable
     *      -8 El movimiento no cuenta con origen
     *
     * @param array $datos Array con datos del movimiento
     *                       fecha       Fecha en que aplica el movimiento
     *                       tipo        Identificador del tipo de movimiento
     *                       descripcion Descripcion del movimiento a ingresar
     *                       importe     Importe del movimiento
     *                       iva         Iva
     *                       membresia   Identificador unico de membresia
     *                       producto    Identificador de producto
     *                       persona     Identificado de persona a la cual aplica el movimiento
     *                       origen      Descripcion corta de origen del movimiento
     *
     * @return integer
     */
    public static function inserta($datos)
    {
        $permiso = new Permiso;

        if (isset($datos['fecha'])) {

        } else {
            $datos['fecha'] = '';
        }
        if (isset($datos['tipo'])) {
            settype($datos['tipo'], 'integer');
        } else {
            $datos['tipo'] = 0;
        }
        if (isset($datos['persona'])) {
            settype($datos['persona'], 'integer');
        } else {
            $datos['persona'] = 0;
        }
        if (isset($datos['descripcion'])) {
            $datos['descripcion'] = trim($datos['descripcion']);
        } else {
            $datos['descripcion'] = '';
        }
        if (isset($datos['importe'])) {
            settype($datos['importe'], 'float');
        } else {
            $datos['importe'] = 0.0;
        }
        if (isset($datos['iva'])) {
            settype($datos['iva'], 'float');
        } else {
            $datos['iva'] = 0.0;
        }
        if (isset($datos['membresia'])) {
            settype($datos['membresia'], 'integer');
        } else {
            $datos['membresia'] = 0;
        }
        if (isset($datos['esquemaPago'])) {
            settype($datos['esquemaPago'], 'integer');
        } else {
            $datos['esquemaPago'] = 0;
        }
        if (isset($datos['producto'])) {
            settype($datos['producto'], 'integer');
        } else {
            $datos['producto'] = 0;
        }
        if (isset($datos['origen'])) {
            $datos['origen'] = trim($datos['origen']);
        } else {
            $datos['origen'] = '';
        }
        if (isset($datos['msi'])) {
            settype($datos['msi'], 'integer');
        } else {
            $datos['msi'] = 1;
        }
        if (isset($datos['numeroCuenta'])) {
            $datos['numeroCuenta'] = trim($datos['numeroCuenta']);
        } else {
            $datos['numeroCuenta'] = '';
        }

        if (isset($datos['cuentaProducto']) && !is_array($datos['cuentaProducto'])) {
            $datos['cuentaProducto'] = trim($datos['cuentaProducto']);
        } else {
            $datos['cuentaProducto'] = '';
        }
        if ($datos['tipo'] == 0) {
            return (-1);
        }
        if ($datos['descripcion'] == '') {
            return (-2);
        }
        if ($datos['iva'] <= 0.0) {
            return (-3);
        }
        if ($datos['fecha'] == '') {
            return (-4);
        }
        if ($datos['persona'] == 0) {
            return (-6);
        }
        if (trim($datos['origen']) == '') {
            return (-8);
        }
        if (isset($datos['idUn'])) {
            settype($datos['idUn'], 'integer');
        } else {
            $datos['idUn'] = $_SESSION('idUn');
        }
        if (!isset($datos['prohibirAppPago'])) {
            $datos['prohibirAppPago'] = '0';
        }
        if (isset($datos['idUnAplica'])) {
            settype($datos['idUnAplica'], 'integer');
        } else {
            $datos['idUnAplica'] = 0;
        }
        if ($datos['idUnAplica'] == 0) {
            $datos['idUnAplica'] = $datos['idUn'];
        }
        if (isset($datos['idTipoEstatusMovimiento'])) {
            settype($datos['idTipoEstatusMovimiento'], 'integer');
        } else {
            $datos['idTipoEstatusMovimiento'] = MOVIMIENTO_PENDIENTE;
        }
        if (isset($datos['cantidad'])) {
            settype($datos['cantidad'], 'float');
        } else {
            $datos['cantidad'] = 0.0;
        }
        if (!isset($datos['cveProductoServicio'])) {
            $datos['cveProductoServicio'] = '';
        }
        if (!isset($datos['cveUnidad'])) {
            $datos['cveUnidad'] = '';
        }

        $msi = $datos['msi'] == 0 ? 1 : $datos['msi'];

        $valores = array(
            'idPersona'               => $datos['persona'],
            'idTipoEstatusMovimiento' => $datos['idTipoEstatusMovimiento'],
            'idUn'                    => $datos['idUn'],
            'descripcion'             => $datos['descripcion'],
            'importe'                 => number_format($datos['importe'], 2, '.', ''),
            'iva'                     => $datos['iva'],
            'idUnicoMembresia'        => $datos['membresia'],
            'idProducto'              => $datos['producto'],
            'origen'                  => $datos['origen'],
            'msi'                     => $msi,
            'prohibirAppPago'         => $datos['prohibirAppPago'],
        );

        $movimiento = DB::connection('crm')->table(TBL_MOVIMIENTO)->insertGetId($valores);
        $total      = $movimiento;
        if ($total == 0) {
            return (-5);
        }
        $permiso->log(utf8_decode('Se inserto Movimiento(' . $movimiento . ') (' . date('Y-m-d') . ')'), LOG_SISTEMAS, $datos['membresia']);
        $numeroCuenta = trim($datos['numeroCuenta']);

        if (!isset($datos['soloMovimiento'])) {
            if ($datos['numeroCuenta'] != '') {
                $numeroCuenta = trim($datos['numeroCuenta']);
            } else {
                $numeroCuenta = '0';
                $query        = DB::connection('crm')->table(TBL_PRODUCTOUN);
                if ($datos['producto'] > 0) {
                    $whereEsquemaPago = '';
                    if ($datos['esquemaPago'] > 0) {
                        $whereEsquemaPago = "AND pp.idEsquemaPago = {$datos['esquemaPago']}";
                    }
                    $sql = "SELECT cc.numCuenta, cp.cuentaProducto
                    FROM productoun as pu
                    INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn AND pp.eliminado=0 AND pp.activo=1 AND DATE(NOW())>=pp.inicioVigencia AND DATE(NOW())<=pp.finVigencia
                    INNER JOIN cuentacontable cc ON cc.idCuentaContable=pp.idCuentaContable AND cc.activo=1
                    INNER JOIN cuentaproducto cp ON cp.idCuentaProducto = pp.idCuentaProducto
                    WHERE pu.idUn = {$datos['idUn']}
                        AND pu.idProducto = {$datos['producto']}
                        AND pu.eliminado = '0'
                        AND pu.activo = '1'
                        AND pp.idTipoRolCliente = '9'
                        {$whereEsquemaPago}
                    ORDER BY pp.idProductoPrecio DESC";
                    $query = DB::connection('crm')->select($sql);

                    if (count($query) > 0) {
                        $fila                    = $query[0];
                        $numeroCuenta            = $fila['numCuenta'];
                        $datos['cuentaProducto'] = $fila['cuentaProducto'];
                    }

                    if ($numeroCuenta == null) {
                        $numeroCuenta = 0;
                    }
                }
            }

            if ($numeroCuenta == '' || $numeroCuenta == '0') {
                $query = DB::connection('crm')->table(TBL_MOVIMIENTO)
                    ->delete(array('idMovimiento' => $movimiento));
                return (-7);
            } else {
                $cta = array(
                    'idMovimiento'        => $movimiento,
                    'numeroCuenta'        => $numeroCuenta,
                    'cuentaProducto'      => $datos['cuentaProducto'],
                    'idPromocion'         => '0',
                    'fechaAplica'         => $datos['fecha'],
                    'importe'             => number_format($datos['importe'], 2, '.', ''),
                    'idTipoMovimiento'    => $datos['tipo'],
                    'idUn'                => $datos['idUnAplica'],
                    'cveProductoServicio' => $datos['cveProductoServicio'],
                    'cveUnidad'           => $datos['cveUnidad'],
                    'cantidad'            => $datos['cantidad'],
                );
                $movimiento_cta = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
                    ->insertGetId($cta);

                $total = $movimiento_cta;
                if ($total == 0) {
                    $sql = 'UPDATE movimiento
                        SET idTipoEstatusMovimiento=' . MOVIMIENTO_CANCELADO . ', fechaEliminacion=NOW()
                        WHERE idMovimiento=' . $movimiento;
                    $query = DB::connection('crm')->select($sql);

                    return (-7);
                }
                $permiso->log(utf8_decode('Se inserto Movimiento Cta. Contable(' . $movimiento_cta . ') con cuenta (' . $numeroCuenta . ') y movimiento (' . $movimiento . ') (' . date('Y-m-d') . ')'), LOG_SISTEMAS, $datos['membresia']);
            }
        }

        if ($movimiento > 0) {
            $movimientoModel = new self;
            $movimientoModel->mttoMontosMenores($movimiento);
        }
        return $movimiento;
    }

}
