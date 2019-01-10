<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use API_EPS\Models\CatRutinas;
use API_EPS\Models\MenuActividad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use API_EPS\Models\Permiso;

class Movimiento extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.movimiento';
    protected $primaryKey = 'idMovimiento';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * [aceptaAjuste description]
     *
     * @param  [type]  $idMovimientoAjuste            [description]
     * @param  string  $motivo                        [description]
     * @param  integer $pnc                           [description]
     * @param  integer $idCategoriaProductoNoConforme [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]                                 [description]
     */
    public function aceptaAjuste($idMovimientoAjuste, $motivo='Atendido', $pnc=0, $idCategoriaProductoNoConforme = 0, $idTipoUnidadNegocio = 0)
    {
        settype($idMovimientoAjuste, 'integer');
        settype($pnc, 'integer');
        settype($idCategoriaProductoNoConforme, 'integer');
        settype($idTipoUnidadNegocio, 'array');

        $where = array(
            'idMovimientoAjuste' => $idMovimientoAjuste,
            'estatus'            => 'Pendiente',
            'fechaEliminacion'   => '0000-00-00 00:00:00'
        );
        $set = array(
            'estatus'                       => 'Atendido',
            'idPersonaAplica'               => $this->session->userdata('idPersona'),
            'pnc'                           => $pnc,
            'idCategoriaProductoNoConforme' => $idCategoriaProductoNoConforme,
        );


        $this->db->update(TBL_MOVIMIENTOAJUSTE, $set, $where);

        $total = $this->db->affected_rows();
        if ($total > 0) {
            if (!empty($idTipoUnidadNegocio) && is_array($idTipoUnidadNegocio) ) {
                foreach($idTipoUnidadNegocio as $t) {
                    $set = array(
                        'idMovimientoAjuste'         => $idMovimientoAjuste,
                        'idUnidadNegocioCorporativo' => $t,
                        'fechaRegistro'              => date('Y-m-d H:i:s')
                    );
                    $this->db->insert(TBL_SELECCIONUNIDADNEGOCIOMOVIMIENTO, $set);
                }
            }

            $ajuste = $this->obtenAjuste($idMovimientoAjuste);
            $idUnicoMembresia = $this->unicoMembresia($ajuste['idMovimiento']);

            $this->permisos_model->log('Aplica ajuste al movimiento ('.$ajuste['idMovimiento'].')',
                LOG_MEMBRESIA, $idUnicoMembresia);

            if ($motivo=='') {
                $motivo = 'Atendido';
            }

            $info = array(
                'idMovimientoAjuste' => $idMovimientoAjuste,
                'respuesta'          => utf8_decode($motivo)
            );
            $this->db->insert(TBL_MOVIMIENTOAJUSTERESPUESTA, $info);

            return true;
        }

        return false;
    }

    /**
     * Cambia la descripcion del movimiento solicitado
     *
     * @param integer $idMovimiento
     * @param integer $nuevaDescripcion
     * @param integer $idUnicoMembresia
     *
     * @return boolean
     */
    public function actualizaDescripcion($idMovimiento, $nuevaDescripcion, $idUnicoMembresia = 0)
    {
        settype($idMovimiento, 'integer');
        settype($idUnicoMembresia, 'integer');

        $this->db->where('idMovimiento', $idMovimiento);
        $this->db->where('eliminado', 0);
        $this->db->where('idTipoEstatusMovimiento !=', '66');
        $datos = array ('descripcion' => $nuevaDescripcion);
        $this->db->update(TBL_MOVIMIENTO, $datos);

        if ($this->db->affected_rows()>0) {
            $this->permisos_model->log(utf8_decode("Corrección  de Descripción de Movimiento(".$idMovimiento.") "),
                LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        }

        return false;
    }

    /**
     * Actualiza el importe de un movimiento
     *
     * @param integer $idMovimiento Identificador de movimiento
     * @param float   $importe      Importe a actualizar
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaImporte ($idMovimiento, $importe, $origenExtra = '', $fechaAplica="")
    {
        settype($idMovimiento, 'integer');
        settype($importe, 'float');
        $result = false;

        if (! $idMovimiento) {
            return $result;
        }
        $datosCtas = $this->obtenTotalCuentasMovimiento($idMovimiento);
        $totalCtas = $datosCtas['totalCuentas'];
        $importeOrigen = $importe;

        $where = array(
            'idMovimiento' => $idMovimiento,
            'eliminado'    => 0
        );
        $whereCtaContable = array(
            'idMovimiento' => $idMovimiento,
            'eliminado'    => 0
        );

        if ($importe > 0) {
            $set = array('importe' => $importe);

            if ($origenExtra) {
                $origen = $this->obtenOrigen($idMovimiento);
                if ($origen) {
                    $set['origen'] = $origen.','.$origenExtra;
                }
            }
            $result = $this->db->update(TBL_MOVIMIENTO, $set, $where);

            $this->permisos_model->log(utf8_decode("Actualiza importe del Movimiento(".$idMovimiento.")"), LOG_SISTEMAS);

            unset($set['origen']);

            if ($totalCtas > 1) {
                $set['importe'] = number_format($set['importe']/$totalCtas, 3, '.','');
            }
            if( $fechaAplica!='' ){
                $set['fechaAplica'] = $fechaAplica;
            }

            $result = $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $set, $whereCtaContable);

            if ($totalCtas%2 > 0) {
                $set['importe'] = number_format($importeOrigen - ($set['importe']*($totalCtas-1)), 3, '.','');

                $query = $this->db->query(
                    "SELECT mcc.idMovimientoCtaContable
                    FROM crm.movimientoctacontable mcc
                    WHERE mcc.eliminado = 0
                        AND mcc.idMovimiento = ".$idMovimiento."
                    ORDER BY mcc.idMovimientoCtaContable LIMIT 1"
                );
                if ($query->num_rows) {
                    $where = array('idMovimientoCtaContable' => $query->row()->idMovimientoCtaContable);
                    $result = $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $set, $whereCtaContable);
                }
            }
        } else {
            $set = array(
                'importe'                 => $importe,
                'idTipoEstatusMovimiento' => MOVIMIENTO_PAGADO
            );
            $result = $this->db->update(TBL_MOVIMIENTO, $set, $where);
            $this->permisos_model->log(utf8_decode("Actualiza importe del Movimiento(".$idMovimiento.")"), LOG_SISTEMAS);

            if ($result) {
                $set    = array('idTipoEstatusMovimiento' => MOVIMIENTO_DESCUENTO_FACULTAMIENTO);
                $result = $this->db->update(TBL_MOVIMIENTO, $set, $where);
            }
            if ($totalCtas == 1) {
                $set = array('importe' => $importe);
                if ($fechaAplica!='') {
                    $set['fechaAplica'] = $fechaAplica;
                }
                $result = $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $set, $whereCtaContable);
            }
        }
        return $result;
    }

    /**
     *
     * @param type $idCtaContable
     * @param type $nuevoImporte
     * @param type $idUnicoMembresia
     * @return type
     */
    public function actualizaImporteCtaContable($idMovimientoCtaContable, $nuevoImporte, $idUnicoMembresia, $fechaAplica = '')
    {
        settype($idMovimientoCtaContable, 'integer');
        settype($idUnicoMembresia, 'integer');
        settype($nuevoImporte, 'float');
        settype($fechaAplica, 'string');

        $this->db->select('idMovimiento');
        $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
        $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $idMovimiento = $fila['idMovimiento'];

            $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
            $this->db->where('eliminado', 0);
            $datos = array ('importe' => $nuevoImporte);
            if ($fechaAplica != '') {
                $datos['fechaAplica'] = $fechaAplica;
            }
            $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $datos);

            if ($this->db->affected_rows()>0) {
                $this->permisos_model->log(
                    utf8_decode("Corrección de importe en cta contable (".$idMovimientoCtaContable.") por $".number_format($nuevoImporte, 2)),
                    LOG_SISTEMAS,
                    $idUnicoMembresia
                );
            }

            $total = $this->obtenerImporteTotal($idMovimiento);
            $imp = array ('importe' => $total);
            $this->db->where('idMovimiento', $idMovimiento);
            $this->db->update(TBL_MOVIMIENTO, $imp);

            if ($this->db->affected_rows()>0) {
                $this->permisos_model->log(
                    utf8_decode("Corrección de importe total (".$idMovimiento.") por $".number_format($total, 2)),
                    LOG_SISTEMAS,
                    $idUnicoMembresia
                );
            }

            return true;
        } else {
            return 0;
        }
    }



    /**
     *
     * @param type $idCtaContable
     * @param type $nuevoImporte
     * @param type $idUnicoMembresia
     * @return type
     */
    public function actualizaProductoServicio($idMovimientoCtaContable, $cveProductoServicio)
    {
        settype($idMovimientoCtaContable, 'integer');

        $res = false;

        $this->db->select('idMovimiento');
        $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
        $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $idMovimiento = $fila['idMovimiento'];
            $idUnicoMembresia = $this->unicoMembresia($idMovimiento);

            $idMovimiento = $fila['idMovimiento'];

            $datos = array ('cveProductoServicio' => $cveProductoServicio);

            $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
            $this->db->where('eliminado', 0);
            $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $datos);

            if ($this->db->affected_rows()>0) {
                $this->permisos_model->log(
                    utf8_decode("Se actualiza clave producto/servicio cta contable (".$idMovimientoCtaContable.")"),
                    LOG_SISTEMAS,
                    $idUnicoMembresia
                );
            }

            return true;
        }

        return $res;
    }


    /**
     *
     * @param type $idCtaContable
     * @param type $nuevoImporte
     * @param type $idUnicoMembresia
     * @return type
     */
    public function actualizaUnidad($idMovimientoCtaContable, $cveUnidad)
    {
        settype($idMovimientoCtaContable, 'integer');

        $res = false;

        $this->db->select('idMovimiento');
        $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
        $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $idMovimiento = $fila['idMovimiento'];
            $idUnicoMembresia = $this->unicoMembresia($idMovimiento);

            $datos = array ('cveUnidad' => $cveUnidad);

            $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
            $this->db->where('eliminado', 0);
            $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $datos);

            if ($this->db->affected_rows()>0) {
                $this->permisos_model->log(
                    utf8_decode("Se actualiza clave unidad cta contable (".$idMovimientoCtaContable.")"),
                    LOG_SISTEMAS,
                    $idUnicoMembresia
                );
            }

            return true;
        }

        return $res;
    }


    /**
     *
     * @param type $idCtaContable
     * @param type $nuevoImporte
     * @param type $idUnicoMembresia
     * @return type
     */
    public function actualizaCantidad($idMovimientoCtaContable, $cantidad)
    {
        settype($idMovimientoCtaContable, 'integer');

        $res = false;

        $this->db->select('idMovimiento');
        $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
        $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $idMovimiento = $fila['idMovimiento'];
            $idUnicoMembresia = $this->unicoMembresia($idMovimiento);

            $datos = array ('cantidad' => $cantidad);

            $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
            $this->db->where('eliminado', 0);
            $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $datos);

            if ($this->db->affected_rows()>0) {
                $this->permisos_model->log(
                    utf8_decode("Se actualiza cantidad para cta contable (".$idMovimientoCtaContable.")"),
                    LOG_SISTEMAS,
                    $idUnicoMembresia
                );
            }

            return true;
        }

        return $res;
    }


    /**
     * Actualiza tabla movimiento
     *
     * @param integer $idMovimiento Identificador de movimiento
     * @param integer $idProducto   Identificador de producto
     * @param integer $descripcion  Descripcion de movimiento
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaMovimiento ($idMovimiento, $idProducto = 0, $descripcion = '')
    {
        settype($idMovimiento, 'integer');
        $set = array();
        $res = false;

        if (! $idMovimiento) {
            return $res;
        }
        $u = $this->datosGral($idMovimiento);

        $where = array(
            'idMovimiento' => $idMovimiento,
            'eliminado'    => 0
        );
        if ($idProducto) {
            $set = array('idProducto' => $idProducto);
        }
        if ($descripcion) {
            $set = array('descripcion' => $descripcion);
        }
        if ($set) {
            $res = $this->db->update(TBL_MOVIMIENTO, $set, $where);
            $this->permisos_model->log("Actualizando movimiento(".$idMovimiento.")", LOG_SISTEMAS, $u['idUnicoMembresia']);
        }
        return $res;
    }

    /**
     * Actualiza campo msi
     *
     * @param type $idMovimiento
     * @param type $msi
     *
     * @author Antonio Sixtos
     *
     * @return int
     */
    public function actualizaMsi($idMovimiento, $msi, $msia)
    {
        settype($idMovimiento, 'integer');

        $u = $this->datosGral($idMovimiento);
        $data = array('msi' => $msi);
        $this->db->where('idMovimiento', $idMovimiento);
        $this->db->update(TBL_MOVIMIENTO, $data);
        if ($this->db->affected_rows()>0) {
            $this->permisos_model->log(utf8_decode("Se actualizo msi de ".$msia." a ".$msi." para el movimiento (".$idMovimiento.") (".date('Y-m-d').")"), LOG_MEMBRESIA, $u['idUnicoMembresia']);
            $res = 1;
        } else {
            $res = 0;
        }
        return $res;
    }

    /**
     * Actualiza tabla sociopagomtto
     *
     * @param integer $idMovimiento    Identificador de movimiento
     * @param integer $idMantenimiento Identificador de mantenimiento
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaSocioPagoMtto ($idMovimiento, $idMantenimiento = 0)
    {
        settype($idMovimiento, 'integer');
        $set = array();
        $res = false;

        if (! $idMovimiento) {
            return $res;
        }
        $u = $this->datosGral($idMovimiento);
        $where = array(
            'idMovimiento' => $idMovimiento,
            'eliminado'    => 0
        );
        if ($idMantenimiento) {
            $set = array('idMantenimiento' => $idMantenimiento);
        }
        if ($set) {
            $res = $this->db->update(TBL_SOCIOPAGOMTTO, $set, $where);
            $this->permisos_model->log("Se cambia el mantenimiento asociado al movimiento(".$idMovimiento.")",
                LOG_MEMBRESIA, $u['idUnicoMembresia']);
        }
        return $res;
    }

    /**
     * Regresa adeudo
     *
     * @param integer $tipoAdeudo     Tipo de adeudo a evaluar (1 - Vigentes, 2 - Vencidos, 3 - Totales)
     * @param integer $persona        Identificador de persona
     * @param integer $membresia      Identificador unico de membresia
     * @param integer $tipoMovimiento Identificador de tipo de movimiento
     *
     * @return float
     */
    public function adeudo($tipoAdeudo, $persona = 0, $membresia = 0, $tipoMovimiento = 0, $procedencia = 0)
    {
        settype($persona, 'integer');
        settype($membresia, 'integer');
        settype($tipoMovimiento, 'integer');
        settype($tipoAdeudo, 'integer');
        settype($procedencia, 'integer');

        if ($membresia == 0) {
            return 0;
        }

        $sql1 = "CALL crm.spAdeudos(?, ?, ?, ?, ?, @respuesta)";
        $query1 = $this->db->query($sql1, array($tipoAdeudo, $persona, $membresia, $tipoMovimiento, $procedencia));
        $sql2 = "SELECT @respuesta AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return (float)$row->resp;
    }

	/**
     * Agrega el texto indicado en campo origen del movimiento
     *
     * @param  integer $idMovimiento Identificador del movimiento
     * @param  integer $origen       Descripcion del origen
     * @return void
     */
    public function agregarOrigen($idMovimiento, $origen)
    {
        settype($idMovimiento, 'integer');

        $this->db->select('origen');
        $this->db->from(TBL_MOVIMIENTO);
        $where = array(
            'idMovimiento' => $idMovimiento,
            'eliminado'    => 0
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $origen = $fila->origen.'-'.$origen;

                $where = array('idMovimiento' => $idMovimiento);
                $set = array('origen' => $origen);
                $res   = $this->db->update(TBL_MOVIMIENTO, $set, $where);
            }
        }
    }

	/**
     * Agrega el texto indicado en la descripcion del movimiento
     *
     * @param  integer $idMovimiento Identificador del movimiento
     * @param  integer $descripcion  Descripcion
     *
     * @author Jorge Cruz <jorge.cruz@sportsworld.com.mx>
     *
     * @return void
     */
    public function agregarDescripcion($idMovimiento, $descripcion)
    {
        settype($idMovimiento, 'integer');

        $this->db->select('descripcion');
        $this->db->from(TBL_MOVIMIENTO);
        $where = array(
            'idMovimiento' => $idMovimiento,
            'eliminado'    => 0
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $descripcion = $fila->descripcion.$descripcion;

                $where = array('idMovimiento' => $idMovimiento);
                $set = array('descripcion' => $descripcion);
                $res = $this->db->update(TBL_MOVIMIENTO, $set, $where);
            }
        }
    }


    /**
     *
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function arrayMotivoCorrecion()
    {
        $salida = array();

        $this->db->select('idMotivoCorreccion, descripcion');
        $this->db->from(TBL_MOTIVOCORRECCION);
        $this->db->where('activo', 1);
        $this->db->order_by('orden');
        $query = $this->db->get();

        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $salida[$fila->idMotivoCorreccion] = $fila->descripcion;
            }
        }

        return $salida;
    }


    /**
     *
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function arrayProductoDevengado()
    {
        $salida = array();

        $this->db->select('idTipoDevengadoProducto, descripcion, cuentaContablePasivo');
        $this->db->from(TBL_TIPODEVENGADOPRODUCTO);
        $this->db->where('activo', 1);
        $this->db->order_by('descripcion');
        $query = $this->db->get();

        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $salida[$fila->idTipoDevengadoProducto] = '('.$fila->cuentaContablePasivo.') - '.$fila->descripcion;
            }
        }

        return $salida;
    }


    /**
     *
     * @param type $idMovimiento
     * @param type $estatus
     * @return type
     */
    public function cambiarEstatus($idMovimiento, $estatus)
    {
        settype($idMovimiento, 'integer');
        settype($estatus, 'integer');

        $nuevoEstatus = $this->listaTipoEstatus($estatus);
        $this->db->select('idMovimiento, idUnicoMembresia');
        $this->db->from(TBL_MOVIMIENTO);
        $where = array(
            'idMovimiento' => $idMovimiento,
        );
        $this->db->where($where);
        if ($estatus == MOVIMIENTO_CANCELADO) {
            $this->db->where_not_in('idTipoEstatusMovimiento', array(MOVIMIENTO_PAGADO, MOVIMIENTO_EN_TRAMITE, MOVIMIENTO_EXCEPCION_PAGO));
        }
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idMovimiento', $fila->idMovimiento);
                $this->db->where('eliminado', 0);
                $datos = array ('idTipoEstatusMovimiento' => $estatus);
                $this->db->update(TBL_MOVIMIENTO, $datos);

                if(!isset($nuevoEstatus[$estatus])) {
                    $nuevoEstatus[$estatus] = $estatus;
                }
                $this->permisos_model->log(utf8_decode('Cambia estatus del Movimiento('.$idMovimiento.') a '.$nuevoEstatus[$estatus]), LOG_SISTEMAS, $fila->idUnicoMembresia);
            }
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            if ($estatus == MOVIMIENTO_CANCELADO) {
                $this->elimina($idMovimiento);
            }
            return true;
        }
    }

    /**
     * Realiza la cancelación de movimientos pendientes por mantenimiento
     *
     * @param integer $idUnicoMembresia Identificador de membresia
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function cancela($idUnicoMembresia, $idUsuario = 0, $validaMtto = 0)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia == 0) {
            return 0;
        }
        $this->db->distinct();
        $this->db->select('idMembresia, idUn');
        $this->db->from(TBL_MEMBRESIA);
        $where = array('idUnicoMembresia' => $idUnicoMembresia);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $membresia = $fila->idMembresia;
                $club = $this->un_model->nombre($fila->idUn);
            }
        }
        if (isset($dato)) {
            return $dato;
        }

        $v = '';
        if ($validaMtto==1) {
            $v = " AND mcc.fechaAplica>=DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01'))";
        }

        $qry = "SELECT m.idMovimiento, COUNT(*) AS total, SUM(IF(mcc.idTipoMovimiento=".TIPO_MOVIMIENTO_MANTENIMIENTO.",1,0)) AS mtto ";
        $qry .= "FROM ".TBL_MOVIMIENTO." m ";
        $qry .= "INNER JOIN ".TBL_MOVIMIENTOCTACONTABLE." mcc ON mcc.idMovimiento=m.idMovimiento AND mcc.eliminado=0 $v ";
        $qry .= "WHERE m.idUnicoMembresia='".$idUnicoMembresia."'
            AND m.idTipoEstatusMovimiento=".MOVIMIENTO_PENDIENTE." AND m.eliminado=0 ";
        $qry .= "GROUP BY m.idMovimiento HAVING total=mtto";
        $query = $this->db->query($qry);

        if ($query->num_rows()>0) {
            $this->permisos_model->log(utf8_decode("Cancela cargos pendientes en la Membresía ($membresia del club $club)"), LOG_MEMBRESIA, $idUnicoMembresia, 0, false, 0, $idUsuario);

            foreach ($query->result() as $fila) {
                $movimiento= $fila->idMovimiento;
                $this->cancelaMovimiento($fila->idMovimiento);
            }

            return true;
        } else {
            return 0;
        }
    }

    /**
     *  Cancela un movimiento
     *
     * @param integer $idMovimiento Identificador de movimiento
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function cancelaMovimiento ($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        if (!$idMovimiento) {
            return false;
        }
        $u = $this->datosGral($idMovimiento);

        $where = array(
            'idMovimiento'               => $idMovimiento,
            'idTipoEstatusMovimiento <>' => MOVIMIENTO_PAGADO,
            'eliminado'                  => 0);
        $set = array(
            'idTipoEstatusMovimiento' => MOVIMIENTO_CANCELADO
        );
        $this->db->update(TBL_MOVIMIENTO, $set, $where);

        $total = $this->db->affected_rows();
        if ($total > 0) {
            $this->permisos_model->log('Se pasa a estatus de cancelado el movimiento ('.$idMovimiento.')', LOG_SISTEMAS, $u['idUnicoMembresia']);

            unset($where);
            unset($set);

            $where = array('idMovimiento' => $idMovimiento, 'idTipoEstatusMovimiento' => MOVIMIENTO_CANCELADO);
            $set   = array('fechaEliminacion' => date('Y-m-d H:i:s'));
            $this->db->update(TBL_MOVIMIENTO, $set, $where);

            return true;
        }

        return false;
    }

    /**
     * Regresa el numero de cuentas contables involucradas por movimiento
     *
     * @param int $idMovimiento
     *
     * @author Jorge Cruz
     *
     * @return int
     */
    public function ctaContablesXMovimiento($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        if($idMovimiento<=0) {
            return 0;
        }

        $this->db->select('numeroCuenta');
        $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
        $this->db->where('idMovimiento', $idMovimiento);
        $this->db->where('eliminado', 0);
        $this->db->group_by('numeroCuenta');
        $query = $this->db->get();

        return $query->num_rows;
    }

    /**
     *
     * @param integer $idMovimiento
     * @return integer
     */
    public function datosGral($idMovimiento)
    {
        $this->db->select('idPersona, idTipoEstatusMovimiento, idUn, importe, iva, idUnicoMembresia, descripcion');
        $this->db->from(TBL_MOVIMIENTO);
        $this->db->where('idMovimiento', $idMovimiento);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows==1) {
            return  $query->row_array();;
        } else {
            return 0;
        }
    }

    /**
     * Regresa dia de corte
     *
     * @param integer $membresia Id unico membresia
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function diaCorte ($membresia = 0)
    {
        $ci =& get_instance();
        $ci->load->model('membresia_model');
        $ci->load->model('socio_model');

        settype($membresia, 'integer');
        if ($membresia == 0) {
            return 5;
        }

        $datos['idTitular'] = $ci->membresia_model->obtenerTitular($membresia);
        $titular  = $datos['idTitular']['idPersona'];
        $socio = $ci->socio_model->datosSocio($titular);

        if (count($socio) == 0) {
            return 5;
        }

        $this->db->select('diaCargo');
        $this->db->from(TBL_SOCIODATOSTARJETA);
        $this->db->where('idSocio', $socio['idSocio']);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows==1) {
            $fila = $query->row_array();

            return $fila['diaCargo'];
        }
        return 5;
    }

    /**
     * Marca como eliminado un determinado movimiento
     *
     * @param integer $idMovimiento Identificador de movimiento
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function elimina ($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        if ($idMovimiento == 0) {
            return false;
        }
        $u = $this->datosGral($idMovimiento);

        $where = array(
            'idMovimiento' => $idMovimiento,
            'idTipoEstatusMovimiento <>' => MOVIMIENTO_PAGADO,
            'eliminado' => 0
        );
        $set = array('fechaEliminacion' => date('Y-m-d H:i:s'));
        $res = $this->db->update(TBL_MOVIMIENTO, $set, $where);

        $this->permisos_model->log(utf8_decode('Elimina Movimiento('.$idMovimiento.') ('.date('Y-m-d').')'), LOG_SISTEMAS, $u["idUnicoMembresia"]);
        return $res;
    }

    /**
     * Elimina los movimiento en cta contable con importe 0
     *
     * @param  integer $idMovimiento Identificador del movimiento
     *
     * @author Jorge Cruz <jorge.cruz@sportsworld.com.mx>
     *
     * @return void
     */
    public function eliminaCtaContableCero($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $where = array(
            'idMovimiento' => $idMovimiento,
            'importe'      => 0.0,
            'eliminado'    => 0
        );
        $set = array('fechaEliminacion' => date('Y-m-d H:i:s'));
        $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $set, $where);
    }


    /**
     * [immporteCtaContable description]
     *
     * @param  [type] $idMovimientoCtaContable [description]
     *
     * @return [type]                          [description]
     */
    public function importeCtaContable ($idMovimientoCtaContable)
    {
        settype($idMovimientoCtaContable, 'integer');

        $this->db->select('importe');
        $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
        $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['importe'];
        } else {
            return 0;
        }
    }


    /**
     * Regresa el estatus del movimiento indicado
     *
     * @param  integer $idMovimiento [description]
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function estatus($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        if ($idMovimiento<=0) {
            return 0;
        }

        $this->db->select('idTipoEstatusMovimiento');
        $this->db->from(TBL_MOVIMIENTO);
        $this->db->where('idMovimiento', $idMovimiento);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idTipoEstatusMovimiento'];
        } else {
            return 0;
        }
    }

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
        if (isset($datos['cuentaProducto'])) {
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
        if($datos['idUnAplica']==0) {
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

        $msi = $datos['msi']==0?1:$datos['msi'];

        $valores = array (
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
            'prohibirAppPago'         => $datos['prohibirAppPago']
        );
        $movimiento = DB::connection('crm')->table(TBL_MOVIMIENTO)->insertGetId($valores);
        $total = $movimiento;
        if ($total == 0) {
            return (-5);
        }
        Permiso::log(utf8_decode('Se inserto Movimiento('.$movimiento.') ('.date('Y-m-d').')'), LOG_SISTEMAS, $datos['membresia']);
        $numeroCuenta = trim($datos['numeroCuenta']);

        if( !isset($datos['soloMovimiento']) ){
            if ($datos['numeroCuenta'] != '' ) {
                $numeroCuenta =  trim($datos['numeroCuenta']);
            } else {
                $numeroCuenta = '0';
                $query = DB::connection('crm')->table(TBL_PRODUCTOUN);
                if ($datos['producto'] > 0) {
                    $query = $query->select('cc.numCuenta, cp.cuentaProducto')
                    ->join(TBL_PRODUCTOPRECIO.' pp', 'pp.idProductoUn=pu.idProductoUn AND pp.eliminado=0 AND pp.activo=1 AND DATE(NOW())>=pp.inicioVigencia AND DATE(NOW())<=pp.finVigencia')
                    ->join(TBL_CUENTACONTABLE.' cc', 'cc.idCuentaContable=pp.idCuentaContable AND cc.activo=1')
                    ->join(TBL_CUENTAPRODUCTO.' cp', 'cp.idCuentaProducto = pp.idCuentaProducto')
                    ->where('pu.idUn', $datos['idUn'])
                    ->where('pu.idProducto', $datos['producto'])
                    ->where('pu.eliminado', 0)
                    ->where('pu.activo', 1)
                    ->where('pp.idTipoRolCliente', ROL_CLIENTE_NINGUNO);
                    if ($datos['esquemaPago']>0) {
                        $query = $query->where('pp.idEsquemaPago', $datos['esquemaPago']);
                    }
                    $query = $query->orderBy('pp.idProductoPrecio', 'desc');
                    
                    if ($query->count() > 0) {
                        $fila = ($query->get()->toArray())[0];
                        $numeroCuenta = $fila['numCuenta'];
                        $datos['cuentaProducto'] = $fila['cuentaProducto'];
                    }

                    if ($numeroCuenta == null) {
                        $numeroCuenta = 0;
                    }
                }
            }

            if ($numeroCuenta=='' || $numeroCuenta=='0') {
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
                    'cantidad'            => $datos['cantidad']
                );
                $movimiento_cta = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
                ->insertGetId($cta);
                
                $total = $movimiento_cta;
                if ($total == 0) {
                    $sql = 'UPDATE movimiento
                        SET idTipoEstatusMovimiento='.MOVIMIENTO_CANCELADO.', fechaEliminacion=NOW()
                        WHERE idMovimiento='.$movimiento;
                    $query = DB::connection('crm')->select($sql);
                    
                    return (-7);
                }
                Permiso::log(utf8_decode('Se inserto Movimiento Cta. Contable('.$movimiento_cta.') con cuenta ('.$numeroCuenta.') y movimiento ('.$movimiento.') ('.date('Y-m-d').')'), LOG_SISTEMAS, $datos['membresia']);
            }
        }
        
        if ($movimiento > 0) {
            $this->mttoMontosMenores($movimiento);
        }
        return $movimiento;
    }

    /**
     * Inserta en tabla movimientoctacontable
     *
     * @param array $set Arreglo con los datos a insertar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function insertaCtaContable ($idMovimiento, $idTipoMovimiento, $idUn, $numeroCuenta, $fechaAplica, $importe, $cuentaProducto = '')
    {
        $idMovimientoCtaContable = 0;

        $set = array(
            'idMovimiento'     => $idMovimiento,
            'idTipoMovimiento' => $idTipoMovimiento,
            'idUn'             => $idUn,
            'numeroCuenta'     => $numeroCuenta,
            'cuentaProducto'   => $cuentaProducto,
            'fechaAplica'      => $fechaAplica,
            'importe'          => $importe
        );
        $u = $this->datosGral($idMovimiento);

        $res = $this->db->insert(TBL_MOVIMIENTOCTACONTABLE, $set);
        $id = $this->db->insert_id();

        $this->permisos_model->log(utf8_decode("Se inserto Movimiento cta. Contable(".$id.") (".date('Y-m-d').")"), LOG_SISTEMAS, $u['idUnicoMembresia']);

        return $id;
    }

    /**
     * Inserta registro en sociopagomtto
     *
     * @param integer $set
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function insertaPagoMtto ($set)
    {
        if (! $set) {
            return false;
        }
        return $this->db->insert(TBL_SOCIOPAGOMTTO, $set);
    }

    /**
     *
     * @param type $datos
     * @return type
     */
    public function insertaParcialidades($datos)
    {
        if( $datos["idMovimientoCtaContable"]=="" || $datos["idMovimientoCtaContable"]==0 ){
            return -1;
        }
        if( $datos["numeroFactura"]=="" || $datos["numeroFactura"]<0 ){
            return -2;
        }
        if( $datos["numeroFactura"]>0 && $datos["numeroFacturaDe"]<0 ){
            return -3;
        }
        if( $datos["numeroFactura"]>0 && $datos["facturaUUID"]=="" ){
            return -4;
        }
        if( $datos["numeroFactura"]>0 && $datos["facturaFolio"]=="" ){
            return -5;
        }
        if( $datos["numeroFactura"]>0 && $datos["facturaFecha"]=="" ){
            return -6;
        }
        if( $datos["numeroFactura"]>0 && ($datos["facturaImporte"]=="" || $datos["facturaImporte"]==0) ){
            return -7;
        }

        $valores = array(
            "idMovimientoCtaContable" => $datos["idMovimientoCtaContable"],
            "numeroFactura"           => $datos["numeroFactura"],
            "numeroFacturaDe"         => $datos["numeroFactura"]==0?"0":$datos["numeroFacturaDe"],
            "facturaUUID"             => $datos["numeroFactura"]==0?"":$datos["facturaUUID"],
            "facturaFolio"            => $datos["numeroFactura"]==0?"":$datos["facturaFolio"],
            "facturaFecha"            => $datos["numeroFactura"]==0?"0000-00-00":$datos["facturaFecha"],
            "facturaImporte"          => $datos["numeroFactura"]==0?"0.000":number_format($datos["facturaImporte"], 3, ".", "")
        );
        $this->db->insert(TBL_MOVIMIENTOPARCIALIDADES, $valores);
        $idMovimientoParcialidad = $this->db->insert_id();
        $this->permisos_model->log(utf8_decode("Se agregó movimiento parcialidad (".$datos["idMovimientoCtaContable"]."-".$idMovimientoParcialidad.") por cargo miscelaneo"), LOG_SISTEMAS);
        return $idMovimientoParcialidad;
    }

    /**
     *
     * @param type $datos
     * @return type
     */
    public function insertaProrrateo($datos)
    {
        if (isset($datos['fechaInicioVigencia'])) {

        } else {
            $datos['fechaInicioVigencia'] = "";
        }

        if (isset($datos['idUn'])) {
            settype($datos['idUn'], 'integer');
        } else {
            $datos['idUn'] = 0;
        }
        if (isset($datos['idMovimiento'])) {
            settype($datos['idMovimiento'], 'integer');
        } else {
            $datos['idMovimiento'] = 0;
        }

        if (isset($datos['numeroMesesAmortizacion'])) {
            settype($datos['numeroMesesAmortizacion'], 'integer');
        } else {
            $datos['numeroMesesAmortizacion'] = 0;
        }
        if (isset($datos['cuentaContableClientes'])) {
            settype($datos['cuentaContableClientes'], 'integer');
        } else {
            $datos['cuentaContableClientes'] = 0;
        }
        if (isset($datos['cuentaContableGastos'])) {
            settype($datos['cuentaContableGastos'], 'integer');
        } else {
            $datos['cuentaContableGastos'] = 0;
        }
        if (isset($datos['numeroUN'])) {
            settype($datos['numeroUN'], 'integer');
        } else {
            $datos['numeroUN'] = 0;
        }

        if ($datos['idUn'] == 0) {
            return (-1);
        }
        if ($datos['idMovimiento'] == 0) {
            return (-3);
        }
        if ($datos['numeroMesesAmortizacion'] == 0) {
            return (-2);
        }
        if ($datos['cuentaContableClientes'] == 0) {
            return (-5);
        }
        if ($datos['fechaInicioVigencia'] == "") {
            return (-4);
        }
        if ($datos['numeroUN'] == "") {
            return (-6);
        }

        $u = $this->datosGral($datos["idMovimiento"]);
        $valores = array (
            'idUn'                    => $datos["idUn"],
            'idMovimientoCtaContable' => $datos["idMovimiento"],
            'fechaInicioVigencia'     => $datos["fechaInicioVigencia"],
            'numeroMesesAmortizacion' => $datos["numeroMesesAmortizacion"],
            'cuentaContableClientes'  => $datos["cuentaContableClientes"],
            'cuentaContableGastos'    => $datos["cuentaContableGastos"],
            'numeroUN'                => $datos["numeroUN"]
        );
        $this->db->insert(TBL_MOVIMIENTOPRORRATEO, $valores);
        $movimientoProrrateo = $this->db->insert_id();
        $this->permisos_model->log(utf8_decode("Se agregó movimiento prorrateo (".$datos['idMovimiento']."-".$movimientoProrrateo.") por cargo miscelaneo"), LOG_SISTEMAS);
        return $movimientoProrrateo;
    }

    /**
     *
     *
     * @param integer $parametros
     * @param integer $totales    Regresar el total registros encontrados
     * @param integer $posicion   Posicion de incio de la busqueda
     * @param integer $registros  Numero de registros a devolver
     * @param boolean $eliminados Bandera de eliminados
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function lista($parametros, $totales = 0, $posicion = 0, $registros = 25, $eliminados = 0)
    {
        $datos = array();
        settype($totales, 'integer');
        settype($posicion, 'integer');
        settype($registros, 'integer');
        settype($eliminados, 'integer');

        if (isset($parametros['membresia'])) {
            settype($parametros['membresia'], 'integer');
        } else {
            $parametros['membresia'] = 0;
        }

        if ($parametros['membresia'] == 0) {
            return $parametros;
        }

        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }

        $this->db->select(
            'm.idMovimiento, m.fechaRegistro, m.idTipoEstatusMovimiento, '.
            'tem.descripcion AS estatus, m.idUn, u.nombre AS club, '.
            'm.importe, m.iva, m.descripcion, m.msi, m.idPersona, IF(m.origen LIKE \'%MTTOMSI%\', 1, 0) AS mttoMSI, '.
            'm.fechaEliminacion, mcc.idTipoMovimiento, IF(mcc.idTipoMovimiento = 47, 1, 0) AS mtto, '.
            'count(mcc.numeroCuenta) as cuentas,m.origen,spm.idEsquemaPago, m.idProducto, '.
            'IF(IFNULL(mdm.idMovimiento, 0)>0 OR m.origen LIKE \'%DescBinSantander%\', 1, 0) AS descuento, '.
            'IFNULL(pi.idPaquete,0) AS promocion, IFNULL(pi.aplicado,0) AS promoAplicada, '.
            'IFNULL(p.nombre, \'\') AS promoNombre, '.
            'SUM( IF(mcc.numeroCuenta LIKE \'420%\' AND YEAR(m.fechaRegistro) = YEAR(NOW()) '.
            'AND MONTH(m.fechaRegistro)=MONTH(NOW()) AND m.idTipoEstatusMovimiento IN (65,66,112), 1, 0)) as mtto ',
            false
        );
        $this->db->from(TBL_MOVIMIENTO.' m');
        $this->db->join(TBL_TIPOESTATUSMOVIMIENTO .' tem', 'tem.idTipoEstatusMovimiento=m.idTipoEstatusMovimiento');
        $this->db->join(TBL_UN .' u', 'u.idUn = m.idUn');
        $this->db->join(TBL_MOVIMIENTOCTACONTABLE.' mcc', 'mcc.idMovimiento= m.idMovimiento and mcc.eliminado=0','LEFT');
        $this->db->join(TBL_MOVIMIENTODESCUENTOMTTO.' mdm', 'mdm.idMovimiento=m.idMovimiento', 'LEFT');
        $this->db->join(TBL_PAQUETEIMPACTO.' pi', 'pi.idMovimiento=m.idMovimiento', 'LEFT');
        $this->db->join(TBL_PAQUETE.' p', 'p.idPaquete=pi.idPaquete', 'LEFT');
        $this->db->join(TBL_SOCIOPAGOMTTO.' spm', 'spm.idMovimiento = m.idMovimiento AND spm.eliminado=0 AND spm.idPersona=m.idPersona ','LEFT');
        if ($parametros['membresia']>0) {
            $this->db->where('m.idUnicoMembresia', $parametros['membresia']);
        }
        if (!$eliminados) {
            $this->db->where('m.eliminado', 0);
        }
        if ($totales == 0) {
            $this->db->limit($registros, $posicion);
        }
        $this->db->where('m.arco', 0);
        $this->db->group_by('m.idMovimiento')->order_by('m.idMovimiento', 'DESC');
        $query = $this->db->get();

        if ($query->num_rows>0) {
            if ($totales == 1) {
                return $query->num_rows;
            }
            return $query->result_array();
        }

        if ($totales == 1) {
            return 0;
        }

        return $datos;
    }

    /**
     * [listaAjustes description]
     *
     * @param  integer $idUn      [description]
     * @param  integer $idPersona [description]
     * @param  date    $fecha     [description]
     * @param  integer $tipo      [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]             [description]
     */
    public function listaAjustes($idUn=0, $idPersona=0, $tipoFecha=0, $fechaInicio='0000-00-00', $fechaFin='0000-00-00',
        $tipo=0, $buscaPNC=0, $soloPend=0, $idCategoriaPNC=0, $idProductoNoConforme=0)
    {
        settype($idUn, 'integer');
        settype($idPersona, 'integer');
        settype($tipo, 'integer');
        settype($buscaPNC, 'integer');
        settype($soloPend, 'integer');
        settype($idCategoriaPNC, 'integer');
        settype($idProductoNoConforme, 'integer');

        if ($fechaInicio=='0000-00-00' || $fechaInicio=='') {
            $fechaInicio = date('Y-m').'-01';
        }
        if ($fechaFin=='0000-00-00' || $fechaFin=='') {
            $fechaFin = date('Y-m-d');
        }

        $resultado = array();

        $per = $this->session->userdata('idPersona');
        $aplicar = $this->permisos_model->validaTodosPermisos($this->_aplicarAjuste);
        $auditoria = $this->permisos_model->validaTodosPermisos($this->_permisoAuditoria);

        $sql = "SELECT idRegion
            FROM region
            WHERE idPersonaDirector=$per OR idPersonaGerente=$per AND activo=1";
        $q_r = $this->db->query($sql);

        if ($q_r->num_rows() > 0) {
            $fila = $q_r->row_array();
            $idRegion = $fila['idRegion'];
            $aplicar = true;
        } else {
            $idRegion = 0;
        }

        $sql = "SELECT DISTINCT ma.idMovimientoAjuste, ma.idMovimiento, ma.idMovimientoCtaContable, u.nombre AS club, mem.idMembresia AS membresia,
                ma.tipo, ma.fechaRegistro AS fechaSolicitud, ma.idMotivoCorreccion, mc.descripcion AS origen,
                ma.importeOriginal, ma.importeAjustado, ma.motivo, ma.estatus, mar.respuesta AS rechazo, m.descripcion AS cargo,
                UPPER(CONCAT_WS(' ',p.nombre, p.paterno, p.materno)) as solicita, mem.idUn AS clubMembresia,
                ma.pnc, cpnc.descripcion AS pncDescripcion, c_pnc.descripcion AS catDescripcion
            FROM movimientoajuste ma
            INNER JOIN movimiento m ON m.idMovimiento=ma.idMovimiento
                AND m.eliminado=0
            INNER JOIN motivocorreccion mc ON mc.idMotivoCorreccion=ma.idMotivoCorreccion
            INNER JOIN persona p ON p.idPersona=ma.idPersonaSolicita
            LEFT JOIN movimientoajusterespuesta mar ON mar.idMovimientoAjuste=ma.idMovimientoAjuste
            LEFT JOIN paqueteimpacto pi ON pi.idMovimiento=m.idMovimiento
            LEFT JOIN movimientoctacontable mcc ON mcc.idMovimientoCtaContable=ma.idMovimientoCtaContable
                AND mcc.eliminado=0
            LEFT JOIN membresia mem ON mem.idUnicoMembresia=if(ma.idMovimiento=0, ma.idUnicoMembresia, m.idUnicoMembresia)
            LEFT JOIN un u ON u.idUn=mem.idUn
            LEFT JOIN categoriaproductonoconforme cpnc ON cpnc.idCategoriaProductoNoConforme=ma.idCategoriaProductoNoConforme
            LEFT JOIN categoriapnc c_pnc ON cpnc.idCategoriaPNC=c_pnc.idCategoriaPNC
            WHERE ma.fechaEliminacion='0000-00-00 00:00:00' ";
        if ($idRegion>0) {
            $sql .= " AND u.idRegion=".$idRegion." AND pi.idPaqueteImpacto IS NULL";
            //$sql .= " AND u.idRegion=".$idRegion." AND (ma.tipo='Cancelacion' OR ".
            //    "(ma.tipo='Ajuste' AND mcc.numeroCuenta IN ('2013', '4101', '4102', '4103', '4201', '4202', '4203')))".
            //    " AND pi.idPaqueteImpacto IS NULL";
        }

        if ($buscaPNC==1) {
            $sql .= " AND ma.pnc=1 ";
        }
        if ($tipo>0) {
            $sql .= " AND ma.tipo=".$tipo;
        }

        if ($tipoFecha==0) {
            $sql .= " AND DATE(ma.fechaRegistro) BETWEEN '".$fechaInicio."' AND '".$fechaFin."' ";
        } else {
            $sql .= " AND DATE(mar.fechaRegistro) BETWEEN '".$fechaInicio."' AND '".$fechaFin."' ";
        }

        if ($aplicar==false && $auditoria==false) {
            $sql .= " AND ( (ma.idUn=".$idUn." OR ma.idPersonaSolicita=".$this->session->userdata('idPersona').
                ") AND (ma.estatus='Pendiente' OR (mar.idMovimientoAjusteRespuesta IS NOT NULL )) ) ";
        } else {
            if ($buscaPNC==0 && $tipoFecha==0) {
                $sql .= " AND ma.estatus='Pendiente'";
            }
            if ($idUn>0) {
                $sql .= " AND ma.idUn=".$idUn;
            }
        }

        if($soloPend==1) {
            $sql .= " AND ma.estatus='Pendiente'";
        }

        if ($idCategoriaPNC>0) {
            $sql .= " AND cpnc.idCategoriaPNC=".$idCategoriaPNC;
        }

        if ($idProductoNoConforme>0) {
            $sql .= " AND ma.idCategoriaProductoNoConforme=".$idProductoNoConforme;
        }

        if ($tipoFecha==0) {
            $sql .= " ORDER BY ma.fechaRegistro ";
        } else {
            $sql .= " ORDER BY mar.fechaRegistro ";
        }

        $query = $this->db->query($sql);
        if ($query->num_rows()>0) {
            return $query->result_array();
        }

        return $resultado;
    }

    /**
     * Lista meses sin intereses activos
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function listaMesesSinIntereses()
    {
        $lista= Array();
        $lista['0']='0';
        $this->db->select('numeroMeses');
        $this->db->where('activo', '1');
        $query = $this->db->order_by('numeroMeses')->get('periodomsi');

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->numeroMeses] = $fila->numeroMeses;
            }
        }
        return $lista;
    }

    /**
     * Lista estus Movimiento
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function listaTipoEstatus()
    {
        $lista = array();
        $lista['0'] = '';

        $this->db->select('idTipoEstatusMovimiento, descripcion');
        $this->db->where('activo', '1');
        $query = $this->db->order_by('descripcion')->get(TBL_TIPOESTATUSMOVIMIENTO);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idTipoEstatusMovimiento] = $fila->descripcion;
            }
        }

        return $lista;
    }

    /**
     * Ejecuta proceso para vailidar montos menores de $50 pesos
     *
     * @param integer $idFactura
     *
     * @author Gustavo Bonilla
     *
     * @return array
     */
    public function movFacturadoConEfectivo($idFactura)
    {
        settype($idFactura, 'integer');

        $numReg     = 0;
        $resultado  = array();

        if($idFactura == 0) {
            return;
        }

        $sql = 'SELECT faccc.*
            FROM crm.factura fac
            INNER JOIN crm.facturacortecaja faccc ON faccc.idFactura=fac.idFactura
                AND faccc.idFormaPago IN (1)
            WHERE fac.idFactura IN ('.$idFactura.')
            ORDER BY fac.idFactura';
        $rs = $this->db->query($sql);
        $numReg = $rs->num_rows();
        if( $numReg>0 ){
            $resultado  = $rs->result_array();
        }

        return $resultado;
    }

    /**
     * Ejecuta proceso para vailidar montos menores de $50 pesos
     *
     * @param integer $idFactura
     *
     * @author Gustavo Bonilla
     *
     * @return array
     */
    public function movFacturadoConEfectivoDetalle($idFactura)
    {
        settype($idFactura, 'integer');

        $numReg     = 0;
        $resultado  = '';

        if($idFactura == 0) {
            return;
        }

        $sql = 'SELECT
                CONCAT_WS(\'|\',
                    IF(unfac.idTipoUn IN (4), \'Upster\', \'Sports World\'),
                    o.razonSocial,
                    unmem.nombre, mem.idMembresia,
                    mov.idMovimiento, faccc.importe, faccc.fecha, \'Reimpresion de comprobante\', unfac.nombre,
                    mov.idUnicoMembresia
                ) AS datosComprobanteEfectivo
            FROM crm.factura fac
            INNER JOIN crm.facturacortecaja faccc ON faccc.idFactura=fac.idFactura
                AND faccc.idFormaPago IN (1)
            INNER JOIN crm.facturamovimiento facm ON facm.idFactura=fac.idFactura
            INNER JOIN crm.un unfac ON unfac.idUn=fac.idUn
            INNER JOIN crm.operador o ON o.idOperador=unfac.idOperador
            INNER JOIN crm.movimiento mov ON mov.idMovimiento=facm.idMovimiento
            INNER JOIN crm.membresia mem ON mem.idUnicoMembresia=mov.idUnicoMembresia
            INNER JOIN crm.un unmem ON unmem.idUn=mem.idUn
            WHERE fac.idFactura IN ('.$idFactura.')
            ORDER BY fac.idFactura';
        $rs = $this->db->query($sql);
        $numReg = $rs->num_rows();
        if( $numReg>0 ){
            $resultado  = $rs->row()->datosComprobanteEfectivo;
        }

        return $resultado;
    }

    /**
     * Ejecuta proceso para vailidar montos menores de $50 pesos
     *
     * @param integer $idMovimiento
     *
     * @author Jorge Cruz
     *
     * @return void
     */
    public function mttoMontosMenores($idMovimiento)
    {
        settype($idMovimiento, 'integer');
        if($idMovimiento == 0) {
            return;
        }
        $sql = 'CALL spMttoMontoMenores('.$idMovimiento.');';
        $query = DB::connection('crm')->select($sql);
    }
    
    /**
     * [obtenAjuste description]
     *
     * @param  integer $idMovimientoAjuste [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]                     [description]
     */
    public function obtenAjuste($idMovimientoAjuste)
    {
        settype($idMovimientoAjuste, 'integer');

        $resultado = array();

        if ($idMovimientoAjuste<=0) {
            return $resultado;
        }

        $this->db->select(
            'idMovimiento, idMovimientoCtaContable, idMotivoCorreccion, idPersonaSolicita, '.
            'tipo, importeOriginal, importeAjustado, estatus, motivo, fechaRegistro, idUn'
        );
        $this->db->from(TBL_MOVIMIENTOAJUSTE);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idMovimientoAjuste', $idMovimientoAjuste);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $resultado = $query->row_array();
        }
        return $resultado;
    }


    /**
     * Regresa un arreglo con todos los datos de un movimiento
     * - Arreglo movimiento => Datos del movimiento
     * - Arreglo movctacont => Arreglo con los registros de las cuentas contables
     * - Arreglo resultado => Contiene OK o Error
     *
     * @autor   Gustavo Bonilla Zagal
     */
    public function obtenDatosMov_MovCC($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $datos = array();
        $datos["movimiento"] = array();
        $datos["movctacont"] = array();
        $datos["resultado"]  = "Error";

        if ($idMovimiento==0 ) {
            $datos["resultado"] .= ", parametros faltantes";
            return $datos;
        }

        $this->db->select("mov.idUn, mov.idUnicoMembresia, mov.idPersona, mov.idTipoEstatusMovimiento, ".
            "mov.descripcion, mov.importe, mov.iva, mov.msi, mov.origen, IFNULL(nc.idFinanzasNotaCredito, 0) AS idNotaCredito", false);
        $this->db->from(TBL_MOVIMIENTO." mov");
        $this->db->join(TBL_FACTURAMOVIMIENTO." facm", "facm.idMovimiento=mov.idMovimiento", "left");
        $this->db->join(TBL_FINANZASNOTACREDITO." nc", "nc.idFactura=facm.idFactura AND YEAR(nc.fechaCancelacion)=0", "left");
        $this->db->where('mov.eliminado', 0);
        $this->db->where("mov.idMovimiento", $idMovimiento);
        $this->db->group_by("mov.idMovimiento");
        $rsMov=$this->db->get();

        if ($rsMov->num_rows>0) {
            foreach ($rsMov->result_array() as $row) {
                $datos["movimiento"] = $row;
            }

            $this->db->select("movcc.idMovimientoCtaContable, movcc.idTipoMovimiento, movcc.idUn, movcc.fechaAplica, ".
                "movcc.numeroCuenta, movcc.cuentaProducto, movcc.importe, movcc.idPromocion", false);
            $this->db->from(TBL_MOVIMIENTOCTACONTABLE." movcc");
            $this->db->where("movcc.eliminado", 0);
            $this->db->where("movcc.idMovimiento", $idMovimiento);
            $this->db->order_by("movcc.idMovimientoCtaContable");
            $rsMovCC=$this->db->get();

            if ($rsMovCC->num_rows>0) {
                foreach ($rsMovCC->result_array() as $row) {
                    $datos["movctacont"][$row["idMovimientoCtaContable"]] = $row;
                }
            }
        } else {
            $datos["resultado"].=", no se encontro el movimiento.";
            return $datos;
        }

        return $datos;
    }

    /**
     * Obtiene datos de la tabla movimientoctacontable
     *
     * @param array $datos Datos para filtrar resultados
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenDatosMovCtaContable ($datos)
    {
        settype($datos['idMovimiento'], 'integer');
        settype($datos['idTipoMovimiento'], 'integer');

        $res = array();

        if ($datos['idMovimiento']<=0) {
            return $res;
        }
        $this->db->select(
            'mcc.idMovimientoCtaContable, mcc.idMovimiento, mcc.idUn, '.
            'mcc.idTipoMovimiento, mcc.numeroCuenta, mcc.cuentaProducto, '.
            'mcc.idPromocion, mcc.fechaAplica, mcc.importe', false
        );
        if ($datos['idMovimiento']) {
            $this->db->where('mcc.idMovimiento', $datos['idMovimiento']);
        }
        if ($datos['idTipoMovimiento']>0) {
            $this->db->where('mcc.idTipoMovimiento', $datos['idTipoMovimiento']);
        }
        $this->db->where('mcc.eliminado', 0);

        $query = $this->db->get(TBL_MOVIMIENTOCTACONTABLE." mcc");

        if ($query->num_rows) {
            $res = $query->row_array();
        }
        return $res;
    }

    /**
     * Obtiene datos del movimiento de mtto
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     * @param integer $idPersona        Identificador de persona
     * @param integer $idUn             Identificador de unidad de negocio
     * @param integer $idMantenimiento  Identificador de mantenimiento
     * @param integer $idEsquemaPago
     * @param integer $tipoAdeudo       Tipo de adeudo a evaluar (1 - Vigentes, 2 - Vencidos, 3 - Totales)
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenDatosMovimientoMtto ($idUnicoMembresia, $idPersona, $idUn, $idMantenimiento, $idEsquemaPago = ESQUEMA_PAGO_CONTADO, $tipoAdeudo = 1)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPersona, 'integer');
        settype($idUn, 'integer');
        settype($idMantenimiento, 'integer');

        $datos = array();
        $diaActual = (int)date('d');
        $fechaInicio = date('Y-m').'-01';

        if (! $idUnicoMembresia or ! $idPersona or ! $idUn or ! $idMantenimiento) {
            return $datos;
        }
        $diaCorte = $this->diaCorte($idUnicoMembresia);
        settype($diaCorte, 'integer');

        $where = array(
            'm.idUnicoMembresia'   => $idUnicoMembresia,
            'spm.idPersona'        => $idPersona,
            'm.idUn'               => $idUn,
            'spm.idMantenimiento'  => $idMantenimiento,
            'spm.idEsquemaPago'    => $idEsquemaPago,
            'mcc.idTipoMovimiento' => TIPO_MOVIMIENTO_MANTENIMIENTO,
            'm.eliminado'          => 0,
            'mcc.eliminado'        => 0,
            'spm.eliminado'        => 0
        );
        switch ($tipoAdeudo) {
        case 1:
            if ($diaActual > $diaCorte) {
                $this->db->where('mcc.fechaAplica >=', date('Y-m-d'));
            } else {
                $this->db->where('mcc.fechaAplica >=', $fechaInicio);
            }
            break;
        case 2:
            if ($diaActual > $diaCorte) {
                $this->db->where('mcc.fechaAplica <', date('Y-m-d'));
            } else {
                $this->db->where('mcc.fechaAplica <', $fechaInicio);
            }
            break;
        case 3:
            break;
        }
        $this->db->join(TBL_MOVIMIENTO." m", "spm.idMovimiento = m.idMovimiento", "inner");
        $this->db->join(TBL_MOVIMIENTOCTACONTABLE." mcc", "m.idMovimiento = mcc.idMovimiento");
        $query = $this->db->select(
            "spm.idMovimiento, spm.fechaInicio, spm.fechaFin, m.importe, m.idTipoEstatusMovimiento, mcc.fechaAplica, mcc.idMovimientoCtaContable",
            false
        )->get_where(TBL_SOCIOPAGOMTTO." spm", $where);

        if ($query->num_rows == 1) {
            $datos               = $query->row_array();
            $datos['numCuentas'] = $query->num_rows;
        } elseif ($query->num_rows > 1) {
            $datos['numCuentas'] = $query->num_rows;
        }
        return $datos;
    }

    /**
    * Regresa datos de sociopagomtto
    *
    * @param integer $idSocioPagoMtto
    *
    * @author Jonathan Alcantara
    *
    * @return array
    */
    public function obtenInfoPagoMtto ($idSocioPagoMtto)
    {
        settype($idSocioPagoMtto, 'integer');
        $datos = array();

        if (! $idSocioPagoMtto) {
            return $datos;
        }
        $where = array('idSocioPagoMtto' => $idSocioPagoMtto);
        $query = $this->db->select(
            'fechaInicio, fechaFin, idPersona, idEsquemaPago, idUnicoMembresia,
            idMantenimiento, activo, origen, porcentaje, idSocio'
        )->get_where(TBL_SOCIOPAGOMTTO, $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Obtiene todos los movimientos pendientes de una membresia
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     * @param integer $idUn             Identificador de unidad de negocio
     * @param integer $idPersona        Identificador de persona
     * @param integer $diasRango        Cantidad de dias vencidos de los movimientos a obtener
     * @param integer $idTipoMovimiento Identificador de tipomovimiento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenMovimientosPendientes ($idUnicoMembresia, $idUn, $idPersona, $diasRango = 0, $idTipoMovimiento = 0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idUn, 'integer');
        settype($idPersona, 'integer');
        settype($idTipoMovimiento, 'integer');
        $datos = array();
        $rango = "";

        if ( ! $idUnicoMembresia) {
            return $datos;
        }
        $where = array(
            'mov.idUnicoMembresia'        => $idUnicoMembresia,
            'mov.eliminado'               => 0,
            'mcc.eliminado'               => 0,
            'mov.idTipoEstatusMovimiento' => MOVIMIENTO_PENDIENTE,
            'mem.idUn'                    => $idUn,
            'mov.idPersona'               => $idPersona
        );
        if ($idTipoMovimiento) {
            $where['mcc.idTipoMovimiento'] = $idTipoMovimiento;
        }
        $this->db->join(TBL_MOVIMIENTO.' mov', 'mem.idUnicoMembresia = mov.idUnicoMembresia AND mov.importe > 0', 'inner');
        $this->db->join(TBL_TIPOESTATUSMOVIMIENTO .' tem', 'tem.idTipoEstatusMovimiento = mov.idTipoEstatusMovimiento', 'inner');
        $this->db->join(TBL_UN .' u', 'u.idUn = mem.idUn', 'inner');
        if ($diasRango) {
            if ($diasRango == 30) {
                $rango = " AND DATEDIFF(DATE(NOW()), mcc.fechaAplica) >= 0 AND DATEDIFF(DATE(NOW()), mcc.fechaAplica) <= ".$diasRango." ";
            } elseif ($diasRango == 60) {
                $rango = " AND DATEDIFF(DATE(NOW()), mcc.fechaAplica) >= 31 AND DATEDIFF(DATE(NOW()), mcc.fechaAplica) <= ".$diasRango." ";
            } elseif ($diasRango == 90) {
                $rango = " AND DATEDIFF(DATE(NOW()), mcc.fechaAplica) >= 61 AND DATEDIFF(DATE(NOW()), mcc.fechaAplica) <= ".$diasRango." ";
            } elseif ($diasRango == 91) {
                $rango = " AND DATEDIFF(DATE(NOW()), mcc.fechaAplica) > 90 ";
            }
        }
        $this->db->join(TBL_MOVIMIENTOCTACONTABLE.' mcc', 'mcc.idMovimiento = mov.idMovimiento '.$rango, 'inner');
        $query = $this->db->select(
            'mov.idMovimiento, mov.fechaRegistro, mov.idTipoEstatusMovimiento, '.
            'tem.descripcion AS estatus, mem.idUn, u.nombre AS club,  '.
            'mov.importe, mov.iva, mov.descripcion, mov.msi, mcc.fechaAplica'
        )->order_by('fechaRegistro', 'DESC')->group_by('mov.idMovimiento')->get_where(TBL_MEMBRESIA.' mem', $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Regresa numero de meses msi
     *
     * @param integer $idPeriodoMsi Identificador de periodo msi
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function obtenNumeroMesesMsi ($idPeriodoMsi)
    {
        settype($idPeriodoMsi, 'integer');

        if ( ! $idPeriodoMsi) {
            return $idPeriodoMsi;
        }
        $where = array('idPeriodoMsi' => $idPeriodoMsi, 'activo' => 1);
        $query = $this->db->select('numeroMeses')->get_where(TBL_PERIODOMSI, $where);

        if ($query->num_rows) {
            $idPeriodoMsi = $query->row()->numeroMeses;
        }
        return $idPeriodoMsi;
    }

    /**
     * Obtiene el origen de un movimiento
     *
     * @param int $idMovimiento Identificador de movimiento
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function obtenOrigen ($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $origen = '';

        if ( ! $idMovimiento) {
            return $origen;
        }
        $this->db->select('origen');
        $this->db->from(TBL_MOVIMIENTO);
        $this->db->where('idMovimiento', $idMovimiento);

        $query = $this->db->get();

        if ($query->num_rows) {
            $origen = $query->row()->origen;
        }
        return $origen;
    }

    /**
     * Obtiene lista de tipo de movimientos
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTipoMovimientos ()
    {
        $movs  = array();
        $where = array("activo" => 1);
        $query = $this->db->select(
            "idTipoMovimiento, descripcion AS tipoMovimiento"
        )->get_where(TBL_TIPOMOVIMIENTO, $where);

        if ($query->num_rows > 0) {
            $movs = $query->result_array();
        }
        return $movs;
    }

	/**
     * Obtiene el total de registros en movimientoctacontable de un movimiento
     *
     * @param integer $idMovimiento Identificador de movimiento
     *
     * @author  Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTotalCuentasMovimiento ($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $datos = array(
            'mensaje'      => 'Error faltan datos',
            'error'        => 1,
            'totalCuentas' => 0
        );
        if ( ! $idMovimiento) {
            return $datos;
        }
        $datos['mensaje'] = '';
        $datos['error']   = 0;

        $where = array(
            'idMovimiento' => $idMovimiento,
            'eliminado'    => 0
        );
        $query = $this->db->select(
            "COUNT(DISTINCT idMovimientoCtaContable)AS totalCuentas", false
        )->get_where(TBL_MOVIMIENTOCTACONTABLE, $where);

        $datos['totalCuentas'] = $query->row()->totalCuentas;

        return $datos;
    }

    /**
     * Obtiene todos los campos de un movimiento
     *
     * @param integer $idMovimiento Identificador unico de membresia
     *
     * @author Gustavo Bonilla
     *
     * @return array
     */
    public function obtenerCamposMovimiento($idMovimiento)
    {
        settype($idUnicoMembresia, 'integer');

        $datos = array();
        if ($idMovimiento==0) {
            $datos["idMovimiento"]=0;
        }

        $this->db->select(
            'idMovimiento, idTipoEstatusMovimiento, descripcion, importe, iva, '.
            'idUN, idPersona, idUnicoMembresia, idProducto, msi, origen, '.
            'fechaRegistro, fechaActualizacion, fechaEliminacion', false
        );
        $this->db->from(TBL_MOVIMIENTO);
        $this->db->where("idMovimiento IN (".$idMovimiento.")");
        $rs=$this->db->get();
        $numMovs=$rs->num_rows;
        if ($numMovs==1 ) {
            //foreach( $rs->fetch_array )
            $datos=$rs->row_array();
        }

        return $datos;
    }

    /**
     *
     * @param type $idMovimientoCtaContable
     * @param type $idMovimiento
     * @return type
     */
    public function obtenerDatosMovimientoCtaContable($idMovimientoCtaContable, $idMovimiento=0)
    {
        settype($idMovimientoCtaContable, 'integer');
        settype($idMovimiento, 'integer');

        $this->db->select('idMovimientoCtaContable, idMovimiento');
        $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
        if ( $idMovimiento==0 ) {
            $this->db->where('idMovimientoCtaContable', $idMovimientoCtaContable);
        } else {
            $this->db->where('idMovimiento', $idMovimiento);
        }
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows==1) {
            return  $query->row_array();
        } else {
            return 0;
        }
    }

    /**
     *
     * @param type $idUnicoMembresia
     * @return type
     */
    public function obtenerIdMovimientoComision($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $this->db->select('cm.idMovimiento');
        $this->db->from(TBL_COMISION.' c');
        $this->db->join(TBL_COMISIONMOVIMIENTO.' cm', 'cm.idComision = c.idComision');
        $this->db->join(TBL_MOVIMIENTO.' m', 'm.idMovimiento = cm.idMovimiento');
        $where = array(
            'c.idTipoComision'    => TIPO_COMISION_VENTA_VENDEDOR,
            'm.idUnicoMembresia'  => $idUnicoMembresia,
            'cm.fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $movimiento = $fila->idMovimiento;
            }
        }
        if (isset($movimiento)) {
            return $movimiento;
        } else {
            return 0;
        }
    }

    /**
     * Regresa el importe total de la sumatoria de los registro en la cuenta contable
     *
     * @param type $idMovimiento
     *
     * @return type
     */
    public function obtenerImporteTotal($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $total = 0;
        $sql = "SELECT SUM(importe) AS total
            FROM ".TBL_MOVIMIENTOCTACONTABLE."
            WHERE idMovimiento=".$idMovimiento." AND eliminado=0";
        $query = $this->db->query($sql);

        if ($query->num_rows()>0) {
            $fila = $query->row_array();
            $total = $fila['total'];
        }

        return $total;
    }

    /**
     * [rechazaAjuste description]
     *
     * @param  [type]  $idMovimientoAjuste            [description]
     * @param  [type]  $motivo                        [description]
     * @param  integer $pnc                           [description]
     * @param  integer $idCategoriaProductoNoConforme [description]
     *
     * @author  Jorge Cruz
     *
     * @return [type]                                 [description]
     */
    public function rechazaAjuste($idMovimientoAjuste, $motivo, $pnc=0, $idCategoriaProductoNoConforme = 0)
    {
        settype($idMovimientoAjuste, 'integer');
        settype($pnc, 'integer');
        settype($idCategoriaProductoNoConforme, 'integer');

        if ($idMovimientoAjuste==0 || $motivo=='') {
            return false;
        }

        $where = array(
            'idMovimientoAjuste' => $idMovimientoAjuste,
            'estatus'            => 'Pendiente',
            'fechaEliminacion'   => '0000-00-00 00:00:00'
        );
        $set = array(
            'estatus'                       => 'Rechazado',
            'pnc'                           => $pnc,
            'idCategoriaProductoNoConforme' => $idCategoriaProductoNoConforme
        );
        $this->db->update(TBL_MOVIMIENTOAJUSTE, $set, $where);

        $total = $this->db->affected_rows();
        if ($total > 0) {
            $s_respuesta = array(
                'idMovimientoAjuste' => $idMovimientoAjuste,
                'respuesta'          => utf8_decode($motivo)
            );
            $this->db->insert(TBL_MOVIMIENTOAJUSTERESPUESTA, $s_respuesta);

            return true;
        }

        return false;
    }

    /**
     * [registraAjuste description]
     *
     * @param  [type] $idMovimiento            [description]
     * @param  [type] $idMovimientoCtaContable [description]
     * @param  [type] $idMotivoCorreccion      [description]
     * @param  [type] $tipo                    [description]
     * @param  [type] $importeOriginal         [description]
     * @param  [type] $importeAjustado         [description]
     * @param  [type] $motivo                  [description]
     *
     * @author Ricardo Lima
     *
     * @return [type]                          [description]
     */
    public function registraAjuste($idMovimiento, $idMovimientoCtaContable, $idMotivoCorreccion,
        $tipo, $importeOriginal, $importeAjustado, $motivo)
    {
        settype($idMovimiento, 'integer');
        settype($idMovimientoCtaContable, 'integer');
        settype($idMotivoCorreccion, 'integer');
        settype($tipo, 'integer');
        settype($importeOriginal, 'float');
        settype($importeAjustado, 'float');

        $id = 0;

        $info = array(
            'idMovimiento'            => $idMovimiento,
            'idMovimientoCtaContable' => $idMovimientoCtaContable,
            'idMotivoCorreccion'      => $idMotivoCorreccion,
            'idPersonaSolicita'       => $this->session->userdata('idPersona'),
            'tipo'                    => $tipo,
            'importeOriginal'         => $importeOriginal,
            'importeAjustado'         => $importeAjustado,
            'motivo'                  => $motivo,
            'idUn'                    => $this->session->userdata('idUn')
        );
        $this->db->insert(TBL_MOVIMIENTOAJUSTE, $info);

        $total = $this->db->affected_rows();
        if ($total > 0) {
            $id = $this->db->insert_id();
        }

        return $id;
    }

    /**
     * [registraAjusteMembresia description]
     *
     * @param  [type] $idMovimiento            [description]
     * @param  [type] $motivo                  [description]
     * @param  [type] $tipo                    [description]
     * @param  [type] $desMotivo               [description]
     * @param  [type] $uni_pnc                 [description]
     * @param  [type] $catPNC                  [description]
     *
     * @author Ricardo Lima
     *
     * @return [type]                          [description]
     */
    public function registraAjusteMembresia($idMembresia, $motivo, $tipo, $desMotivo, $uni_pnc, $catPNC)
    {
        settype($idMembresia, 'integer');
        settype($motivo, 'integer');
        settype($tipo, 'integer');
        settype($desMotivo, 'string');
        settype($uni_pnc, 'array');
        settype($catPNC, 'integer');

        $id = 0;

        $info = array(
            'tipo'                    => $tipo,
            'idMotivoCorreccion'      => $motivo,
            'motivo'                  => $desMotivo,
            'idUnicoMembresia'        => $idMembresia,
            'idUn'                    => $this->session->userdata('idUn'),
            'pnc'                     => '1',
            'idCategoriaProductoNoConforme' =>  $catPNC,
        );

        $this->db->insert(TBL_MOVIMIENTOAJUSTE, $info);

        $total = $this->db->affected_rows();
        if ($total > 0) {
            $id = $this->db->insert_id();

            if (!empty($uni_pnc) && is_array($uni_pnc) ) {
                foreach($uni_pnc as $u) {
                    $set = array(
                        'idMovimientoAjuste'         => $id,
                        'idUnidadNegocioCorporativo' => $u,
                        'fechaRegistro'              => date('Y-m-d H:i:s')
                    );
                    $this->db->insert(TBL_SELECCIONUNIDADNEGOCIOMOVIMIENTO, $set);
                }
            }
        }

        return $id;
    }


    /**
     * [registraDevengado description]
     *
     * @param  [type] $idMovimientoCtaContable [description]
     * @param  [type] $idTipoDevengadoProducto [description]
     * @param  [type] $idTipoDevengado         [description]
     * @param  [type] $aplicaciones            [description]
     *
     * @return [type]                          [description]
     */
    public function registraDevengado($idMovimientoCtaContable, $idTipoDevengadoProducto, $idTipoDevengado, $aplicaciones)
    {
        settype($idMovimientoCtaContable, 'integer');
        settype($idTipoDevengadoProducto, 'integer');
        settype($idTipoDevengado, 'integer');
        settype($aplicaciones, 'integer');

        $id = 0;

        if ($idMovimientoCtaContable>0 && $idTipoDevengadoProducto>0 && $idTipoDevengado>0 AND $aplicaciones>0) {
            $info = array(
                'idMovimientoCtaContable' => $idMovimientoCtaContable,
                'idTipoDevengadoProducto' => $idTipoDevengadoProducto,
                'idTipoDevengado'         => $idTipoDevengado,
                'numeroAplicaciones'      => $aplicaciones
            );
            $this->db->insert(TBL_MOVIMIENTODEVENGADO, $info);

            $total = $this->db->affected_rows();
            if ($total > 0) {
                $id = $this->db->insert_id();
            }
        }

        return $id;
    }


    /**
     * [spInsertarMovDiv description]
     *
     * @param  [type] $datos [description]
     *
     * @return [type]        [description]
     */
    public function spInsertarMovDiv($datos)
    {
        settype($datos["idMovimiento"], "integer");
        settype($datos["nuevosImportes"], "string");

        $resultado="";

        $this->db->query("SET @resGeneracion='';");
        $this->db->query("CALL crm.spGeneracionManualMovimientosDiv(".$datos["idMovimiento"].", '".$datos["nuevosImportes"]."', @resGeneracion);");
        $rs=$this->db->query("SELECT @resGeneracion AS res;");
        if( $rs->num_rows()>0 ){
            foreach( $rs->result_array() as $row ){
                $resultado=$row["res"];
            }
        }

        return $resultado;
    }

    /**
     * Regresa el identificador unico de membresia asignado al movimiento solicitado
     *
     * @param int $idMovimiento Identificador unico de movimiento
     *
     * @author Jorge Cruz
     *
     * @return int
     */
    public function unicoMembresia($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        if ($idMovimiento <= 0) {
            return 0;
        }

        $idUnicoMembresia = 0;
        $this->db->select('idUnicoMembresia');
        $this->db->where('idMovimiento', $idMovimiento);
        $query = $this->db->get(TBL_MOVIMIENTO);

        if ($query->num_rows()>0) {
            $idUnicoMembresia = $query->row()->idUnicoMembresia;
        }

        return $idUnicoMembresia;
    }


    /**
     * [validaDatosCFDI description]
     *
     * @param  integer $idMovimiento [description]
     *
     * @return boolean
     */
    public function validaDatosCFDI($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $res = false;

        if ($idMovimiento==0) {
            return $res;
        }

        $sql = "SELECT idMovimientoCtaContable
            FROM movimientoctacontable
            WHERE idMovimiento=$idMovimiento AND eliminado=0
                AND (cveProductoServicio='' OR cveUnidad='' OR cantidad=0)";
        $query = $this->db->query($sql);

        if ($query->num_rows() == 0) {
            $res = true;
        }

        return $res;
    }


    /**
     * Valida si el importe del movimiento coincide con el total por cta contable
     *
     * @param  integer $idMovimiento Identificado del movimiento
     *
     * @author Jorge Cruz <jorge.cruz@gmail.com>
     *
     * @return boolean
     */
    public function validaCtaContable($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        if ($idMovimiento==0) {
            return false;
        }

        $this->db->select('importe');
        $this->db->from(TBL_MOVIMIENTO);
        $this->db->where('eliminado', 0);
        $this->db->where('idMovimiento', $idMovimiento);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $row = $query->row_array();
            $importeTotal = $row['importe'];

            $this->db->select_sum('importe');
            $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
            $this->db->where('eliminado', 0);
            $this->db->where('idMovimiento', $idMovimiento);
            $query2 = $this->db->get();

            if ($query2->num_rows>0) {
                $row2 = $query2->row_array();
                $importeCta = $row2['importe'];

                if ($importeCta == $importeTotal) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Valida mov de ausencia
     *
     * @param integer $idMovimiento Identificador de movimiento
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function validaMovimientoAusencia($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $res = false;

        if ( ! $idMovimiento) {
            return $res;
        }
        $this->db->join(TBL_SOCIOPAGOMTTO.' spm', "spm.idMovimiento = mov.idMovimiento AND mov.idMovimiento = ".$idMovimiento, "INNER");
        $query = $this->db->select(
            'MAX(spm.ausencia) AS movAusencia', false
        )->get(TBL_MOVIMIENTO.' mov');

        return $query->row()->movAusencia ? true : false;
    }

    /**
     * Valida si un pase esta pagado
     *
     * @param integer $idPersona Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaPasePagado ($idPersona)
    {
        settype($idPersona, 'integer');

        $datos = array(
            'error'     => 1,
            'mensaje'   => 'Error faltan datos',
            'idFactura' => 0,
            'importe'   => 0,
        );

        if ( ! $idPersona) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';
        $where = array('p.idPersona' => $idPersona);

        $this->db->join(TBL_PASEMOVIMIENTO.' pm', "p.idPase = pm.idPase", 'INNER');
        $this->db->join(TBL_MOVIMIENTO.' m', "pm.idMovimiento = m.idMovimiento AND m.fechaEliminacion = '0000-00-00 00:00:00' AND m.idTipoEstatusMovimiento = ".MOVIMIENTO_PAGADO, 'INNER');
        $this->db->join(TBL_FACTURAMOVIMIENTO.' fm', " m.idMovimiento = fm.idMovimiento", 'LEFT');
        $this->db->join(TBL_FACTURA." f", "fm.idFactura = f.idFactura AND f.idTipoEstatusFactura = ".ESTATUS_FACTURA_PAGADA, 'LEFT');

        $query = $this->db->select("f.idFactura, m.importe", false)->get_where(TBL_PASE.' p', $where);

        if ($query->num_rows) {
            $datos['idFactura'] = $query->row()->idFactura;
            $datos['importe']   = $query->row()->importe;
        }
        return $datos;
    }

    /**
     * Revisa si el identificador de movimiento enviado tiene ajuste pendientes de validar
     *
     * @param  integer $idMovimiento Identificador de movimiento
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function validaAjustePendiente($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $this->db->select('idMovimiento');
        $this->db->from(TBL_MOVIMIENTOAJUSTE);
        $this->db->where('idMovimiento', $idMovimiento);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('estatus', 'Pendiente');
        $query = $this->db->get();

        if ($query->num_rows) {
            return true;
        }

        return false;
    }

    /**
     * obtenerCargoCredencial Obtiene número de cargos pagados de credencial
     * @param  int $idPersona
     * @param  int $idUnicoMembresia
     *
     *  @author Armando Paez
     *  @author David Arias
     *
     * @return int
     */
    public function obtenerCargoCredencial($idPersona, $idUnicoMembresia)
    {
        settype($idPersona, 'integer');
        settype($idUnicoMembresia, 'integer');

        $this->db->select('count(m.idMovimiento) as n');
        $this->db->from(TBL_MOVIMIENTO.' m')
            ->join(TBL_FACTURAMOVIMIENTO.' fm','m.idMovimiento = fm.idMovimiento')
            ->join(TBL_FACTURA.' f','fm.idFactura = f.idFactura')
            ->join(TBL_FINANZASNOTACREDITO.' fnc','f.idFactura = fnc.idFactura and year(fnc.fechaActivacion) > 0','left');
        $this->db->where('m.idPersona', $idPersona);
        if ($idUnicoMembresia>0) {
            $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
        }
        $this->db->where('m.eliminado', 0);
        $this->db->where('m.fechaRegistro >=', '2016-06-01 00:00:00');
        $this->db->where('m.idTipoEstatusMovimiento', MOVIMIENTO_PAGADO);
        $this->db->where('f.idTipoEstatusFactura', ESTATUS_FACTURA_PAGADA);
        $this->db->where('fnc.idFinanzasNotaCredito IS NULL');
        $this->db->where('m.idProducto',CARGO_CREDENCIAL);
        $query = $this->db->get();

        $rs = $query->row();

        return $rs->n;
    }

    /**
     * obtenerCargoCredencialPendiente Obtiene número de cargos pendientes de credencial
     * @param  int $idPersona
     * @param  int $idUnicoMembresia
     *
     *  @author David Arias
     *
     * @return int
     */
    public function obtenerCargoCredencialPendiente($idPersona, $idUnicoMembresia)
    {
        settype($idPersona, 'integer');
        settype($idUnicoMembresia, 'integer');

        $this->db->select('count(m.idMovimiento) as n');
        $this->db->from(TBL_MOVIMIENTO.' m');
        $this->db->where('m.idPersona', $idPersona);
        if ($idUnicoMembresia>0) {
            $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
        }
        $this->db->where('m.eliminado', 0);
        $this->db->where('m.fechaRegistro >=', '2016-06-01 00:00:00');
        $this->db->where('m.idTipoEstatusMovimiento', MOVIMIENTO_PENDIENTE);
        $this->db->where('m.idProducto',CARGO_CREDENCIAL);
        $query = $this->db->get();

        $rs = $query->row();

        return $rs->n;
    }

    /**
     * [obtenerDatosCredencial description]
     *
     *  @author Armando Paez
     *
     * @return [type] [description]
     */
    public function obtenerDatosCredencial()
    {
        $query = $this->db->select('mov.idPersona, mov.descripcion, concat_ws(" ",p.nombre,p.paterno,p.materno) as nombreCompleto, '
                                    .'p.fechaNacimiento,p.edad,cu.nombre as clubOrigen, cf.nombre as clubFactura, '
                                    .'m.idMembresia,'
                                    .'mov.origen as movOrigen,ifnull(enviado,"0") as enviado'
                                    .',mov.idMovimiento',false)
                ->from(TBL_MOVIMIENTO.' `mov`')
                ->join('crm.membresia m','mov.idUnicoMembresia = m.idUnicoMembresia')
                ->join('crm.facturamovimiento fm','mov.idMovimiento = fm.idMovimiento')
                ->join('crm.factura f','f.idFactura = fm.idFactura and year(f.fechaCancelacion) = 0')
                ->join('crm.un cf','cf.idUn = f.idUn')
                ->join('crm.persona p','mov.idPersona = p.idPersona and year(p.fechaEliminacion) = 0')
                ->join('crm.un cu','cu.idUn = mov.idUn')
                ->join('crm.credencialenvio cre','cre.idMovimiento = mov.idMovimiento and cre.fechaEliminacion = "0000-00-00 00:00:00" and cre.enviado = 0')
                ->where('mov.idProducto',390)
                ->where('mov.idTipoEstatusMovimiento',66)
                ->where('cre.cre.credencialEnvioId  is null')
                ->where("f.fechaParticion >= 20160601 and f.fechaParticioin <= 20160630")
                ->get();
        $rs = $query->result();
        return $rs;
    }


    public function obtieneCorreoFacturacion($idMovimiento)
    {
        $this->db->select('m.mail');
        $this->db->from('movimiento mo');
        $this->db->join('mail m', ' mo.idPersona=m.idPersona and  m.fechaEliminacion =\'0000-00-00 00:00:00\'');
        $this->db->where('mo.idMovimiento',$idMovimiento);
        $this->db->limit(1);
        $result=$this->db->get();
        if($result->num_rows()>0)
        {
            return $result->row()->mail;
        }
        return 0;

    }
}
