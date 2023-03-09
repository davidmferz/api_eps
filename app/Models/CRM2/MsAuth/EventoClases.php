<?php
namespace App\Models\CRM2\MsAuth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EventoClases extends Model
{

    protected $connection = 'crm2';
    protected $table      = 'msmantenimiento.evento_clases';
    protected $primaryKey = 'id';
    public $timestamps    = false;

    public static function unassignedClasses($type, $idUsuario)
    {
        if ($type == 'cliente') {
            $whereType = " v.cliente_id={$idUsuario} ";
        } else {
            $whereType = " cpi.responsable_id={$idUsuario} ";

        }
        $sql = "SELECT par.id AS participanteId,
                v.folio_factura as folioFactura,
                v.cliente_id AS personaId,
                CONCAT_WS(' ',pe.nombre,pe.primer_apellido,pe.primer_apellido ) AS persona,
                p.nombre AS producto,
                p.id AS productoId,
                pri.eventos AS numeroSesiones,
                pri.id AS productoIntancia,
                pri.eventos - IF(ec.sesionesImpartidas IS NULL  ,0,ec.sesionesImpartidas) AS sesionesRestantes,
                par.cantidad_paticipantes as paticipantes
                FROM ventas v
                JOIN mspersona.persona AS pe ON pe.persona_id=v.cliente_id
                JOIN msmantenimiento.cotizacion_cajas AS cc ON cc.id=v.cotizacion_caja_id
                JOIN msmantenimiento.cotizacion_cajas_items AS cci ON cci.cotizacion_caja_id=cc.id
                JOIN msmantenimiento.cotizacion_productos_items AS cpi ON cpi.id=cci.cotizacion_productos_item_id
                JOIN msmantenimiento.productos_instancias pri ON pri.cotizacion_producto_item_id=cpi.id
                JOIN msmantenimiento.participante AS par ON par.producto_instancia_id=pri.id
                JOIN msmantenimiento.productos AS p ON p.id=cpi.producto_id
                left JOIN (
                SELECT producto_instancia_id , COUNT(*) AS sesionesImpartidas
                    FROM  msmantenimiento.evento_clases
                    WHERE es_activo=1 AND estatus IN ('IMPARTIDO' ,'ASIGNADO')
                    GROUP BY producto_instancia_id
                ) AS ec ON pri.id=ec.producto_instancia_id
                WHERE {$whereType}
                AND v.estatus='FACTURADA'
                AND pri.eventos - IF(ec.sesionesImpartidas IS NULL  ,0,ec.sesionesImpartidas) >0";
        $query = DB::connection('crm2')->select($sql);
        if (count($query) > 0) {
            return $query;
        } else {
            return [];
        }
    }

    public static function reportSellUsers(array $users)
    {
        $idsStr = implode(',', $users);
        $sql    = "SELECT
                            au.user_id as userId,
                            DATE_FORMAT(v.fecha, '%Y-%m') as mes,
                            SUM(v.importeSinIva) AS ventaMes
                    FROM
                            view_deportiva v
                            JOIN msauth.auth_user AS au ON au.numero_empleado = v.numEmpleado
                    WHERE
                            au.user_id IN ({$idsStr})
                            AND v.fecha > DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH)
                    GROUP BY
                            au.user_id,
                            v.periodo";
        $query = DB::connection('crm2')->select($sql);
        if (count($query) > 0) {
            return $query;
        } else {
            return [];
        }
    }

}
