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

class Membresia extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.membresia';
    protected $primaryKey = 'idMembresia';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * actualiza alertaEmitida de sociomensaje en base al idsocioMensaje
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function actualizaAlertaMensajes($idSocioMensaje)
    {
        settype($idSocioMensaje, 'integer');

        $data = array('alertaEmitida'=> 1);

        $this->db->where('idSocioMensaje', $idSocioMensaje);
        $this->db->update('sociomensaje', $data);
        if($this->db->affected_rows()>0) {
            $regresa = 1;
        } else {
            $regresa = 0;
        }

        return $regresa;
    }

    /**
     * Actualiza convenio en membresía
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function actualizaConvenio($idUnicoMembresia, $idConvenioDetalle)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idConvenioDetalle, 'integer');

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $ci =& get_instance();
            $ci->load->model('mantenimientos_model');

            $nombre = $ci->convenios_model->obtieneNombreConvenio($idConvenioDetalle);

            $n =  explode('(', $nombre);
            $t = '';
            if (count($n)>0) {
                $t = ' ('.trim($n[0]).')';
            }

            $fila = $query->row_array();
            $idMembresia = $fila['idUnicoMembresia'];
            $datos = array ('idConvenioDetalle' => $idConvenioDetalle);
            $this->db->where('idUnicoMembresia', $idMembresia);
            $this->db->where('eliminado', 0);
            $this->db->update(TBL_MEMBRESIA, $datos);
            $this->permisos_model->log(utf8_decode("Se actualizó convenio (".date('Y-m-d').")".$t) , LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Actualiza mantenimiento para la conversion de mantenimiento
     *
     * @author Santa Garcia
     *
     * @return void
     */
    function actualizaConversionMtto($idConversion, $idMtto)
    {
        settype($idConversion, 'integer');
        settype($idMtto, 'integer');

        $this->db->select('idMantenimientoConversion');
        $this->db->from(TBL_MANTENIMIENTOCONVERSION);
        $where = array('idMantenimientoConversion' => $idConversion);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idMantenimientoConversion', $fila->idMantenimientoConversion);
                $datos = array ('idMantenimientoNuevo' => $idMtto);
                $this->db->update(TBL_MANTENIMIENTOCONVERSION, $datos);
            }
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se actualizo el mtto nuevo como conversion ('.$idConversion.')', LOG_MEMBRESIA);
            return true;
        }
    }

    /**
     * funcion que registra el descuento de mantenimiento para alguna membresia
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function actualizaDescuentoMtto($idUnicoMembresia, $idDescuento)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idDescuento, 'integer');

        if ($idDescuento==DESCUENTO_ESPECIAL || $idDescuento==DESCUENTO_VACACIONAL || $idDescuento==DESCUENTO_VACACIONAL_2
            || $idDescuento==DESCUENTO_VACACIONAL_3 || $idDescuento==DESCUENTO_VACACIONAL_4) {
            $ci =& get_instance();
            $ci->load->model('mantenimientos_model');

            $anterior = $ci->mantenimientos_model->mesAnteriorPagado($idUnicoMembresia);
            $v = $ci->mantenimientos_model->mesAnteriorVacacional($idUnicoMembresia);

            if ($anterior==false || $v==true) {
                return false;
            }

            $this->db->select('m.idMovimiento');
            $this->db->from(TBL_MOVIMIENTODESCUENTOMTTO.' md');
            $this->db->join(TBL_MOVIMIENTO.' m', 'md.idMovimiento = m.idMovimiento');
            $this->db->where_in('md.idMembresiaDescuentoMtto', array(DESCUENTO_ESPECIAL, DESCUENTO_VACACIONAL, DESCUENTO_VACACIONAL_2));
            $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
            $this->db->where('m.idUnicoMembresia <>', MOVIMIENTO_CANCELADO);
            $this->db->where('m.eliminado', 0);
            $this->db->where('YEAR(m.fechaRegistro)', date('Y'));
            $query = $this->db->get();

            if ($query->num_rows>=2) {
                return false;
            }

            if ($idDescuento==DESCUENTO_VACACIONAL || $idDescuento==DESCUENTO_VACACIONAL_2 ||
                $idDescuento==DESCUENTO_VACACIONAL_3 || $idDescuento==DESCUENTO_VACACIONAL_4) {
                $continuidad = 0;
                $sql = 'SELECT fncContinuidad('.$idUnicoMembresia.') AS continuidad;';
                $query = $this->db->query($sql);
                if ($query->num_rows > 0) {
                    foreach ($query->result() as $fila) {
                        $continuidad = $fila->continuidad;
                    }
                }

                if ($continuidad<3) {
                    $actual = $ci->mantenimientos_model->mesActualPagado($idUnicoMembresia);

                    if ($actual==true) {
                        return false;
                    }
                }

                $f_1 = date('Y-m').'-01';
                $f_1 = fecha_restar($f_1, 1);
                $this->db->select('m.idMovimiento');
                $this->db->from(TBL_MOVIMIENTODESCUENTOMTTO.' md');
                $this->db->join(TBL_MOVIMIENTO.' m', 'md.idMovimiento = m.idMovimiento');
                $this->db->join(TBL_SOCIOPAGOMTTO.' spm', "m.idMovimiento=spm.idMovimiento AND spm.fechafin='$f_1'");
                $this->db->where_in('md.idMembresiaDescuentoMtto', array(DESCUENTO_ESPECIAL, DESCUENTO_VACACIONAL, DESCUENTO_VACACIONAL_2));
                $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
                $this->db->where('m.eliminado', 0);
                $this->db->where('spm.eliminado', 0);
                $this->db->group_by('md.idMovimiento');
                $query = $this->db->get();

                if ($query->num_rows>=1) {
                    return false;
                }
            }
        }

        $this->db->select('descripcion');
        $this->db->from(TBL_MEMBRESIADESCUENTOMTTO);
        $this->db->where('idMembresiaDescuentoMtto', $idDescuento);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $descripcion = $fila['descripcion'];
        }

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $idMembresia = $fila['idUnicoMembresia'];
            $datos = array ('idMembresiaDescuentoMtto' => $idDescuento);
            $this->db->where('idUnicoMembresia', $idMembresia);
            $this->db->where('eliminado', 0);
            $this->db->update(TBL_MEMBRESIA, $datos);
            $this->permisos_model->log(utf8_decode("Se actualizó descuento ".$descripcion." (".date('Y-m-d').")") , LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        } else {
            return false;
        }
    }

    /**
     * [actualizaFechaInicioMtto description]
     *
     * @param  [type] $idUnicoMembresia [description]
     * @param  [type] $fechaInicioMtto  [description]
     *
     * @return [type]                   [description]
     */
    public function actualizaFechaInicioMtto($idUnicoMembresia, $fechaInicioMtto)
    {
        settype($idUnicoMembresia, 'integer');
        $datos = array ('fechaInicioMtto'  => $fechaInicioMtto);

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $id = $fila['idUnicoMembresia'];
            $this->db->where('idUnicoMembresia', $id);
            $this->db->update(TBL_MEMBRESIA, $datos);
            $this->permisos_model->log('Se actualizo fecha de Inicio de Mtto. a '.$fechaInicioMtto.' ', LOG_MEMBRESIA,$idUnicoMembresia);
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Actualiza el campo de formato una vez que se ha generado el formato de traspaso
     *
     * @author Santa Garcia
     *
     * @return integer
     */
    public function actualizaFormato($idMembresiaTraspaso, $activo, $unico)
    {
        settype($idMembresiaTraspaso, 'integer');
        settype($activo, 'integer');

        $this->db->select('idMembresiaTraspaso');
        $this->db->from(TBL_MEMBRESIATRASPASO);
        $where=array('idMembresiaTraspaso'=> $idMembresiaTraspaso,'fechaEliminacion'=> '0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idMembresiaTraspaso', $fila['idMembresiaTraspaso']);
            $datos = array ('formato'  => $activo);
            $this->db->update(TBL_MEMBRESIATRASPASO, $datos);
            $this->permisos_model->log(utf8_decode("Se generó formato de traspaso (".date('Y-m-d').")"), LOG_MEMBRESIA, $unico);
            return true;
       } else {
           return false;
       }
    }

    /**
     * Actualiza el tipo de mantenimiento de un socio
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function actualizaManttoSocio($idMantenimiento, $idUnicoMembresia)
    {
        $CI =& get_instance();
        $CI->load->model('mantenimientos_model');

        $resultado = false;

        settype($idMantenimiento, 'integer');
        settype($idUnicoMembresia, 'integer');

        if (($idMantenimiento == 0)or ($idUnicoMembresia == 0)) {
            return $resultado;
        }
        $mantenimiento = $CI->mantenimientos_model->obtenMantenimientoNombre($idMantenimiento);

        $where = array(
            'idUnicoMembresia' => $idUnicoMembresia
        );
        $set = array(
            'idMantenimiento' => $idMantenimiento
        );
        $resultado = $this->db->update(TBL_SOCIO, $set, $where);

        if ($resultado) {
            $this->permisos_model->log('Cambia tipo de mantenimiento a '.$mantenimiento, LOG_MEMBRESIA, $idUnicoMembresia);
        }
        return $resultado;
    }

    /**
     *
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function actualizaMembresia ($idUnicoMembresia, $idUn, $idMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idUn, 'integer');
        settype($idMembresia, 'integer');

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $idUnicoMembresia = $fila['idUnicoMembresia'];
            $datos = array ('idUn' => $idUn, 'idMembresia'=>$idMembresia);
            $this->db->where('idUnicoMembresia', $idUnicoMembresia);
            $this->db->where('eliminado', 0);
            $this->db->update(TBL_MEMBRESIA, $datos);
            $this->permisos_model->log('Cambio de club y numero de membresia', LOG_SISTEMAS, $idUnicoMembresia);
            return true;
        } else {
            return false;
        }
    }

    /**
     * actualiza registros de sociomensaje en base al idsocioMensaje
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function actualizaMensajes($idSocio, $titulo, $descripcion, $alerta, $fechaAlerta, $macceso, $idSocioMensaje)
    {
        settype($idsocio, 'integer');
        settype($alerta, 'integer');
        settype($macceso, 'integer');
        settype($macceso, 'integer');
        settype($idSocioMensaje, 'integer');

        $data = array(
            'idSocio'            => $idSocio,
            'idPersona'          => $this->session->userdata('idPersona'),
            'titulo'             => utf8_decode($titulo),
            'mensaje'            => utf8_decode($descripcion),
            'alerta'             => $alerta,
            'fechaAlerta'        => $fechaAlerta,
            'enviarAcceso'       => $macceso,
            'fechaActualizacion' => date('Y-m-d H:i:s')
        );

        $this->db->where('idSocioMensaje', $idSocioMensaje);
        $this->db->update('sociomensaje', $data);
        if ($this->db->affected_rows()>0) {
            $regresa = 1;
        } else {
            $regresa = 0;
        }
        return $regresa;
    }

    /**
     * Actualiza movimiento por generacion de mantenimiento
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function actualizaMovimiento($movimiento, $datosMovimiento, $unico)
    {
        settype($movimiento, 'integer');

        $this->db->select('idMovimiento');
        $this->db->from(TBL_MOVIMIENTO);
        $this->db->where('idMovimiento', $movimiento);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $id = $fila['idMovimiento'];
            $this->db->where('idMovimiento', $id);
            $this->db->update(TBL_MOVIMIENTO, $datosMovimiento);
            $this->permisos_model->log('Se actualizo movimiento '.$movimiento.' ('.date('Y-m-d').')', LOG_MEMBRESIA,$unico);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Actualiza movimiento cuenta contable
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function actualizaMovimientoCtaContable($movimiento, $datosCtaContable, $unico)
    {
        settype($movimiento, 'integer');

        $this->db->select('idMovimientoCtaContable');
        $this->db->from(TBL_MOVIMIENTOCTACONTABLE);
        $this->db->where('idMovimiento', $movimiento);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idMovimientoCtaContable', $fila->idMovimientoCtaContable);
                $this->db->update(TBL_MOVIMIENTOCTACONTABLE, $datosCtaContable);
            }
            $this->permisos_model->log('Se actualizo movimiento cuenta contable  con movimiento '.$movimiento.' ('.date('Y-m-d').')', LOG_MEMBRESIA,$unico);
            return true;
        } else {
            return false;
        }
    }

    /*
     * Actualiza nombre en socio pago mtto
     *
     * @author  Santa Garcia
     *
     * @return int
     */
    public function actualizaNombreSocioPagoMtto($idPersona, $idSocio, $idSocioPagoMtto)
    {
        settype($idPersona, 'integer');
        settype($idSocio, 'integer');
        settype($idSocioPagoMtto, 'integer');

        $this->db->select('idSocioPagoMtto, idUnicoMembresia');
        $this->db->from(TBL_SOCIOPAGOMTTO);
        $where = array('idSocioPagoMtto' => $idSocioPagoMtto);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idSocioPagoMtto', $fila->idSocioPagoMtto);
                $idUnico = $fila->idUnicoMembresia;
                $datos = array ('idPersona' => $idPersona, 'idSocio' => $idSocio);
                $this->db->update(TBL_SOCIOPAGOMTTO, $datos);
            }
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se actualizo el nombre del socio en pago de mantenimiento  ('.$idPersona.')', LOG_MEMBRESIA, $idUnico);
            return true;
        }
    }

    /**
     * Actualiza movimiento en socio pago mtto
     *
     * @param integer $idSocioPagoMtto Identificador de pago mtto
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function actualizaPagoMtto($opciones,$idSocioPagoMtto)
    {
        $datos = array();
        if (isset ($opciones['idMovimiento']) && $opciones['idMovimiento'] != '') {
            settype($opciones['idMovimiento'], 'integer');
            $datos = array ('idMovimiento' => $opciones['idMovimiento']);
        }

        if (isset ($opciones['idEsquemaPago'])&& $opciones['idEsquemaPago'] != '') {
            settype($opciones['idEsquemaPago'], 'integer');
            $datos = array ('idEsquemaPago' => $opciones['idEsquemaPago']);
        }

        if (isset ($opciones['idMantenimiento']) && $opciones['idMantenimiento'] > 0) {
            settype($opciones['idMantenimiento'], 'integer');
            $datos = array ('idMantenimiento' => $opciones['idMantenimiento']);
        }

        if (isset ($opciones['activo']) && $opciones['activo'] != '') {
            settype($opciones['activo'], 'integer');
            $datos = array ('activo' => $opciones['activo']);
        }
         if (isset ($opciones['fechaInicio']) && $opciones['fechaInicio'] != '') {
            settype($opciones['fechaInicio'], 'string');
            $datos = array ('fechaInicio' => $opciones['fechaInicio']);
        }
         if (isset ($opciones['fechaFin']) && $opciones['fechaFin'] != '') {
            settype($opciones['fechaFin'], 'string');
            $datos = array ('fechaFin' => $opciones['fechaFin']);
        }

        if (isset ($opciones['porcentaje']) && $opciones['porcentaje'] != '') {
            settype($opciones['porcentaje'], 'float');
            $datos = array ('porcentaje' => $opciones['porcentaje']);
        }
        if (isset ($opciones['idSocio']) && $opciones['idSocio'] != 0) {
            settype($opciones['idSocio'], 'integer');
            $datos = array ('idSocio' => $opciones['idSocio']);
        }
        if (isset ($opciones['idPersona']) && $opciones['idPersona'] != 0) {
            settype($opciones['idPersona'], 'integer');
            $datos = array ('idPersona' => $opciones['idPersona']);
        }

        $this->db->select('idSocioPagoMtto,idUnicoMembresia');
        $this->db->from(TBL_SOCIOPAGOMTTO);
        $this->db->where('idSocioPagoMtto', $idSocioPagoMtto);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ( $query->num_rows > 0 ) {
            $fila = $query->row_array();
            $idUnicoMembresia = $fila['idUnicoMembresia'];
            $this->db->where('idSocioPagoMtto',$fila['idSocioPagoMtto']);
            $this->db->update(TBL_SOCIOPAGOMTTO, $datos);
            $this->permisos_model->log(utf8_decode('Se actualizó el registro pago mtto (' . $idSocioPagoMtto . ')'), LOG_SISTEMAS, $idUnicoMembresia);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Actualiza persona en membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function actualizaPersonaMembresia($idUnicoMembresia, $idPersona)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPersona, 'integer');

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $idMembresia = $fila['idUnicoMembresia'];
            $datos = array ('idPersona' => $idPersona);
            $this->db->where('idUnicoMembresia', $idMembresia);
            $this->db->where('eliminado', 0);
            $this->db->update(TBL_MEMBRESIA, $datos);
            $this->permisos_model->log('Actualiza persona membresia', LOG_SISTEMAS, $idUnicoMembresia);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Actualiza el porcentaje que le corresponde a cada socio en pago mtto
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function actualizaPorcentajeSocioPagoMtto($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $sql = "SELECT SUM(DATEDIFF(spm.fechaFin, spm.fechaInicio)+1) as dias_totales, idUnicoMembresia
                FROM sociopagomtto spm WHERE spm.idMovimiento=$idMovimiento";
        $query = $this->db->query($sql);

        $u = 0;
        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $diasTotales = $fila->dias_totales;
                $u = $fila->idUnicoMembresia;
            }
        }

        $sql = "UPDATE sociopagomtto spm
            SET spm.porcentaje=ROUND(((DATEDIFF(spm.fechaFin, spm.fechaInicio)+1)/$diasTotales)*100,2)
            WHERE spm.idMovimiento=$idMovimiento";
        $query = $this->db->query($sql);

        $this->permisos_model->log('Actualizando porcentajes asignados del pago de mantenimiento', LOG_SISTEMAS, $u);

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        }
        return true;
    }

    /**
     * Actualiza socio pagomtto
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function actualizaSocioPagoMtto($idSocioPagoMtto, $datosSocioMtto, $unico)
    {
        settype($idSocioPagoMtto, 'integer');
        settype($unico, 'integer');

        $this->db->select('idSocioPagoMtto');
        $this->db->from(TBL_SOCIOPAGOMTTO);
        $this->db->where('idSocioPagoMtto', $idSocioPagoMtto);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $id = $fila['idSocioPagoMtto'];
            $this->db->where('idSocioPagoMtto', $id);
            $this->db->update(TBL_SOCIOPAGOMTTO, $datosSocioMtto);
            $this->permisos_model->log('Se actualizo pago de mantenimiento  ('.date('Y-m-d').')', LOG_MEMBRESIA, $unico);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Agrega a los participantes tanto como propietarioActual, propietarioNuevo, Testigo1 y Testigo2
     *
     * @param integer $idUnicoMembresia   Identificador unico de la membresía
     * @param integer $id                 Identificador de la membresia cesión agregada
     * @param integer $idPropietarioNuevo Identificador de tipo de participante
     * @param integer $tipo               Indicador de tipo de participante
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function agregarCesionParticipante($idUnicoMembresia, $id, $idPropietarioNuevo, $tipo)
    {
        $datos = array (
            'idMembresiaCesion' => $id,
            'tipoParticipante'  => $tipo,
            'idPersona'         => $idPropietarioNuevo
        );
        $this->db->insert(TBL_MEMBRESIACESIONPARTICIPANTE, $datos);

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Busca si existe alguna alerta de mensaje en base al idUnicoMembresia
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function alertaMensajes($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT sm.idSocioMensaje FROM sociomensaje sm
            LEFT JOIN socio s ON s.idsocio=sm.idSocio
            WHERE s.idunicoMembresia=".$idUnicoMembresia." AND  sm.fechaAlerta='".date('Y-m-d')."'
            AND sm.fechaEliminacion='0000-00-00 00:00:00' AND enviarAcceso=0 AND alertaEmitida=0";
        $query = $this->db->query($sql);
        $total = $query->num_rows();

        return $total;
    }

    /**
     * Realiza la alta de Beneficiario al decidir cambiarlo o ingresarlo en caso de no existir
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param integer $idPersona        Identificador de la persona propietaria de la membresia
     * @param integer $idTipoContacto   Identificador de tipocontacto
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function altaBeneficiario($idUnicoMembresia, $idPersona, $idTipoContacto = TIPO_CONTACTO_NINGUNO)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPersona, 'integer');
        settype($idTipoContacto, 'integer');
        $data = array (
            'idUnicoMembresia'  => $idUnicoMembresia,
            'idPersona'         => $idPersona,
            'idTipoInvolucrado' => TIPO_INVOLUCRADO_BENEFICIARIO,
            'idTipoContacto'    => $idTipoContacto
        );
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $nombre = strtoupper($ci->persona_model->nombre($idPersona));
        $this->db->insert(TBL_MEMBRESIAINVOLUCRADO, $data);

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->permisos_model->log("Alta de Beneficiario ($nombre)", LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        }
    }

    /**
    *Realiza la baja de Propietario al decidir cambiarlo o ingresarlo en caso de no existir
    *
    * @param integer $idUnicoMembresia Identificador unico de la membresia
    * @param integer $idPersona        Identificador de la persona propietaria de la membresia
    * @param integer $permiso          Indicador que permite saltarse los permisos
    *
    * @author Santa Garcia
    *
    * @return array
    */
    public function altaPropietario($idUnicoMembresia, $idPersona,$permiso = 0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPersona, 'integer');
        $data = array (
                'idUnicoMembresia' => $idUnicoMembresia,
                'idPersona' => $idPersona,
                'idTipoInvolucrado' => INVOLUCRADO_PROPIETARIO
            );
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $nombre = strtoupper($ci->persona_model->nombre($idPersona));
        $this->db->insert(TBL_MEMBRESIAINVOLUCRADO, $data);

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            if ($permiso == 1) {
                $this->permisos_model->log("Correccion de Propietario ($nombre)", LOG_MEMBRESIA, $idUnicoMembresia);
            } else {
                $this->permisos_model->log("Alta de Propietario ($nombre)", LOG_MEMBRESIA, $idUnicoMembresia);
            }

            return true;
        }
    }


    /**
     * [arrayDatosGenerales description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return array
     */
    public function arrayDatosGenerales($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $res = array();
        $fecha = date('Y-m-d');

        $sql = "SELECT
                u.fechaApertura,
                p.idProducto,
                pu.idProductoUn,
                p.nombre AS nombreProducto, tem.descripcion AS estatusMembresia,
                m.idConvenioDetalle,
                UPPER(IFNULL(c.nombre,'')) AS nombreConvenio,
                IFNULL(c.idConvenio, 0) AS idConvenio,
                IFNULL(cd.idTipoConvenio, 0) AS idTipoConvenio,
                IFNULL(tc.descripcion, '') AS tipoConvenio,
                m.intransferible,
                m.ventaEnLinea,
                m.fechaInicioMtto,
                m.fechaRegistro,
                mc.idTipoMembresia,
                mc.idMembresiaConfiguracion,
                IFNULL(mdm.idMembresiaDescuentoMtto, 0) AS idMembresiaDescuentoMtto,
                IFNULL(mdm.descripcion, '') AS descuentoMantenimiento,
                IFNULL(mi.idPersona, 0) AS idPropietario,
                TRIM(UPPER(CONCAT_WS(' ', p_p.nombre, p_p.paterno, p_p.materno))) AS nombrePropietario,
                IFNULL(s.idPersona, 0) AS idTitular,
                TRIM(UPPER(CONCAT_WS(' ', p_t.nombre, p_t.paterno, p_t.materno))) AS nombreTitular,
                IFNULL(s.idTipoEstatusSocio, 0) AS idTipoEstatusSocio,
                IFNULL(s.idMantenimiento, 0) AS idMantenimiento,
                IFNULL(mf.idMembresiaFidelidad, 0) AS idMembresiaFidelidad,
                IFNULL(mf.idTipoFidelidad,0) AS idTipoFidelidad,
                IFNULL(mf.autorizacionEspecial, 0) AS autorizacionEspecial,
                IFNULL(tf.descripcion, '') AS tipoFidelidad,
                IFNULL(mf.mesesConsecutivos,0) AS mesesConsecutivos,
                IFNULL(amp.activo, 0) AS ampliacion,
                IFNULL(trans.activo, 0) AS transferencia,
                IFNULL(cance.activo, 0) AS cancelacion,
                IFNULL(reac.activo, 0) AS reactivacion,
                IFNULL(trans.activo, 0) AS traspaso,
                IFNULL(cesi.activo, 0) AS cesion,
                IFNULL(invi.activo, 0) AS invitados,
                IFNULL(alte.activo, 0) AS alterno,
                m.limiteInicioMtto,
                m.idUnAlterno,
                fncEvaluaVacacional(m.idUnicoMembresia) AS permiteVacacional
            FROM membresia m
            INNER JOIN un u ON u.idUn=m.idUn
            INNER JOIN producto p ON p.idProducto=m.idProducto
            INNER JOIN productoun pu ON pu.idProducto=p.idProducto AND pu.idUn=m.idUn
            INNER JOIN membresiaconfiguracion mc ON mc.idProductoUn=pu.idProductoUn
            INNER JOIN tipoestatusmembresia tem ON tem.idTipoEstatusMembresia=m.idTipoEstatusMembresia
            LEFT JOIN membresiadescuentomtto mdm ON mdm.idMembresiaDescuentoMtto=m.idMembresiaDescuentoMtto
                 AND mdm.activo=1 AND '$fecha' BETWEEN mdm.inicioVigencia AND mdm.finVigencia
                 AND mdm.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiainvolucrado mi ON mi.idUnicoMembresia=m.idUnicoMembresia
                AND mi.idTipoInvolucrado=1
                AND mi.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN socio s ON s.idUnicoMembresia=m.idUnicoMembresia AND s.idTipoRolCliente=1
                AND s.eliminado=0
            LEFT JOIN persona p_p ON p_p.idPersona=mi.idPersona
            LEFT JOIN persona p_t ON p_t.idPersona=s.idPersona
            LEFT JOIN conveniodetalle cd ON cd.idConvenioDetalle=m.idConvenioDetalle
                AND cd.idConvenioDetalle>0 AND cd.activo=1 AND '$fecha' BETWEEN cd.fechaInicio AND cd.fechaFin
                AND cd.eliminado=0
            LEFT JOIN tipoconvenio tc ON tc.idTipoConvenio=cd.idTipoConvenio
            LEFT JOIN convenio c ON c.idConvenio=cd.idConvenio
                AND c.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiafidelidad mf ON mf.idUnicoMembresia=m.idUnicoMembresia
                AND mf.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN tipofidelidad tf ON tf.idTipoFidelidad=mf.idTipoFidelidad
            LEFT JOIN membresiaatributos amp ON amp.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
                AND amp.idTipoMembresiaAtributo=3 AND amp.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiaatributos trans ON trans.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
                AND trans.idTipoMembresiaAtributo=6 AND trans.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiaatributos cance ON cance.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
                AND cance.idTipoMembresiaAtributo=5 AND cance.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiaatributos reac ON reac.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
                AND reac.idTipoMembresiaAtributo=1 AND reac.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiaatributos cesi ON cesi.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
                AND cesi.idTipoMembresiaAtributo=4 AND cesi.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiaatributos invi ON invi.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
                AND invi.idTipoMembresiaAtributo=14 AND invi.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiaatributos alte ON alte.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
                AND alte.idTipoMembresiaAtributo=22 AND alte.fechaEliminacion='0000-00-00 00:00:00'
            WHERE m.idUnicoMembresia=$idUnicoMembresia";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            return $query->row_array();
        }

        return $res;
    }


    /**
     * funcion que lista los descuentos de mtto vigentes
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function arrayDescuento($nombre)
    {
        $permisoMttoDescuento = $this->permisos_model->validaTodosPermisos($this->_especialMttoDescuento);
        $p = ' AND m.requierePermiso=0';
        if ($permisoMttoDescuento==true) {
            $p = '';
        }

        $sql = "SELECT m.idMembresiaDescuentoMtto,m.descripcion
            FROM membresiadescuentomtto m
            WHERE m.activo=1
            AND (m.finVigencia > DATE(NOW()) OR (m.inicioVigencia='0000-00-00' AND m.finVigencia='0000-00-00'))
            AND m.descripcion LIKE '%".$nombre."%' AND m.fechaEliminacion='0000-00-00 00:00:00' $p";
        $query = $this->db->query($sql);

        $data  = array();

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $fila) {
                $data[$fila['idMembresiaDescuentoMtto']] = $fila['descripcion'];
            }
        }
        return $data;
    }

    /**
     * Realiza la asignación de fechas para invitado
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param string  $inicioVigencia   fecha de inicio de vigencia
     * @param string  $finVigencia      fecha de fin de vigencia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function asignaFechas($idUnicoMembresia, $inicioVigencia, $finVigencia)
    {
        settype($idUnicoMembresia, 'integer');
        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idUnicoMembresia', $fila['idUnicoMembresia']);
            $datos = array ('inicioVigencia' => $inicioVigencia,'finVigencia'=> $finVigencia);
            $this->db->update(TBL_MEMBRESIA, $datos);
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->permisos_model->log(utf8_decode('Actulización de periodo de vigencia para invitado'), LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        }
    }

    /**
     * Realiza la asignación de estado intransferible como activo o desactivo
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param string  $opcion           estado true o false
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function asignaIntransferible($idUnicoMembresia,$opcion)
    {
        settype($idUnicoMembresia, 'integer');
        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idUnicoMembresia', $fila['idUnicoMembresia']);
            $datos = array ('intransferible' => $opcion);
            $this->db->update(TBL_MEMBRESIA, $datos);
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->permisos_model->log('Actulización del estado de intransferible', LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        }
    }

    /**
     * Realiza la asignación de estado invitado como activo o desactivo
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param string  $opcion           estado true o false
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function asignaInvitado($idUnicoMembresia,$opcion)
    {
        settype($idUnicoMembresia, 'integer');
        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idUnicoMembresia', $fila['idUnicoMembresia']);
            $datos = array ('invitado' => $opcion);
            $this->db->update(TBL_MEMBRESIA, $datos);
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->permisos_model->log(utf8_decode('Actulización del estado de invitado'), LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        }
    }

    /**
     * [asignar description]
     *
     * @param  [type]  $club                   [description]
     * @param  [type]  $producto               [description]
     * @param  [type]  $persona                [description]
     * @param  integer $precio                 [description]
     * @param  integer $descuento              [description]
     * @param  integer $convenio               [description]
     * @param  integer $forma                  [description]
     * @param  integer $periodo                [description]
     * @param  integer $codigo                 [description]
     * @param  integer $intransferible         [description]
     * @param  integer $memAnterior            [description]
     * @param  integer $clubAlterno            [description]
     * @param  integer $certificado            [description]
     * @param  string  $fechaInicioMttoPaquete [description]
     * @param  integer $limiteInicioMtto       [description]
     *
     * @return [type]                          [description]
     */
    public function asignar($club, $producto, $persona, $precio=0, $descuento=0, $convenio=0, $forma=0, $periodo=0,$codigo=0, $intransferible = 0, $memAnterior = 0, $clubAlterno=0, $certificado=0, $fechaInicioMttoPaquete = '0000-00-00', $limiteInicioMtto = 0)
    {
        settype($club,      'integer');
        settype($producto,  'integer');
        settype($persona,   'integer');
        settype($precio,    'float');
        settype($descuento, 'float');
        settype($convenio,  'integer');
        settype($forma,     'integer');
        settype($periodo,   'integer');
        settype($codigo,    'integer');
        settype($certificado, 'integer');
        settype($clubAlterno, 'integer');
        settype($fechaInicioMttoPaquete, 'string');
        settype($limiteInicioMtto, 'integer');

        $ci =& get_instance();
        $ci->load->model('persona_model');
        $ci->load->model('un_model');
        $nombreClub = $ci->un_model->nombre($club);
        $nombrePersona = $ci->persona_model->nombre($persona);
        if ($club==0) {
            return 0;
        }
        if ($producto==0) {
            return 0;
        }

        $qry_u = $this->db->query('SELECT MAX(idUnicoMembresia) AS unico FROM '.TBL_MEMBRESIA);
        $row_u = $qry_u->row();
        $unico = $row_u->unico;
        $unico++;

        $query = $this->db->query('SELECT MAX(idMembresia) AS maxid FROM '.TBL_MEMBRESIA.' WHERE idUn='.$club);
        $row = $query->row();
        $max_id = $row->maxid;
        $max_id++;

        $sql = "INSERT INTO `log` (idLogCategoria, idUsuario, idPersona,
               descripcion, idUnicoMembresia, idUn, `query`,
               idPersonaAplica, idProducto)
            SELECT 18, IFNULL(u.idUsuario, 1000), IFNULL(e.idPersona,0),
                CONCAT(b.descripcion, ' (', REPLACE(REPLACE(IFNULL(t.mail, ''), '@upster.com.mx', ''), '@sportsworld.com.mx', '') ,')'),
                $unico, 0, '',
                b.idPersona, 0
            FROM (
                SELECT * FROM (
                    SELECT pv.idProspectoVendedor, pv.idPersona, aa.idAgendaActividad,
                        CONCAT(IF(aa.titulo='Nota rapida', CONCAT(aa.titulo, ' - ', aa.descripcion) ,aa.titulo), ' (', aa.fechaRegistro,')') AS descripcion,
                        aa.fechaRegistro, IFNULL(e2.idEmpleado, pvh.idEmpleado) AS idEmpleado,
                        pvh.idProspectoVendedorHistorico
                    FROM prospectoactividad pa
                    INNER JOIN prospectovendedor pv ON pv.idPersona=pa.idPersona
                        AND pv.fechaEliminacion='0000-00-00 00:00:00'
                    INNER JOIN agendaactividad aa ON aa.idAgendaActividad=pa.idAgendaActividad
                    LEFT JOIN agendaactividadparticipante aap ON aap.idAgendaActividad=aa.idAgendaActividad
                    LEFT JOIN empleado e2 ON e2.idPersona=aap.idPersona
                    LEFT JOIN prospectovendedorhistorico pvh ON pvh.idProspectoVendedor=pv.idProspectoVendedor
                        AND pvh.fechaRegistro>=aa.fechaRegistro AND pvh.idEmpleado>0
                    WHERE pa.idPersona=$persona
                    ORDER BY aa.idAgendaActividad, pvh.idProspectoVendedorHistorico
                ) a GROUP BY a.idAgendaActividad
            ) b
            LEFT JOIN empleado e ON e.idEmpleado=b.idEmpleado AND e.idEmpleado>0
            LEFT JOIN mail t ON t.idPersona=e.idPersona AND t.idTipoMail=37 AND t.fechaEliminacion='0000-00-00 00:00:00'
                AND t.idPersona>0
            LEFT JOIN usuarios u ON u.IdEmpleado=e.idEmpleado AND u.Estatus=1
                AND u.fechaEliminacion='0000-00-00 00:00:00'
            ORDER BY b.idAgendaActividad";
        $this->db->query($sql);

        $datos = array (
            'idMembresia'            => $max_id,
            'idUn'                   => $club,
            'idPersona'              => $persona,
            'idProducto'             => $producto,
            'idTipoEstatusMembresia' => ESTATUS_MEMBRESIA_ACTIVA,
            'importe'                => number_format($precio, 2, '.', ''),
            'descuento'              => number_format($descuento, 2, '.', ''),
            'idConvenioDetalle'      => $convenio,
            'idEsquemaFormaPago'     => $forma,
            'idUnAlterno'            => $clubAlterno,
            'idPeriodoMsi'           => $periodo,
            'intransferible'         => $intransferible,
            'nueva'                  => 1,
            'certificado'            => $certificado,
            'fechaInicioMtto'        => $fechaInicioMttoPaquete,
            'limiteInicioMtto'       => $limiteInicioMtto
        );
        $this->db->insert(TBL_MEMBRESIA, $datos);
        $idMembresia = $this->db->insert_id();

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        }
        if ($memAnterior >0) {
            $this->permisos_model->log(utf8_decode("Se creó nueva membresia # $max_id en el club de $nombreClub a nombre de $nombrePersona (".date('Y-m-d').")") , LOG_MEMBRESIA, $memAnterior);
        } else {
            $this->permisos_model->log("Se agrego membresia $max_id en el club de $nombreClub", LOG_MEMBRESIA, $idMembresia);
        }
        return $idMembresia;
    }

    /**
     * Realiza la baja de Beneficiario al decidir cambiarlo
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function bajaBeneficiario($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $this->db->select('idMembresiaInvolucrado,idPersona');
        $this->db->from(TBL_MEMBRESIAINVOLUCRADO);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('idTipoInvolucrado', TIPO_INVOLUCRADO_BENEFICIARIO);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $idPersona=$fila['idPersona'];

            $ci =& get_instance();
            $ci->load->model('persona_model');
            $nombre = strtoupper($ci->persona_model->nombre($idPersona));
        }
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $idPersona=$fila['idPersona'];
            $this->db->where('idMembresiaInvolucrado', $fila['idMembresiaInvolucrado']);
            $datos = array ('fechaEliminacion' => date("Y-m-d H:i:s"));
            $this->db->update(TBL_MEMBRESIAINVOLUCRADO, $datos);
        }

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->permisos_model->log("Baja de Beneficiario ($nombre)", LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        }
    }

    /**
     * Realiza la baja de propietario
     *
     * @param integer $membresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function bajaPropietario($membresia)
    {
        settype($membresia, 'integer');
        if ($membresia == 0) {
            return 0;
        }

        $this->db->distinct();
        $this->db->select('idMembresiaInvolucrado,idPersona');
        $this->db->from(TBL_MEMBRESIAINVOLUCRADO);
        $where = array('idUnicoMembresia' => $membresia, 'idTipoInvolucrado' => INVOLUCRADO_PROPIETARIO,'fechaEliminacion'=>'0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $idPersona=$fila['idPersona'];
            $ci =& get_instance();
            $ci->load->model('persona_model');
            $nombre = strtoupper(utf8_decode($ci->persona_model->nombre($idPersona)));
        }

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $idPersona=$fila['idPersona'];
            $this->db->where('idMembresiaInvolucrado', $fila['idMembresiaInvolucrado']);
            $datos = array ('fechaEliminacion' => date("Y-m-d H:i:s"));
            $this->db->update(TBL_MEMBRESIAINVOLUCRADO, $datos);
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->permisos_model->log("Baja de Propietario ($nombre)", LOG_MEMBRESIA, $membresia);
            return $fila['idPersona'];
        }
    }

    /**
     * Busca socio por nombre
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function buscaSocioNombre($opciones, $totales=0, $posicion=0, $registros=25, $orden = '')
    {
        settype($opciones['idUn'], 'integer');
        settype($opciones['idEmpresa'], 'integer');
        settype($posicion, 'integer');
        settype($registros, 'integer');

        $sql = "SELECT s.idPersona,m.idMembresia, CONCAT_WS(' ', p.nombre, p.paterno,p.materno) AS nombre ,u.nombre AS club
            FROM ".TBL_SOCIO." s
            INNER JOIN ".TBL_PERSONA." p on p.idPersona = s.idPersona
            INNER JOIN ".TBL_MEMBRESIA." m on m.idUnicoMembresia = s.idUnicoMembresia
            INNER JOIN ".TBL_UN." u on u.idUn = m.idUn
            WHERE s.eliminado=0 AND CONCAT_WS(' ', p.nombre, p.paterno,p.materno) LIKE '%".
            $this->db->escape_like_str($opciones['nombre'])."%'";

        if ($opciones['idUn'] != 0) {
            $sql .= " AND m.idUn = ".$opciones['idUn'];
        }
        if ($opciones['idEmpresa'] != 0) {
            $sql .= " AND u.idEmpresa = ".$opciones['idEmpresa'];
        }
        if ($orden == '') {
            $sql .= " ORDER BY m.idMembresia";
        } else {
            $sql .= " ORDER BY ".$this->db->escape($orden);
        }
        if ($totales == 0) {
            if ($posicion == '') {
                $posicion = 0;
            }
            $sql .= " LIMIT $posicion,$registros ";
        }
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            if ($totales == 1) {
                return $query->num_rows();
            }
            return $query->result_array();
        } else {
            return 0;
        }
    }


    /**
     * [calculaMtto2 description]
     *
     * @param  [type]  $idUn            [description]
     * @param  [type]  $idMembresia     [description]
     * @param  integer $idPersona       [description]
     * @param  string  $fecha           [description]
     * @param  integer $idMantenimiento [description]
     * @param  integer $idTipoRolSocio  [description]
     * @param  integer $idEsquemaPago   [description]
     * @param  integer $idTipoMembresia [description]
     * @param  integer $ausencia        [description]
     * @param  integer $numIntegrantes  [description]
     * @param  integer $fidelidad       [description]
     *
     * @return [type]                   [description]
     */
    public function calculaMtto2($idUn, $idMembresia, $idPersona=0, $fecha="0000-00-00", $idMantenimiento=0, $idTipoRolSocio=0,
        $idEsquemaPago=0, $idTipoMembresia=0, $ausencia=0, $numIntegrantes=0, $fidelidad = 0)
    {
        $sql1 = "CALL crm.spCalculaMtto2(   ".$idUn.", ".$idMembresia.", ".$idPersona.", '".$fecha."', ".
            $idMantenimiento.", ".$idTipoRolSocio.", ".$idEsquemaPago.", ".
            $idTipoMembresia.", ".$ausencia.",".$numIntegrantes.", ".$fidelidad.", ".
            "@des, @imp, @iva, @cuenta, @producto)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT IF(@imp IS NULL, '0.00', @imp) AS resp";
        $query2 = $this->db->query($sql2);

        $row = $query2->row();
        return $row->resp;
    }

    /**
     * Funcion que cambia el estatus de una membresía
     *
     * @param integer $idUnicoMembresia       Identificador unico de la membresia
     * @param integer $idTipoEstatusMembresia Nuevo estatus de la membresía
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function cambiaEstatus($idUnicoMembresia, $idTipoEstatusMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idTipoEstatusMembresia, 'integer');

        $datos = array (
            'idTipoEstatusMembresia'  => $idTipoEstatusMembresia
        );

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $e = '';
            $this->db->select('descripcion');
            $this->db->from(TBL_TIPOESTATUSMEMBRESIA);
            $this->db->where('idTipoEstatusMembresia', $idTipoEstatusMembresia);
            $query = $this->db->get();
            if ($query->num_rows() > 0) {
                $fila = $query->row_array();
                $e = $fila['descripcion'];
            }

            $this->db->where('idUnicoMembresia', $idUnicoMembresia);
            $this->db->update(TBL_MEMBRESIA, $datos);
            $this->permisos_model->log('Cambia estatus de membresia a '.$e, LOG_MEMBRESIA, $idUnicoMembresia);

            if ($idTipoEstatusMembresia == ESTATUS_MEMBRESIA_ACTIVA) {
                $s = array (
                    'idTipoEstatusSocio' => ESTATUS_SOCIO_ACTIVO
                );

                $this->db->where('eliminado', 0);
                $this->db->where('idTipoEstatusSocio', ESTATUS_SOCIO_INACTIVO);
                $this->db->where('idUnicoMembresia', $idUnicoMembresia);
                $this->db->update(TBL_SOCIO, $s);
                $this->permisos_model->log('Cambia estatus de socio(s) a Activo', LOG_MEMBRESIA, $idUnicoMembresia);
            }

            if ($idTipoEstatusMembresia == ESTATUS_MEMBRESIA_INACTIVA ||
                $idTipoEstatusMembresia ==ESTATUS_MEMBRESIA_PASIVA) {
                $s = array (
                    'idTipoEstatusSocio' => ESTATUS_SOCIO_INACTIVO
                );

                $this->db->where('eliminado', 0);
                $this->db->where('idTipoEstatusSocio', ESTATUS_SOCIO_ACTIVO);
                $this->db->where('idUnicoMembresia', $idUnicoMembresia);
                $this->db->update(TBL_SOCIO, $s);
                $this->permisos_model->log('Cambia estatus de socio(s) a Activo', LOG_MEMBRESIA, $idUnicoMembresia);
            }

            return 1;
        } else {
            return 0;
        }
    }

    /**
     * [cambiaMembresiaGrupalSinReglas description]
     *
     * @param  [type] $idUnicoMembresia  [description]
     * @param  [type] $idConvenioDetalle [description]
     * @param  [type] $idMantenimiento   [description]
     *
     * @return [type]                    [description]
     */
    public function cambiaMembresiaGrupalSinReglas($idUnicoMembresia, $idConvenioDetalle, $idMantenimiento)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idConvenioDetalle, 'integer');
        settype($idMantenimiento, 'integer');

        $respuesta = 0;
        $sql1  = "SELECT p.idProducto, p.nombre
            FROM ".TBL_PRODUCTO." P
            INNER JOIN ".TBL_PRODUCTOMANTENIMIENTO." pm ON pm.idProducto=p.idProducto AND pm.idMantenimiento=".$idMantenimiento;
        $query = $this->db->query($sql1);
         if ( $query->num_rows > 0 ) {
            $datosMtto =$query->result_array();
            $sql2  = "UPDATE membresia SET idProducto=43, idConvenioDetalle=0 WHERE idUnicoMembresia=".$idUnicoMembresia;
            $this->db->query($sql2);
            if(intval($this->db->affected_rows())>0)
            {
                $sql3  = "UPDATE crm.socio s
                    SET s.idMantenimiento=".$idMantenimiento."
                    WHERE s.idUnicoMembresia=".$idUnicoMembresia." AND s.eliminado=0
                        AND s.idTipoEstatusSocio IN (81)";
                $this->db->query($sql3);
            }
            if (intval($this->db->affected_rows())>0) {
                $sql4  = "UPDATE crm.socio s
                    SET s.idTipoRolCliente=12
                    WHERE s.idUnicoMembresia=".$idUnicoMembresia."
                        AND s.idTipoRolCliente IN (17,18) AND s.eliminado=0 AND s.idTipoEstatusSocio IN (81)";
                $this->db->query($sql4);
            }
            if (intval($this->db->affected_rows())>0) {
                $this->permisos_model->log('Se genera cambio de membresia de individual a grupal con mantenimiento: '.
                    $datosMtto[0]['nombre'].' y rol de socios a COTITULAR GRUPAL', LOG_MEMBRESIA,$idUnicoMembresia);
                $respuesta = 1;
            }
        }

        return $respuesta;
    }

    /**
     * [cambiaNumero description]
     * @param  [type] $idUnicoMembresia [description]
     * @param  [type] $idMembresia      [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]                   [description]
     */
    public function cambiaNumero($idUnicoMembresia, $idMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idMembresia, 'integer');

        if ($idUnicoMembresia>0 && $idMembresia>0) {
            $sql = "UPDATE membresia SET idMembresia=$idMembresia WHERE idUnicoMembresia=$idUnicoMembresia";
            $this->db->query($sql);
        }
    }

    /**
     * Realiza el cambio de cotitular a titular
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param integer $idCotitular      Identificador del cotitular
     * @param integer $idTitular        Identificador del  titular
     *
     *  @author Santa Garcia
     *
     * @return array
     */
    public function cambioTitular($idCotitular, $idTitular, $idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idTitular, 'integer');
        settype($idCotitular, 'integer');
        $ci =& get_instance();
        $ci->load->model('socio_model');


        $nombre1 = $ci->socio_model->obtenNombre($idCotitular);
        $nombre2 = $ci->socio_model->obtenNombre($idTitular);
        $datosSocio = $ci->socio_model->obtenSocios($idUnicoMembresia,$idCotitular);
        foreach($datosSocio as $valor){
            $idTipoRolCliente=$valor->idTipoRolCliente;
        }

        $data = array('idTipoRolCliente'=> ROL_CLIENTE_TITULAR);
        $dato = array('idTipoRolCliente'=> $idTipoRolCliente);

        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $this->db->where('idPersona', $idCotitular);
        $this->db->update(TBL_SOCIO, $data);
        $this->permisos_model->log(utf8_decode("Cambio de Cotitular $nombre1 a Titular"), LOG_MEMBRESIA, $idUnicoMembresia);
        $total = $this->db->affected_rows();
        if ($total == 0) {
            $datos=0;
        } else {
            $this->db->where('idUnicoMembresia', $idUnicoMembresia);
            $this->db->where('eliminado', 0);
            $this->db->where('idPersona', $idTitular);
            $this->db->update(TBL_SOCIO, $dato);
            $this->permisos_model->log(utf8_decode("Cambio de Titular $nombre2 a Cotitular"), LOG_MEMBRESIA, $idUnicoMembresia);
            $total = $this->db->affected_rows();
            if ($total == 0) {
                $datos=0;
            } else {
                $datos=1;
            }
        }
        return $datos;
    }

    /**
     * [cancelaAbrilMayo description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function cancelaAbrilMayo($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $res = false;

        if ($idUnicoMembresia>0) {
            $this->db->query("DELETE m FROM membresiamayoabril m WHERE m.idUnicoMembresia=$idUnicoMembresia");
            $total = $this->db->affected_rows();

            if ($total>0) {
                $this->permisos_model->log(utf8_decode("Se cancela mantenimiento especial para membresia Abril-Mayo 2017  (".date('Y-m-d').")"), LOG_MEMBRESIA, $idUnicoMembresia);
                return $res = true;
            }
        }

        return $res;
    }


    /**
     * Funcion que realiza la cancelacion de la membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param integer $idPropietario    Identificador del propietario de la membresía
     * @param string  $motivo           causa por la cual se esta cancelando la membresía
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function cancelacion($idUnicoMembresia, $idPropietario, $motivo='')
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPropietario, 'integer');

        $ci =& get_instance();
        $ci->load->model('empleados_model');
        $ci->load->model('un_model');

        $data = array (
            'idUnicoMembresia' => $idUnicoMembresia,
            'idPersona'        => $idPropietario,
            'idEmpleado'       => $ci->empleados_model->obtenIdEmpleado($this->session->userdata('idPersona')),
            'motivo'           => $motivo
        );
        $datosGral = $this->obtenerDatosGeneralesMem($idUnicoMembresia);
        $club = $ci->un_model->nombre($datosGral[0]->idUn);
        $membresia = $datosGral[0]->idMembresia;
        $this->db->insert(TBL_MEMBRESIACANCELACION, $data);
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->db->query(
                "UPDATE membresia
                SET fechaEliminacion=NOW()
                WHERE idUnicoMembresia=$idUnicoMembresia AND eliminado=0");

            $this->permisos_model->log(
                utf8_decode("Cancelación de Membresía ($membresia  del club $club)"),
                LOG_MEMBRESIA,
                $idUnicoMembresia
            );

            $query2 = $this->db->query(
                "SELECT idMembresiaCancelacion
                FROM membresiacancelacion mc
                WHERE idUnicoMembresia=$idUnicoMembresia
                ORDER BY idMembresiaCancelacion DESC LIMIT 1");
            $row = $query2->row();
            return $row->idMembresiaCancelacion;
        }
    }

    /**
     * [categoria description]
     *
     * @param  [type]  $idUnicoMembresia     [description]
     *
     * @return [type]                        [description]
     */
    public function categoria($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $this->db->select('categoria');
        $this->db->from(TBL_MEMBRESIACATEGORIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['categoria'];
        }

        return '';
    }

    /**
     * Funcion que regresa la cantidad de reactivaciones realizadas para una membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la Membresía
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function cantidadMembresiaReactivacion($idUnicoMembresia, $autorizacionEspecial = 0)
    {
        settype($idUnicoMembresia, 'integer');
        $mes = date('m');
        $condicion = '';
        $m = '01';
        if ($mes <= 6) {
            $contador = '0';
            $dia = strftime("%d", mktime(0, 0, 0, 6+1, 0, date('Y')));
            $condicion = date('Y').'-06-'.$dia;
            $m = '01';
        } else {
            $contador = '1';
            $dia = strftime("%d", mktime(0, 0, 0, 12+1, 0, date('Y')));
            $condicion = date('Y').'-12-'.$dia;
            $m = '07';
        }
        $this->db->select('idMembresiaReactivacion');
        $this->db->from(TBL_MEMBRESIAREACTIVACION);
        $where = array('idUnicoMembresia' => $idUnicoMembresia, 'fechaEliminacion'=>'0000-00-00 00:00:00' , 'fechaRegistro >='=> date('Y').'-'.$m.'-01', 'fechaRegistro <='=> $condicion);
        $this->db->where($where);
        $query = $this->db->get();

        if ($autorizacionEspecial == true) {
            if ($contador == 0) {
                if ($query->num_rows()) {
                    return 1;
                } else {
                    return 0;
                }
            } else {
                if ($query->num_rows()) {
                    return 1;
                } else {
                    return 0;
                }
            }
        } else {
            if ($contador == 0) {
                if ($query->num_rows() < 1) {
                    return 1;
                } else {
                    return 0;
                }
            } else {

                if ($query->num_rows() < 1) {
                    return 1;
                } else {
                    return 0;
                }
            }
        }
    }

    /**
     * Agrega a los participantes tanto como propietarioActual, propietarioNuevo, Testigo1 y Testigo2
     *
     * @param integer $idUnicoMembresia    Identificador unico de la membresia
     * @param integer $idUn                Identificador de la unidad de negocios
     * @param integer $folio               Folio
     * @param integer $idActualPropietario Identificador del propietario actual
     * @param integer $idNuevoPropietario  Identificador del nuevo propietario
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function cesion($idUnicoMembresia, $idUn, $folio, $idActualPropietario, $idNuevoPropietario)
    {
        $data = array (
                'idUnicoMembresia' => $idUnicoMembresia,
                'idUn' => $idUn,
                'folio' => $folio
            );
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $nombre1 = strtoupper($ci->persona_model->nombre($idActualPropietario));
        $nombre2 = strtoupper($ci->persona_model->nombre($idNuevoPropietario));
        $this->db->insert(TBL_MEMBRESIACESION, $data);
        $id = $this->db->insert_id();

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->permisos_model->log(utf8_decode("Cesión de derechos de $nombre1 a $nombre2"), LOG_MEMBRESIA, $idUnicoMembresia);
            return $id;
        }
    }

    /**
     * Obtiene el club al qure pertenece la membresia indicada
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function club($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia == 0) {
            return 0;
        }
        $this->db->distinct();
        $this->db->select('idUn');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idUn'];
        }
        return 0;
    }

    /**
     * Función agrega o actualiza la configuración de clubes extra para acceso
     *
     * @param integer $idsocio    Identificador del socio
     * @param integer $idUn       Identificador del socio
     * @param integer $idEmpleado Identificador del socio
     * @param integer $activo     Identificador del socio
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function clubExtraAccesos($idSocio, $idUn, $idEmpleado, $activo, $idUnicoMmebresia = 0)
    {
        $ci =& get_instance();
        $ci->load->model('un_model');
        $ci->load->model('socio_model');

        $club = $ci->un_model->nombre($idUn);
        $nombre = $ci->socio_model->obtenNombre($idSocio);

        $data = array(
            'idSocio'=> $idSocio,
            'idUn'=> $idUn,
            'idPersona'=> $idEmpleado,
            'activo'=> $activo,
        );

        $this->db->select('idSocioUnExtra');
        $this->db->from(TBL_SOCIOUNEXTRA);
        $this->db->where('idSocio', $idSocio);
        $this->db->where('idUn', $idUn);
        $this->db->where('fechaEliminacion ', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idSocioUnExtra', $fila['idSocioUnExtra']);
            $datos = array('activo'=> $activo);
            $this->db->update(TBL_SOCIOUNEXTRA, $datos);
            if ($activo == 1) {
                $this->permisos_model->log(utf8_decode("Se permitió el acceso a $nombre en el club de $club"), LOG_MEMBRESIA, $idUnicoMmebresia);
                return 1;
            } else {
                $this->permisos_model->log(utf8_decode("Se denegó el acceso a $nombre en el club de $club"), LOG_MEMBRESIA, $idUnicoMmebresia);
                return 1;
            }
        } else {
            $this->db->insert(TBL_SOCIOUNEXTRA, $data);
            if ($activo == 1) {
                $this->permisos_model->log(utf8_decode("Se permitió el acceso a $nombre en el club de $club"), LOG_MEMBRESIA, $idUnicoMmebresia);
                return 1;
            } else {
                $this->permisos_model->log(utf8_decode("Se denegó el acceso a $nombre en el club de $club"), LOG_MEMBRESIA, $idUnicoMmebresia);
                return 1;
            }
        }
    }

    /**
     * Busca la configuración de mantenimientos MSI (promoción fin de año)
     *
     * @author Gustavo Bonilla
     *
     * @return void
     */
    function configuracionMttoMSIBusqueda($idUN=0, $idProducto=0, $idEstatus=2, $id=0)
    {
        settype($idUN, 'integer');
        settype($idProducto, 'integer');
        settype($idEstatus, 'integer');
        settype($id, 'integer');

        $configuraciones=array();

        $this->db->select("
            mm.idFinanzasConfigMttoMSI,
            mm.idUN, IFNULL(un.nombre, 'Todos') AS club,
            mm.idProducto, IF(p.descripcion IS NULL OR p.descripcion='No disponible', 'Todos', p.nombre) AS producto,
            mm.idMantenimiento, IF(pmtto.descripcion IS NULL OR pmtto.descripcion='No disponible', 'Todos', pmtto.nombre) AS mantenimiento,
            mm.mesesMtto, mm.mesesMSI,
            mm.idEsquemaPago, ep.descripcion AS esquemaPago,
            mm.descuento,
            mm.pases, mm.pasesDias, mm.sesionNutritionCenter,
            mm.vigencia, mm.fechaInicio, mm.fechaFin, mm.activo, mm.descuento", false);
        $this->db->from("finanzasConfigMttoMSI mm");
        $this->db->join("esquemaPago ep", "ep.idEsquemaPago=mm.idEsquemaPago", "INNER");
        $this->db->join("un un", "un.idUN=mm.idUN", "LEFT");
        #$this->db->join("productoUN pun", "pun.idProductoUN=mm.idProducto AND pun.idUN=mm.idUN", "LEFT");
        $this->db->join("producto p", "p.idProducto=mm.idProducto", "LEFT");
        $this->db->join("producto pmtto", "pmtto.idProducto=mm.idMantenimiento", "LEFT");
        if( $idUN!=0 && $idProducto!=0 ){
            $this->db->where("mm.idUN IN (".$idUN.") AND mm.idProducto IN (".$idProducto.") ");
        }else if( $idUN!=0 && $idProducto==0 ){
            $this->db->where("mm.idUN IN (".$idUN.") ");
        }
        if( $idEstatus!=2 ){
            $this->db->where("mm.activo IN (".$idEstatus.") ");
        }
        if( $id!=0 ){
            $this->db->where("mm.idFinanzasConfigMttoMSI IN (".$id.") ");
        }
        $this->db->order_by("mm.idUN, mm.idProducto, mm.mesesMtto, mm.mesesMSI");
        $qryListaConfiguraciones=$this->db->get();
        if( $qryListaConfiguraciones->num_rows()>0 ){
            foreach( $qryListaConfiguraciones->result_array() as $row ){
                $configuraciones[$row["idFinanzasConfigMttoMSI"]]=$row;
            }
        }

        return $configuraciones;
    }

    /**
     * Busca la configuración de mantenimientos MSI (promoción fin de año)
     *
     * @author Gustavo Bonilla
     *
     * @return void
     */
    function configuracionMttoMSIGuardar($datos)
    {
        settype($datos["id"], 'integer');

        $resultado="";

        $datosActualizar=array(
            "idUN"=>$datos["idUN"],
            "idProducto"=>$datos["idProducto"],
            "mesesMtto"=>$datos["mesesMtto"],
            "mesesMSI"=>$datos["mesesMSI"],
            "descuento"=>$datos["descuento"],
            "activo"=>$datos["activo"],
            "idEsquemaPago"=>$datos["idEsquemaPago"],
            "vigencia"=>$datos["vigencia"],
            "fechaInicio"=>$datos["fechaInicio"],
            "fechaFin"=>$datos["fechaFin"],
            "pases"=>$datos["pases"],
            "pasesDias"=>$datos["pasesDias"],
            "sesionNutritionCenter"=>$datos["sesionNutritionCenter"]
        );
        $this->db->update("finanzasConfigMttoMSI", $datosActualizar, array("idFinanzasConfigMttoMSI"=>$datos["id"]));
        $total = $this->db->affected_rows();
        if( $total>0 ){
            $resultado="Actualizado.";
        }else{
            $resultado="Error al actualizar el ID #".$datos["id"].", no hubo cambios o hay un error en la consulta.";
        }

        return $resultado;
    }

    /**
     * Obtiene numero de adltos en base al tipo de soccio
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function contarAdultosPorTSocio($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $query = $this->db->query("SELECT COUNT(*) as total
            from socio
            where idUnicoMembresia=".$idUnicoMembresia." and (idTipoRolCliente=1 or idTipoRolCliente=2)
                and eliminado=0");
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->total;
        } else {
            return 0;
        }
    }

    /**
     * Obtiene numero de agregados adultos en base al tipo de soccio
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function contarAgregadosAdultosPorTSocio($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $query = $this->db->query("SELECT COUNT(*) AS total
            FROM socio
            WHERE idUnicoMembresia=$idUnicoMembresia AND idTipoRolCliente=10
                AND eliminado=0");
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->total;
        } else {
            return 0;
        }
    }

    /**
     * Obtiene numero de agregados en base al tipo de soccio
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function contarAgregadosPorTSocio($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $query = $this->db->query("SELECT COUNT(*) as total
            FROM socio
            WHERE idUnicoMembresia=$idUnicoMembresia AND (idTipoRolCliente=10 OR idTipoRolCliente=11)
                AND eliminado=0");
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->total;
        } else {
            return 0;
        }
    }

    /**
     * [continuidad description]
     *
     * @param  integer $idUnicoMembresia [description]
     *
     * @return integer
     */
    public function continuidad($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $fecha = date('Y-m').'-01';
        list($year,$mon,$day) = explode('-', $fecha);
        $fecha = date('Y-m-d', mktime(0, 0, 0, $mon,$day-1, $year));

        $sql = "SELECT meses FROM crm_estadisticas.continuidad
            WHERE idUnicoMembresia=$idUnicoMembresia AND fechaCorte='$fecha' LIMIT 1";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['meses'];
        } else {
            return 0;
        }
    }

    /**
     * [cuentaActivosAgregado description]
     *
     * @param  integer $idUnicoMembresia [description]
     *
     * @return integer
     */
    public function cuentaActivosAgregado($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $tpm = $this->membresia_model->obtenerTipoMembresia($idUnicoMembresia);

        if ($tpm['idProducto']==1) {
            $sql = "CREATE TEMPORARY TABLE tmp_indv_agre
                SELECT s.idPersona, s.idTipoEstatusSocio, s.fechaRegistro, spm.fechaInicio, spm.fechaFin
                FROM socio s
                LEFT JOIN sociopagomtto spm ON spm.idPersona=s.idPersona AND spm.eliminado=0
                    AND spm.activo=1 AND DATE_SUB(DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')), INTERVAL 1 DAY)
                    BETWEEN spm.fechaInicio AND spm.fechaFin
                WHERE s.idUnicoMembresia=$idUnicoMembresia
                    AND s.eliminado=0 AND s.idTipoEstatusSocio IN (83, 84, 81)";
            $this->db->query($sql);

            $sql = "DELETE t FROM tmp_indv_agre t
                WHERE t.fechaRegistro>=DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01'))
                    AND fechaInicio IS NOT NULL";
            $this->db->query($sql);
        } else {
            $sql = "CREATE TEMPORARY TABLE tmp_indv_agre
                SELECT s.idPersona, s.idTipoEstatusSocio, s.fechaRegistro, spm.fechaInicio, spm.fechaFin
                FROM socio s
                LEFT JOIN sociopagomtto spm ON spm.idPersona=s.idPersona AND spm.eliminado=0
                    AND spm.activo=1 AND DATE_SUB(DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')), INTERVAL 1 DAY)
                    BETWEEN spm.fechaInicio AND spm.fechaFin
                WHERE s.idUnicoMembresia=$idUnicoMembresia
                    AND s.eliminado=0 AND s.idTipoEstatusSocio IN (83, 84, 81)
                    AND s.fechaRegistro>='2014-03-19 00:00:00'";
            $this->db->query($sql);

            $sql = "DELETE t FROM tmp_indv_agre t
                WHERE t.fechaRegistro>=DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-19'))
                    AND fechaInicio IS NOT NULL";
            $this->db->query($sql);
        }

        $sql = "DELETE t FROM tmp_indv_agre t
            WHERE t.idTipoEstatusSocio=83";
        $this->db->query($sql);

        $sql = "DELETE t FROM tmp_indv_agre t
            WHERE t.idTipoEstatusSocio=84";
        $this->db->query($sql);

        $sql = "SELECT * FROM tmp_indv_agre";
        $query = $this->db->query($sql);

        return $query->num_rows();
    }

    /**
     *
     * @param integer $idUn
     * @param integer $idProductoUn
     *
     * @return type
     */
    public function datosMantenimiento($idUn, $idProductoUn)
    {
        settype($idUn, 'integer');
        settype($idProductoUn, 'integer');

        $data = array();

        $sql="SELECT DISTINCT b.idMembresiaConfigMtto, a.idMantenimiento, a.nombre, b.idmantenimiento as mntto, b.activo, b.default FROM (
                SELECT m.idMantenimiento, p.nombre FROM ".TBL_MANTENIMIENTO." m
                INNER JOIN ".TBL_PRODUCTOMANTENIMIENTO." pm ON pm.idMantenimiento=m.idMantenimiento
                INNER JOIN ".TBL_PRODUCTO." p ON p.idProducto=pm.idProducto
                INNER JOIN ".TBL_PRODUCTOUN." pu ON pu.idProducto=p.idProducto
                WHERE p.activo=1 AND p.fechaEliminacion='0000-00-00 00:00:00'
                AND pu.activo=1 AND pu.fechaEliminacion='0000-00-00 00:00:00'
                AND pu.idUn=".$idUn.") a
            LEFT JOIN (
                SELECT mcm.idMembresiaConfigMtto, mcm.idMantenimiento, mcm.activo, mcm.default
                FROM ".TBL_MEMBRESIACONFIGURACION." mc
                INNER JOIN ".TBL_MEMBRESIACONFIGMTTO." mcm ON mcm.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
                WHERE mc.idProductoUn=".$idProductoUn." AND mc.fechaEliminacion='0000-00-00 00:00:00'
                ) b ON b.idMantenimiento=a.idMantenimiento
            ORDER BY a.nombre";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $fila) {
                $data[] = $fila;
            }
        }
        return $data;
    }

    /**
     * Obtiene datos de traspaso de una membresia
     *
     * @param integer $membresia Numero de membresia/inscripcion
     * @param integer $club      Identificador del club
     *
     * @return integer
     */
    public function datosMembresiaTraspaso($membresia, $club)
    {
        settype($membresia, "integer");
        settype($club, "integer");

        if ($membresia == 0 or $club == 0) {
            return 0;
        }

        $this->db->select('idUnicoMembresia, idMembresiaTraspaso, idMovimiento, formato');
        $this->db->from(TBL_MEMBRESIATRASPASO);
        $where = array(
            'idMembresia' => $membresia,
            'idUn' => $club,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene datos generales de socio pago mtto
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function datosSocioPagoMtto($idPersona, $idUnico, $fecha = '', $activo=1, $movimiento=0)
    {
        settype($idUnico, 'integer');
        settype($idPersona, 'integer');

        $this->db->select('s.activo, s.idSocioPagoMtto, s.idUnicoMembresia, s.idMovimiento,m.idTipoEstatusMovimiento, s.fechaInicio, s.fechaFin, s.idMantenimiento, s.idEsquemaPago');
        $this->db->from(TBL_SOCIOPAGOMTTO.' s');
        $this->db->join(TBL_MOVIMIENTO." m", "m.idMovimiento = s.idMovimiento");
        if($movimiento != 0){
            $this->db->where('s.idMovimiento', $movimiento);
        }
        $this->db->where('s.idPersona', $idPersona);
        $this->db->where('s.idUnicoMembresia', $idUnico);
        $this->db->where('s.activo', $activo);
        $this->db->where('s.eliminado', 0);
        $this->db->where('m.eliminado', 0);
        if($fecha != ''){
            $this->db->where('s.fechaInicio <=', $fecha);
            $this->db->where('s.fechaFin >=', $fecha);
        }
        $query = $this->db->get();
        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Función que regresa el idUnicoMembresia de la tabla membresiaTraspaso
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function datosTraspaso ($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT MAX(fechaRegistro) AS fecha, idUn, idMembresia, idMovimiento
            FROM ".TBL_MEMBRESIATRASPASO."
            WHERE idUnicoMembresia=$idUnicoMembresia
            AND fechaEliminacion = '0000-00-00 00:00:00'
            GROUP BY idUn, idMembresia, idMovimiento
            ORDER BY 1 DESC
            LIMIT 1";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            return $query->row_array();
        } else {
            return false;
        }
    }

    /**
     * Funcion que regresa el estatus de la membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la Membresía
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function descripcionEstatus($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $this->db->select('te.descripcion');
        $this->db->from(TBL_TIPOESTATUSMEMBRESIA.' te');
        $this->db->join(TBL_MEMBRESIA.' m', 'm.idTipoEstatusMembresia = te.idTipoEstatusMembresia');
        $where = array('m.idUnicoMembresia' => $idUnicoMembresia, 'm.eliminado'=>0,'te.activo'=>1);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $estatus = $fila->descripcion;
            }
        }
        if (isset($estatus)) {
            return $estatus;
        } else {
            return null;
        }
    }

    /**
     *
     * Obtiene detalle de adeudos del reporte de adeudos de mtto
     *
     * @param array data      contiene idPersona, idSocio y periodo
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function detalleAdeudosRangoMeses($data)
    {
        if ($data['periodo']=='1') {
            $periodo = "AND amm.idTipoEstatusMovimiento IN (65) AND (EXTRACT(YEAR_MONTH FROM NOW()) BETWEEN EXTRACT(YEAR_MONTH FROM amm.fechaAplica) AND EXTRACT(YEAR_MONTH FROM amm.fechaAplicaFin))";
        }
        if ($data['periodo']=='2') {
            $periodo = "AND IF( amm.idTipoEstatusMovimiento IN (65) AND (EXTRACT(YEAR_MONTH FROM (NOW()- INTERVAL 1 MONTH)) BETWEEN EXTRACT(YEAR_MONTH FROM amm.fechaAplica) AND EXTRACT(YEAR_MONTH FROM amm.fechaAplicaFin)),amm.importePorUsuario,0)";
        }
        if ($data['periodo']=='3') {
            $periodo = "AND IF( amm.idTipoEstatusMovimiento IN (65) AND (EXTRACT(YEAR_MONTH FROM (NOW()- INTERVAL 2 MONTH)) BETWEEN EXTRACT(YEAR_MONTH FROM amm.fechaAplica) AND EXTRACT(YEAR_MONTH FROM amm.fechaAplicaFin)),amm.importePorUsuario,0)";
        }
        if ($data['periodo']=='4') {
            $periodo = "AND IF( amm.idTipoEstatusMovimiento IN (65) AND (EXTRACT(YEAR_MONTH FROM DATE(NOW() - INTERVAL 3 MONTH)) >= EXTRACT(YEAR_MONTH FROM amm.fechaAplica)) ,amm.importePorUsuario,0)";
        }
        $sql = "SELECT amm.idMovimiento, amm.descripcion, ROUND(amm.importePorUsuario,2) AS importe
           FROM adeudosmttomontos amm
           WHERE amm.idPersona=".$data['idPersona']." AND amm.idSocio=".$data['idSocio']." ".$periodo;
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $dat[] = $fila;
            }
            return $dat;
        } else {
            return $dat;
        }
    }

    /**
     *
     * Inserta la solicitud de devolucion
     *
     * @param type $datos
     *
     * @author Gustavo Bonilla
     *
     * @return string
     *
     */
    public function devolucionXTesoreriaAgregar($datos)
    {
        $resultado="Error desconocido";
        $idDevolucion=0;

        $devolucion = array("idUn"=>$this->session->userdata("idUn"), "idUnicoMembresia"=>$datos["idUnicoMembresia"], "idFactura"=>$datos["idFactura"],
            "importe"=>$datos["importeADevolver"], "importeFactura"=>$datos["importeFactura"], "comentarios"=>$datos["comentarios"],
            "comparaNumeroOperacion"=>$datos["comparaNumeroOperacion"], "comparaIdFactura"=>$datos["comparaIdFactura"],
            "fechaRegistro"=>date("Y-m-d h:i:s"));
        $devolucionDetalle = $datos["devoluciones"];

        # Insertando solicitud de devolucion
        $this->db->insert("crm.finanzasDevolucionTesoreria", $devolucion);
        $idDevolucion=$this->db->insert_id();

        if ( $idDevolucion>0 ) {
            # Insertando detalle de la solicitud
            $contadorDetalle=0;
            foreach( $devolucionDetalle as $detalle ){
                $detalle["idDevolucion"]=$idDevolucion;
                $this->db->insert("crm.finanzasDevolucionTesoreriaDetalle", $detalle);
                $contadorDetalle++;
            }
            if ( $contadorDetalle==count($devolucionDetalle) ) {
                $resultado="OK|".$idDevolucion;
            }
        } else {
            $resultado="Error, no se inserto correctamente la solicitud.";
        }

        return $resultado;
    }

    /**
     *
     * Busca los datos de un cobro en MIT por numero de operacion o referencia
     *
     * @param type $datos
     *
     * @author Gustavo Bonilla
     *
     * @return string
     *
     */
    public function devolucionXTesoreriaBuscarCobroCP($datos)
    {
        $datosCobro="";

        $this->db->select("mit.*, IFNULL(devtesdet.idDevolucion, 0) AS idDevolucion", false);
        $this->db->from("crm.finanzasHistoricoCobros mit");
        $this->db->join("crm.finanzasdevoluciontesoreriadetalle devtesdet", "devtesdet.".$datos["campo"]."=mit.".$datos["campo"]." AND YEAR(devtesdet.fechaEliminacion)='0000'", "left");
        $this->db->where("mit.".$datos["campo"], $datos["valor"]);
        $this->db->where("mit.respuesta", "approved");
        $this->db->having("idDevolucion=0");
        $rs=$this->db->get();
        #echo "<pre>".$this->db->last_query()."</pre>";

        if( $rs->num_rows()>0 ){
            foreach( $rs->result_array() as $row ){
                $datosCobro=$row["numeroOperacion"]."|".
                    $row["referencia"]."|".$row["importe"]."|".$row["numeroTarjeta"]."|".$row["nombre"]."|".$row["autorizacion"]."|".
                    substr($row["registro"], 0, strlen($row["registro"])-(strlen($row["registro"])>19?2:0)).
                    "|".$row["afiliacion"].'|'.$row["instrumento"].'|'.$row["tp"];
            }
        }

        return $datosCobro;
    }

    /**
     *
     * Busca los datos de un cobro en MIT por numero de operacion o referencia
     *
     * @param type $datos
     *
     * @author Gustavo Bonilla
     *
     * @return string
     *
     */
    public function devolucionXTesoreriaBuscarCobroFactura($referencia)
    {
        $datosCobro="";

        $this->db->select("fac.idFactura, CONCAT(fac.prefijoFactura, fac.folioFactura) AS folioFactura", false);
        $this->db->from(TBL_FACTURACORTECAJA." faccc");
        $this->db->join(TBL_FACTURA." fac", "fac.idFactura=faccc.idFactura", "inner");
        $this->db->where("referencia", $referencia);
        $rs=$this->db->get();

        if( $rs->num_rows()>0 ){
            foreach( $rs->result_array() as $row ){
                $datosCobro="OK|".$row["idFactura"]."|".$row["folioFactura"];
            }
        }

       return $datosCobro;
    }

    /**
     *
     * Obtiene las facturas de una membresia
     *
     * @param type $idUnicoMembresia
     *
     * @author Gustavo Bonilla
     *
     * @return array
     */
    public function devolucionXTesoreriaListaFacturas($idUnicoMembresia)
    {
        settype($idUnicoMembresia, "integer");

        $lista=array();

        if( $idUnicoMembresia==0 ){
            return $lista;
        }

        $this->db->select("fac.idFactura, CONCAT(fac.prefijoFactura, fac.folioFactura) AS folioFactura, IFNULL(devtes.idDevolucion, 0) AS idDevolucion", false);
        $this->db->from(TBL_FACTURA." fac");
        $this->db->join(TBL_FACTURAMOVIMIENTO." facmov", "facmov.idFactura=fac.idFactura", "inner");
        $this->db->join(TBL_MOVIMIENTO." mov ", "mov.idMovimiento=facmov.idMovimiento AND mov.idUnicoMembresia IN (".$idUnicoMembresia.")", "inner");
        $this->db->join("crm.finanzasDevolucionTesoreria devtes", "devtes.idFactura=fac.idFactura AND YEAR(devtes.fechaEliminacion)='0000'", "left");
        $this->db->group_by("fac.idFactura");
        $this->db->having("idDevolucion=0");
        $this->db->order_by("fac.idFactura");
        $rs=$this->db->get();

        if( $rs->num_rows()>0 ){
            foreach( $rs->result_array() as $row ){
                $lista[$row["idFactura"]]=$row["folioFactura"];
            }
        }

        return $lista;
    }

    /**
     *
     * Regresa un arreglo con los ids de las cuentas contables y sus importes
     *
     * @param type $idFactura
     *
     * @author Gustavo Bonilla
     *
     * @return array
     *
     */
    public function devolucionXTesoreriaListaMovimientosCC($idFactura)
    {
        $lista=array();

        $this->db->select("fac.idFactura, mov.idMovimiento, mov.importe AS importeMov, movcc.idMovimientoCtaContable, movcc.importe AS importeMovCC", false);
        $this->db->from(TBL_FACTURA." fac");
        $this->db->join(TBL_FACTURAMOVIMIENTO." facmov", "facmov.idFactura=fac.idFactura", "inner");
        $this->db->join(TBL_MOVIMIENTO." mov", "mov.idMovimiento=facmov.idMovimiento", "inner");
        $this->db->join(TBL_MOVIMIENTOCTACONTABLE." movcc", "movcc.idMovimiento=mov.idMovimiento", "inner");
        $this->db->where("fac.idFactura", $idFactura);
        $this->db->order_by("movcc.idMovimientoCtaContable");
        $rs=$this->db->get();

        if( $rs->num_rows()>0 ){
            foreach( $rs->result_array() as $row ){
                $lista[]=$row;
            }
        }

        return $lista;
    }


    /**
     * Regresa el dia de cargo para la membresia especificada
     *
     * @param int $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jorge Cruz
     *
     * @return int
     */
    public function diaCargo($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia>0) {
            $sql = "SELECT fncDiaCorte(".$idUnicoMembresia.") AS dia";
            $query = $this->db->query($sql);
            $row = $query->row();
            return $row->dia;
        }
        return 5;
    }


    /**
     * Cuenta el numero de dias trancurridos desde el registro de la membresia
     *
     * @param  [type] $idUInicoMembresia [description]
     *
     * @return [type]                    [description]
     */
    public static function diasRegistro($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $res = -1;

        if ($idUnicoMembresia>0) {
            $sql = "SELECT DATEDIFF(DATE(NOW()), DATE(m.fechaRegistro)) AS dias
                FROM membresia m
                WHERE m.idUnicoMembresia=$idUnicoMembresia AND m.eliminado=0";
            $query = DB::connection('crm')->select($sql);
            if (count($query) > 0) {
                $res = $query[0]->dias;
            }
        }

        return $res;
    }

    /**
     * [esGhost description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function esGhost($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT COUNT(*) AS total
            FROM paqueteimpacto pi
            INNER JOIN movimiento m ON m.idMovimiento=pi.idMovimiento
                AND m.idTipoEstatusMovimiento IN (66, 70)
            INNER JOIN membresia mem ON mem.idUnicoMembresia=m.idUnicoMembresia
            WHERE pi.idPaquete IN (300, 299, 298, 297, 296, 295, 294, 292)
                AND mem.idUnicoMembresia=".$idUnicoMembresia;
        $query = $this->db->query($sql);

        $fila = $query->row_array();

        if ($fila['total']>0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Revisa si es invitado
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function esInvitado($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        if ($idUnicoMembresia == 0) {
            return null;
        }

        $this->db->select('invitado');
        $this->db->from(TBL_MEMBRESIA);
        $where = array('idUnicoMembresia' => $idUnicoMembresia);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
        }
        if (isset($fila)) {
            if ($fila['invitado']>0) {
                return true;
            } else {
                return false;
            }
        } else {
            return null;
        }
    }

    /**
     * Revisa si es propietario de una membresía
     *
     * @param integer $idPersona Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function esPropietario($idPersona)
    {
        settype($idPersona, 'integer');

        $this->db->select('m.idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA. ' m');
        $this->db->join(TBL_MEMBRESIAINVOLUCRADO .' mi', 'm.idUnicoMembresia=mi.idUnicoMembresia');
        $this->db->where('m.idPersona', $idPersona);
        $this->db->where('m.eliminado', 0);
        $this->db->where('mi.idTipoInvolucrado', INVOLUCRADO_PROPIETARIO );
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->num_rows();
        }
        return 0;
    }

    /**
     * Revisa si es titular solamente o cuenta con más integrantes la membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function esSoloTitular($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $this->db->select('tc.idTipoRolCliente');
        $this->db->from(TBL_TIPOROLCLIENTE.' tc');
        $this->db->join(TBL_SOCIO .' s', 's.idTipoRolCliente=tc.idTipoRolCliente');
        $this->db->where('s.idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('s.eliminado', 0);
        $this->db->where('s.idTipoEstatusSocio <>', TIPOESTAUSSOCIOBAJA);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->num_rows();
        }
        return true;
    }

    /**
     * Revisa si es transferible la membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function esTransferible($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        if ($idUnicoMembresia == 0) {
            return null;
        }

        $this->db->select('intransferible');
        $this->db->from(TBL_MEMBRESIA);
        $where = array('idUnicoMembresia' => $idUnicoMembresia);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila      = $query->row_array();
        }
        if (isset($fila)) {
            if ($fila['intransferible']>0) {
                return true;
            } else {
                return false;
            }
        } else {
            return null;
        }
    }

    /**
     * Actualiza el campo de convenioDetalle en membresia
     *
     * @author Santa Garcia
     *
     * @return integer
     */
    function eliminarConvenio($unico, $idConvenioDetalle)
    {
        settype($unico, 'integer');
        settype($idConvenioDetalle, 'integer');

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $where=array('idUnicoMembresia'=> $unico,'eliminado'=> 0);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idUnicoMembresia', $fila['idUnicoMembresia']);
            $datos = array ('idConvenioDetalle'  => 0);
            $this->db->update(TBL_MEMBRESIA, $datos);
            $this->permisos_model->log(utf8_decode("Se eliminó convenio (".date('Y-m-d').")"), LOG_MEMBRESIA, $unico);
            return true;
       } else {
           return false;
       }
    }

    /**
     * funcion que elimina el descuento de mantenimiento para alguna membresia
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    function eliminarDescuentoMtto($unico)
    {
        settype($unico, 'integer');

        $this->db->select('m.idUnicoMembresia,md.descripcion');
        $this->db->from(TBL_MEMBRESIA. ' m');
        $this->db->join(TBL_MEMBRESIADESCUENTOMTTO. ' md','md.idMembresiaDescuentoMtto=m.idMembresiaDescuentoMtto ');
        $where=array('m.idUnicoMembresia'=> $unico,'m.eliminado'=> 0,'md.fechaEliminacion'=> '0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idUnicoMembresia', $fila['idUnicoMembresia']);
            $descuento = $fila['descripcion'];
            $datos = array ('idMembresiaDescuentoMtto'  => 0);
            $this->db->update(TBL_MEMBRESIA, $datos);
            $this->permisos_model->log(utf8_decode("Se eliminó descuento ".$descuento." (".date('Y-m-d').")"), LOG_MEMBRESIA, $unico);
            return true;
        } else {
           return false;
        }
    }

    /**
     * Eliminar Descuento Mtto
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    function eliminarDescuentoMttoConf($opciones)
    {
        $this->db->select('idMembresiaDescuentoMtto');
        $this->db->from(TBL_MEMBRESIADESCUENTOMTTO);
        $where=array('idMembresiaDescuentoMtto'=> $opciones['idMembresiaDescuentoMtto'],'fechaEliminacion'=> '0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idMembresiaDescuentoMtto', $fila['idMembresiaDescuentoMtto']);
            $datos = array ('fechaEliminacion'  => date("Y-m-d H:i:s"));
            $this->db->update(TBL_MEMBRESIADESCUENTOMTTO, $datos);
            $this->permisos_model->log(utf8_decode("Se eliminó configuracion descuento de mantenimiento (".date('Y-m-d').")"), LOG_MEMBRESIA);
            return true;
        } else {
           return false;
        }
    }

    /**
     * elimina Datos de socioMensaje
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function eliminaMensajes($idSocioMensaje)
    {
        $data = array('fechaEliminacion' => date('Y-m-d H:i:s'));

        $this->db->where('idSocioMensaje', $idSocioMensaje);
        $this->db->update('sociomensaje', $data);
        if($this->db->affected_rows()>0) {
            $regresa=1;
        } else {
            $regresa=0;
        }

        return $regresa;
    }

    /**
     * Funcion que elimina pagos mtto relacionados a una mebresía
     *
     * @param integer $idSocioPagoMtto Identificador de pago mtto
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function eliminaPagoMtto($idSocioPagoMtto)
    {
        settype($idSocioPagoMtto, 'integer');
        $datos = array ('fechaEliminacion' => date("Y-m-d H:i:s"));
        $this->db->select('idSocioPagoMtto, idUnicoMembresia');
        $this->db->from(TBL_SOCIOPAGOMTTO);
        $this->db->where('idSocioPagoMtto', $idSocioPagoMtto);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ( $query->num_rows > 0 ) {
            $fila = $query->row_array();
            $idUnicoMembresia = $fila['idUnicoMembresia'];
            $this->db->where('idSocioPagoMtto',$fila['idSocioPagoMtto']);
            $this->db->update(TBL_SOCIOPAGOMTTO, $datos);
            $this->permisos_model->log(utf8_decode('Se eliminó el registro pago mtto (' . $idSocioPagoMtto . ')'), LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Elimina una promocion en especifico
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function eliminaPromocion($idMembresiaPromoMtto)
    {
        $this->db->select('idMembresiaPromoMtto, idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIAPROMOMTTO);
        $where = array('idMembresiaPromoMtto' => $idMembresiaPromoMtto,'fechaEliminacion'=>'0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idMembresiaPromoMtto', $fila->idMembresiaPromoMtto);
                $datos = array ('fechaEliminacion' => date("Y-m-d H:i:s"));
                $this->db->update(TBL_MEMBRESIAPROMOMTTO, $datos);
            }
        }
       $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se elimino a el promocion ('.$idMembresiaPromoMtto.') de la membresía ('.$fila->idUnicoMembresia.')('.date('Y-m-d').')', LOG_MEMBRESIA,$fila->idUnicoMembresia);
            return true;
        }
    }

    /**
     * Funcion que regresa el estatus de la membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la Membresía
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function estatus($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $this->db->select('te.idTipoEstatusMembresia');
        $this->db->from(TBL_TIPOESTATUSMEMBRESIA.' te');
        $this->db->join(TBL_MEMBRESIA.' m', 'm.idTipoEstatusMembresia = te.idTipoEstatusMembresia');
        $where = array('m.idUnicoMembresia' => $idUnicoMembresia, 'm.eliminado'=>0,'te.activo'=>1);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $estatus = $fila->idTipoEstatusMembresia;
            }
        }
        if (isset($estatus)) {
            return $estatus;
        } else {
            return null;
        }
    }

    /**
     * [estatusCodigosVueloAnualidades2015 description]
     *
     * @param  [type] $idUnicoMembresia [description]
     * @param  [type] $idMovimiento     [description]
     *
     * @return [type]                   [description]
     */
    public function estatusCodigosVueloAnualidades2015($idUnicoMembresia, $idMovimiento)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idMovimiento, 'integer');

        $data = Array();
        $sql  = "SELECT CONCAT_WS(' ',per.nombre, per.paterno, per.materno) AS nombreCompleto,
            IFNULL(GROUP_CONCAT(ma.mail),'') AS mail, acv.envioMail1, acv.envioMail2, acv.envioMail3, acvr.codigo, acv.idAnualidadesCodigosViajes, acv.idPersona
            FROM crm.anualidadescodigosviajes acv
            INNER JOIN crm.anualidadescodigosviajesreales acvr ON acvr.idAnualidadesCodigosViajesReales=acv.consecutivo
            INNER JOIN crm.persona per ON per.idPersona=acv.idPersona
            LEFT JOIN crm.mail ma ON ma.idPersona=per.idPersona
            WHERE acv.idMovimiento IN (".$idMovimiento.") AND acv.idUnicoMembresia IN (".$idUnicoMembresia.") AND acv.fechaEliminacion='0000-00-00 00:00:00'
            GROUP BY per.idPersona";
        $query = $this->db->query($sql);

        if ($query->num_rows()>0) {
            $data = $query->result_array();
        }
        return $data;
    }

    /**
     * Evalua la relacion entre el numero de integrantes activos contra agregados
     *
     * @param  integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jorge Cruz <jorge.cruz@sportsworld.com.mx>
     *
     * @return integer
     */
    public function evaluaAgregado($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT IF(b.agregados=0, 1, IF(a.activos>b.agregados, 1, 0)) AS evalua
            FROM (
                SELECT COUNT(*) AS activos
                FROM ".TBL_SOCIO." s
                WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.eliminado=0
                    AND s.idTipoEstatusSocio=81 AND s.idTipoRolCliente NOT IN (1, 17, 5)
            ) a, (
                SELECT COUNT(*) AS agregados
                FROM ".TBL_SOCIO." s
                WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.eliminado=0
                    AND s.idTipoRolCliente=17 AND s.idTipoEstatusSocio<>82
            ) b";
        $query = $this->db->query($sql);

        $fila = $query->row_array();
        return $fila['evalua'];
    }

    /**
     * Evalua las altas de socios realizadas en el mes, para permitir calculo en fecha de mtto
     *  diferentes a la fecha de alta.
     *
     * @param  integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jorge Cruz <jorge.cruz@sportsworld.com.mx>
     *
     * @return integer
     */
    public function evaluaAltasMes($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT IF(a.nuevos>0 AND b.anteriores=0, 1, 0) AS resultado FROM (
                SELECT COUNT(*) AS nuevos
                FROM ".TBL_SOCIO." s
                INNER JOIN ".TBL_SOCIOPAGOMTTO." spm ON spm.idSocio=s.idSocio AND spm.eliminado=0
                    AND LAST_DAY(NOW()) BETWEEN spm.fechaInicio AND spm.fechaFin
                INNER JOIN ".TBL_MOVIMIENTO." mv1 ON mv1.idMovimiento=spm.idMovimiento AND mv1.idTipoEstatusMovimiento=65
                WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.eliminado=0
                    AND DATE(s.fechaRegistro) >= DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')) AND s.idTipoRolCliente<>17
            ) a, (
                SELECT COUNT(*) AS anteriores
                FROM ".TBL_SOCIO." s
                INNER JOIN ".TBL_SOCIOPAGOMTTO." spm ON spm.idSocio=s.idSocio AND spm.eliminado=0
                    AND LAST_DAY(NOW()) BETWEEN spm.fechaInicio AND spm.fechaFin AND spm.activo=0
                WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.eliminado=0
                    AND date(s.fechaRegistro) < DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')) AND s.idTipoRolCliente<>17
            ) b";
        $query = $this->db->query($sql);

        $fila = $query->row_array();
        return $fila['resultado'];
    }

    /**
     * Obtiene la fecha de inicio de mtto
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function fechaInicioMtto($idUnicoMembresia)
    {
        settype($idUnicoMembresia, "integer");

        $this->db->select('fechaInicioMtto');
        $this->db->from(TBL_MEMBRESIA);
        $where = array(
            'idUnicoMembresia' => $idUnicoMembresia,
            'eliminado' => 0
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['fechaInicioMtto'];
        } else {
            return '0000-00-00';
        }
    }

    /**
     * Función que checa si existe fecha vigente en pago mtto
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function fechaVigentePagoMtto($idUnicoMembresia, $idPersona)
    {
        $this->db->select('idSocioPagoMtto');
        $this->db->from(TBL_SOCIOPAGOMTTO);
        $this->db->where('fechaInicio <=', date('Y-m-d'));
        $this->db->where('fechaFin >=', date('Y-m-d'));
        $this->db->where('eliminado ', 0);
        $this->db->where('idUnicoMembresia ', $idUnicoMembresia);
        $this->db->where('idPersona ', $idPersona);
        $this->db->where('activo ', 1);

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Realiza el cambio de Membresia
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param integer $idProductoNuevo  Identificador de la nueva membresía
     * @param integer $importe          costo por cambio de membresía
     * @param integer $idPersona        Identificador del propietario de la membresía
     * @param integer $idProductoActual Identificador de la membresía actual
     * @param integer $idUn             Identificador de la unidad de negocio
     *  @param array   $socios           Arreglo de socios que integran la membresia
     * @param integer $correccion       Bandera que indica si es correcion o se guarda normal
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function guardaCambioMembresia ($idUnicoMembresia, $idProductoNuevo, $importe, $idPersona, $idProductoActual, $idMantenimientoActual, $idMantenimientoNuevo, $idUn, $socios, $correccion = 0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idProductoNuevo, 'integer');
        settype($idPersona, 'integer');
        settype($idProductoActual, 'integer');
        settype($idUn, 'integer');

        $mensaje = 'Cambio tipo de producto de';

        if (! $idUnicoMembresia) {
            return 6;
        }
        if ($correccion) {
            $mensaje = 'Correccion cambio tipo de producto de';
        }

        $set   = array ('idProducto'      => $idProductoNuevo);
        $where = array('idUnicoMembresia' => $idUnicoMembresia);
        $res   = $this->db->update(TBL_MEMBRESIA, $set, $where);

        if (count($socios) and $res) {
            $ci =& get_instance();
            $ci->load->model('producto_model');
            $ci->load->model('mantenimientos_model');

            $membresiaActual = utf8_encode($ci->producto_model->nombre($idProductoActual));
            $membresiaNuevo  = utf8_encode($ci->producto_model->nombre($idProductoNuevo));
            $mttoActual      = $ci->mantenimientos_model->obtenMantenimientoNombre($idMantenimientoActual);
            $mttoNuevo       = $ci->mantenimientos_model->obtenMantenimientoNombre($idMantenimientoNuevo);

            //$this->permisos_model->log(utf8_decode('Cambio de tipo membresia ('.$membresiaActual.') a ('.$membresiaNuevo.')'), LOG_MEMBRESIA, $idUnicoMembresia);

            $idMantenimiento = $this->obtenerIdMantenimiento($idUnicoMembresia, $idUn);
            if($idMantenimiento == null){
                $idMantenimiento = SINGLE_CLUB;
            }
            $where = array(
                'idUnicoMembresia' => $idUnicoMembresia,
                'eliminado' => 0
            );
            $set = array('idMantenimiento'  => $idMantenimiento);
            $res = $this->db->update(TBL_SOCIO, $set, $where);
            $this->permisos_model->log(utf8_decode('Cambio de tipo mantenimiento de '.$mttoActual.' a '.$mttoNuevo.' por cambio de membresia'), LOG_MEMBRESIA, $idUnicoMembresia);

            if ( ! $correccion) {
                $datos = array (
                    'idUnicoMembresia'      => $idUnicoMembresia,
                    'idEmpleado'            => $this->session->userdata('idPersona'),
                    'idPersona'             => $idPersona,
                    'importe'               => $importe,
                    'idTipoActual'          => $idProductoActual,
                    'idTipoNuevo'           => $idProductoNuevo,
                    'idMantenimientoActual' => $idMantenimientoActual,
                    'idMantenimientoNuevo'  => $idMantenimientoNuevo,
                );
                $res = $this->db->insert(TBL_MEMBRESIAAMPLIACION, $datos);
                $this->permisos_model->log(utf8_decode($mensaje." ".$membresiaActual."(".$mttoActual.") a ".$membresiaNuevo."(".$mttoNuevo.")"), LOG_MEMBRESIA, $idUnicoMembresia);

                if ($importe > 0 and $res) {
                    $ci->load->model('movimientos_model');
                    $ci->load->model('un_model');
                    $iva = $ci->un_model->iva($this->session->userdata('idUn'));
                }
            } else {
                $this->permisos_model->log(utf8_decode($mensaje." ".$membresiaActual."(".$mttoActual.") a ".$membresiaNuevo."(".$mttoNuevo.")"), LOG_MEMBRESIA, $idUnicoMembresia);
                return 1;
            }
        } else {
            return 6;
        }

        return 1;
    }

    /**
     * Guarda comprobante por persona
     *
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function guardaComprobante($idPersona, $idTipoDocumento, $idTipoDocumentoActual, $unico=0)
    {
        settype($idTipoDocumento, 'integer');
        settype($idTipoDocumentoActual, 'integer');
        settype($idPersona, 'integer');

        $datos = array ('idPersona' => $idPersona, 'idTipoDocumento' => $idTipoDocumento);

        $this->db->select('idPersonaDocumento');
        $this->db->from(TBL_PERSONADOCUMENTO);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('idTipoDocumento', $idTipoDocumentoActual);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ( $query->num_rows > 0 ) {
            $fila = $query->row_array();
            $id = $fila['idPersonaDocumento'];
            $this->db->where('idPersonaDocumento', $id);
            $this->db->update(TBL_PERSONADOCUMENTO, $datos);
            $this->permisos_model->log(utf8_decode("Se actualizo comprobante para socio (".date('Y-m-d').")") , LOG_MEMBRESIA, $unico);
            return true;
        } else {
            $this->db->insert(TBL_PERSONADOCUMENTO, $datos);
            $id = $this->db->insert_id();
            $this->permisos_model->log(utf8_decode("Se inserto comprobante para socio (".date('Y-m-d').")") , LOG_MEMBRESIA, $unico);
            return true;
        }
    }

    /**
     * guarda descuento registrado
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    function guardaDescuentoMttoConf($opciones)
    {
        $modificacion=0;
        if (isset ($opciones['descripcion'])) {
            $data = array (
                'descripcion'         => $opciones['descripcion'],
                'activo'              => $opciones['activo'],
                'requierePermiso'     => $opciones['permiso'],
                'habilitarPromocion'  => $opciones['promocion'],
                'porcentajeDescuento' => $opciones['descuento'],
                'idMantenimiento'     => $opciones['idMantenimiento'],
                'idEsquemaPago'       => $opciones['idEsquemaPago'],
                'inicioVigencia'      => $opciones['inicio'],
                'finVigencia'         => $opciones['fin']
            );
            $modificacion++;
        } else {
            $data = array ('activo' => $opciones['activo']);
            $modificacion++;
        }

        if ($opciones['idMembresiaDescuentoMtto'] >= 0) {
            $this->db->where('idMembresiaDescuentoMtto', $opciones['idMembresiaDescuentoMtto']);
            $this->db->update(TBL_MEMBRESIADESCUENTOMTTO, $data);
            $this->permisos_model->log('Actualizacion de configuracion en promocion de mantenimiento', LOG_SISTEMAS);
        } else {
            $this->db->insert(TBL_MEMBRESIADESCUENTOMTTO, $data);
            $this->permisos_model->log('Inserta configuracion en promocion de mantenimiento', LOG_SISTEMAS);
        }

        $total = $this->db->affected_rows();
        if ($total == 0) {
            if ($modificacion == 1) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Guarda extras de membresia
     *
     * @param integer $idUnicoMembresia      Identificador unico de membresia
     * @param integer $idTipoMembresiaExtras Identificador tipomembresiaextras
     * @param integer $valor                 Valor a guardar
     *
     * @author Jonathan Alcantara
     */
    public function guardaExtras ($idUnicoMembresia, $idTipoMembresiaExtras, $valor = '')
    {
        settype($idUnicoMembresia, 'integer');
        settype($idTipoMembresiaExtras, 'integer');
        settype($valor, 'string');

        $datos = array(
            'error'             => 1,
            'mensaje'           => 'Error faltan datos',
            'idMembresiaExtras' => 0
        );
        if ( ! $idUnicoMembresia or ! $idTipoMembresiaExtras) {
            return $datos;
        }
        $datos['mensaje'] = '';
        $datos['error']   = 0;
        $set              = array (
            'valor'                 => $valor,
            'idUnicoMembresia'      => $idUnicoMembresia,
            'idTipoMembresiaExtras' => $idTipoMembresiaExtras

        );
        if ($this->db->insert(TBL_MEMBRESIAEXTRAS, $set)) {
            $datos['idMembresiaExtras'] = $this->db->insert_id();
        }
        if ($datos['idMembresiaExtras']) {
            $this->permisos_model->log(utf8_decode("Usuario ".$this->session->userdata('usuario')." marca membresia con actualizacion de datos"), LOG_MEMBRESIA, $idUnicoMembresia);
        }
        return $datos;
    }

    /**
     * [guardaFidelidad description]
     *
     * @param  [type] $idTipoFidelidad      [description]
     * @param  [type] $mesesConsecutivos    [description]
     * @param  [type] $idMembresiaFidelidad [description]
     * @param  [type] $idUnicoMembresia     [description]
     * @return [type]                       [description]
     *
     */
    public function guardaFidelidad($idTipoFidelidad, $mesesConsecutivos, $idMembresiaFidelidad, $idUnicoMembresia)
    {
        settype($idTipoFidelidad, 'integer');
        settype($mesesConsecutivos, 'integer');
        settype($idMembresiaFidelidad, 'integer');
        settype($idUnicoMembresia, 'integer');

        $resultado = 0;
        $this->db->select('tf.descripcion');
        $this->db->from(TBL_MEMBRESIAFIDELIDAD.' mf');
        $this->db->join(TBL_TIPOFIDELIDAD.' tf ', 'tf.idTipoFidelidad=mf.idTipoFidelidad');
        $this->db->where('mf.idMembresiaFidelidad', $idMembresiaFidelidad);
        $rs0 = $this->db->get();
        if ( $rs0->num_rows()>0 ) {
            $row = $rs0->row();
            $fidelidadAnterior = $row->descripcion;
        }

        $this->db->select('mesesConsecutivos');
        $this->db->from(TBL_MEMBRESIAFIDELIDAD);
        $this->db->where('idMembresiaFidelidad', $idMembresiaFidelidad);
        $rs9 = $this->db->get();
        if ( $rs9->num_rows()>0 ) {
            $row = $rs9->row();
            $mesesConsecutivosAnterior = $row->mesesConsecutivos;
        }

        $this->db->select('descripcion');
        $this->db->from(TBL_TIPOFIDELIDAD);
        $this->db->where('idTipoFidelidad', $idTipoFidelidad);
        $rs1 = $this->db->get();
        if ( $rs1->num_rows()>0 ) {
            $row = $rs1->row();
            $fidelidadNueva = $row->descripcion;
        }

        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idMembresiaFidelidad', $idMembresiaFidelidad);
        $datos = array ('fechaEliminacion' => date("Y-m-d H:i:s"));
        $this->db->update(TBL_MEMBRESIAFIDELIDAD, $datos);

        if ( $this->db->affected_rows()>0 ) {
            $set = array (
                'idUnicoMembresia'     => $idUnicoMembresia,
                'idTipoFidelidad'      => $idTipoFidelidad,
                'mesesConsecutivos'    => $mesesConsecutivos,
                'autorizacionEspecial' => 1,
                'fechaRegistro'        => date("Y-m-d H:i:s")
            );
            $this->db->insert(TBL_MEMBRESIAFIDELIDAD, $set);

            if ( $this->db->affected_rows()>0 ) {
                $this->permisos_model->log("Cambio de fidelidad de ".$fidelidadAnterior." (".$mesesConsecutivosAnterior." meses) a ".$fidelidadNueva." (".$mesesConsecutivos." meses)", LOG_MEMBRESIA, $idUnicoMembresia);
                $resultado = 1;
            }
        }

        return $resultado;
    }

    /**
     * Guarda Mensajes
     *
     * @author
     *
     * @return array
     */
    public function guardaMensajes($idSocio, $titulo, $descripcion, $alerta, $fechaAlerta, $macceso)
    {
        settype($idSocio, 'integer');
        settype($alerta, 'integer');
        settype($macceso, 'integer');

        $datos = array (
            'idSocio'      => $idSocio,
            'idPersona'    => $this->session->userdata('idPersona'),
            'titulo'       => utf8_decode($titulo),
            'mensaje'      => utf8_decode($descripcion),
            'alerta'       => $alerta,
            'fechaAlerta'  => $fechaAlerta,
            'enviarAcceso' => $macceso
        );
        $this->db->insert('sociomensaje', $datos);
        if ( $this->db->affected_rows() > 0 ) {
            $regresa=1;
        } else {
            $regresa=0;
        }

        return $regresa;
    }

    /**
     * Actualiza el club alterno de una membresia
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     * @param integer $idUnAlterno      Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    function guardaUnAlterno ($idUnicoMembresia, $idUnAlterno = 0)
    {
        settype($idUnAlterno, 'integer');
        settype($idUnAlterno, 'integer');

        if ( ! $idUnicoMembresia) {
            return false;
        }
        $where = array('idUnicoMembresia' => $idUnicoMembresia);
        $set   = array('idUnAlterno'      => $idUnAlterno);

        $r = $this->db->update(TBL_MEMBRESIA, $set, $where);
        $this->permisos_model->log('Asigna club alterno a la membresia', LOG_SISTEMAS, $idUnicoMembresia);
        return $r;
    }

    /**
     * Guarda Involucrado
     *
     * @param integer $membresia Identificador unico de la membresia
     * @param integer $persona   Identificador de persona
     * @param integer $tipo      Identificador tipo persona
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function guardarInvolucrado($membresia, $persona, $tipo)
    {
        settype($persona, 'integer');
        settype($membresia, 'integer');
        settype($tipo, 'integer');

        if ($persona == 0) {
            return false;
        }

        if ($membresia == 0) {
            return false;
        }

        if ($tipo == 0) {
            return false;
        }

        $datos = array (
            'idUnicoMembresia'  => $membresia,
            'idPersona'         => $persona,
            'idTipoInvolucrado' => $tipo
        );

        $this->db->select('idMembresiaInvolucrado');
        $this->db->from(TBL_MEMBRESIAINVOLUCRADO);
        $this->db->where('idUnicoMembresia', $membresia);
        $this->db->where('idTipoInvolucrado', $tipo);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $id = $fila['idMembresiaInvolucrado'];
            $this->db->where('idMembresiaInvolucrado', $id);
            $this->db->update(TBL_MEMBRESIAINVOLUCRADO, $datos);
            $this->permisos_model->log('Se actualiza involucrado en la membresia', LOG_SISTEMAS, $membresia);
        } else {
            $this->db->insert(TBL_MEMBRESIAINVOLUCRADO, $datos);
            $id = $this->db->insert_id();
            $this->permisos_model->log('Se inserta involucrado en la membresia', LOG_SISTEMAS, $membresia);
        }

        return true;
    }

    /**
     * [historicoCobrosCA description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function historicoCobrosCA($idUnicoMembresia)
    {
        settype($idUnicoMebresia, 'integer');

        $datos  = array();

        $sql = "SELECT
                unca.clave AS unClave, unca.nombre AS unNombre,
                ca.fechaCobro, ca.tipoTarjeta AS tipoProceso,
                CASE ca.tipoTarjeta
                    WHEN 1 THEN 'Visa/Mastercard'
                    WHEN 2 THEN 'American Express'
                    WHEN 3 THEN 'Domiciliado'
                    ELSE 'NE'
                END AS tipoProcesoDesc,
                RIGHT(ca.numeroTarjetaCta, 4) AS numeroTarjeta, ca.referencia,
                ca.importe, ca.estatus, ca.descEstatus,
                IF(ca.estatus IN (2), 'ERROR', REPLACE(SUBSTRING_INDEX(ca.descEstatus, '[', 1), '|', '')) AS estatusProceso,
                IF(ca.estatus IN (2), 'Sin respuesta del banco', REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(ca.descEstatus, '[', -1), ']', 1), '|', '')) AS estatusProcesoCod,
                IF(ca.estatus IN (2), 'Sin respuesta del banco', REPLACE(SUBSTRING_INDEX(ca.descEstatus, ']', -1), '|', '')) AS estatusProcesoDesc
            FROM crm.finanzascahistoricocobros ca
                INNER JOIN crm.un unca ON unca.idUn=ca.idUN
            WHERE ca.idUnicoMembresia IN (".$idUnicoMembresia.")
            ORDER BY ca.fechaCobro DESC";
        $rs     = $this->db->query($sql);
        if( $rs->num_rows()>0 ){
            foreach ( $rs->result_array() as $id=>$row ) {
                $datos[$id]=$row;
            }
        }

        return $datos;
    }

    /**
     * Agrega leyenda Soc free en socio pago mtto
     *
     * @author Santa Garcia
     *
     * @return string
     */
    function insertaLeyendaSocFree($idSocioPagoMtto)
    {
        settype($idSocioPagoMtto, 'integer');

        $sql = "SELECT idSocioPagoMtto, origen, idUnicoMembresia
            FROM (sociopagomtto)
            WHERE idSocioPagoMtto = ".$idSocioPagoMtto."
            AND eliminado  = 0
            AND  (origen NOT LIKE '%SOC-FREE%' or origen is null)";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idSocioPagoMtto', $fila['idSocioPagoMtto']);
            $origen = $fila['origen'].' SOC-FREE';
            $datos = array('origen'=> $origen);
            $this->db->update(TBL_SOCIOPAGOMTTO, $datos);
            $this->permisos_model->log('Marcando pago mtto con leyenda SOC-FREE', LOG_SISTEMAS, $fila['idUnicoMembresia']);
        }
        return true;
    }

    /**
     * Inserta pago mtto
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function insertaPagoMtto($opciones, $pagoGratis = 0, $validaAusencia = 1, $numeroIntegrantes = 0)
    {
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $ci->load->model('movimientos_model');
        $nombre = $ci->persona_model->nombre($opciones['idPersona']);
        $fecha = explode('-',$opciones['inicio']);
        if($opciones['idEsquemaPago'] == ESQUEMA_PAGO_ANUAL){
            $fechaFin = $fecha[0].'-'.'12-'.'31';
        } else {
            $fecha = explode('-',$opciones['inicio']);
            $fechaFin = $fecha[0].'-'.$fecha[1].'-'.date("d",(mktime(0,0,0,$fecha[1]+1,1,$fecha[0])-1));
        }
        $origen = utf8_decode('MembresiaPagoMtto');

        if($opciones['idMovimiento']==0 && $pagoGratis!= 1){
            $sql = "CALL spCalculaMtto2(".$opciones['un'].", ".$opciones['membresia'].", ".$opciones['idPersona'].", '".$opciones['inicio']."', ".$opciones['idMantenimiento'].", 0, ".$opciones['idEsquemaPago'].", 0, ".$validaAusencia.",".$numeroIntegrantes.", 0, @des, @imp, @iva, @cuenta, @producto)";
            $query = $this->db->query($sql);

            $sql = "SELECT @des AS descripcion, @imp AS importe, @iva AS iva, @cuenta AS cuenta, @producto AS producto, IF('".$opciones['inicio']."'>LAST_DAY(DATE(NOW())), 1, 0) AS adelantado ";
            $query = $this->db->query($sql);

            $fila = $query->row_array();
            if($opciones['ausencia'] == 1){
                if ($opciones['un'] == 26) {
                    $importe = $fila['importe']/4;
                } else {
                    $importe = $fila['importe']/2;
                }
                $auseuncia = ' Ausencia';
            } else {
                $importe = $fila['importe'];
                $auseuncia = '';
            }

            if ($fila['adelantado'] == 1) {
                $origen = utf8_decode('MembresiaPagoMtto').' MTTOADE ';
            }
            if(isset ($opciones['descripcion'])){
                $descripcion = $opciones['descripcion'];
            } else {
                $descripcion = 'Mantenimiento '.utf8_decode($fila['descripcion']).$auseuncia;
            }
            if (isset ($opciones['origen'])) {
                $origen = $opciones['origen'];
            }
            if (isset ($opciones['generaMovimiento'])) {

            } else {
                $opciones['generaMovimiento'] = 0;
            }
            $movimiento = 0;
            if($opciones['generaMovimiento'] == 0){
                $datosMovimiento = array (
                    'fecha'       => $opciones['inicio'],
                    'tipo'        => MOVIMIENTO_TIPO_MANTENIMIENTO,
                    'descripcion' => $descripcion,
                    'importe'     => number_format($importe, 2, '.', ''),
                    'iva'         => $fila['iva'],
                    'membresia'   => $opciones['unico'],
                    'producto'    => $fila['producto'],
                    'persona'     => $opciones['idPersona'],
                    'numeroCuenta'=> $fila['cuenta'],
                    'origen'      => $origen,
                    'idUn'        => $opciones['un']
                );
                if (isset ($opciones['movimiento1'])) {
                    $movimiento = 0;
                } else {
                    $movimiento = $ci->movimientos_model->inserta($datosMovimiento);
                }
            } else {
                $movimiento = 0;
            }
        } else {
            $movimiento = $opciones['idMovimiento'];
        }

        if (isset ($opciones['porcentaje'])) {
            $porcentaje = $opciones['porcentaje'];
        } else {
            $porcentaje ='100.00';
        }
        $datos = array (
            'fechaInicio'      => $opciones['inicio'],
            'fechaFin'         => $fechaFin,
            'idMovimiento'     => $movimiento,
            'idPersona'        => $opciones['idPersona'],
            'idEsquemaPago'    => $opciones['idEsquemaPago'],
            'idUnicoMembresia' => $opciones['unico'],
            'idMantenimiento'  => $opciones['idMantenimiento'],
            'activo'           => $opciones['estatus'],
            'origen'           => $origen,
            'porcentaje'       => $porcentaje,
            'idSocio'          => $opciones['idSocio'],
            'ausencia'         => $opciones['ausencia']
        );
        $this->db->insert(TBL_SOCIOPAGOMTTO, $datos);
        $idpagoMtto = $this->db->insert_id();

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se ingreso pago de mantenimiento '.$opciones['mantenimiento'].' a '.$nombre.' ('.date('Y-m-d').')', LOG_MEMBRESIA,$opciones['unico']);
            return true;
        }
    }

    /**
     * Inserta Promocion
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function insertaPromocion($unico, $idPromoMttoUn, $fechaInicio,$fechafin , $indeterminado,  $cliente)
    {
        settype($unico, 'integer');
        settype($idPromoMttoUn, 'integer');
        settype($indeterminado, 'integer');
        settype($cliente, 'integer');

        $datos = array (
            'idPromoMttoUn'     => $idPromoMttoUn,
            'idUnicoMembresia'  => $unico,
            'idTipoRolCliente'  => $cliente,
            'indeterminado'     => $indeterminado,
            'fechaInicio'       => $fechaInicio,
            'fechaFin'          => $fechafin,
            'idPersona'         => $this->session->userdata('idPersona')
        );
        $this->db->select('idMembresiaPromoMtto');
        $this->db->from(TBL_MEMBRESIAPROMOMTTO);
        $where = array('idPromoMttoUn' => $idPromoMttoUn, 'idUnicoMembresia'=>$unico,'idTipoRolCliente'=>$cliente,'fechaEliminacion'=>'0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idMembresiaPromoMtto', $fila->idMembresiaPromoMtto);
                $this->db->update(TBL_MEMBRESIAPROMOMTTO, $datos);
                $this->permisos_model->log('Se actuliaza promocion mtto a la membresia ('.$unico.') ('.date('Y-m-d').')', LOG_MEMBRESIA, $unico);

                return $fila->idMembresiaPromoMtto;
            }
        } else {
            $this->db->insert(TBL_MEMBRESIAPROMOMTTO, $datos);
            $id = $this->db->insert_id();
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se registro una nueva promocion a la membresia ('.$unico.') ('.date('Y-m-d').')', LOG_MEMBRESIA, $unico);
            return $id;
        }
    }

    /**
     * Inserta los movimientos correspondientes a la reactivacion
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function insertaReactivacionMovimiento($reactivacion, $idMovimiento, $tipo, $unico)
    {
        settype($reactivacion, 'integer');
        settype($idMovimiento, 'integer');
        settype($tipo, 'integer');

        $movimiento = array (
            'idMembresiaReactivacion' => $reactivacion,
            'idMovimiento'            => $idMovimiento,
            'clasificacionMovimiento' => $tipo
        );
        $this->db->insert(TBL_MEMBRESIAREACTIVACIONMOVIMIENTO, $movimiento);
        $idMemReactivacionMovimiento = $this->db->insert_id();

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            if ($tipo==1) {
                $mensaje='Reactivacion';
            } else {
                $mensaje='Mantenimiento';
            }
            $this->permisos_model->log('Se registro movimiento de '.$mensaje , LOG_SISTEMAS, $unico);
            return true;
        }
    }

    /**
     * Inserta los participantes correspondientes a la reactivacion
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function insertaReactivacionParticipantes($reactivacion, $idPersona, $tipo, $unico)
    {
        settype($reactivacion, 'integer');
        settype($idPersona, 'integer');
        settype($tipo, 'integer');

        $participante = array (
            'idMembresiaReactivacion' => $reactivacion,
            'idPersona'               => $idPersona,
            'tipoParticipantes'       => $tipo
        );
        $this->db->insert(TBL_MEMBRESIAREACTIVACIONPARTICIPANTES, $participante);
        $idMemReactivacionparticipante = $this->db->insert_id();

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            if ($tipo==1) {
                $mensaje='Empleado';
            } else {
                if ($tipo==2) {
                    $mensaje='Titular';
                } else {
                    $mensaje='Vendedor';
                }
            }
            $this->permisos_model->log('Se registro '.$mensaje.' ('.$idPersona.') como participante en reactivacion', LOG_SISTEMAS, $unico);
            return true;
        }
    }

    /**
     * Lista de alta de socios
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaAltaSocios($opciones)
    {
        $opcion='';
        if($opciones['idUn'] != 0){
            $opcion = " and m.idUn=".$opciones['idUn'];
        }
        $membresia ='';
        if($opciones['idMembresia'] != 0){
            $membresia = " and m.idMembresia=".$opciones['idMembresia'];
        }
        $sql = "SELECT u.nombre as Club, m.idMembresia AS Membresia,  pr.nombre as TipoMantenimiento, concat_ws(' ',p.nombre,p.paterno,p.materno) as Socio,
                date(s.fechaRegistro) as Fecha
            from socio s
            inner join membresia m on m.idUnicoMembresia=s.idUnicoMembresia and m.eliminado=0
            inner join persona p on p.idPersona = s.idPersona
            inner join un u on u.idUn = m.idUn and u.activo = 1
            inner join productomantenimiento pm on pm.idMantenimiento = s.idMantenimiento
            inner join producto pr on pr.idProducto = pm.idProducto and pr.activo=1
            where
            date(s.fechaRegistro) between  '".$opciones['fechaInicio']."' and '".$opciones['fechaFin']."' $opcion $membresia  and m.idUnicoMembresia not in(3,5442) order by 1 desc ";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de ampliaciones
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaAmpliaciones($opciones)
    {
        $opcion ='';
        if($opciones['idUn'] != 0){
            $opcion = " AND m.idUn = ".$opciones['idUn'];
        }
        $membresia ='';
         if($opciones['idMembresia'] != 0){
            $membresia = " AND m.idMembresia = ".$opciones['idMembresia'];
        }
        $sql = "
            SELECT u.nombre as club, m.idMembresia, pr.descripcion as tipomembresia, CONCAT_WS(' ',per.nombre,per.paterno,per.materno) as propietario,
                CONCAT_WS(' ',per2.nombre,per2.paterno,per2.materno) as elaboro, ma.importe, DATE(ma.fechaRegistro) as fecha, prod.nombre as actualProducto, prod2.nombre as nuevoProducto,
                prod3.nombre AS mttoActual, prod4.nombre AS mttoNuevo, ma.idUnicoMembresia
            FROM membresiaampliacion ma
            INNER JOIN membresia m ON m.idUnicoMembresia = ma.idUnicoMembresia and m.eliminado=0
            INNER JOIN un u ON u.idUn = m.idUn and u.activo = 1
            INNER JOIN producto pr ON pr.idProducto = m.idProducto and pr.activo=1
            INNER JOIN membresiainvolucrado mi ON mi.idUnicoMembresia = m.idUnicoMembresia AND mi.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN persona per ON per.idPersona = mi.idPersona AND mi.idTipoInvolucrado = ".INVOLUCRADO_PROPIETARIO."
            INNER JOIN persona per2 ON per2.idPersona = ma.idEmpleado
            INNER JOIN producto prod ON prod.idProducto = ma.idTipoActual
            INNER JOIN producto prod2 ON prod2.idProducto = ma.idTipoNuevo
            LEFT JOIN productomantenimiento pm ON pm.idMantenimiento = ma.idMantenimientoActual
            LEFT JOIN productomantenimiento pm2 ON pm2.idMantenimiento = ma.idMantenimientoNuevo
            LEFT JOIN producto prod3 ON prod3.idProducto = pm.idProducto
            LEFT JOIN producto prod4 ON prod4.idProducto = pm2.idProducto
            WHERE  DATE(ma.fechaRegistro) BETWEEN '".$opciones['fechaInicio']."' AND '".$opciones['fechaFin']."'  $opcion  $membresia AND ma.idUnicoMembresia NOT IN(3,5442)
            GROUP BY ma.idUnicoMembresia, ma.fechaRegistro DESC";

        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de ausencia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaAusencias($opciones)
    {
        $opcion = '';
        if($opciones['idUn'] != 0){
            $opcion = " and m.idUn=".$opciones['idUn'];

        }
        $membresia ='';
        if($opciones['idMembresia'] != 0){
            $membresia = " and m.idMembresia=".$opciones['idMembresia'];
        }
        $sql = "SELECT u.nombre as club, m.idMembresia, concat_ws(' ',p.nombre,p.paterno,p.materno) as socio,
                date(s.fechaAusencia) as fecha
            from socioausencia s
            inner join socio so on so.idSocio = s.idSocio
            inner join membresia m on m.idUnicoMembresia = so.idUnicoMembresia and m.eliminado=0
            inner join persona p on p.idPersona = so.idPersona
            inner join un u on u.idUn = m.idUn and u.activo = 1
            where date(s.fechaAusencia) between '".$opciones['fechaInicio']."' and '".$opciones['fechaFin']."' and s.fechaEliminacion = '0000-00-00 00:00:00'
                $opcion $membresia and m.idUnicoMembresia not in(3,5442)
                order  by 1 desc";

        $query = $this->db->query($sql);
        #echo $this->db->last_query();
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de alta de socios
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaBajaSocios($opciones)
    {
        $opcion='';
        if($opciones['idUn'] != 0){
            $opcion = " and m.idUn=".$opciones['idUn'];
        }
        $membresia ='';
        if($opciones['idMembresia'] != 0){
            $membresia = " and m.idMembresia=".$opciones['idMembresia'];
        }
        $sql = "SELECT u.nombre as Club, m.idMembresia AS Membresia,  pr.nombre as TipoMantenimiento,
                concat_ws(' ',p.nombre,p.paterno,p.materno) as Socio, date(s.fechaEliminacion) as Fecha
            from socio s
            inner join persona p on p.idPersona = s.idPersona
            inner join membresia m on m.idUnicoMembresia = s.idUnicoMembresia and m.eliminado=0
            inner join un u on u.idUn = m.idUn and u.activo = 1
            inner join productomantenimiento pm on pm.idMantenimiento = s.idMantenimiento
            inner join producto pr on pr.idProducto = pm.idProducto and pr.activo=1
            where date(s.fechaEliminacion) between '".$opciones['fechaInicio']."' and '".$opciones['fechaFin']."' $opcion $membresia and m.idUnicoMembresia not in(3,5442)  order by 1 desc ";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de cargo automatico
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaCAT($opciones)
    {
        $opcion = '';
        if($opciones['idUn'] != 0){
            $opcion = " and m.idUn=".$opciones['idUn'];

        }
        $membresia ='';
        if($opciones['idMembresia'] != 0){
            $membresia = " and m.idMembresia=".$opciones['idMembresia'];
        }
        $sql = "SELECT u.nombre as club, m.idMembresia, concat_ws(' ',p.nombre,p.paterno,p.materno) as socio,
                tt.descripcion, date(s.fechaRegistro) as fecha from sociodatostarjeta s
            inner join socio so on so.idSocio = s.idSocio
            inner join tipotarjeta tt on tt.idTipoTarjeta=s.tipoTarjeta
            inner join membresia m on m.idUnicoMembresia = so.idUnicoMembresia and m.eliminado=0
            inner join persona p on p.idPersona = so.idPersona
            inner join un u on u.idUn = m.idUn and u.activo = 1
            where date(s.fechaRegistro) between '".$opciones['fechaInicio']."' and '".$opciones['fechaFin']."' and s.activo=1
                $opcion $membresia and m.idUnicoMembresia not in(3,5442)
            order  by 1 desc";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de cancelacion de cargo automatico
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaCancelacionCAT($opciones)
    {
        $opcion = '';
        if($opciones['idUn'] != 0){
            $opcion = " and m.idUn=".$opciones['idUn'];

        }
        $membresia ='';
        if($opciones['idMembresia'] != 0){
            $membresia = " and m.idMembresia=".$opciones['idMembresia'];
        }
        $sql = "select u.nombre as club, m.idMembresia, concat_ws(' ',p.nombre,p.paterno,p.materno) as socio, tt.descripcion,
        date(s.fechaEliminacion) as fecha from sociodatostarjeta s
            inner join socio so on so.idSocio = s.idSocio
                         inner join tipotarjeta tt on tt.idTipoTarjeta=s.tipoTarjeta
            inner join membresia m on m.idUnicoMembresia = so.idUnicoMembresia and m.eliminado=0
            inner join persona p on p.idPersona = so.idPersona
            inner join un u on u.idUn = m.idUn and u.activo = 1
                where date(s.fechaEliminacion) between '".$opciones['fechaInicio']."' and '".$opciones['fechaFin']."' and s.activo=0
                $opcion $membresia and m.idUnicoMembresia not in(3,5442)
                order  by 1 desc";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de cesion d ederechos
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaCesionDerechos($opciones)
    {
        $opcion ='';
        $opcion2 ='';
        if($opciones['idUn'] != 0){
            $opcion = " and mi.idUn=".$opciones['idUn'];
            $opcion2 = " and ma.idUn=".$opciones['idUn'];
        }
        $membresia ='';
        if($opciones['idMembresia'] != 0){
            $membresia = " and m.idMembresia=".$opciones['idMembresia'];
        }
        $sql = "SELECT u.nombre as club, m.idMembresia, t.nombre as cedente, t2.nombre as cesionario, date(ma.fechaRegistro) as fecha from membresiacesion ma
            inner join membresia m on m.idUnicoMembresia = ma.idUnicoMembresia and m.eliminado=0
                        inner join un u on u.idUn = ma.idUn and u.activo = 1
            inner join ( select mp.idMembresiaCesion,concat_ws(' ',pe.nombre,pe.paterno,pe.materno) as nombre
            from membresiacesion mi
                inner join membresiacesionparticipante mp on mp.idMembresiaCesion= mi.idMembresiaCesion
                inner join persona pe on pe.idPersona = mp.idPersona
                inner join membresia m on m.idUnicoMembresia = mi.idUnicoMembresia
             where mp.tipoParticipante in (1) and mp.tipoParticipante not in (2,3,4) $opcion
             group by mi.idMembresiaCesion
             ) as t on t.idMembresiaCesion = ma.idMembresiaCesion
              inner join ( select mp.idMembresiaCesion,concat_ws(' ',pe.nombre,pe.paterno,pe.materno) as nombre
            from membresiacesion mi
                inner join membresiacesionparticipante mp on mp.idMembresiaCesion= mi.idMembresiaCesion
                inner join persona pe on pe.idPersona = mp.idPersona
                inner join membresia m on m.idUnicoMembresia = mi.idUnicoMembresia
             where mp.tipoParticipante in (2) and mp.tipoParticipante not in (1,3,4)  $opcion
             group by mi.idMembresiaCesion
                ) as t2 on t2.idMembresiaCesion = ma.idMembresiaCesion
            where   date(ma.fechaRegistro) between '".$opciones['fechaInicio']."' and '".$opciones['fechaFin']."' $opcion2 $membresia order  by 5 desc";

        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista los los roles de clientes que aplican para una promocion
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function listaClientesPromocion($idPromoMttoUn)
    {
        settype($idPromoMttoUn, 'integer');

        $this->db->select('prc.idTipoRolCliente, trc.descripcion');
        $this->db->from(TBL_PROMOMTTOUNROLCLIENTE.' prc');
        $this->db->join(TBL_TIPOROLCLIENTE.' trc', 'trc.idTipoRolCliente = prc.idTipoRolCliente');
        $this->db->where('prc.idPromoMttoUn', $idPromoMttoUn);
        $this->db->where('trc.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista membresias por club
     *
     * @param integer $idUn           Identificador de la unidad de negocio
     * @param integer $numIntegrantes Indica el numero de integrantes en memebresía configuración
     * @param integer $opcion         Indicador para dferenciar entre productos, en este caso se hizo la diferencia para membresía collage
     * @param integer $tipoMembresia  Indica el tipo de membresía
     *
     *  @author Santa García
     *
     * @return array
     */
    public function listaClubMembresia($idUn, $numIntegrantes = 0, $opcion = 0, $tipoMembresia = 0, $idUnicoMembresia=0)
    {
        settype($idUn, 'integer');
        settype($numIntegrantes, 'integer');
        settype($idUnicoMembresia, 'integer');

        $this->db->distinct();
        $this->db->select('p.idProducto,p.nombre', false);
        $this->db->from(TBL_PRODUCTO .' p');
        $this->db->join(TBL_CATEGORIA .' c', 'c.idCategoria = p.idCategoria');
        $this->db->join(TBL_PRODUCTOUN .' pu', 'pu.idProducto = p.idProducto');
        $this->db->join(TBL_TIPOPRODUCTO.' tp', 'tp.idTipoProducto = p.idTipoProducto');
        $this->db->join(TBL_MEMBRESIACONFIGURACION.' mc', 'mc.idProductoUn = pu.idProductoUn');
        $this->db->join(TBL_MEMBRESIAOPCIONES." mo", "mc.idMembresiaConfiguracion = mo.idMembresiaConfiguracion");
        if ($tipoMembresia != 0) {
            $this->db->join(TBL_TIPOMEMBRESIA.' tm', 'tm.idTipoMembresia = mc.idTipoMembresia');
            $this->db->where('tm.idTipoMembresia', $tipoMembresia);
        }
        if ($idUn != 0) {
            $this->db->where('pu.idUn', $idUn);
        }
        if ($tipoMembresia != 0) {
            $this->db->where('pu.idUn', $idUn);
        }
        $this->db->where('tp.idTipoProducto', '1');
        $this->db->where('p.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pu.activo', '1');
        $this->db->where('mo.idTipoMembresiaOpcion', TIPO_NUMEROINTEGRANTES);
        if ($numIntegrantes > 0) {
            $this->db->where('mo.valor >=', $numIntegrantes);
        }
        $this->db->order_by("p.nombre");
        $query = $this->db->get();
        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $datos[0] = utf8_decode('Seleccione una opción');
                if ($idUnicoMembresia == 0) {
                    $datos[$fila->idProducto] = utf8_encode($fila->nombre);
                } else {
                if ($opcion == 0) {
                    if ($fila->idProducto != PRODUCTO_MEMBRESIACOLLEGE) {
                        $permiso  = $this->tieneAtributo($idUnicoMembresia, $idUn, MEM_ATRIB_SEL_AMPLIAR, $fila->idProducto);
                        if ($permiso == true) {
                            $datos[$fila->idProducto] = $fila->nombre;
                        }
                    }
                } else {
                    $permiso  = $this->tieneAtributo($idUnicoMembresia, $idUn, MEM_ATRIB_SEL_AMPLIAR, $fila->idProducto);
                    if ($permiso == true) {
                        $datos[$fila->idProducto] = utf8_encode($fila->nombre);
                    }
                }
               }
            }
        }
        if (isset($datos)) {
            return $datos;
        } else {
            return 0;
        }
    }

    /**
     * Busca la configuracion hecha para configurar conversion de mtto
     *
     * @author Santa Garcia
     *
     * @return void
     */
    function listaConversionMtto($opciones, $totales=0, $posicion=0, $registros=25, $orden = '')
    {
        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }
        $m='';
        $p='';
        if ($totales == 0) {
            if ($posicion == '') {
                $posicion = 0;
            }
            $m=" limit $posicion,$registros ";
        }
        if ($orden == '') {
            $orden = 'p.nombre, m.titular,
            m.cotitular,m.hijoMayor, m.hijoMenor,m.bebe,m.agregadoMayor,m.agregadoMenor,m.importe';
        }

        $sql = "
            select  t.descripcion,m.idMantenimientoConversion, m.idUn,u.nombre,m.idProducto,p.nombre as membresia,m.titular,
            m.cotitular,m.hijoMayor, m.hijoMenor,m.bebe,m.agregadoMayor,m.agregadoMenor,m.importe,m.idMantenimiento,p2.nombre as mtto,
            m.idMantenimientoNuevo,p3.nombre as mttoNuevo from mantenimientoconversion m
            inner join producto p on p.idProducto = m.idProducto and p.fechaEliminacion='0000-00-00 00:00:00'
            inner join un u on u.idUn = m.idUn and u.activo = 1
            inner join productomantenimiento pm on pm.idMantenimiento=m.idMantenimiento
            inner join producto p2 on p2.idProducto = pm.idProducto and p2.fechaEliminacion='0000-00-00 00:00:00'
            inner join productoun pu on pu.idProducto = p.idProducto and pu.fechaEliminacion= '0000-00-00 00:00:00' and pu.idUn=".$opciones["idUn"]."
            inner join membresiaconfiguracion mc on mc.idProductoUn = pu.idProductoUn   and mc.fechaEliminacion = '0000-00-00 00:00:00'
            inner join tipomembresia t on t.idTipoMembresia = mc.idTipoMembresia and t.activo = 1
            #left  join productomantenimiento pm2 on pm2.idMantenimiento=m.idMantenimientoNuevo
            left join producto p3 on p3.idProducto = m.idMantenimientoNuevo and p3.fechaEliminacion='0000-00-00 00:00:00' and p3.idProducto>0
            where m.idUn = ".$opciones["idUn"]." and t.idTipoMembresia=".$opciones["tipo_mtto"]."
            order by $orden $m";

        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            if ($totales == 1) {
                return $query->num_rows();
            }
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * funcion que lista los descuentos registrados en tabla
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    function listaDescuentosMtto()
    {
        $sql = "SELECT m.idMembresiaDescuentoMtto,m.descripcion,m.porcentajeDescuento,m.inicioVigencia,
                m.finVigencia, m.activo, m.idMantenimiento, m.idEsquemaPago, p.nombre AS mantenimiento,
                e.descripcion AS formaPago
            FROM membresiadescuentomtto m
            LEFT JOIN productomantenimiento pm ON pm.idProducto = m.idMantenimiento
            LEFT JOIN producto p ON p.idProducto = pm.idProducto AND p.activo=1
            LEFT JOIN esquemapago e ON e.idEsquemaPago = m.idEsquemaPago AND e.activo = 1
            WHERE m.fechaEliminacion='0000-00-00 00:00:00'
            ORDER BY inicioVigencia,m.finVigencia DESC";
        $query = $this->db->query($sql);
        $data  = array();

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista estatus de membresias
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function listaEstatusMembresia ()
    {
        $datos = array();
        $where = array('activo' => 1);

        $query = $this->db->select(
            'idTipoEstatusMembresia, descripcion AS estatusMembresia'
        )->order_by('estatusMembresia')->get_where(TBL_TIPOESTATUSMEMBRESIA, $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Lista Integrantes membresia
     *
     * @author
     *
     * @return array
     */
    public function listaIntegrantesMembresia($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $lista = array();
        $lista['0'] = 'Selecciona una persona...';
        $sql = "SELECT s.idSocio, CONCAT(p.nombre,' ',p.paterno,' ',p.materno) AS nombrec
            FROM socio s
            LEFT JOIN persona p ON s.idPersona=p.idPersona
            WHERE s.idunicoMembresia=$idUnicoMembresia AND s.eliminado=0";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idSocio] = utf8_encode($fila->nombrec);
            }
        }

        return $lista;
    }

    /**
     * Lista promociones vigentes existentes por club
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function listaPromociones($idUn, $idProducto, $venta = 0)
    {
        settype($idUn, 'integer');
        settype($idProducto, 'integer');
        settype($venta, 'integer');

        $a = '';
        if($idUn != 0){
            $a = " pu.idUn=$idUn";
        }
        $c='';
        if($idProducto != 0){
            $c = " AND pu.idProducto=$idProducto";
        }
        $b='';
        if($venta != 0){
            $b = " AND pu.automatico=$venta";
            $sql = "SELECT * FROM (
                    SELECT pu.idUn,pu.idPromoMttoUn, tp.descripcion
                    FROM promomttoun pu
                    INNER JOIN tipopromomtto tp ON tp.idTipoPromoMtto=pu.idTipoPromoMtto
                    WHERE tp.fechaEliminacion='0000-00-00 00:00:00'
                        AND ((NOW() BETWEEN pu.fechaInicio AND pu.fechafin) || pu.indeterminado=1)
                        AND ($a || pu.idUn=0) $b $c
                    ORDER BY pu.idUn DESC, tp.descripcion
                ) a GROUP BY a.descripcion";
        } else {
            $sql = "SELECT pu.idUn,pu.idPromoMttoUn, CONCAT_WS(' ',tp.descripcion,
                ROUND(pu.porcentajeDescuento,2),'%') AS descripcion
            FROM promomttoun pu
            INNER JOIN tipopromomtto tp ON tp.idTipoPromoMtto=pu.idTipoPromoMtto
            WHERE tp.fechaEliminacion='0000-00-00 00:00:00'
                AND ((NOW() BETWEEN pu.fechaInicio AND pu.fechafin) || pu.indeterminado=1)
                AND ($a || pu.idUn=0) $c
            ORDER BY tp.descripcion ASC, pu.fechaInicio ASC, tp.descripcion";
        }

        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idPromoMttoUn] = $fila->descripcion;
            }
             return $lista;
        } else {
            return 0;
        }
    }

    /**
     * Lista las promociones registradas en una membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function listaPromocionesRegistradas($idUnicoMembresia=0, $idMembresiaPromoMtto=0)
    {
        settype($idUnicoMembresia, 'integer');

        $a = '';
        if ($idUnicoMembresia == 0) {
            $a = " AND m.idMembresiaPromoMtto=$idMembresiaPromoMtto";
        } else {
            $a = " AND m.idUnicoMembresia=$idUnicoMembresia";
        }

        $sql = "SELECT m.idMembresiaPromoMtto, ROUND(p.porcentajeDescuento, 2) AS porcentajeDescuento,
                m.idTipoRolCliente, m.idPromoMttoUn, m.idUnicoMembresia, m.idTipoRolCliente, m.indeterminado,
                m.fechaInicio, m.fechaFin, tr.descripcion AS rol, t.descripcion AS promocion
            FROM (membresiapromomtto m)
            INNER JOIN promomttoun p ON p.idPromoMttoUn=m.idPromoMttoUn
            INNER JOIN tipopromomtto t ON t.idTipoPromoMtto=p.idTipoPromoMtto
            INNER JOIN tiporolcliente tr ON tr.idTipoRolCliente=m.idTipoRolCliente
            WHERE m.fechaEliminacion = '00000-00-00 00:00:00' AND t.fechaEliminacion = '00000-00-00 00:00:00'
                AND tr.fechaEliminacion = '00000-00-00 00:00:00' $a";
        $query = $this->db->query($sql);
        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de reactivaciones
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaReactivaciones($opciones)
    {
        $opcion ='';
        if ($opciones['idUn'] != 0) {
            $opcion = " AND m.idUn=".$opciones['idUn'];
        }
        $membresia ='';
        if ($opciones['idMembresia'] != 0) {
            $membresia = " AND m.idMembresia=".$opciones['idMembresia'];
        }
        $sql = "SELECT u.nombre AS club, m.idMembresia, pr.nombre AS tipomembresia,
                CONCAT_WS(' ',p.nombre,p.paterno,p.materno) AS propietario,
                ma.importe, DATE(ma.fechaRegistro) AS fecha, t3.nombre AS vendedor
            FROM membresiareactivacion ma
            INNER JOIN membresia m on m.idUnicoMembresia = ma.idUnicoMembresia AND m.eliminado=0
            INNER JOIN un u on u.idUn = m.idUn AND u.activo = 1
            INNER JOIN producto pr on pr.idProducto = ma.idProducto AND pr.activo=1
            INNER JOIN socio so on so.idUnicoMembresia = ma.idUnicoMembresia
            INNER JOIN membresiareactivacionparticipantes mi on mi.idMembresiaReactivacion=ma.idMembresiaReactivacion
                AND mi.tipoParticipantes=2
            LEFT JOIN (
                SELECT mr.idUnicoMembresia, CONCAT_WS(' ',pe.nombre,pe.paterno,pe.materno) AS nombre
                FROM membresiareactivacion mr
                INNER JOIN membresiareactivacionparticipantes mi ON mi.idMembresiaReactivacion=mr.idMembresiaReactivacion
                INNER JOIN persona pe ON pe.idPersona = mi.idPersona
                INNER JOIN membresia m ON m.idUnicoMembresia = mr.idUnicoMembresia $opcion
                WHERE mi.tipoParticipantes=3 AND DATE(mr.fechaRegistro) BETWEEN '".$opciones['fechaInicio']."' AND '".$opciones['fechaFin']."'
                GROUP BY mr.idUnicoMembresia
            ) as t3 on t3.idUnicoMembresia = ma.idUnicoMembresia
            INNER JOIN persona p ON p.idPersona = mi.idPersona
            WHERE DATE(ma.fechaRegistro) BETWEEN '".$opciones['fechaInicio']."' AND '".$opciones['fechaFin'].
                "' $opcion $membresia AND ma.idUnicoMembresia NOT IN (3,5442)
            GROUP BY ma.idUnicoMembresia ORDER BY 1 DESC";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de ausencia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaRegresoAusencias($opciones)
    {
        $opcion = '';
        if ($opciones['idUn'] != 0) {
            $opcion = " and m.idUn=".$opciones['idUn'];
        }
        $membresia ='';
        if ($opciones['idMembresia'] != 0) {
            $membresia = " and m.idMembresia=".$opciones['idMembresia'];
        }
        $sql = "SELECT u.nombre AS club, m.idMembresia, CONCAT_WS(' ',p.nombre,p.paterno,p.materno) AS socio,
                DATE(s.fechaRegresoAusencia) AS fecha
            FROM socioausencia s
            INNER JOIN socio so ON so.idSocio = s.idSocio
            INNER JOIN membresia m ON m.idUnicoMembresia = so.idUnicoMembresia
                AND m.eliminado=0
            INNER JOIN persona p ON p.idPersona = so.idPersona
            INNER JOIN un u ON u.idUn = m.idUn AND u.activo = 1
            WHERE DATE(s.fechaRegresoAusencia) BETWEEN '".$opciones['fechaInicio']."' AND '".$opciones['fechaFin']."'
                AND s.fechaEliminacion = '0000-00-00 00:00:00' $opcion $membresia AND m.idUnicoMembresia NOT IN (3,5442)
            ORDER BY 1 DESC";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Lista de traspasos
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaTraspasos($opciones)
    {
        $opcion  ='';
        $opcion2 ='';
        $opcion3 ='';
        if($opciones['idUn'] != 0){
            $opcion = " AND mi.idUn=".$opciones['idUn'];
            $opcion2 = " AND ma.idUn=".$opciones['idUn'];
            $opcion3 = " AND mt.idUn=".$opciones['idUn'];
        }
        $membresia ='';
        if($opciones['idMembresia'] != 0){
            $membresia = ' AND m.idMembresia='.$opciones['idMembresia'];
        }
        $sql = "SELECT round(mov.importe,3) AS importe,u.nombre AS unOrigen, ma.idMembresia AS origen,
                t2.idMembresia AS destino, t2.nombre AS unDestino, pr.nombre AS tipomembresia,
                concat_ws(' ',p.nombre,p.paterno,p.materno) AS elaboro, t.nombre AS propietario,
                DATE(ma.fechaRegistro) AS fecha
            FROM membresiatraspaso ma
            INNER JOIN membresia m ON m.idUnicoMembresia = ma.idUnicoMembresia
                AND m.eliminado=0
            INNER JOIN persona p ON p.idPersona = ma.idPersona
            INNER JOIN un u ON u.idUn = ma.idUn AND u.activo = 1
            INNER JOIN producto pr ON pr.idProducto = m.idProducto AND pr.activo=1
            INNER JOIN movimiento mov ON mov.idMovimiento = ma.idMovimiento
            left join (
                SELECT mi.idUnicoMembresia,concat_ws(' ',pe.nombre,pe.paterno,pe.materno) as nombre
                FROM membresiainvolucrado mi
                INNER JOIN persona pe ON pe.idPersona = mi.idPersona
                INNER JOIN membresiatraspaso mt ON mt.idUnicoMembresia = mi.idUnicoMembresia
                INNER JOIN membresia m ON m.idUnicoMembresia = mi.idUnicoMembresia  $opcion3 $membresia
                WHERE mi.idTipoInvolucrado = 1 AND mi.eliminado = 0
            ) AS t ON t.idUnicoMembresia = ma.idUnicoMembresia
            LEFT JOIN (
                SELECT m.idUnicoMembresia,m.idMembresia, u.nombre
                FROM membresiatraspaso mi
                INNER JOIN membresia m ON m.idUnicoMembresia = mi.idUnicoMembresia $opcion $membresia
                INNER JOIN un u ON u.idUn = m.idUn AND u.activo = 1
                WHERE DATE(mi.fechaTraspaso) BETWEEN '".$opciones['fechaInicio']."' AND '".$opciones['fechaFin']."'
            ) AS t2 ON t2.idUnicoMembresia = ma.idUnicoMembresia
            WHERE DATE(ma.fechaTraspaso) BETWEEN '".$opciones['fechaInicio']."' AND '".$opciones['fechaFin']."'
                AND DATE(ma.fechaTraspaso) IS NOT NULL AND DATE(ma.fechaTraspaso)<>'0000-00-00'
                $opcion2 $membresia AND ma.idUnicoMembresia NOT IN (3,5442)
            ORDER BY 1 desc";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Muestra detalle de acceso de los usuarios
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function mostrarDetalleAccesos($dato, $posicion=0, $registros=25)
    {
        settype($posicion, 'integer');
        settype($registros, 'integer');

        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }
        if ($posicion == '') {
            $posicion = 0;
        }

        $data = Array();
        $sql="SELECT u.nombre, CONCAT_WS(' ',r.fecha,r.hora) AS fecha, r.direccion, r.estatus, r.idEmpleado
            FROM ".TBL_REGISTROACCESO." r
            INNER JOIN ".TBL_UN." u ON u.idUn=r.idUn
            WHERE  r.idPersona=".$dato['idPersona']." ORDER BY fecha DESC LIMIT ".$posicion.", ".$registros;
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Muestra total detalle de acceso de los usuarios
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    public function mostrarTotalDetalleAccesos($dato)
    {
       $sql = "SELECT COUNT(*) AS total
            FROM registroacceso
            WHERE idPersona=".$dato['idPersona'];
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->total;
        } else {
            return 0;
        }
    }

    /**
     * Muestra los mensajes ligados al idunicoMembresia
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function muestraMensajes($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT sm.idSocioMensaje, sm.fecharegistro, concat(p.nombre,' ',p.paterno,' ',p.materno) as nombrec, sm.titulo, sm.fechaAlerta, sm.alertaEmitida
            from sociomensaje sm
            left join socio s on s.idSocio=sm.idSocio and s.eliminado=0
            left join persona p on p.idPersona=s.idPersona and p.fechaEliminacion='0000-00-00 00:00:00'
            where s.idUnicoMembresia=".$idUnicoMembresia." and sm.fechaEliminacion='0000-00-00 00:00:00'";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Muestra la tabla de reporte del CAT
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    function muestraReporteCat($idUn, $tipoTarjeta, $diaCargo)
    {
        settype($idUn, 'integer');
        settype($tipoTarjeta, 'integer');
        settype($diaCargo, 'integer');

        $data = Array();
        $sql="SELECT mem.idmembresia, CONCAT('************',RIGHT(sdt.numeroTarjetaCta,4)) AS numeroTarjetaCta, tt.descripcion, sdt.mesExpiracion, sdt.anioExpiracion, sdt.diaCargo
            FROM membresia mem
            INNER JOIN socio s ON s.idUnicoMembresia=mem.idUnicoMembresia
            INNER JOIN sociodatostarjeta sdt ON sdt.idSocio=s.idSocio
            INNER JOIN tipotarjeta tt ON tt.idTipoTarjeta=sdt.tipoTarjeta
            WHERE mem.idUn=".$idUn." AND sdt.activo=1";
        if ($tipoTarjeta!='0') {
           $sql.=" AND sdt.tipoTarjeta=".$tipoTarjeta;
        }
        if ($diaCargo!='0') {
           $sql.=" AND sdt.diaCargo=".$diaCargo;
        }
        $sql.=" GROUP BY mem.idmembresia ORDER By mem.idmembresia";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Obtiene el numero de adultos por producto
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author Santa García
     *
     * @return array
     */
    public function numAdultosActual($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia == 0) {
            return 0;
        }

        $sql = "SELECT SUM(p.edad>=18) AS adultos
            FROM socio s
            INNER JOIN persona p ON p.idPersona=s.idPersona
            WHERE s.eliminado=0 AND s.idTipoEstatusSocio=81 AND ";
        $sql .= "s.idUnicoMembresia=".$idUnicoMembresia;

        $query = $this->db->query($sql);

        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $numIntegrantes = $fila->adultos;
            }
        }
        if (isset($numIntegrantes)) {
            return $numIntegrantes;
        }
    }

    /**
     * Obtiene el numero de membresias local en club en basea al identificador de membresia
     *
     * @param integer $membresia
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function numero($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia == 0) {
            return 0;
        }

        $this->db->distinct();
        $this->db->select('idMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idMembresia'];
        }

        return 0;
    }

    /**
     * Obtiene lista de membresias que presentan adeudos a 30, 60 y 90 dias
     *
     * @param integer $idEmpresa              Identificador de empresa
     * @param integer $idUn                   Identificador de unidad de negocio
     * @param integer $idTipoEstatusMembresia Identificador de estatus de membresia
     * @param array   $ultimoMtto             Filtro para ultimo mantenimiento
     * @param array   $rangoDias              Filtro para rango de dias de adeudo
     * @param integer $elementos              Total de elementos a regresar
     * @param integer $posicion               Posicion desde donde empiezan los registros a regresar
     * @param integer $orden                  Orden de los registros
     * @param integer $direction              Direccion de los registros
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function obtenAdeudosRangoMeses ($idEmpresa, $idUn, $idTipoEstatusMembresia = 0, $ultimoMtto = array('Todos'), $rangoDias = array('Todos'), $registros = REGISTROS_POR_PAGINA, $posicion = 0, $orden = 'ORDER BY idMembresia', $totales = 0)
    {
        settype($idEmpresa, 'integer');
        settype($idUn, 'integer');
        settype($idTipoEstatusMembresia, 'integer');

        if ($totales) {
            $datos = 0;
        } else {
            $datos = array();
        }
        $limit = '';
        $offset = '';
        $estatusMembresia = '';
        $where = '';
        $where2 = '';
        $where3 = '';
        $where4 = '';
        $where5 = '';

        if ($registros) {
            $limit = "LIMIT ".$registros." ";
        }
        if ($posicion > 0) {
            $offset = "OFFSET ".$posicion." ";
        }
        if ($idTipoEstatusMembresia > 0) {
            $estatusMembresia .= " AND t.idTipoEstatusMembresia = ".$idTipoEstatusMembresia." ";
        }
        if ($ultimoMtto) {
            foreach ($ultimoMtto as $idRow => $mtto) {
                if ($mtto != 'Todos') {
                    $ultimoMtto[$idRow] = "'".$mtto."'";
                } else {
                    unset($ultimoMtto[$idRow]);
                }
            }
            $ultimoMtto = $ultimoMtto ? " AND t.ultimoMtto IN (".implode(',', $ultimoMtto).") " : '';
        }
        if ($rangoDias) {
            if (in_array('sinAdeudos', $rangoDias)) {
                if ($where2 == '') {
                    $where2 .= 'AND (t.sinAdeudos = 1 ' ;
                    if (count($rangoDias) == 5) {#Si seleccionaron todos los filtros es necesario tomar en cuenta este tambien
                        $where2 .= ' OR t.sinAdeudos = 0 ' ;
                    }
                } else {
                    $where2 .= ' OR t.sinAdeudos = 1 ' ;
                }
            } else {
                $where3 .= ' AND t.sinAdeudos = 0 ' ;
            }
            if (in_array('treintaDias', $rangoDias)) {
                if ($where2 == '') {
                    $where2 .= 'AND (t.treintaDias > 0 ' ;
                } else {
                    $where2 .= ' OR t.treintaDias > 0 ' ;
                }
            }
            if (in_array('sesentaDias', $rangoDias)) {
                if ($where2 == '') {
                    $where2 .= 'AND (t.sesentaDias > 0 ' ;
                } else {
                    $where2 .= ' OR t.sesentaDias > 0 ' ;
                }
            }
            if (in_array('noventaDias', $rangoDias)) {
                if ($where2 == '') {
                    $where2 .= 'AND (t.noventaDias > 0 ' ;
                } else {
                    $where2 .= ' OR t.noventaDias > 0 ' ;
                }
            }
            if (in_array('masDeNoventaDias', $rangoDias)) {
                if ($where2 == '') {
                    $where2 .= 'AND (t.masDeNoventaDias > 0 ' ;
                } else {
                    $where2 .= ' OR t.masDeNoventaDias > 0 ' ;
                }
            }
        }
        $where2 .= $where2 ? ')' : $where2;

        $sql =
            "SELECT t.idUnicoMembresia, t.idMembresia, t.idPersona, trc.descripcion as rolcliente,
                t.socio, t.idUn, t.mail, t.telefono, t.tipoMembresia, t.idSocioPagoMtto, t.ultimoMtto,
                t.estatusMembresia, t.sinAdeudos, t.treintaDias, t.sesentaDias,
                t.noventaDias, t.masDeNoventaDias
            FROM ".TBL_TMPADEUDOMEMBRESIAS." t
            INNER JOIN socio s on s.idPersona=t.idPersona and s.eliminado=0
            INNER JOIN tiporolcliente trc on trc.idTipoRolCliente=s.idTipoRolCliente and trc.activo=1
            INNER JOIN un ON un.idUn = t.idUn AND un.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN empresa e ON e.idEmpresa = un.idEmpresa AND e.fechaEliminacion = '0000-00-00 00:00:00'
            WHERE t.fechaRegistro = DATE(NOW()) ";
            if ($idUn) {
                $sql .= " AND t.idUn = ".$idUn." ";
            } else {
                $sql .= " AND e.idEmpresa = ".$idEmpresa." ";
            }
            $sql .= "
            ".$estatusMembresia."
            ".$ultimoMtto."
            ".$where2."
            ".$where3."
            ".$orden;

        if ($totales == 0) {
            $sql .= " ".$limit." ".$offset;
        }

        $query = $this->db->query($sql);

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
     * Muestra los mensajes almanezadossocioMensaje
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function obtenAlertaMensajes($idUnicoMembresia, $posicion=0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($posicion, 'integer');

        $sql="SELECT sm.idSocioMensaje, sm.titulo, sm.mensaje FROM sociomensaje sm
            left join socio s on s.idsocio=sm.idSocio
            where s.idunicoMembresia=".$idUnicoMembresia." and sm.fechaAlerta='".date('Y-m-d')."'
                and sm.fechaEliminacion='0000-00-00 00:00:00' and enviarAcceso=0
                and alertaEmitida=0 limit ".$posicion.",1";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return false;
        }
    }

    /**
     * [obtenCargosPromocionMTTOMSI description]
     * @param  [type] $idUnicoMembresia [description]
     * @return [type]                   [description]
     */
    function obtenCargosPromocionMTTOMSI($idUnicoMembresia)
    {
        $sql1 = "CALL crm.spMTTOMSIObtenCargos(".$idUnicoMembresia.", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * [obtenCargosPromocionMTTOMSICambioMaMSI description]
     * @param  [type] $ids [description]
     * @return [type]      [description]
     */
    public function obtenCargosPromocionMTTOMSICambioMaMSI($ids)
    {
        $sql1 = "UPDATE crm.movimiento mov SET mov.origen=CONCAT(mov.origen, '_MTTOMSI') WHERE mov.idMovimiento IN (".$ids.");";
        $query1 = $this->db->query($sql1);
        return 1;
    }

    /**
     * [obtenCargosPromocionMTTOMSIIds description]
     * @param  [type] $idUnicoMembresia [description]
     * @return [type]                   [description]
     */
    public function obtenCargosPromocionMTTOMSIIds($idUnicoMembresia)
    {
        $sql1 = "CALL crm.spMTTOMSIObtenCargosIds(".$idUnicoMembresia.", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * [obtenCargosPromocionMTTOMSIPaquete description]
     * @param  [type] $idUnicoMembresia [description]
     * @return [type]                   [description]
     */
    public function obtenCargosPromocionMTTOMSIPaquete($idUnicoMembresia)
    {
        $sql1 = "CALL crm.spMTTOMSIObtenPaquete(".$idUnicoMembresia.", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * [obtenCargosPromocionMTTOMSISoloMensual description]
     * @param  [type] $idUnicoMembresia [description]
     * @return [type]                   [description]
     */
    public function obtenCargosPromocionMTTOMSISoloMensual($idUnicoMembresia)
    {
        $sql1 = "CALL crm.spMTTOMSIObtenCargosSoloMensual(".$idUnicoMembresia.", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * Obtiene catalogo completo del tipo de membresia
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenCatalogoTipoMembresia()
    {
        $datos = array();
        $where = array(
            'activo'         => 1,
            'idTipoProducto' => 1
        );

        $query = $this->db->select("idProducto, nombre", false)->get_where(TBL_PRODUCTO, $where);
        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idProducto] = $fila->nombre;
            }
        }
        return $datos;
    }

    /**
     * Regresa convenio de membresia
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function obtenConvenio ($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $datos = array();
        $datos['idConvenio'] = 0;
        $datos['nombre']     = 'NA';
        $datos["idTipoConvenio"]=0;

        if ( ! $idUnicoMembresia) {
            return $datos;
        }
        $where = array(
            'm.idUnicoMembresia' => $idUnicoMembresia,
            'm.eliminado'        => 0,
            'cd.eliminado'       => 0,
            'c.eliminado'        => 0,
            'c.activo'           => 1,
            'cd.activo'          => 1
        );
        $this->db->join(TBL_CONVENIODETALLE." cd", "m.idConvenioDetalle = cd.idConvenioDetalle", "inner");
        $this->db->join(TBL_CONVENIO." c", "cd.idConvenio = c.idConvenio", "inner");
        $query = $this->db->select(
            'c.idConvenio, c.nombre, cd.idTipoConvenio'
        )->get_where(TBL_MEMBRESIA." m", $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Regresa los datos del beneficiario de una membresia
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenDatosBeneficiario ($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $datos = array();

        if (! $idUnicoMembresia) {
            return $datos;
        }
        $where = array(
           'mi.fechaEliminacion' => '0000-00-00 00:00:00',
           'p.fechaEliminacion' => '0000-00-00 00:00:00',
           'mi.idTipoInvolucrado' => TIPO_INVOLUCRADO_BENEFICIARIO,
           'p.fallecido' => 0,
           'tc.activo' => 1,
           'mi.idUnicoMembresia' => $idUnicoMembresia,
        );
        $this->db->join(TBL_PERSONA.' p', 'p.idPersona = mi.idPersona', 'inner');
        $this->db->join(TBL_TIPOCONTACTO.' tc', 'mi.idTipoContacto = tc.idTipoContacto', 'inner');
        $query = $this->db->select(
            "mi.idMembresiaInvolucrado, mi.idPersona, tc.idTipoContacto, tc.descripcion AS tipoContacto,
            CONCAT_WS(' ',p.nombre, p.paterno, p.materno) AS nombre, p.fechaNacimiento,
            p.edad AS edad,
            (
                SELECT If(t.lada, CONCAT('(', t.lada, ')', t.telefono), t.telefono)
                FROM telefono t
                WHERE t.fechaEliminacion = '0000-00-00 00:00:00'
                AND t.idTipoTelefono IN (30,31,32)
                AND t.idPersona = p.idPersona
                LIMIT 1
            )AS telefono",
            false
        )->get_where(TBL_MEMBRESIAINVOLUCRADO." mi", $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Obtiene los datos generales de socioMensaje
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function obtenDatosMensajes($idSocioMensaje)
    {
        settype($idSocioMensaje, 'integer');
        $sql="select * from sociomensaje where idSocioMensaje=".$idSocioMensaje;

        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Regresa las opciones de una membresia
     *
     * @param integer $tipoOpcion   Identificador de tipo de opcion de membresia
     * @param integer $idUnicoMemb  Identificador unico de membresia
     * @param integer $idUn         Identificador de unidad de negocio
     * @param integer $idProducto   Identificador de producto
     * @param integer $idMembConfig Identificador de membresiaconfiguracion
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function obtenDatosOpcion ($tipoOpcion, $idUnicoMemb, $idUn = 0, $idProducto = 0, $idMembConfig = 0)
    {
        settype($tipoOpcion, 'integer');
        settype($idUnicoMemb, 'integer');
        settype($idUn, 'integer');
        settype($idProducto, 'integer');
        settype($idMembConfig, 'integer');

        $datos = array(
            'idMembresiaOpciones'      => 0,
            'activo'                   => 0,
            'idMembresiaConfiguracion' => 0,
            'valor'                    => 0
        );
        if (($tipoOpcion == 0) or ($idUnicoMemb == 0 and $idProducto == 0 and $idMembConfig == 0)) {
            return $datos;
        }
        $this->db->select("mo.idMembresiaOpciones, mo.activo, mo.idMembresiaConfiguracion, mo.valor");
        $this->db->join(TBL_MEMBRESIACONFIGURACION." mc", 'mc.idMembresiaConfiguracion = mo.idMembresiaConfiguracion', 'inner');
        $this->db->join(TBL_PRODUCTOUN." pu", "mc.idProductoUn = pu.idProductoUn");

        if ($idUnicoMemb > 0) {
            $this->db->join(TBL_MEMBRESIA." m", "pu.idProducto = m.idProducto AND pu.idUn = m.idUn");
        }
        if ($idProducto > 0) {
            $this->db->join(TBL_PRODUCTO." p", "pu.idProducto = p.idProducto");
        }
        $this->db->where('pu.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('mo.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('mc.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('mo.activo', 1);
        $this->db->where('mo.idTipoMembresiaOpcion', $tipoOpcion);

        if ($idUnicoMemb > 0) {
            $this->db->where("m.idUnicoMembresia", $idUnicoMemb);
        }
        if ($idUn > 0) {
            $this->db->where('pu.idUn', $idUn);
        }
        if ($idProducto > 0) {
            $this->db->where("pu.idProducto", $idProducto);
        }
        if ($idMembConfig > 0) {
            $this->db->where('mc.idMembresiaConfiguracion', $idMembConfig);
        }
        $this->db->group_by("mo.idMembresiaOpciones, mo.activo, mo.idMembresiaConfiguracion, mo.valor");
        $query = $this->db->get(TBL_MEMBRESIAOPCIONES." mo");
        if ($query->num_rows > 0) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Regresa datos de tipo de membresia
     *
     * @param integer $idProductoUn Identificador de productoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenDatosTipoMembresia ($idProductoUn)
    {
        settype($idProductoUn, 'integer');
        $datos = array();

        if (! $idProductoUn) {
            return $datos;
        }
        $where = array(
            'mc.idProductoUn'     => $idProductoUn,
            'mc.fechaEliminacion' => '0000-00-00 00:00:00',
            'tm.activo'           => 1
        );
        $this->db->join(TBL_TIPOMEMBRESIA." tm", "tm.idTipoMembresia = mc.idTipoMembresia", "inner");
        $query = $this->db->select(
            "mc.idTipoMembresia, tm.descripcion AS tipoMembresia, mc.idMembresiaConfiguracion"
        )->get_where(TBL_MEMBRESIACONFIGURACION." mc", $where);

        if ($query->num_rows == 1) {
            $datos            = $query->row_array();
            $datos['error']   = 0;
        } else {
            $datos['error']   = 1;
            $datos['mensaje'] = 'Error, se encuentra duplicado el registro de idProductoUn en membresiaconfiguracion';
        }
        return $datos;
    }

    /**
     * Obtiene la edad minima y maxima de un mantenimiento en membresia
     *
     * @param integer $idProducto      Identificador de producto
     * @param integer $idUn            Identificador de unidad de negocio
     * @param integer $idMantenimiento Identificador de mantenimiento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function obtenEdadMinimaMaximaMtto ($idProducto, $idUn, $idMantenimiento)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        settype($idMantenimiento, 'integer');

        $datos = array('edadMinima' => 0, 'edadMaxima' => 0);

        if ( ! $idProducto or ! $idUn or ! $idMantenimiento) {
            return $datos;
        }
        $where = array(
            'pu.idUn'                => $idUn,
            'pu.idProducto'          => $idProducto,
            'mts.activo'             => 1,
            'pu.activo'              => 1,
            'mttoc.idMantenimiento'  => $idMantenimiento,
            'pu.fechaEliminacion'    => '0000-00-00 00:00:00',
            'mc.fechaEliminacion'    => '0000-00-00 00:00:00',
            'trc.fechaEliminacion'   => '0000-00-00 00:00:00',
            'mttoc.fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $this->db->join(TBL_PRODUCTOUN.' pu', 'pu.idProductoUn = mc.idProductoUn', 'inner');
        $this->db->join(TBL_MEMBRESIATIPOSOCIO.' mts', 'mts.idMembresiaConfiguracion = mc.idMembresiaConfiguracion', 'inner');
        $this->db->join(TBL_TIPOROLCLIENTE.' trc', "trc.idTipoRolCliente = mts.idTipoRolCliente AND trc.fechaEliminacion = '0000-00-00 00:00:00'", 'inner');
        $this->db->join(TBL_MANTENIMIENTOCLIENTE.' mttoc', "mttoc.idTipoRolCliente = trc.idTipoRolCliente AND mttoc.idUn = pu.idUn AND mttoc.fechaEliminacion = '0000-00-00 00:00:00'", 'inner');

        $query = $this->db->select(
            'mttoc.edadMinima, mttoc.edadMaxima'
        )->order_by('mttoc.idTipoRolCliente')->get_where(TBL_MEMBRESIACONFIGURACION.' mc', $where);

        if ($query->num_rows) {
            foreach ($query->result_array() as $fila) {
                if ( ! $datos['edadMinima']) {
                    $datos['edadMinima'] = $fila['edadMinima'];
                } else {
                    $datos['edadMinima'] = ($fila['edadMinima'] < $datos['edadMinima']) ? $fila['edadMinima'] : $datos['edadMinima'];
                }
                if ( ! $datos['edadMaxima']) {
                    $datos['edadMaxima'] = $fila['edadMaxima'];
                } else {
                    $datos['edadMaxima'] = ($fila['edadMaxima'] > $datos['edadMaxima']) ? $fila['edadMaxima'] : $datos['edadMaxima'];
                }
            }
        }
        return $datos;
    }

    /**
     * Obtiene valores extra de membresia
     *
     * @param integer $idUnicoMembresia      Identificador unico de membresia
     * @param integer $idTipoMembresiaExtras Identificador de campo extra a obtener su valor
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenExtras ($idUnicoMembresia, $idTipoMembresiaExtras)
    {
        settype($idUnicoMembresia, 'integer');

        $datos  = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos',
            'valor'   => '',
        );
        if ( ! $idUnicoMembresia or ! $idTipoMembresiaExtras) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $this->db->join(TBL_MEMBRESIAEXTRAS.' me', "me.idTipoMembresiaExtras = tme.idTipoMembresiaExtras AND me.idUnicoMembresia = ".$idUnicoMembresia." AND me.fechaEliminacion = '0000-00-00 00:00:00'", "LEFT");
        $this->db->where('tme.idTipoMembresiaExtras', $idTipoMembresiaExtras);

        $query = $this->db->select(
            "IFNULL(me.valor, '') AS valor,
            IFNULL(me.idMembresiaExtras, 0)AS idMembresiaExtras,
            If(tme.activo AND DATE(NOW()) BETWEEN DATE(tme.inicioVigencia) AND DATE(tme.finVigencia), 1, 0) AS vigente", false
        )->order_by('me.idMembresiaExtras DESC')->get(TBL_TIPOMEMBRESIAEXTRAS.' tme', 1);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }

        return $datos;
    }

    /**
     * Obtiene configuracion de campo extra
     *
     * @param integer $idTipoMembresiaExtras Identificador de tipomembresiaextras
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenExtrasConfiguracion ($idTipoMembresiaExtras)
    {
        settype($idTipoMembresiaExtras, 'integer');

        $datos = array(
            'mensaje' => 'Error faltan datos',
            'error'   => 1
        );
        if (! $idTipoMembresiaExtras) {
            return $datos;
        }
        $where = array('idTipoMembresiaExtras' => $idTipoMembresiaExtras);
        $query = $this->db->select(
            "descripcion, activo, inicioVigencia, finVigencia", false
        )->get_where(TBL_TIPOMEMBRESIAEXTRAS, $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
            $datos['mensaje'] = '';
            $datos['error']   = 0;
        } else {
            $datos['mensaje'] = 'Error no se encontro configuracion de extras';
            $datos['error']   = 2;
        }
        return $datos;
    }

    /**
     * [obtenFidelidad description]
     * @param  [type] $idUnicoMembresia [description]
     *
     * @author Antonio Sixtos <antonio.sixtos@sportsworld.com.mx>
     *
     * @return [type]                   [description]
     */
    public function obtenFidelidad($idUnicoMembresia)
    {
        $lista = array();
        $rs = $this->db->query("SELECT a.idMembresiaFidelidad, a.idtipoFidelidad, tf.descripcion,
            a.mesesConsecutivos, a.autorizacionEspecial
            FROM
            (
                SELECT MAX(mf.idMembresiaFidelidad) AS idMembresiaFidelidad, mf.idUnicoMembresia, mf.idTipoFidelidad,
                    mf.mesesConsecutivos, mf.autorizacionEspecial
                FROM crm.membresiafidelidad mf
                WHERE mf.idUnicoMembresia IN (".$idUnicoMembresia.") AND mf.fechaEliminacion='0000-00-00 00:00:00'
                GROUP BY mf.idUnicoMembresia
            ) a
            INNER JOIN crm.tipofidelidad tf ON tf.idTipoFidelidad=a.idTipoFidelidad
            GROUP BY a.idUnicoMembresia");
        if ( $rs->num_rows()>0 ) {
            foreach ( $rs->result_array() as $row ) {
               $lista[]=$row;
            }
        } else {
            $lista[0]['idMembresiaFidelidad']  = 0;
            $lista[0]['idtipoFidelidad']       = 1;
            $lista[0]['descripcion']           = '';
            $lista[0]['mesesConsecutivos']     = 0;
            $lista[0]['autorizacionEspecial']  = 0;
        }

        return $lista;
    }

    /**
     * Obtiene el horario por tipo de mantenimiento
     *
     * @param integer $idMantenimiento Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenHorario($idMantenimiento)
    {
        settype($idMantenimiento, 'integer');

        $this->db->select('th.descripcion');
        $this->db->from(TBL_TIPOHORARIO.' th');
        $this->db->join(TBL_MANTENIMIENTO.' m', 'm.idTipoHorario = th.idTipoHorario');
        $where = array('m.idMantenimiento' => $idMantenimiento, 'm.fechaEliminacion'=>'0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $horario = $fila->descripcion;
            }
        }

        if (isset($horario)) {
            return $horario;
        }
    }

    /**
     * [obtenIdConvenioDetalle description]
     * @param  [type] $idUnicoMembresia [description]
     * @return [type]                   [description]
     */
    public function obtenIdConvenioDetalle($idUnicoMembresia)
    {
        $resultado = 0;
        $rs = $this->db->query("SELECT mem.idConvenioDetalle
            FROM crm.membresia mem
            WHERE mem.idUnicoMembresia IN (".$idUnicoMembresia.")");
        if ( $rs->num_rows()>0 ) {
           $row = $rs->row();
           $resultado = $row->idConvenioDetalle;
        }

        return $resultado;
    }

    /**
     * [obtenIdMantenimientoCongelado description]
     *
     * @param  [type] $idUn             [description]
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function obtenIdMantenimientoCongelado($idUn, $idUnicoMembresia)
    {
        settype($idUn, 'integer');
        settype($idUnicoMembresia, 'integer');
        $resultado = 0;

        $idMantenimiento = 0;
        $rs2 = $this->db->query("SELECT soc.idMantenimiento
            FROM crm.socio soc
            WHERE soc.idUnicoMembresia IN (".$idUnicoMembresia.") AND soc.idTipoRolCliente IN (1)
                AND soc.eliminado = 0");
        if ( $rs2->num_rows()>0 ) {
            $row = $rs2->row();
            $idMantenimiento = $row->idMantenimiento;
        }
        $rs3 = $this->db->query("SELECT mc.idMantenimientoCongelado
            FROM crm.mantenimientocongelado mc
            WHERE mc.anio IN (YEAR(NOW()),2014) AND mc.idUn IN (".$idUn.") AND mc.idMantenimiento IN (".$idMantenimiento.")
                AND mc.fechaEliminacion = '0000-00-00 00:00:00';");
        if ( $rs3->num_rows()>0 ) {
            $row = $rs3->row();
            $resultado = $row->idMantenimientoCongelado;
        }
        return $resultado;
    }

    /**
     * [obtenCargosPromocionMTTOMSIMensuales description]
     * @param  [type] $idUnicoMembresia [description]
     * @return [type]                   [description]
     */
    public function obtenCargosPromocionMTTOMSIMensuales($idUnicoMembresia)
    {
        $sql1 = "CALL crm.spMTTOMSIObtenCargosMensuales(".$idUnicoMembresia.", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * [obtenIdMantenimientoNoFidelidad description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function obtenIdMantenimientoNoFidelidad($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $resultado = 0;
        $rs = $this->db->query("SELECT mem.idProducto
            FROM crm.membresia mem
            WHERE mem.idUnicoMembresia IN (".$idUnicoMembresia.")");
        $idProducto = 0;
        if ( $rs->num_rows()>0 ) {
            $row = $rs->row();
            $idProducto = $row->idProducto;
        }
        $idMantenimiento = 0;

        $rs2 = $this->db->query("SELECT soc.idMantenimiento
            FROM crm.socio soc
            WHERE soc.idUnicoMembresia IN (".$idUnicoMembresia.") AND soc.idTipoRolCliente IN (1)
                AND soc.eliminado=0");
        if ( $rs2->num_rows()>0 ) {
            $row = $rs2->row();
            $idMantenimiento = $row->idMantenimiento;
        }

        $rs3 = $this->db->query("SELECT mnf.idMantenimientoNoFidelidad
            FROM mantenimientonofidelidad mnf
            WHERE mnf.idProducto IN (".$idProducto.") AND mnf.idMantenimiento IN (".$idMantenimiento.")
                AND mnf.fechaEliminacion='0000-00-00 00:00:00';");
        if ( $rs3->num_rows()>0 ) {
            $row = $rs3->row();
            $resultado = $row->idMantenimientoNoFidelidad;
        }
        return $resultado;
    }

    /**
     * Obtiene el idMembresiaConfiguracion de una membresia
     *
     * @param integer $idProductoUn     Identificador productoun
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function obtenIdMembresiaConfiguracion($idProductoUn = 0, $idUnicoMembresia = 0)
    {
        settype($idProductoUn, 'integer');
        settype($idUnicoMembresia, 'integer');
        $idMembConfig = 0;

        if ($idProductoUn > 0) {
            $this->db->select('idMembresiaConfiguracion');
            $this->db->where('idproductoun', $idProductoUn);
            $query = $this->db->get(TBL_MEMBRESIACONFIGURACION);
        } elseif ($idUnicoMembresia > 0) {
            $this->db->select("mc.idMembresiaConfiguracion");
            $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
            $this->db->join(TBL_PRODUCTOUN." pu", 'm.idProducto = pu.idProducto AND m.idUn = pu.idUn','inner');
            $this->db->join(TBL_MEMBRESIACONFIGURACION." mc", 'pu.idProductoUn = mc.idProductoUn','inner');
            $query = $this->db->get(TBL_MEMBRESIA." m");
        } else {
            return $idMembConfig;
        }
        if ($query->num_rows > 0) {
            $idMembConfig = $query->row()->idMembresiaConfiguracion;
        }
        return $idMembConfig;
    }


    /**
     * Obtiene el IdProductoUn de una membersia
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     * @param integer $idUn             Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function obtenIdProductoUn ($idUnicoMembresia, $idUn)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idUn, 'integer');
        $IdProductoUn = 0;

        if (($idUnicoMembresia == 0) or ($idUn == 0)) {
            return $IdProductoUn;
        }
        $this->db->select("pu.idProductoUn");
        $this->db->join(TBL_PRODUCTOUN." pu", "m.idProducto = pu.idProducto AND m.idUn = pu.idUn", "inner");
        $this->db->where("m.idUn", $idUn);
        $this->db->where("m.idUnicoMembresia", $idUnicoMembresia);
        $query = $this->db->get(TBL_MEMBRESIA." m");

        if ($query->num_rows > 0) {
            $IdProductoUn = $query->row()->idProductoUn;
        }
        return $IdProductoUn;
    }

    /**
     * [obtenListaCambioMtto description]
     *
     * @param  [type] $idUn             [description]
     * @param  [type] $idUnicoMembresia [description]
     * @param  [type] $bandera          [description]
     *
     * @return [type]                   [description]
     */
    public function obtenListaCambioMtto($idUn, $idUnicoMembresia, $bandera)
    {
        $data = Array();

        $sql = "CALL crm.spObtenListaCambioMtto(".$idUn.",".$idUnicoMembresia.",".$bandera.", 0, @respuesta)";
        $query = $this->db->query($sql);
        $sql2 = "SELECT @respuesta AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        $resultado= $row->resp;
        $pipes=explode("|", $resultado);
        foreach ($pipes as $sep1) {
            $comas=explode(",", $sep1);
            $data[$comas[0]] = $comas[1];
        }

        return $data;
    }

    /**
     * [obtenListaFidelidad description]
     * @param  integer $descMeses [description]
     * @return [type]             [description]
     */
    public function obtenListaFidelidad($descMeses=0)
    {
        $data = array();

        settype($descMeses, 'integer');

        if ($descMeses==0) {
            $sql="SELECT tf.idTipoFidelidad, tf.descripcion
                FROM crm.tipofidelidad tf WHERE tf.fechaEliminacion ='0000-00-00 00:00:00'";
        } else {
            $sql="SELECT tf.idTipoFidelidad, CONCAT(tf.descripcion,' (',tf.minMeses,'-',tf.maxMeses,' meses)') AS descripcion
                FROM crm.tipofidelidad tf WHERE tf.fechaEliminacion ='0000-00-00 00:00:00' AND tf.activo IN (1)";
        }

        $query=$this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idTipoFidelidad] = $fila->descripcion;
            }
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Obtiene numero de agregados de una membresia
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenNumAgregados($tipoOpcion, $unico, $idUn)
    {
        settype($tipoOpcion, "integer");
        settype($unico, "integer");
        settype($idUn, "integer");

        $query =$this->db->query("SELECT mo.valor
            FROM membresia m
            JOIN productoun pu ON pu.idProducto=m.idProducto AND pu.idUn=m.idUn
            JOIN membresiaconfiguracion mc ON mc.idProductoUn=pu.idProductoUn
            JOIN membresiaOpciones mo ON mo.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
            WHERE m.idUnicoMembresia=".$unico." AND mc.fechaEliminacion='0000-00-00 00:00:00'
                AND mo.idTipoMembresiaOpcion=".$tipoOpcion." AND m.idUn=".$idUn);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->valor;
        } else {
            return 0;
        }
    }

    /**
     * Obtiene numero de integrantes de una membresia
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenNumIntegrantes($tipoOpcion, $unico, $idUn)
    {
        settype($tipoOpcion, 'integer');
        settype($unico, 'integer');
        settype($idUn, 'integer');

        $query =$this->db->query("SELECT mo.valor
            from membresia m
            join productoun pu on pu.idProducto=m.idProducto and pu.idUn=m.idUn
            join membresiaconfiguracion mc on mc.idProductoUn= pu.idProductoUn
            join membresiaOpciones mo on mo.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
            where m.idUnicoMembresia=".$unico." and mc.fechaEliminacion='0000-00-00 00:00:00'
               and mo.idTipoMembresiaOpcion=".$tipoOpcion." and m.idUn=".$idUn);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->valor;
        } else {
            return 0;
        }
    }

    /**
     * Obtiene opciones de la membresia
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenOpciones ()
    {
        $datos = array();
        $where = array('activo' => 1);
        $this->db->order_by('descripcion');
        $datos = $this->db->select(
            'idTipoMembresiaOpcion AS idTipoMemOp, descripcion'
        )->get_where(TBL_TIPOMEMBRESIAOPCION, $where)->result_array();

        return $datos;
    }

    /**
     * [obtenPorcentajeAnualidad description]
     *
     * @param  [type] $idUn [description]
     *
     * @return [type]       [description]
     */
    public function obtenPorcentajeAnualidad($idUn)
    {
        $data = Array();
        $sql = "SELECT idTipoAnualidad, GROUP_CONCAT(CONCAT_WS(',' ,mes, porcentajeDescuento, idEsquemaPago) ORDER BY mes SEPARATOR ';') AS cadena
            FROM finanzasconfigmttoanual f
            WHERE anio=YEAR(NOW()) AND idUn=".$idUn."
            GROUP BY idTipoAnualidad ORDER BY idTipoAnualidad, anio, mes;";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Regrear el Id de promocion que se aplico duarante la compra de la membresia
     *
     * @param  integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jorge Cruz <jorge.cruz@sportsworld.com.mx>
     *
     * @return integer
     */
    public function obtenPromocionVenta($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia==0) {
            return 0;
        }

        $this->db->select('idPaquete');
        $this->db->from(TBL_MEMBRESIAVENTA);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['idPaquete'];
        }

        return 0;
    }

    /**
     * Obtiene reporte de membresias sin titular
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenReporteSinTitular($club, $totales=0, $posicion=0, $registros=25, $orden = '')
    {
        settype($club, 'integer');
        settype($totales, 'integer');
        settype($posicion, 'integer');
        settype($registros, 'integer');

        if ($registros == 0) { $registros = REGISTROS_POR_PAGINA; }

        $sql = "SELECT m.idMembresia, p.nombre, CONCAT(per.nombre,' ',per.paterno,' ',per.materno) AS nombrePropietario,
                mi.idPersona, a.idunicoMembresia, a.total
            FROM membresia m
            LEFT JOIN (
                SELECT COUNT(*) AS total, s.idUnicoMembresia
                FROM socio s
                INNER JOIN membresia m ON m.idunicoMembresia=s.idUnicoMembresia
                    AND m.eliminado=0
                WHERE s.eliminado=0 AND s.idTipoRolCliente=1 AND m.idUn=".$club."
                GROUP BY idUnicoMembresia
            ) AS a ON a.idUnicoMembresia=m.idUnicoMembresia
            LEFT JOIN producto p ON p.idProducto=m.idProducto AND p.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN productoun pu ON pu.idProducto=p.idProducto AND pu.idUn=m.idUn
                AND pu.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiaInvolucrado mi ON mi.idUnicoMembresia=m.idUnicoMembresia
            LEFT JOIN persona per ON per.idPersona=mi.idPersona
            WHERE m.idUn=".$club." AND m.eliminado=0
                AND mi.idTipoInvolucrado=1 AND mi.fechaEliminacion='0000-00-00 00:00:00'
            HAVING a.total IS NULL OR a.total>1";
        if ($orden == ''){
          $sql.=" ORDER BY m.idMembresia";
        } else {
            $sql.=" ORDER BY $orden";
        }
        if ($totales == 0) {
            if ($posicion == '') {
                $posicion = 0;
            }
            $sql.=" LIMIT $posicion,$registros ";
        }
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            if ($totales == 1) { return $query->num_rows(); }
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /*
     * revisa si tiene alguna reacticacion pagada en el mes actual
     *
     * @author  Antonio Sixtos
     *
     * @return int
     */
    public function obtenSiTieneReactivacionPagada($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $res=0;
        $sql="SELECT mr.idMembresiaReactivacion
            FROM membresiareactivacion mr
            INNER JOIN membresiareactivacionmovimiento mrm ON mrm.idMembresiaReactivacion=mr.idMembresiaReactivacion
            INNER JOIN movimiento mov ON mov.idMovimiento=mrm.idMovimiento
            INNER JOIN facturaMovimiento facmov ON facmov.idMovimiento=mov.idMovimiento
            INNER JOIN factura fac ON fac.idFactura=facmov.idFactura
                AND EXTRACT(YEAR_MONTH FROM fac.fecha)='".date("Ym")."'
            WHERE mr.idUnicoMembresia=".$idUnicoMembresia." AND mov.idTipoEstatusMovimiento=66";
       $query = $this->db->query($sql);

       if ($query->num_rows() > 0) {
           $res=1;

       }
       return $res;
    }

    /**
     * [obtenSumaAdeudos description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function obtenSumaAdeudos($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $resultado = 0;
        $rs = $this->db->query("SELECT  IFNULL(CONCAT('1|||',SUM(mov.importe)),'0|||0.00') AS montoAdeudo
            FROM crm.movimiento mov
            INNER JOIN crm.movimientoctacontable mcc ON mcc.idMovimiento=mov.idMovimiento AND mcc.numeroCuenta IN (4201,4202,4203)
            WHERE mov.idUnicoMembresia IN (".$idUnicoMembresia.") AND mov.idTipoEstatusMovimiento IN (65) AND mov.eliminado=0;");
        if ( $rs->num_rows()>0 ) {
            $row = $rs->row();
            $resultado = $row->montoAdeudo;
        }

        return $resultado;
    }

    /**
    * Obtiene tipo de pago de anualidad
    *
    * @author Antonio Sixtos
    *
    * @return array
    */
    public function obtenTipoAnualidad()
    {
        $data = Array();
        $sql="SELECT * from tipoanualidad";

        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Regresa el ultimo numero de membresia de un club
     *
     * @param integer $idUn Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenUltimaMembresia ($idUn)
    {
        settype($idUn, 'integer');

        $datos = array(
            'mensaje'     => 'Error, faltan datos',
            'error'       => 1,
            'idMembresia' => 0
        );
        if ( ! $idUn) {
            return $datos;
        }
        $datos['mensaje'] = 'Error al obtener ultima membresia';
        $datos['error']   = 2;
        $where = array(
            'idUn'      => $idUn,
            'eliminado' => 0
        );
        $query = $this->db->select_max('idMembresia', 'idMembresia')->get_where(TBL_MEMBRESIA, $where);

        if ($query->num_rows) {
            $datos['idMembresia'] = $query->row()->idMembresia;
            $datos['mensaje']     = '';
            $datos['error']       = 0;
        }
        return $datos;
    }

    /**
     * Regresa el identificador de persona registra como beneficiario de la membresia
     *
     * @param integer $membresia Identificador de membresia
     *
     *
     * @return integer
     */
    public function obtenerBeneficiario($membresia)
    {
        settype($membresia, 'integer');

        if ($membresia == 0) {
            return 0;
        }

        $this->db->distinct();
        $this->db->select('idPersona');
        $this->db->from(TBL_MEMBRESIAINVOLUCRADO);
        $where = array(
            'idUnicoMembresia' => $membresia,
            'idTipoInvolucrado' => MEM_BENFICIARIO,
            'fechaEliminacion'=> '0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idPersona'];
        }

        return 0;
    }

    /**
     * Obtiene el comprobante que se ha entregado por persona
     *
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerComprobanteEntregado($idTipoComprobante, $idPersona)
    {
        settype($idTipoComprobante, 'integer');
        settype($idPersona, 'integer');

        $this->db->select('pd.idPersonaDocumento, pd.idTipoDocumento');
        $this->db->from(TBL_PERSONADOCUMENTO.' pd');
        $this->db->join(TBL_TIPODOCUMENTO.' td', 'td.idTipoDocumento=pd.idTipoDocumento');
        $this->db->join(TBL_COMPROBANTEDOCUMENTO.' cd', 'cd.idTipoDocumento=td.idTipoDocumento');
        $this->db->where('cd.idTipoComprobante', $idTipoComprobante);
        $this->db->where('pd.idPersona', $idPersona);
        $this->db->where('pd.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('td.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ( $query->num_rows > 0 ) {
             foreach ($query->result() as $fila) {
                return $fila->idTipoDocumento;
            }
        } else {
            return 0;
        }
    }

    /**
     * Datos de Descuento Mtto
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function obtenerDatosDescuentoMtto($idDescuento)
    {
        settype($idDescuento, 'integer');

        $this->db->select('idMembresiaDescuentoMtto, descripcion, activo,
            porcentajeDescuento, inicioVigencia, finVigencia, idMantenimiento,
            idEsquemaPago, requierePermiso, habilitarPromocion');
        $this->db->from(TBL_MEMBRESIADESCUENTOMTTO);
        $this->db->where('idMembresiaDescuentoMtto', $idDescuento);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene los datos generales de la membresia por idUnicoMembresia
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author
     *
     * @return array
     */
    public function obtenerDatosGeneralesMem($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $dato = array();

        if ($idUnicoMembresia == 0) {
            return $dato;
        }

        $sql = "SELECT m.idUnicoMembresia, m.idMembresia, m.idUn, m.idPersona, m.idProducto, m.importe, m.descuento,
                m.idConvenioDetalle, m.idTipoEstatusMembresia, m.idPeriodoMsi, m.idUnAlterno, m.claveMembresia, m.intransferible,
                mc.idTipoMembresia, m.ventaEnLinea, m.fechaInicioMtto, m.limiteInicioMtto, DATE(m.fecharegistro) AS fechaRegistro,
                IFNULL(mf.idTipoFidelidad, 0) AS idTipoFidelidad, IFNULL(mdm.idMembresiaDescuentoMtto, 0) AS idMembresiaDescuentoMtto,
                IFNULL(EXTRACT(YEAR_MONTH FROM mdm.finVigencia), '0') AS vigenciaDescuentoMtto
            FROM membresia m
            INNER JOIN productoun pu ON pu.idProducto=m.idProducto AND pu.idUn=m.idUn
                AND pu.eliminado=0 AND pu.activo=1
            INNER JOIN membresiaconfiguracion mc ON mc.idProductoUn=pu.idProductoUn
            LEFT JOIN membresiafidelidad mf ON mf.idUnicoMembresia=m.idUnicoMembresia
                AND mf.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN membresiadescuentomtto mdm ON mdm.idMembresiaDescuentoMtto=m.idMembresiaDescuentoMtto
            WHERE m.idUnicoMembresia=$idUnicoMembresia
            GROUP BY m.idUnicoMembresia";
        $query = $this->db->query($sql);

        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $dato[] = $fila;
            }
        }
        return $dato;
    }

    /**
     * Obtiene datos generales de socio pago mtto
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerDatosSocioPagoMtto($idSocioPagoMtto)
    {
        settype($idSocioPagoMtto, 'integer');

        $this->db->select('idSocioPagoMtto, idUnicoMembresia, idMovimiento,idMantenimiento');
        $this->db->from(TBL_SOCIOPAGOMTTO);
        $this->db->where('idSocioPagoMtto', $idSocioPagoMtto);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Pago mtto del proximo año
     *
     * @author Santa Garcia
     *
     * @return void
     */
    function obtenerDatosSocioPagoMttoProximo($idPersona, $idUnicoMembresia, $fecha)
    {
        $sql = "SELECT s.idMantenimiento
            from sociopagomtto s
            where s.idPersona =$idPersona  and $fecha  between s.fechaInicio
                and s.fechaFin and s.activo = 1
                and s.eliminado = 0 and s.idUnicoMembresia=$idUnicoMembresia";
        $query = $this->db->query($sql);

        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $idMantenimiento = $fila->idMantenimiento;
            }
            return $idMantenimiento;
        } else {
            return 0;
        }
    }

    /**
     * Obtiene el tipo de descuento con el que cuenta la membresia
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    function obtenerDescuentoMtto($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $this->db->select('m.idMembresia,m.idUn,m.idMembresiaDescuentoMtto,mdm.descripcion,mdm.porcentajeDescuento,mdm.inicioVigencia,mdm.finVigencia');
        $this->db->from(TBL_MEMBRESIA.' m');
        $this->db->join(TBL_MEMBRESIADESCUENTOMTTO.' mdm', 'mdm.idMembresiaDescuentoMtto=m.idMembresiaDescuentoMtto');
        $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('mdm.activo', 1);
        $this->db->where('mdm.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

     /**
     * Obtiene las fechas tanto de Registro, como de inicio de vigencia y fin de vigencia
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerFechasVigencia($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        if ($idUnicoMembresia == 0) {
            return null;
        }

        $this->db->select('inicioVigencia,finVigencia,fechaRegistro');
        $this->db->from(TBL_MEMBRESIA);
        $where = array('idUnicoMembresia' => $idUnicoMembresia);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila      = $query->row_array();
        }
        if (isset($fila)) {
            return $fila;
        } else {
            return null;
        }
    }

    /**
     * Función que regresa el folio asociado a la membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param integer $idUn             Identificador de la unidad de negocios
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerFolio($idUnicoMembresia,$idUn)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idUn, 'integer');

        $sql='select max(folio) as folio from membresiacesion WHERE';
        if ($idUnicoMembresia!=0) {
            $sql .= ' idUnicoMembresia='.$idUnicoMembresia;
        }
        if ($idUn!=0) {
            $sql .= ' AND idUn='.$idUn;
        }
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $folio = $fila->folio;
            }
            return $folio;
        } else {
            return true;
        }
    }

    /**
     * Obtiene la forma de pago por membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresía
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function obtenerFormaPago($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia == 0) {
            return 0;
        }

        $formaPago = 0;

        $this->db->select('s.idEsquemaPago');
        $this->db->from(TBL_MEMBRESIA.' m');
        $this->db->join(TBL_SOCIO.' s', 's.idUnicoMembresia=m.idUnicoMembresia');
        $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('m.eliminado', 0);
        $this->db->where('s.idTipoRolCliente', '1');
        $this->db->where('s.eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $formaPago = $fila['idEsquemaPago'];
        }
        return $formaPago;
    }

    /**
     * Obtiene el identificador del mantenimiento de acuerdo al identificador de membresía
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param integer $idUn             Identificador de unidad de negocio
     * @param integer $default          Bandera para traer mantenimiento por default
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerIdMantenimiento($idUnicoMembresia, $idUn, $default = 1)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idUn, 'integer');

        if (!$idUnicoMembresia or ! $idUn) {
            return MTTO_DEFAULT;
        }
        $this->db->select('mcm.idMantenimiento');
        $this->db->from(TBL_MEMBRESIA.' m');
        $this->db->join(TBL_PRODUCTOUN.' pu', 'pu.idProducto = m.idProducto');
        $this->db->join(TBL_MEMBRESIACONFIGURACION.' mc', 'mc.idProductoUn = pu.idProductoUn');
        $this->db->join(TBL_MEMBRESIACONFIGMTTO.' mcm', 'mcm.idMembresiaConfiguracion = mc.idMembresiaConfiguracion');
        $this->db->where('m.eliminado', 0);
        $this->db->where('pu.eliminado', 0);
        $this->db->where('mc.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('pu.idUn', $idUn);
        $this->db->where('pu.activo', 1);
        $this->db->where('mcm.activo', 1);
        $this->db->where('mcm.default', $default);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idMantenimiento'];
        } else {
            return MTTO_DEFAULT;
        }
    }

    /**
     * Obtener el importe dl mtto del mes en curso
     *
     * @author Santa Garcia
     *
     * @return void
     */
    function obtenerImporteMttoMes($idPersona)
    {
        settype($idPersona, 'integer');

        $sql = "SELECT m.importe
            from sociopagomtto sp
            inner join movimiento m on m.idMovimiento = sp.idMovimiento and m.idTipoEstatusMovimiento=66
            where sp.idPersona=$idPersona and sp.eliminado=0
                and date(now()) between sp.fechaInicio and sp.fechaFin";

        $query = $this->db->query($sql);

        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                return $fila->importe;
            }
        } else {
            return 0;
        }
    }

    /**
     * Obtiene al involucrado en la membresia con idUnicoMebresia
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerInvolucrado ($idUnicoMembresia)
    {
        settype($idMembresia, 'integer');
        if ($idUnicoMembresia == 0) {
            return null;
        }

        $this->db->select('mi.idPersona');
        $this->db->from(TBL_MEMBRESIAINVOLUCRADO.' mi');
        $this->db->join(TBL_TIPOINVOLUCRADO.' ti', 'ti.idTipoInvolucrado= mi.idTipoInvolucrado');
        $where = array('mi.idUnicoMembresia' => $idUnicoMembresia,'ti.descripcion'=>'Beneficiario','mi.fechaEliminacion'=>'0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila      = $query->row_array();
            $idInvolucrado = $fila['idPersona'];
        }
        if (isset($idInvolucrado)) {
            return $idInvolucrado;
        } else {
            return null;
        }
    }

    /**
     * Obtiene la lista de documento spor tipo comprobante
     *
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerListaDocumentosXTipoDocumento($idTipoComprobante)
    {
        settype($idTipoComprobante, 'integer');

        $this->db->select('td.concepto, td.idTipoDocumento');
        $this->db->from(TBL_TIPODOCUMENTO.' td');
        $this->db->join(TBL_COMPROBANTEDOCUMENTO.' cd', 'cd.idTipoDocumento=td.idTipoDocumento');
        $this->db->where('td.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('cd.idTipoComprobante', $idTipoComprobante);
        $this->db->where('td.activo', 1);
        $query = $this->db->get();
        $lista=array();
        $lista[0] = 'Seleccione una opcion';
        if ( $query->num_rows > 0 ) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idTipoDocumento] = $fila->concepto;
            }
            return $lista;
        } else {
            return 0;
        }
    }

    /**
     * Obtiene el numero de integrantes por mantenimiento y unidad de negocio
     *
     * @param integer $idMantenimiento Identificador del mantenimiento
     * @param integer $idProducto      Identificador del producto
     * @param integer $idUn            Identificador de la unidad de negocio
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerMantenimientoUnHorario($idMantenimiento, $idProducto, $idUn)
    {
        settype($idMantenimiento, 'integer');
        settype($idProducto, 'integer');
        settype($idUn, 'integer');

        $this->db->select('trc.descripcion,trc.idTipoRolCliente');
        $this->db->from(TBL_MEMBRESIACONFIGURACION.' mc');
        $this->db->join(TBL_PRODUCTOUN .' pu', 'pu.idProductoUn = mc.idProductoUn');
        $this->db->join(TBL_MEMBRESIACONFIGMTTO.' mcm', 'mcm.idMembresiaConfiguracion = mc.idMembresiaConfiguracion');
        $this->db->join(TBL_MEMBRESIATIPOSOCIO .' mts', 'mts.idMembresiaConfiguracion = mc.idMembresiaConfiguracion');
        $this->db->join(TBL_TIPOROLCLIENTE.' trc', 'trc.idTipoRolCliente = mts.idTipoRolCliente');
        $where = array('mcm.idMantenimiento' => $idMantenimiento, 'pu.idProducto'=>$idProducto,'idUn'=>$idUn);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $integrantes[] = $fila;
            }
        }
        if (isset($integrantes)) {
            return $integrantes;
        }
    }

    /**
     *
     * Obtiene membresias adquiridas por venta en linea
     *
     * @param integer $idUn      Identificador de unidad de negocio
     * @param integer $totales   Bandera de totales
     * @param integer $registros Variable de limite de registros
     * @param integer $posicion  Variable de posicion
     * @param string  $order     Orden de la consulta
     * @param string  $direccion Direccion de la consulta
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function obtenMembresiasVentaEnLinea ($idUn, $totales = 0, $registros = REGISTROS_POR_PAGINA, $posicion = 0, $orden = 'mem.fechaRegistro', $direccion = 'DESC')
    {
        settype($idUn, 'integer');
        settype($totales, 'integer');
        settype($registros, 'integer');
        settype($posicion, 'integer');
        settype($orden, 'string');
        settype($direccion, 'string');

        if ($totales) {
            $registros = null;
            $posicion  = null;
            $datos     = 0;
        } else {
            $datos = array();
        }
        $where = array(
            'mem.ventaEnLinea' => 1,
            'mem.eliminado'    => 0
        );
        if ($idUn) {
            $where['mem.idUn'] = $idUn;
        }
        $this->db->join(TBL_UN, "un.idUn = mem.idUn AND un.fechaEliminacion = '0000-00-00 00:00:00'", "INNER");
        $this->db->join(TBL_MOVIMIENTO." mov", "mov.idUnicoMembresia = mem.idUnicoMembresia AND mov.eliminado = 0 AND mov.idTipoEstatusMovimiento = ".MOVIMIENTO_PENDIENTE, "INNER");
        $this->db->join(TBL_MOVIMIENTOCTACONTABLE." mcc", "mcc.idMovimiento = mov.idMovimiento AND mcc.idTipoMovimiento = ".TIPO_MOVIMIENTO_MEMBRESIA." AND mcc.eliminado = 0", "INNER");
        $this->db->join(TBL_MEMBRESIAINVOLUCRADO." mi", "mi.idUnicoMembresia = mem.idUnicoMembresia AND mi.fechaEliminacion = '0000-00-00 00:00:00' AND mi.idTipoInvolucrado = 1", "INNER");
        $this->db->join(TBL_PERSONA." p", "p.idPersona = mi.idPersona", "INNER");

        $query = $this->db->select(
            "mem.idUnicoMembresia, p.idPersona, mem.idMembresia, un.nombre AS club,CONCAT_WS(' ', p.nombre, p.paterno, p.materno)AS nombre, mem.fechaRegistro", false
        )->order_by($orden, $direccion)->group_by('mem.idUnicoMembresia')->get_where(TBL_MEMBRESIA.' mem', $where, $registros, $posicion);

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
     * Obteniene el numero de ausencias
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function obtenerNumeroAusencias($idPersona)
    {
        $this->db->select('numeroAusencias');
        $this->db->from(TBL_SOCIO);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['numeroAusencias'];
        } else {
            return -1;
        }
    }

    /**
     * Funcion que obtiene los pagos mtto relacionados a una mebresía
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerPagosMtto($opciones, $totales=0, $posicion=0, $registros=25, $orden = '', $todo = false)
    {
        if (isset ($opciones['membresia'])) {
            settype($opciones['membresia'], 'integer');
        } else {
            return 0;
        }

        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }

        if ($orden == '') {
            $orden = 'fechaInicio';
        }

        $this->db->select('p.nombre, p.paterno, p.materno, s.idSocioPagoMtto, s.fechaInicio, s.fechaFin, '.
            's.idMovimiento, s.idPersona, s.idEsquemaPago, s.idMantenimiento, s.activo, s.porcentaje, '.
            'e.descripcion as esquemaPago, pr.nombre as mantenimiento, s.idSocio, s.ausencia');
        $this->db->from(TBL_SOCIOPAGOMTTO.' s');
        $this->db->join(TBL_PERSONA .' p', 'p.idPersona = s.idPersona');
        $this->db->join(TBL_ESQUEMAPAGO .' e', 'e.idEsquemaPago= s.idEsquemaPago');
        $this->db->join(TBL_PRODUCTOMANTENIMIENTO .' pm', 'pm.idMantenimiento = s.idMantenimiento');
        $this->db->join(TBL_PRODUCTO .' pr', 'pr.idProducto = pm.idProducto and pr.activo = 1');
        $this->db->where('s.eliminado', 0);
        $this->db->where('s.idUnicoMembresia', $opciones['membresia']);

        if(isset ($opciones['fecha'])){
            $this->db->where('s.fechaInicio <=', $opciones['fecha']);
            $this->db->where('s.fechaFin >=', $opciones['fecha']);
        }
        if(isset ($opciones['idPersona'])){
            $this->db->where('s.idPersona', $opciones['idPersona']);
        }

        if ($totales == 0) {
            if ( ! $todo) {
                $this->db->limit($registros, $posicion);
            }
        }
        $this->db->order_by("$orden",'desc');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            if ($totales == 1) {
                return $query->num_rows;
            }
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /* Obtiene el precio de traspaso entre clubes
     *
     * @author Santa Garcia
     *
     * @return float
     */
    public function obtenerPrecioTraspaso($idUnicoMembresia, $idUn)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idUn, 'integer');

        $sql="select fncObtenPrecioTraspaso($idUnicoMembresia,$idUn) as precio;";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->precio;
        } else {
            return 0;
        }
    }

    /**
     * Regresa el identificador de persona registra como propietario de la membresia
     *
     * @param integer $membresia Identificador de membresia
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function obtenerPropietario($membresia)
    {
        settype($membresia, 'integer');

        if ($membresia == 0) {
            return 0;
        }

        $this->db->distinct();
        $this->db->select('idPersona');
        $this->db->from(TBL_MEMBRESIAINVOLUCRADO);
        $where = array(
            'idUnicoMembresia' => $membresia,
            'idTipoInvolucrado' => INVOLUCRADO_PROPIETARIO,
            'fechaEliminacion'=> '0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idPersona'];
        }

        return 0;
    }

    /**
     * Obtener reactivacion por periodo
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function obtenerReactivacionXPeriodo($unico, $fechaInicio, $fechaFin)
    {
        settype($unico, 'integer');

        $this->db->select('idMembresiaReactivacion');
        $this->db->from(TBL_MEMBRESIAREACTIVACION);
        $this->db->where('idUnicoMembresia', $unico);
        $this->db->where('fechaRegistro >=', $fechaInicio);
        $this->db->where('fechaRegistro <=', $fechaFin);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Lista las reactivaciones realizadas durante el año en curso
     *
     * @author Santa Garcia
     *
     * @return string
     */
    function obtenerReactivacionesPorPeriodo($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $mes = date('m');
        $condicion = '';
        if ($mes <= 6) {
            $contador = '0';
            $dia = strftime("%d", mktime(0, 0, 0, 6+1, 0, date('Y')));
            $condicion1 = date('Y').'-01-01';
            $condicion2 = date('Y').'-06-'.$dia;
        } else {
            $contador = '1';
            $dia = strftime("%d", mktime(0, 0, 0, 12+1, 0, date('Y')));
            $condicion1 = date('Y').'-06-01';
            $condicion2 = date('Y').'-12-'.$dia;
        }
        $this->db->select('idMembresiaReactivacion');
        $this->db->from(TBL_MEMBRESIAREACTIVACION);
        $where = array('idUnicoMembresia' => $idUnicoMembresia, 'fechaEliminacion'=>'0000-00-00 00:00:00' , 'fechaRegistro >='=> $condicion1, 'fechaRegistro <='=> $condicion2);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene el Rol del cliente por id de persona
     *
     * @ param integer $idPersona Identificador de la persona propietaria de la membresia
     *
     * @author
     *
     * @return array
     */
    public function obtnerRolCliente($idPersona)
    {
        settype($idPersona, 'integer');
        if ($idPersona == 0) {
            return null;
        }

        $tipoCliente = "";

        $this->db->select('t.idTipoRolCliente,t.descripcion');
        $this->db->from(TBL_TIPOROLCLIENTE.' t');
        $this->db->join(TBL_SOCIO .' s', 't.idTipoRolCliente = s.idTipoRolCliente');
        $this->db->where('s.idPersona', $idPersona);
        $this->db->where('s.eliminado', 0);
        $this->db->where('t.idTipoRolCliente <>', 0);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
        }
        if (isset($fila)) {
            return $fila;
        } else {
            return 0;
        }
    }

    /**
     * Obtiene los tipo de documento que son requeridos para un rol de cliente
     *
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerTipoDocumentoPersona($idTipoRolCliente)
    {
        settype($idTipoRolCliente, 'integer');

        $this->db->select('tc.descripcion, tc.idTipoComprobante');
        $this->db->from(TBL_CLIENTECOMPROBANTE.' cd');
        $this->db->join(TBL_TIPOCOMPROBANTE.' tc', 'tc.idTipoComprobante=cd.idTipoComprobante');
        $this->db->where('tc.activo', '1');
        $this->db->where('cd.idTipoRolCliente', $idTipoRolCliente);
        $query = $this->db->get();
        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return array();
        }
    }

    /**
     * Obtiene el tipo de membresia
     *
     * @param integer $idMembresia Identificador unico de la membresia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerTipoMembresia($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        if ($idUnicoMembresia == 0) {
            return null;
        }
        $this->db->distinct();
        $this->db->select('p.nombre, p.idProducto');
        $this->db->from(TBL_PRODUCTO.' p');
        $this->db->join(TBL_MEMBRESIA .' m', 'm.idProducto= p.idProducto');
        $where = array('m.idUnicoMembresia' => $idUnicoMembresia,'p.fechaEliminacion'=>'0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();
         if ($query->num_rows() > 0) {
            $fila      = $query->row_array();
            $Membresia = $fila;
        }
        if (isset($Membresia)) {
            return $Membresia;
        } else {
            return null;
        }
    }

    /**
    * Obtiene el titular de la membresía
    *
    * @param integer $idUnicoMembresia Identificador unico de la membresia
    *
    * @author Santa Garcia
    *
    * @return array
    */
    public function obtenerTitular($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $this->db->select('idPersona, idTipoEstatusSocio');
        $this->db->from(TBL_SOCIO);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('idTipoRolCliente', ROL_CLIENTE_TITULAR);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $idPersona = $query->row_array();
            }
            return $idPersona;
        }
        return 0;
    }

    /**
     * Regresa el identificador de persona registra como vendedor de la membresia
     *
     * @param integer $membresia Identificador de la membresia
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function obtenerVendedor($membresia)
    {
        settype($membresia, 'integer');

        if ($membresia == 0) {
            return 0;
        }

        $this->db->distinct();
        $this->db->select('idPersona');
        $this->db->from(TBL_MEMBRESIAINVOLUCRADO);
        $where = array('idUnicoMembresia' => $membresia, 'idTipoInvolucrado' => INVOLUCRADO_VENDEDOR);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idPersona'];
        }

        return 0;
    }

    /**
     *
     *
     * @author Santa Garcia
     *
     * @return string
     */
    function obtieneDatosCAvencidos($idSocio)
    {
        settype($idSocio, 'integer');

        $sql = "select sdt.idSocioDatosTarjeta, m.idUnicoMembresia, m.idUn,sdt.diaCargo,sdt.activo, if(date(CURDATE()) > DATE(concat_ws('-',YEAR(CURDATE()),MONTH(CURDATE()),sdt.diaCargo)),1,0) as vencido from socio s
                inner join  membresia m on m.idUnicoMembresia = s.idUnicoMembresia
                left join sociodatostarjeta sdt on s.idSocio = sdt.idSocio and sdt.fechaEliminacion = '0000-00-00 00:00:00'
                #and date(CURDATE()) > DATE(concat_ws('-',YEAR(CURDATE()),MONTH(CURDATE()),sdt.diaCargo))
                and s.idTipoEstatusSocio=81
                where s.idEsquemaPago = 2 and s.idSocio=".$idSocio;
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene datos generales de una promocion
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtieneDatosPromocion($idPromoMttoUn)
    {
        settype($idPromoMttoUn, 'integer');

        $this->db->select('idPromoMttoUn,idTipoPromoMtto, idUn, idProducto, porcentajeDescuento, meses, automatico, indeterminado, fechaInicio, fechafin');
        $this->db->from(TBL_PROMOMTTOUN);
        $this->db->where('idPromoMttoUn', $idPromoMttoUn);
        $query = $this->db->get();

        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene los invitados por convenio vigentes
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtieneInvitadosConvenio($idConvenio)
    {
        settype($idConvenio, 'integer');

        $this->db->select('m.idUnicoMembresia, cd.idConvenioDetalle');
        $this->db->from(TBL_CONVENIODETALLE.' cd');
        $this->db->join(TBL_MEMBRESIA.' m', 'm.idConvenioDetalle=cd.idConvenioDetalle');
        $this->db->where('cd.eliminado', 0);
        $this->db->where('m.eliminado', 0);
        $this->db->where('cd.idConvenio', $idConvenio);
        $query = $this->db->get();
        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * [permite2x1 description]
     *
     * @param  integer $idUnicoMembresia Identificador unico de membresia
     *
     * @return boolean
     */
    public function permite2x1($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "CALL spPermite2x1(".$idUnicoMembresia.", @res_agregado)";
        $query = $this->db->query($sql);

        $sql = "SELECT @res_agregado AS resultado";
        $query = $this->db->query($sql);
        $fila = $query->row_array();

        return $fila['resultado'];
    }

    /**
     * [permiteAgregado description]
     *
     * @param  integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jorge Cruz <jorge.cruz@sportsworl.com.mx>
     *
     * @return integer
     */
    public function permiteAgregado($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "CALL spPermiteAgregado(".$idUnicoMembresia.", @res_agregado)";
        $query = $this->db->query($sql);

        $sql = "SELECT @res_agregado AS resultado";
        $query = $this->db->query($sql);
        $fila = $query->row_array();

        return $fila['resultado'];
    }

    /**
     * Funcion que realiza la reactivacion
     *
     * @param integer $idUnicoMembresia Identificador unico de la Membresía
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function realizaReactivacion ($idUnicoMembresia,$idProducto,$importe = 0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idProducto, 'integer');

        $data = array (
            'idUnicoMembresia' => $idUnicoMembresia,
            'idProducto' => $idProducto,
            'importe' => $importe
        );

        $ci =& get_instance();
        $ci->load->model('un_model');

        $datosGral=$this->obtenerDatosGeneralesMem($idUnicoMembresia);
        $club=$ci->un_model->nombre($datosGral[0]->idUn);
        $membresia=$datosGral[0]->idMembresia;
        $this->db->insert(TBL_MEMBRESIAREACTIVACION, $data);
        $reactivacion = $this->db->insert_id();
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log(utf8_decode("Reactivación de Membresía ($membresia  del club $club)"), LOG_MEMBRESIA, $idUnicoMembresia);
            return $reactivacion;
        }
    }

    /**
     * [reenviaMailCodigosVueloAnualidades2015 description]
     *
     * @param  [type] $idAnualidadesCodigosViajes [description]
     * @param  [type] $idUnicoMembresia           [description]
     * @param  [type] $idPersona                  [description]
     *
     * @return [type]                             [description]
     */
    public function reenviaMailCodigosVueloAnualidades2015($idAnualidadesCodigosViajes, $idUnicoMembresia, $idPersona)
    {
        $data  = 0;
        $sql = "UPDATE crm.anualidadescodigosviajes acv
            SET acv.envioMail2=1, acv.fechaActualizacion=NOW()
            WHERE idAnualidadesCodigosViajes IN (".$idAnualidadesCodigosViajes.");";
        $query = $this->db->query($sql);

        if ($this->db->affected_rows()>0) {
            $this->permisos_model->log(
                utf8_decode("Reenvio de correo con codigo de viajes Anualidades 2015 a [".$idPersona."][".date('Y-m-d')."]") ,
                LOG_MEMBRESIA,
                $idUnicoMembresia
            );
            $data = 1;
        }
        return $data;
    }

    /**
     * [spGeneracionManualMovimientos description]
     *
     * @param  [type] $idMovimiento     [description]
     * @param  string $nuevaDescripcion [description]
     * @param  string $nuevoOrigen      [description]
     *
     * @return [type]                   [description]
     */
    public function spGeneracionManualMovimientos($idMovimiento, $nuevaDescripcion="", $nuevoOrigen="")
    {
        $nuevoCargo = 0;

        $rsSP = $this->db->query("CALL crm.spGeneracionManualMovimientos(".$idMovimiento.", 0, 0, 0, 0, 0, 0, '".$nuevaDes."', 0, 0, '".$nuevoOrigen."', @nuevoMovimiento);");
        $rsR = $this->db->query("SELECT @nuevoMovimiento AS nuevoCargo;");

        if( $rsR->num_rows()>0 ){
            $nuevoCargo = $rsR->row();
        }

        return $nuevoCargo;
    }

    /**
     * [spObtenCargosPromocionAnualidadFacturados description]
     *
     * @param  integer $anioAplica [description]
     *
     * @return [type]              [description]
     */
    public function spObtenCargosPromocionAnualidadFacturados($anioAplica=0)
    {
        $sql1 = "CALL crm.spMTTOMSIObtenCargosFacturados(".$anioAplica.", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * [spObtenUltimoMesPagado description]
     *
     * @param  [type] $idPersona [description]
     *
     * @return [type]            [description]
     */
    function spObtenUltimoMesPagado($idPersona)
    {
        $sql1 = "CALL crm.spObtenUltimoMesPagado(".$idPersona.", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * [spObtenUltimoPeriodoPagado description]
     *
     * @param  [type] $idPersona [description]
     *
     * @return [type]            [description]
     */
    function spObtenUltimoPeriodoPagado($idPersona)
    {
        $sql1 = "CALL crm.spObtenUltimoPeriodoPagado(".$idPersona.", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2 = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * Revisa si la membresía cuanta con el atributo enviado
     *
     * @param integer $idUnicoMembresia Identificador unico de la membresia
     * @param integer $idUn             Identificador de la unidad de negocios
     * @param integer $atributo         atributo de tipo membresia atributo
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function tieneAtributo($idUnicoMembresia, $idUn, $atributo, $idProducto=0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idUn, 'integer');
        settype($atributo, 'integer');
        settype($idProducto, 'integer');

        $this->db->select('m.idUnicoMembresia');
        $this->db->from(TBL_PRODUCTOUN.' pu');
        if ($idProducto != 0) {
            $this->db->join(TBL_MEMBRESIA.' m', 'pu.idUn=m.idUn');
        } else {
            $this->db->join(TBL_MEMBRESIA.' m', 'pu.idProducto=m.idProducto');
        }
        $this->db->join(TBL_MEMBRESIACONFIGURACION.' mc', 'pu.idProductoUn=mc.idProductoUn AND mc.fechaEliminacion="0000-00-00 00:00:00"');
        $this->db->join(TBL_MEMBRESIAATRIBUTOS.' ma', 'mc.idMembresiaConfiguracion=ma.idMembresiaConfiguracion');
        $this->db->join(TBL_TIPOMEMBRESIAATRIBUTO.' ta', 'ma.idTipoMembresiaAtributo=ta.idTipoMembresiaAtributo');
        $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
        if ($idProducto != 0) {
            $this->db->where('pu.idProducto', $idProducto);
            $this->db->where('ta.idTipoMembresiaAtributo', $atributo);
        }
        $this->db->where('ta.idTipoMembresiaAtributo', $atributo);
        $this->db->where('pu.eliminado', 0);
        $this->db->where('pu.idUn', $idUn);
        $this->db->where('ma.activo', 1);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return true;
        }
        return false;
    }

    /**
     * Regresa el numero total de integrantes dentro de una membresia
     *
     * @param integer $unico
     *
     * @return integer
     */
    public function totalIntegrantes ($unico)
    {
        $this->db->from(TBL_SOCIO.' s');
        $this->db->where('s.idUnicoMembresia', $unico);
        $this->db->where('s.eliminado', 0);

        return (int)$this->db->count_all_results();
    }

    /**
     * Función que realiza inserta eltraspaso entre clubes
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function traspaso($idUnicoMembresia, $idUn, $idMovimiento, $idUnNuevo, $idMembresia)
    {
        $this->db->select('idMembresiaTraspaso');
        $this->db->from(TBL_MEMBRESIATRASPASO);
        $this->db->where('idMembresia', 0);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('fechaEliminacion ', '0000-00-00 00:00:00');
        $this->db->where('fechaTraspaso', '0000-00-00');

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idMembresiaTraspaso', $fila->idMembresiaTraspaso);
                $datos = array ('fechaEliminacion' => date("Y-m-d H:i:s"));
                $this->db->update(TBL_MEMBRESIATRASPASO, $datos);
            }
        }

        $ci =& get_instance();
        $ci->load->model('un_model');
        $ci->load->model('empleados_model');

        $club1= $ci->un_model->nombre($idUn);
        $club2= $ci->un_model->nombre($idUnNuevo);

        $traspaso = array (
            'idUnicoMembresia' => $idUnicoMembresia,
            'idPersona'        => $ci->empleados_model->obtenIdEmpleado($this->session->userdata('idPersona')),
            'idUn'             => $idUnNuevo,
            'idMembresia'      => 0,
            'fechaTraspaso'    => '0000-00-00',
            'idMovimiento'     => $idMovimiento
        );
        $this->db->insert(TBL_MEMBRESIATRASPASO, $traspaso);
        $idTraspaso = $this->db->insert_id();

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        }

        $this->permisos_model->log(utf8_decode("Se realizó un traspaso del club de $club1 Membresía No. ".$idMembresia." al club de $club2"), LOG_MEMBRESIA, $idUnicoMembresia);

        return $idTraspaso;
    }

    /**
     * Obtiene el identificador unico de membresia
     *
     * @param integer $membresia Numero de membresia/inscripcion
     * @param integer $club      Identificador del club
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function unico($membresia, $club)
    {
        settype($membresia, "integer");
        settype($club, "integer");

        if ($membresia == 0 or $club == 0) {
            return 0;
        }

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIA);
        $where = array(
            'idMembresia' => $membresia,
            'idUn'        => $club,
            'eliminado'   => 0
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idUnicoMembresia'];
        } else {
            return 0;
        }
    }

    /**
     * Obtiene el identificador unico de membresia
     *
     * @param integer $membresia Numero de membresia/inscripcion
     * @param integer $club      Identificador del club
     *
     * @return integer
     */
    public function unicoTraspaso($membresia, $club)
    {
        settype($membresia, 'integer');
        settype($club, 'integer');

        if ($membresia == 0 or $club == 0) {
            return 0;
        }

        $this->db->select('idUnicoMembresia');
        $this->db->from(TBL_MEMBRESIATRASPASO);
        $where = array(
            'idMembresia' => $membresia,
            'idUn' => $club,
            'fechaEliminacion' => '00000-00-00 00:00:00'
        );
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['idUnicoMembresia'];
        } else {
            return 0;
        }
    }

    /**
     * Valida si todos los socios(descartando titular) estan ausentes
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function validaAusencias ($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $valido = false;

        if ($idUnicoMembresia == 0) {
            return $valido;
        }

        $this->db->select("COUNT(DISTINCT s.idSocio) AS sociosSinTitular");
        $this->db->join(TBL_SOCIO." s", 's.idUnicoMembresia = m.idUnicoMembresia', 'inner');
        $this->db->where("m.idUnicoMembresia", $idUnicoMembresia);
        $this->db->where("s.idTipoRolCliente <>", ROL_CLIENTE_TITULAR);
        $this->db->where("s.idTipoEstatusSocio <> ", ESTATUS_SOCIO_BAJA);
        $this->db->where("s.eliminado", 0);
        $query = $this->db->get(TBL_MEMBRESIA." m");

        $sociosSinTitular = $query->row_object()->sociosSinTitular;

        $this->db->select("COUNT(DISTINCT s.idSocio) AS sociosAusentes");
        $this->db->join(TBL_SOCIO." s", 's.idUnicoMembresia = m.idUnicoMembresia', 'inner');
        $this->db->join(TBL_SOCIOAUSENCIA." sa", 'sa.idSocio = s.idSocio', 'left');
        $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('s.idTipoRolCliente <>', ROL_CLIENTE_TITULAR);
        $this->db->where('s.idTipoEstatusSocio <>', ESTATUS_SOCIO_BAJA);
        $this->db->where('s.eliminado', 0);
        $this->db->where('sa.fechaRegresoAusencia', '0000-00-00 00:00:00');
        $this->db->where('sa.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_MEMBRESIA." m");

        $sociosAusentes = $query->row_object()->sociosAusentes;

        if ($sociosSinTitular == $sociosAusentes) {
            $valido = true;
        }
        return $valido;
    }

    /**
     * Valida si existe la opcion de una membresia
     *
     * @param integer $idMembConfig Identificador de membresiaconfiguracion
     * @param integer $idTipoMemOp  Identificador de tipomembresiaopcion
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function validaExisteOpcion ($idMembConfig, $idTipoMemOp)
    {
        settype($idMembConfig, 'integer');
        settype($idTipoMemOp, 'integer');

        $where = array(
            'idMembresiaConfiguracion' => $idMembConfig,
            'idTipoMembresiaOpcion'    => $idTipoMemOp
        );
        $total = $this->db->select(
            'COUNT(*)as total'
        )->get_where(
            TBL_MEMBRESIAOPCIONES, $where
        )->row()->total;

        $existe = ($total > 0) ? true : false;

        return $existe;
    }

    /**
     * [validaMesesConsecutivos description]
     *
     * @param  [type] $idTipoFidelidad   [description]
     * @param  [type] $mesesConsecutivos [description]
     *
     * @return [type]                    [description]
     */
    public function validaMesesConsecutivos($idTipoFidelidad, $mesesConsecutivos)
    {
        $resultado = 0;
        $rs = $this->db->query("SELECT tf.idTipoFidelidad
            FROM crm.tipofidelidad tf
            WHERE tf.idTipoFidelidad IN (".$idTipoFidelidad.") AND ".$mesesConsecutivos." BETWEEN tf.minMeses AND tf.maxMeses");
        if ( $rs->num_rows()>0 ) {
            $row = $rs->row();
            $resultado = $row->idTipoFidelidad;
        }

        return $resultado;
    }

    /**
     * Valida si existe un movimiento pendiente de pago por regreso de ausencia
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaRegresoAusencias ($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $datos = array(
            'mensaje' => 'Error, faltan datos',
            'error'   => 1,
            'adeudo'  => 0
        );

        if ( ! $idUnicoMembresia) {
            return $datos;
        }
        $datos['mensaje'] = '';
        $datos['error']   = 0;
        $where = array(
            's.eliminado'        => 0,
            's.idUnicoMembresia' => $idUnicoMembresia
        );
        $this->db->join(TBL_SOCIO.' s', "s.idSocio = sa.idSocio AND s.eliminado = 0", 'INNER');
        $this->db->join(TBL_MOVIMIENTO.' m',
            "m.idUnicoMembresia = s.idUnicoMembresia AND m.idTipoEstatusMovimiento = ".MOVIMIENTO_PENDIENTE." AND m.eliminado=0 AND m.descripcion LIKE '%ausencia%'",
            'INNER'
        );
        $this->db->join(TBL_MOVIMIENTOCTACONTABLE.' mcc',
            "mcc.idMovimiento = m.idMovimiento AND mcc.idTipoMovimiento = ".TIPO_MOVIMIENTO_MANTENIMIENTO." AND mcc.eliminado=0",
            'INNER'
        );
        $query = $this->db->select(
                'COUNT(DISTINCT m.idMovimiento) AS totalMovs', false
        )->get_where(TBL_SOCIOAUSENCIA.' sa', $where);

        $datos['adeudo'] = ($query->row()->totalMovs) ? 1 : 0;

        return $datos;
    }

    /**
     * Verifica si existe un registro en de socio pago mtto en un perodo de tiempo
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function verificaSocioPagomtto($idSocio, $inicio, $fechaFin)
    {
        settype($idSocio, 'integer');

        $this->db->select('idSocioPagoMtto,activo,fechaInicio,idMovimiento, idMantenimiento, idEsquemaPago');
        $this->db->from(TBL_SOCIOPAGOMTTO);
        $this->db->where('idSocio', $idSocio);
        $this->db->where('eliminado', 0);
        $this->db->where('fechaInicio >=', $inicio);
        $this->db->where('fechaFin <=', $fechaFin);
        $query = $this->db->get();
        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Verifica si existe un registro en de socio pago mtto en un perodo de tiempo
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function verificaSocioPagomttoPendiente($idSocio, $inicio, $fechaFin)
    {
        settype($idSocio, 'integer');

        $this->db->select('idSocioPagoMtto, idMovimiento, idEsquemaPago,idMantenimiento, fechaInicio');
        $this->db->from(TBL_SOCIOPAGOMTTO);
        $this->db->where('idSocio', $idSocio);
        $this->db->where('eliminado', 0);
        $this->db->where('fechaInicio >=', date('Y-m').'-01');
        $this->db->where('fechaFin <=', $fechaFin);
        $this->db->where('activo', 0);
        $query = $this->db->get();
        if ( $query->num_rows > 0 ) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * [membresiaSinCatFirmado description]
     *
     * @param  integer $idUnicoMembresia [description]
     *
     * @author Armando Paez
     *
     * @return [type]                   [description]
     */
    public function membresiaSinCatFirmado($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $CI = &get_instance();
        $CI->load->model('digital_model');

        $formatosCat = $CI->digital_model->obtenDocumentosMembresia($idUnicoMembresia, TIPO_DOCUMENTO_ALTA_CARGO_AUTOMATICO);
        if (count($formatosCat)>0) {
            $lastFormat = $formatosCat[0];
            if (strlen($lastFormat['firma'])>0) {
                return array();
            } else {
                return array(
                    'idUnicoMembresia' => $idUnicoMembresia,
                    'concepto' => $formatosCat[0]['concepto']
                );
            }
        } else {
            return array();
        }
    }

    /**
     * [documentos_pendientes description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @author Armando Paez
     *
     * @return [type]                   [description]
     */
    public function documentos_pendientes($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $CI = &get_instance();
        $CI->load->model('documentos_model');

        $membresiaSinCatFirmado = $this->membresiaSinCatFirmado($idUnicoMembresia);
        $documentosFaltantes = $CI->documentos_model->obtenerListaDocumentosPendientes($idUnicoMembresia);

        if (count($documentosFaltantes)==0) {
            $documentos = array(
                'documentosCompletos' => 1,
                'lista' => ''
            );
        } else {
            $documentos = array(
                'documentosCompletos' => 0,
                'lista' => $documentosFaltantes
            );
        }

        return $documentos;
    }

    /**
     * Funcion Actualiza idProducto de Membresia
     *
     * @author Ruben Alcocer
     *
     * @return boolean
     */
    public function actualizaProductoMembresia($idUnicoMembresia, $idProducto, $idProductoAnt)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idProducto, 'integer');
        settype($idProductoAnt, 'integer');
        $anterior = utf8_encode($this->obtenerNombreProducto($idProductoAnt));
        $actual =  utf8_encode($this->obtenerNombreProducto($idProducto));
        $datos = array (
            'idProducto' => $idProducto
        );
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->update(TBL_MEMBRESIA, $datos);
        $total = $this->db->affected_rows();
        if ($total > 0){
            $this->permisos_model->log(utf8_decode("Cambio Tipo de ".$anterior." a ".$actual."."), LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        }
        return false;
     }

     /**
     * Funcion Obtiene Nombre de Producto
     *
     * @author Ruben Alcocer
     *
     * @return texto
     */
     public function obtenerNombreProducto($idProducto)
     {
        $sql = 'SELECT nombre FROM '.TBL_PRODUCTO.' where idProducto = '.$idProducto.' LIMIT 1';
        $consulta = $this->db->query($sql);
        $fila = $consulta->row_array();
        return $fila['nombre'];
     }

     /**
     * Funcion Valida si el Club participa en Forever Fit
     *
     * @author Ruben Alcocer
     *
     * @return int
     */
     public function validaParticipacionClub($idUn)
     {

        settype($idUn, 'integer');

        $this->db->select('idUn');
        $this->db->from('unforeverfit');
        $this->db->where('idUn', $idUn);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ( $query->num_rows > 0 ) {
            return 1;
        } else {
            return 0;
        }
     }


    public function reporteCredencializacion(){
        $max_fp = $this->db->select_max('fechaParticion','fp')->from('crm.credencialenvio');
        $q1 = $this->db->_compile_select();
        echo $q1.'<br />';
        $max_fp = $this->db->get()->result();
        $max_fp = $max_fp[0]->fp;
        //En el caso que $max_fp acabe siendo algo diferente a un numero
        if(!is_int($max_fp) && !is_numeric($max_fp)) $max_fp = 20160815;
        $max_fp = mktime(0,0,0,substr($max_fp,4,2),substr($max_fp,6,2),substr($max_fp,0,4));
        $max_fp = $max_fp-(16*24*60*60); #16 dias por 24 horas por 60 minutos por 60 segundos
        $max_fp = date('Ymd',$max_fp);
        #else $max_fp = ($max_fp-16);
        $this->db->select('m.idMovimiento,'.
            'uf.nombre as clubFactura,'.
            'ifnull(mm.idMembresia,m.idPersona) as idMembresia,'.
            'substring(uf.clave,3,3) as cveFac,'.
            'if(isnull(um.clave),\'IE\',substring(um.clave,3,3)) as clave,'. //Esta linea hace que tengamos que NO escapar los campos
            'm.idPersona,'.
            'TIMESTAMPDIFF(YEAR,p.fechaNacimiento,now()) as edad,'.
            'f.fechaParticion as fechaParticion,'.
            '\'1\' as nuevo,'.
            'IFNULL(mm.idtipoestatusmembresia,27) as estatus,'.
            'IF(isnull(s.idTipoEstatusSocio) and m.idUnicoMembresia=0,81,s.idTipoEstatusSocio) as estatus_socio,'.
            'concat_ws(\' \',p.nombre,p.paterno,p.materno) as nombreCompleto',false);
        $this->db->from('crm.movimiento m');
        $this->db->join('crm.persona p','m.idPersona = p.idPersona')
             ->join('crm.facturamovimiento fm','m.idMovimiento = fm.idMovimiento')
             ->join('crm.factura f','fm.idFactura = f.idFactura')
             ->join('crm.un uf','f.idun = uf.idun')
             ->join('crm.finanzasnotacredito fnc','f.idFactura = fnc.idFactura and year(fnc.fechaActivacion) > 0 and year(fnc.fechaCancelacion) = 0','left')
             ->join('crm.membresia mm','m.idUnicoMembresia = mm.idunicomembresia','left')
             ->join('crm.un um','mm.idun = um.idun','left')
             ->join('crm.credencialenvio ce','m.idMovimiento = ce.idMovimiento','left')
             ->join('crm.socio s','p.idPersona = s.idPersona and mm.idunicomembresia = s.idunicomembresia and s.eliminado=0','left');
        $this->db->where('f.fechaParticion > '.$max_fp)
            ->where('f.fechaParticion < '.date('Ymd'))
            ->where('m.idProducto','390')
            ->where('m.importe > 0')
            ->where('m.idTipoEstatusMovimiento','66')
            ->where('f.idTipoEstatusFactura','109')
            ->where('fnc.idFinanzasNotaCredito is null')
            ->where('ce.idPersona is null');
        #$this->db->having('edad < 18');
        $q1 = $this->db->_compile_select();
        echo $q1.'<br />';
        $this->db->_reset_select();

        $this->db->select('cre.idMovimiento,'.
            'ifnull(uf2.nombre,\'ADM\') as clubFactura,'.
            'cre.idMembresia,'.
            'substring(ifnull(uf2.clave,\'SWADM\'),3,3) as cveFac,'.
            'cre.cvemm as clave,'.
            'cre.idPersona,'.
            'TIMESTAMPDIFF(YEAR,p2.fechaNacimiento,now()) as edad,'.
            'cre.fechaParticion,'.
            '\'0\' as nuevo,'.
            'IFNULL(mm2.idtipoestatusmembresia,27) as estatus,'.
            'IF(isnull(s2.idTipoEstatusSocio) and m2.idUnicoMembresia=0,81,s2.idTipoEstatusSocio) as estatus_socio,'.
            'concat_ws(\' \',p2.nombre,p2.paterno,p2.materno) as nombreCompleto');
        $this->db->from('crm.credencialenvio cre');
        $this->db->join('crm.facturamovimiento fm2','cre.idMovimiento = fm2.idMovimiento','left')
            ->join('crm.factura f2','fm2.idFactura = f2.idFactura','left')
            ->join('crm.persona p2','cre.idPersona = p2.idPersona')
            ->join('crm.movimiento m2','cre.idMovimiento = m2.idMovimiento AND m2.fechaEliminacion = 0')
            ->join('crm.membresia mm2','m2.idUnicoMembresia = mm2.idunicomembresia','left')
            ->join('crm.socio s2','p2.idPersona = s2.idPersona and mm2.idunicomembresia = s2.idunicomembresia and s2.eliminado=0','left')
            ->join('crm.un uf2','f2.idUn = uf2.idUn','left');
        $this->db->where('cre.fechaEnvio is null');
        $q2 = $this->db->_compile_select();
        $this->db->_reset_select();

        echo "\n".$q1.' UNION '.$q2."\n";
        $rs = $this->db->query($q1.' UNION '.$q2);

        return $rs->result();
    }

     public function reporteMembresiaFamiliarSinResponsable() {
        /*
         * Obtener las membresías que tienen menores (y cuantos)
         */
         $this->db->select('s2.idUnicoMembresia, count(s2.idPersona) as menores')
             ->from('crm.socio s2')
             ->join('crm.persona pr','s2.idPersona = pr.idPersona')
             ->where('s2.eliminado = 0')
             ->where('s2.idTipoEstatusSocio','81')
             ->where('pr.fechaNacimiento > DATE_SUB(date(now()), INTERVAL 16 YEAR)')
             ->group_by('s2.idUnicoMembresia');
         $q1 = $this->db->_compile_select();
         $this->db->_reset_select();

        /*
         * Obtener las membresias con menores que no tienen responsable
         */
         $this->db->select('m.idMembresia, '.
             'u.nombre as club, '.
             'p.nombre as producto, '.
             's.menores, '.
             'count(rm.idResponsableMenor) as responsables');
         $this->db->from('crm.membresia m')
             ->join('crm.un u','m.idUn = u.idUn')
             ->join('crm.producto p','m.idProducto = p.idProducto')
             ->join('crm.responsablemenor rm','m.idUnicoMembresia = rm.idUnicoMembresia','left')
             ->join('('.$q1.') s','m.idUnicoMembresia = s.idUnicoMembresia','left');
         $this->db->where('m.idTipoEstatusMembresia','27')
             ->where('m.eliminado',0)
             ->where('s.menores > 0');
         $this->db->group_by('m.idUnicoMembresia');
         $this->db->having('responsables','0');

         /*
          * Regresar objetos
          */
         $query = $this->db->get();
         if($query->num_rows() > 0)
             return $query->result();
         return array();
     }
    public function getAnexosPartTime($idMantenimiento,$idUn){
        $query= $this->db->query("SELECT u.idUn,u.nombre,p.nombre,m.idMantenimiento,m.idTipoAcceso,ta.descripcion,m.idTipoHorario,th.descripcion,
        m.fullTimeFinSemana,m.allClubFinSemana,mu.horaEntrada,
        mu.horaSalida,mu.horaEntradaFinSemana,mu.horaSalidaFinSemna,mu.integrante
        from  mantenimiento m
        inner join productomantenimiento pm on m.idMantenimiento=pm.idMantenimiento
        inner join producto p on pm.idProducto=p.idProducto
        inner join mantenimientounacceso mu on m.idMantenimiento=mu.idMantenimiento
        inner join tipoacceso ta   on  m.idTipoAcceso=ta.idTipoAcceso
        inner join tipohorario th on  m.idTipoHorario=th.idtipoHorario
        inner join un u on mu.idUn=u.idUn
        where m.idTipoHorario=2 and m.fechaEliminacion=0 and mu.integrante='Titular'
        and u.idUn=$idUn and m.idMantenimiento=$idMantenimiento;");
        $rs=$query->result_array();
        return $rs;
    }

    /**
     * Trae la membresia y idUnidad apartir del IdUnico
     *
     * @author Victor Rodriguez
     *
     * @return array
     */
    public function getIdMembresia($idUnicoMembresia)
    {
        $sql="select idMembresia,idUn from membresia where idUnicoMembresia=".$idUnicoMembresia;
        $query=$this->db->query($sql);
        if($query->num_rows() > 0)
        { 
            $res=$query->result();
            return $res->idMembresia;
        }
        return 0;
       
    }
}
