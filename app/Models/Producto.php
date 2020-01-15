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

                    $aux[] = ['id' => 0, 'value' => 'contado'];
                    foreach ($formasPago[$value->idProducto] as $keyPago => $tipoPago) {

                        $aux[] = ['id' => $keyPago, 'value' => $tipoPago];
                    }

                    $tiposPagos = $aux;
                } else {
                    $tiposPagos = ['id' => 0, 'value' => 'contado'];
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

}
