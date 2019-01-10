<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use API_EPS\Models\CatRutinas;
use API_EPS\Models\MenuActividad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Socio extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.objecto';
    protected $primaryKey = 'idObjeto';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     *
     * Actualiza el esquema de pago del titular y los integrantes de la membresia de modo particular
     *
     * @param   $idUnicoMembresia
     * @param   $idesquemaPago
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
     function actCorreccionEsquemaPago($idUnicoMembresia, $idEsquemaPago, $idPersona)
     {
        $pm=&get_instance();
        $pm->load->model('persona_model');

        $socioNuevo=$pm->persona_model->nombre($idPersona);
        $socioNuevo=strtoupper($socioNuevo);

        $query = $this->db->query('select es.descripcion FROM esquemapago es LEFT JOIN socio s on es.idEsquemaPago=s.idEsquemaPago where s.idPersona='.$idPersona);
        $row = $query->row();
        $esquemaPagoViejo=$row->descripcion;

        $query = $this->db->query('select descripcion FROM esquemapago where idEsquemaPago='.$idEsquemaPago);
        $row = $query->row();
        $esquemaPagoNuevo=$row->descripcion;

        $data = array('idEsquemaPago' => $idEsquemaPago);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('idTipoEstatusSocio !=', ESTATUS_SOCIO_BAJA);
        $this->db->where('eliminado', 0);
        $res=$this->db->update('socio', $data);
        if ($res==TRUE) {
            $this->permisos_model->log('Correccion cambio de forma de pago ('.$socioNuevo.', de '.$esquemaPagoViejo.' a '.$esquemaPagoNuevo.')' , LOG_MEMBRESIA, $idUnicoMembresia);
            return $esquemaPagoNuevo;
        } else {
            return false;
        }
    }

    /**
     * Activa la fecha de ausencia de un socio
     *
     * @param integer $idSocio       Id del socio a insertar
     * @param string  $fechaAusencia Fecha de ausencia a isertar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    function activaAusencia ($idSocio = 0, $fechaAusencia = '', $idPersona = 0, $idUnicoMembresia = 0, $nombreSocio = '')
    {
        settype($idSocio, 'integer');

        $datos = array();
        $idSocioAusencia = 0;
        $resultado = true;

        if ($idSocio == 0 or $fechaAusencia == '') {
            return $idSocioAusencia;
        }
        $mesActual = date('m');
        settype($mesActual, 'integer');
        $mesAusencia = date('m', strtotime($fechaAusencia));
        settype($mesAusencia, 'integer');

        if (($mesActual) == ($mesAusencia)) {
            $set = array(
                'idTipoEstatusSocio' => ESTATUS_SOCIO_AUSENCIA
            );
            $where = array(
                'idSocio' => $idSocio
            );
            $resultado = $this->db->update(TBL_SOCIO, $set, $where);
        }
        if ($resultado) {
            $datos = array(
                'idSocio'       => $idSocio,
                'fechaAusencia' => $fechaAusencia
            );

            $query = $this->db->insert(TBL_SOCIOAUSENCIA, $datos);

            if ($this->db->affected_rows() > 0) {
                $idSocioAusencia = $this->db->insert_id();
                $this->permisos_model->log('Socio "'.ucwords(strtolower($nombreSocio)).'" activa ausencia para: '.$fechaAusencia, LOG_MEMBRESIA, $idUnicoMembresia, $idPersona);
            }
        }
        return $idSocioAusencia;
    }

    /**
     * Actualiza datos de tarjeta
     *
     * @param  [type]  $id                [description]
     * @param  [type]  $idBanco           [description]
     * @param  [type]  $idDia             [description]
     * @param  [type]  $idUnicoMembresia  [description]
     * @param  [type]  $nombre            [description]
     * @param  [type]  $activo            [description]
     * @param  integer $mes               [description]
     * @param  integer $anio              [description]
     * @param  integer $idConvenioDetalle [description]
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function actualizaDatosTarjeta($id, $idBanco, $idDia, $idUnicoMembresia, $nombre, $activo, $mes=0, $anio=0, $idConvenioDetalle = 0)
    {
        settype($id, 'integer');
        settype($idBanco, 'integer');
        settype($idDia, 'integer');

        $CI =& get_instance();

        if ($mes == 0 && $anio == 0) {
            $datos = array (
                'idBanco'       => $idBanco,
                'diaCargo'      => $idDia,
                'nombreTarjeta' => $nombre,
                'activo'        => $activo);
        } else {
            $datos = array (
                'idBanco'       => $idBanco,
                'diaCargo'      => $idDia,
                'nombreTarjeta' => $nombre,
                'activo'        => $activo,
                'mesExpiracion' => $mes,
                'anioExpiracion'=> $anio);
        }
        if ($activo=='1') {
            $act='activa';
        } else {
            $act='inactiva';
        }
        if ($idConvenioDetalle) {
            $where = array('idConvenioDatosTarjeta' => $id);
            $this->db->update(TBL_CONVENIODATOSTARJETA, $datos, $where);

            $this->permisos_model->log("Se actualizo tarjeta idConvneioDetalle ".$idConvenioDetalle." (".date('Y-m')."). Tarjeta ".$act, LOG_CONVENIO);
        } else {
            $CI->load->model('persona_model');
            $this->db->select('st.idSocioDatosTarjeta,st.idSocio,st.numeroTarjetaCta,s.idPersona');
            $this->db->from(TBL_SOCIODATOSTARJETA.' st');
            $this->db->join(TBL_SOCIO.' s', 's.idSocio=st.idSocio');
            $this->db->where('st.idSocioDatosTarjeta', $id);
            $this->db->where('st.fechaEliminacion', '0000-00-00 00:00:00');
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                $fila = $query->row_array();
                $idSocio=$fila['idSocio'];
                $idPersona=$fila['idPersona'];
                $numTarjeta=$fila['numeroTarjetaCta'];
                $nombreCompleto = $CI->persona_model->nombre($idPersona);
                $this->db->where('idSocioDatosTarjeta', $fila['idSocioDatosTarjeta']);
                $this->db->update(TBL_SOCIODATOSTARJETA, $datos);

                $this->permisos_model->log("Se actualizo tarjeta (xxxx xxxx xxxx ".substr ($numTarjeta,-4,4).") de socio (".$nombreCompleto.") (".date('Y-m')."). Tarjeta ".$act, LOG_MEMBRESIA, $idUnicoMembresia);
            }
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *
     * Actualiza el esquema de pago del titular y los integrantes de la membresia
     *
     * @param   $idUnicoMembresia
     * @param   $idesquemaPago
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
     function actualizaEsquemaPago($idUnicoMembresia, $idEsquemaPago, $idPersona=0)
     {
        $mm=&get_instance();
        $mm->load->model('membresia_model');
        $mm->load->model('persona_model');
        $idMembresiaLocal = $mm->membresia_model->numero($idUnicoMembresia);

        $data = array('idEsquemaPago' => $idEsquemaPago);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('idTipoEstatusSocio !=', ESTATUS_SOCIO_BAJA);
        $this->db->where('eliminado', 0);
        if ($idPersona != 0) {
            $this->db->where('idPersona', $idPersona);
        }

        $res=$this->db->update('socio', $data);

        if ($idPersona != 0) {
            if ($idPersona>0) {
                $formaPagoAnterior = $this->obtenerEsquemaFormaPago($idPersona);
                $formaPagoActual = $this->obtenerEsquemaFormaPago(0,$idEsquemaPago);
            }
            $mensaje='Cambio de forma de pago a '.$formaPagoAnterior.' a  '.$formaPagoActual.' a ('.$mm->persona_model->nombre($idPersona).') integrante de la membresia: ';
        } else {
            $formaPagoActual = $this->obtenerEsquemaFormaPago(0,$idEsquemaPago);
            $mensaje='Cambio de forma de pago  a '.$formaPagoActual.' a todos los integrantes de la membresia: ';
        }

        if($res==TRUE){
            $this->permisos_model->log($mensaje.$idMembresiaLocal, LOG_MEMBRESIA, $idUnicoMembresia);
            return $uno=1;
        }else{
            return false;
        }
    }

    /*
     * Actualiza el estus de socio nuevo
     *
     * @author  Antonio Sixtos
     *
     * @return int
     */
    function actualizaEstatusSocioNuevo($idUnicoMembresia, $idTipoEstatusSocio, $idPersona = 0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idTipoEstatusSocio, 'integer');
        settype($idPersona, 'integer');

        $this->db->select('idSocio');
        $this->db->from(TBL_SOCIO);
        if($idPersona > 0){
            $this->db->where('idPersona', $idPersona);
        }
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $this->db->where('nuevo', '1');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $datos = array('nuevo'=> $idTipoEstatusSocio);
                $this->db->where('idSocio', $fila->idSocio);
                $this->db->update(TBL_SOCIO, $datos);
            }
            $this->permisos_model->log(utf8_decode("Se modificó el estatus del socio nuevo"), LOG_MEMBRESIA, $idUnicoMembresia);
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Actualiza el tipo de mantenimiento de un socio
     *
     * @param integer $idMantenimiento  Id de mantenimiento a actualizar
     * @param integer $idSocio          Id de socio a filtrar
     * @param integer $idUnicoMembresia Id unico de membresia a filtrar
     * @param integer $idPersona        Id de persona a filtrar
     * @param string  $nombreSocio      Nombre del socio a actualizar
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    function actualizaManttoSocio($idMantenimiento, $idSocio, $idUnicoMembresia, $idPersona = 0, $nombreSocio = '', $correcion = false)
    {
        $CI =& get_instance();
        $CI->load->model('mantenimientos_model');

        $resultado = false;

        settype($idMantenimiento, 'integer');
        settype($idSocio, 'integer');
        settype($idUnicoMembresia, 'integer');

        if (($idMantenimiento == 0) or ($idSocio == 0) or ($idUnicoMembresia == 0)) {
            return $resultado;
        }
        $mantenimiento = $CI->mantenimientos_model->obtenMantenimientoNombre($idMantenimiento);

        $where = array(
            'idSocio'          => $idSocio,
            'idUnicoMembresia' => $idUnicoMembresia
        );
        $set = array(
            'idMantenimiento' => $idMantenimiento
        );
        $resultado = $this->db->update(TBL_SOCIO, $set, $where);

        if ($resultado and ! $correcion) {
            $this->permisos_model->log('Socio "'.strtolower($nombreSocio).'" cambia tipo de mantenimiento a '.$mantenimiento, LOG_MEMBRESIA, $idUnicoMembresia, $idPersona);
        } elseif ($resultado and $correcion) {
            $this->permisos_model->log(utf8_decode('Corrección para Socio "'.strtolower($nombreSocio).'" se le cambia tipo de mantenimiento a '.$mantenimiento), LOG_MEMBRESIA, $idUnicoMembresia, $idPersona);
        }
        return $resultado;
    }

    /**
     * [actualizaTipoEstatusSocio description]
     *
     * @param  [type] $idUnicoMembresia   [description]
     * @param  [type] $idTipoEstatusSocio [description]
     * @param  [type] $idPersona          [description]
     *
     * @return [type]                     [description]
     */
    function actualizaTipoEstatusSocio($idUnicoMembresia, $idTipoEstatusSocio, $idPersona)
    {
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $ci->load->model('catalogos_model');

        $socio=$ci->persona_model->nombre($idPersona);
        $socio=strtoupper($socio);

        $this->db->select('t.descripcion');
        $this->db->from(TBL_TIPOESTATUSSOCIO.' t');
        $this->db->join(TBL_SOCIO.' s', 's.idTipoEstatusSocio=t.idTipoEstatusSocio', 'LEFT');
        $this->db->where('s.idPersona', $idPersona);
        $this->db->where('s.eliminado', 0);
        $query1 = $this->db->get();
        $row1 = $query1->row();
        $tipoEsSocioViejo = $row1->descripcion;

        $ci->catalogos_model->opcionesCampo(40, $idTipoEstatusSocio, 'descripcion');
        $tipoEsSocioNuevo = $ci->catalogos_model->opcionesCampo(40, $idTipoEstatusSocio, 'descripcion');;

        $data = array('idTipoEstatusSocio' => $idTipoEstatusSocio);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $res = $this->db->update('socio', $data);
        if ($res==true) {
            $this->permisos_model->log('Correccion del estatus de socio ('.$socio.', de '.$tipoEsSocioViejo.' a '.$tipoEsSocioNuevo.' )', LOG_MEMBRESIA, $idUnicoMembresia);
            return $uno = 1;
        } else {
            return false;
        }
    }

    /**
     * [actualizaTipoSocio description]
     *
     * @param  [type] $idUnicoMembresia [description]
     * @param  [type] $idTipoSocio      [description]
     * @param  [type] $idPersona        [description]
     *
     * @return [type]                   [description]
     */
    function actualizaTipoSocio($idUnicoMembresia, $idTipoSocio, $idPersona)
    {
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $ci->load->model('tipocliente_model');

        $socio=$ci->persona_model->nombre($idPersona);
        $socio=strtoupper($socio);

        $sql1="SELECT descripcion
            FROM tiporolcliente trc
            LEFT JOIN socio s ON s.idTipoRolCliente=trc.idTipoRolCliente AND s.eliminado=0
            WHERE s.idpersona=".$idPersona;
        $query1=$this->db->query($sql1);
        $row1 = $query1->row();
        $tipoSocioViejo=$row1->descripcion;

        $tipoSocioNuevo = $ci->tipocliente_model->nombreRolCliente($idTipoSocio);

        $data = array('idTipoRolCliente' => $idTipoSocio);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('eliminado', 0);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $res=$this->db->update('socio', $data);
        if($res==TRUE){
            $this->permisos_model->log('Correccion de tipo de socio ('.$socio.', de '.$tipoSocioViejo.' a '.$tipoSocioNuevo.' )', LOG_MEMBRESIA, $idUnicoMembresia);
            return $uno=1;
        } else {
            return false;
        }
    }

    /**
     * Regresa un arrat con la lista de estados registrado en la base de datos
     *
     * @return array
     */
    function arrayBanco()
    {
        $data = array();
        $this->db->select('idBanco, descripcion');
        $query = $this->db->order_by('idBanco')->get(TBL_BANCO);
        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idBanco] = $fila->descripcion;
            }
        }
        return $data;
    }

    /**
     * Regresa un arrat con la lista de tipos de tarjeta
     *
     * @return array
     */
    function arrayTipoTarjeta()
    {
        $data = array();
        $this->db->select('idTipoTarjeta, descripcion');
        $query = $this->db->order_by('idTipoTarjeta')->get('tipotarjeta');
        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idTipoTarjeta] = $fila->descripcion;
            }
        }
        return $data;
    }

    /**
     * Asignar programa de lealtad
     *
     * @param int $idUnicoMembresia
     *
     * @author Antonio Sixtos
     *
     * @return int
     */
    function asignarLealtad($idUnicoMembresia, $asignarLealtad, $mensajeLealtad)
    {
        $asignarLealtad = intval($asignarLealtad);
        $query  = $this->db->query("SELECT s.idSocio
            FROM socio s
            WHERE s.idUnicoMembresia IN (".$idUnicoMembresia.") AND s.idTipoRolCliente=1
                AND s.eliminado=0");
        $row    = $query->row();
        $idSocio= $row->idSocio;

        $query1 = $this->db->query("UPDATE socio SET asignarLealtad=".$asignarLealtad." WHERE idSocio=".$idSocio);
        $res  = $this->db->affected_rows();
        if($res==1){
            $mensaje1=($asignarLealtad==1)?'Asignacion':'Cancelaci&oacuten';
            $this->permisos_model->log($mensaje1.' de programa de lealtad, por motivo de: "'.utf8_decode($mensajeLealtad).'"', LOG_MEMBRESIA, $idUnicoMembresia);
        }
        return $res;
    }

    /**
     * Query baja socio
     *
     * @param integer $idSocio          identificador socio
     * @param date    $fechabaja        fecha de baja
     * @param text    $motivo           motivo por el cual se da de baja
     * @param integer $idpersona        id de persona
     *
     * @author Antonio Sixtos
     * @return array
     */
    function baja($idsocio, $fechabaja, $motivo, $idpersona, $unico)
    {
        $ci =& get_instance();
        $ci->load->model('persona_model');

        $idUnicoMembresia = $unico;
        $da['nombrecompleto'] = $ci->persona_model->nombre($idpersona);

        $motivo   = utf8_decode($motivo);
        $fechab   = stringTodate($fechabaja);
        $fechahoy = date("Y-m-d");
        $fecha    = diffmktime($fechab);
        $hoy      = diffmktime($fechahoy);

        $datos = array (
            'idSocio'   => $idsocio,
            'idPersona' => $this->session->userdata('idPersona'),
            'motivo'    => $motivo,
            'fechaBaja' => $fechab
        );
        $this->db->insert(TBL_SOCIOBAJA, $datos);
        $this->permisos_model->log('Baja de Socio ('.$da['nombrecompleto'].')', LOG_MEMBRESIA, $idUnicoMembresia);

        if ($fecha==$hoy) {
            $socio = array (
                'idTipoEstatusSocio' => ESTATUS_SOCIO_BAJA,
                'fechaEliminacion'   => date('Y-m-d H:i:s')
            );
            $this->db->where('idSocio', $idsocio);
            $this->db->update(TBL_SOCIO, $socio);

            $this->db->where('spm.activo', 1);
            $this->db->where('spm.idUnicoMembresia', $unico);
            $this->db->where('spm.idPersona', $idpersona);
            $this->db->where('spm.idSocio', $idsocio);
            $this->db->where('spm.eliminado', 0);
            $this->db->where("DATE(NOW()) BETWEEN spm.fechaInicio AND spm.fechaFin");
            $this->db->update(TBL_SOCIOPAGOMTTO.' spm', array('spm.idPersona' => 0, 'spm.idSocio' => 0));
        }

        $query2 = $this->db->query('SELECT sb.idSocioBaja FROM sociobaja sb ORDER BY 1 DESc LIMIT 1');
        $row = $query2->row();
        return $row->idSocioBaja;
    }

    /**
     * [cambialSocio description]
     *
     * @param  [type] $idPersona        [description]
     * @param  [type] $idSocio          [description]
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    function cambialSocio($idPersona, $idSocio, $idUnicoMembresia)
    {
        $ci =& get_instance();
        $ci->load->model('persona_model');

        $socioAnterior=$this->obtenNombre($idSocio);

        $socioNuevo=$ci->persona_model->nombre($idPersona);
        $socioNuevo=strtoupper($socioNuevo);

        $data = array('idPersona' => $idPersona);
        $this->db->where('idSocio', $idSocio);
        $res=$this->db->update('socio', $data);
        if ($res==TRUE) {
            $this->permisos_model->log('Correccion de cambio de persona  (De '.$socioAnterior.' a '.$socioNuevo.')', LOG_MEMBRESIA, $idUnicoMembresia);
            return $uno=1;
        } else {
            return false;
        }
    }

    /**
     * Cancelacion de baja programada del socio
     *
     * @author Antonio Sixtos
     *
     * @return variable
     */
    function cancelaBajaProgramada($idsocio)
    {
        $ci =& get_instance();
        $ci->load->model('persona_model');

        $this->db->select('idUnicoMembresia, idPersona');
        $this->db->from(TBL_SOCIO);
        $this->db->where('idSocio', $idsocio);
        $query1 = $this->db->get();
        $row1 = $query1->row();
        $idUnicoMembresia = $row1->idUnicoMembresia;
        $idPersona = $row1->idPersona;

        $da['nombrecompleto'] = $ci->persona_model->nombre($idPersona);

        $this->db->select_max('idSocioBaja');
        $this->db->from(TBL_SOCIOBAJA);
        $this->db->where('idSocio', $idsocio);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query3 = $this->db->get();
        $row3 = $query3->row();
        $idSocioBaja = $row3->idSocioBaja;

        if ($idSocioBaja > 0) {
            $datos = array ('fechaEliminacion'  => date('Y-m-d H:i:s'));
            $this->db->where('idSocio', $idsocio);
            $this->db->where('idSocioBaja', $idSocioBaja);
            $this->db->update(TBL_SOCIOBAJA, $datos);

            $datos = array ('fechaEliminacion'  => date('Y-m-d H:i:s'));
            $this->db->where('idSocio', $idsocio);
            $this->db->where('idRelacionExterna', $idSocioBaja);
            $this->db->update('respuestamotivobaja', $datos);
            $this->permisos_model->log('Cancelaci&oacute;n de baja programada del socio ('.$da['nombrecompleto'].')', LOG_MEMBRESIA, $idUnicoMembresia);
        }
    }

    /**
     * cuenta el numero de socios activos que tiene una membresia
     *
     * @author Antonio Sixtos
     *
     * @param integer $unico    IdUnicoMembresia
     *
     * @return array
     */
    function cuentaSocios($unico)
    {
        settype($unico, 'integer');

        if ($unico==0) {
            return 0;
        }

        $this->db->from(TBL_SOCIO.' s');
        $this->db->join(TBL_PERSONA.' p', 's.idPersona=p.idPersona');
        $this->db->join(TBL_TIPOROLCLIENTE.' trc', 's.idTipoRolCliente=trc.idTipoRolCliente');
        $this->db->join(TBL_PRODUCTOMANTENIMIENTO.' pma', 's.idMantenimiento=pma.idMantenimiento');
        $this->db->join(TBL_PRODUCTO.' pro', 'pma.idProducto=pro.idProducto');
        $this->db->where('s.idUnicoMembresia', $unico);
        $this->db->where('s.eliminado', 0);
        $this->db->where('s.idTipoEstatusSocio !=', ESTATUS_SOCIO_BAJA);

        return $this->db->count_all_results();
    }

    /**
     * Obtiene datos del domicilio del titular a partir del idUnicoMembresia
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function datosDomicilioTitular($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT t.descripcion as tipodomicilio, d.calle, d.numero, d.colonia, e.descripcion as estado,
                m.descripcion as municipio, d.rfc, d.nombrefiscal, d.cp FROM domicilio d
            LEFT JOIN socio s on s.idPersona=d.idPersona
            LEFT JOIN tipodomicilio t on t.idTipoDomicilio=d.idTipoDomicilio
            LEFT JOIN estado e on e.idEstado=d.idEstado
            LEFT JOIN municipio m on m.idMunicipio=d.idMunicipio
            WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.idTipoRolCliente=1
                AND s.eliminado=0
                AND d.fechaeliminacion='0000-00-00 00:00:00'";
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
     * Obtiene datos del precio especial para un socio con un tipo mantenimiento
     *
     * @author Santa Garcia
     *
     * @return integer
     */
    function datosPrecioEspecialMtto($idSocio,$rolCliente,$mantenimiento)
    {
        settype($idSocio,'integer');
        settype($rolCliente,'integer');
        settype($mantenimiento,'integer');

        $this->db->select('idSocioPrecioMtto,importe,porcentaje,fechaInicio,fechaFin,idEsquemaPago,idMantenimiento,idTipoRolCliente');
        $this->db->from(TBL_SOCIOPRECIOMTTO);
        $this->db->where('idSocio', $idSocio,'idTipoRolCliente', $rolCliente,'idMantenimiento', $mantenimiento);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->row_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene datos del socio en base al idPersona
     *
     * @author Antonio Sixtos
     *
     * @return array data
     */
    public function datosSocio($idPersona, $validaEliminado = true)
    {
        settype($idPersona, 'integer');
        $data = array();

        if ($idPersona==0) {
            return $data;
        }

        $this->db->select(
            's.idSocio, p.idPersona, p.idTipoPersona, s.idUnicoMembresia, trc.idTipoRolCliente, trc.descripcion AS tipoRolCliente,
            s.idTipoEstatusSocio, s.idMantenimiento, s.idEsquemaPago, tc.idTipoCliente,
            tc.descripcion AS tipoCliente, s.fechaRegistro, s.numeroAusencias, YEAR(s.fechaEliminacion)AS eliminado, p.edad'
        );
        $this->db->from(TBL_SOCIO." s");
        $this->db->join(TBL_PERSONA." p", "s.idPersona = p.idPersona", "inner");
        $this->db->join(TBL_TIPOROLCLIENTE." trc", "s.idTipoRolCliente = trc.idTipoRolCliente", "inner");
        $this->db->join(TBL_TIPOCLIENTE." tc", "trc.idTipoCliente = tc.idTipoCliente", "inner");
        $this->db->where('s.idPersona', $idPersona);
        if ($validaEliminado) {
            $this->db->where('s.eliminado', 0);
        } else {
            $this->db->order_by('s.idSocio', 'DESC');
        }
        $this->db->where('trc.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('tc.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('trc.activo', 1);
        $this->db->where('tc.activo', 1);
        $query = $this->db->get();

        if ($query->num_rows) {
            $data = $query->row_array();
        }
        return $data;
    }

    /**
     * Obtiene datos del titular en base al idUnicoMembresia
     *
     * @author Antonio Sixtos
     *
     * @return array data
     */
    function datosTitular($unico)
    {
        $sql = "SELECT CONCAT(p.nombre,' ',p.paterno,' ',p.materno) as nombreCompleto,
                m.idMembresia, u.nombre as nombreUnidad, pro.nombre as tipoMembresia, tel.telefono, mail
            FROM socio s
            INNER JOIN persona p on s.idpersona=p.idpersona
            INNER JOIN tiporolcliente trc on s.idtiporolcliente=trc.idtiporolcliente
            INNER JOIN membresia m on s.idUnicoMembresia=m.idUnicoMembresia
            INNER JOIN un u on m.idUn=u.idUn
            INNER JOIN producto pro on m.idProducto=pro.idProducto
            LEFT JOIN telefono tel on s.idPersona=tel.idPersona
            LEFT JOIN mail on s.idPersona=mail.idPersona
            WHERE s.idunicomembresia=".$unico." AND s.eliminado=0 AND trc.idTipoRolCliente=1 limit 1";
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
     * Obtiene edades del socio en base al idSocio, idUn y idMantenimiento
     *
     * @author Antonio Sixtos
     *
     * @return array data
     */
    function edadesTSocio($idUn, $idMantenimiento, $tipoSocio){
        $this->db->select('edadMinima, edadMaxima');
        $this->db->from(TBL_MANTENIMIENTOCLIENTE);
        $where=array('idUn'=>$idUn , 'idMantenimiento'=>$idMantenimiento, 'idTipoRolCliente'=>$tipoSocio);
        $this->db->where($where);
        $query = $this->db->get();
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
     * Desactiva la ausencia de un socio
     *
     * @param integer $idSocioAusencia  Id del registro a desactivar
     * @param integer $idPersona        Id de persona a filtrar
     * @param integer $idUnicoMembresia Id Unico de la membresia a filtrar
     * @param string  $nombreSocio      Nombre del socio
     * @param integer $idSocio          Id del socio a filtrar
     * @param boolean $accion           Bandera para identificar si el usuario regresa o cancela
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    function eliminaAusencia ($idPersona = 0, $idUnicoMembresia = 0, $accion = 'cancelar')
    {
        settype($idPersona, 'integer');

        if (($idPersona == 0)) {
            return false;
        }

        $this->db->select('idSocio');
        $this->db->from(TBL_SOCIO);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            $idSocio = $query->row()->idSocio;
        } else {
            return false;
        }

        $set = array('idTipoEstatusSocio' => ESTATUS_SOCIO_ACTIVO, 'numeroAusencias' => 0);
        $where = array('idSocio' => $idSocio);
        $this->db->update(TBL_SOCIO, $set, $where); #echo $this->db->last_query();

        $set = array('fechaRegresoAusencia' => date('Y-m-d H:i:s'));
        $where = array('idSocio' => $idSocio, 'fechaRegresoAusencia' => '0000-00-00 00:00:00');
        $this->db->update(TBL_SOCIOAUSENCIA, $set, $where); #echo $this->db->last_query();

        $this->permisos_model->log('Se cancela ausencia para Socio ('.$idPersona.')', LOG_MEMBRESIA, $idUnicoMembresia, $idPersona);

        return true;
    }

    /**
     * Elimina una tarjeta
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function eliminarTarjetaPagoAutomatico($id, $idUnicoMembresia = 0, $mensaje = '', $idConvenioDetalle = 0)
    {
        settype($id,'integer');
        settype($idUnicoMembresia,'integer');
        settype($idConvenioDetalle,'integer');

        $CI =& get_instance();

        if ($idConvenioDetalle) {
            $where = array('idConvenioDatosTarjeta' => $id);
            $set   = array (
                'fechaEliminacion'  => date("Y-m-d H:i:s"),
                'motivoCancelacion' => $mensaje
            );

            $this->db->update(TBL_CONVENIODATOSTARJETA, $set, $where);
        } else {
            $CI->load->model('persona_model');

            $this->db->select('st.idSocioDatosTarjeta,st.idSocio,st.numeroTarjetaCta,s.idPersona');
            $this->db->from(TBL_SOCIODATOSTARJETA.' st');
            $this->db->join(TBL_SOCIO.' s', 's.idSocio=st.idSocio');
            $this->db->where('st.idSocioDatosTarjeta', $id);
            $this->db->where('st.fechaEliminacion', '0000-00-00 00:00:00');
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                $fila = $query->row_array();
                $idSocio=$fila['idSocio'];
                $idPersona=$fila['idPersona'];
                $numTarjeta=$fila['numeroTarjetaCta'];
                $this->db->where('idSocioDatosTarjeta', $fila['idSocioDatosTarjeta']);
                $datos = array(
                    'fechaEliminacion'  => date("Y-m-d H:i:s"),
                    'motivoCancelacion' => $mensaje
                );
                $this->db->update(TBL_SOCIODATOSTARJETA, $datos);
            }
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            if ($idConvenioDetalle) {
                $this->permisos_model->log("Se elimino tarjeta de idConvenioDetalle (".$idConvenioDetalle.") (".date('Y-m').")", LOG_CONVENIO);
            } else {
                $this->permisos_model->log("Se elimino tarjeta (xxxx xxxx xxxx ".substr ($numTarjeta,-4,4).") de socio (".$CI->persona_model->nombre($idPersona).") (".date('Y-m').")", LOG_MEMBRESIA, $idUnicoMembresia);
            }
            return true;
        }
    }

    /**
     * Valida si la persona enviada es fidelidad Elite
     *
     * @param  integer $idPersona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    function elite($idPersona)
    {
        settype($idPersona, 'integer');

        $this->db->select('idSocio');
        $this->db->from(TBL_SOCIO.' s');
        $this->db->join(TBL_MEMBRESIA.' mem', "mem.idUnicoMembresia=s.idUnicoMembresia and mem.eliminado=0");
        $this->db->join(TBL_MEMBRESIAFIDELIDAD.' mf', "mf.idUnicoMembresia=mem.idUnicoMembresia AND mf.fechaEliminacion='00000-00-00 00:00:00' AND mf.idTipoFidelidad=5");

        $this->db->where('s.idPersona', $idPersona);
        $this->db->where('s.eliminado', 0);
        $this->db->where('s.idTipoEstatusSocio <>', 82);

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * [estatusSocio description]
     *
     * @param  [type] $idSocio [description]
     *
     * @return [type]          [description]
     */
    function estatusSocio($idSocio)
    {
        $this->db->select('idTipoEstatusSocio');
        $where=array('idSocio'=>$idSocio);
        $this->db->where($where);
        $this->db->from(TBL_SOCIO);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                return $fila->idTipoEstatusSocio;
            }
        } else {
            return 0;
        }
    }

    /**
     * Obtiene la fecha de baja de la tabla sociobaja
     *
     * @author Antonio Sixtos
     *
     * @return variable fechabaja
     */
    function fechaBaja($idSocio)
    {
        $this->db->select('fechaBaja');
        $this->db->where('idSocio', $idSocio);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_SOCIOBAJA);

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila['fechaBaja'];
        } else {
            return false;
        }
    }

    /**
     *
     * @param type $idUnicoMembresia
     * @param type $idTipoSocio
     * @param type $idPersona
     * @return type
     */
    public function generaDocumentosAltaSocio($idUnicoMembresia, $idTipoSocio, $idPersona)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idTipoSocio, 'integer');
        settype($idPersona, 'integer');

        $CI =& get_instance();
        $CI->load->model('un_model');
        $CI->load->model('membresia_model');
        $CI->load->model('tipocliente_model');
        $CI->load->model('catalogos_model');
        $CI->load->model('movimientos_model');
        $CI->load->model('mantenimientos_model');
        $CI->load->model('documentos_model');
        $CI->load->model('persona_model');
        $CI->load->model('digital_model');
        $CI->load->model('operadores_model');

        $datos['generales']        = $CI->membresia_model->obtenerDatosGeneralesMem($idUnicoMembresia);
        $doc['tipoEmpresa']        = $this->tipoEmpresa($idPersona);
        $datos['idTitular']        = $CI->membresia_model->obtenerTitular($idUnicoMembresia);
        $titular                   = $datos['idTitular']['idPersona'];
        $dat['nombreTitular']      = $CI->persona_model->nombre($titular);
        $club                      = $CI->membresia_model->club($idUnicoMembresia);
        $tm                        = $CI->membresia_model->obtenerTipoMembresia($idUnicoMembresia);
        $dat['idMembresiaTitular'] = $CI->membresia_model->numero($idUnicoMembresia);
        $dat['nombreUnidad']       = $CI->un_model->nombre($club);
        $dat['tipoMembresia']      = utf8_encode($tm['nombre']);

        $datosNuevo             = $this->datosSocio($idPersona);

        $dat['nombreSocio']     = $CI->persona_model->nombre($idPersona);
        $dat['tipoRolSocio']    = $CI->tipocliente_model->nombreRolCliente($datosNuevo['idTipoRolCliente']);
        $dat['tipoEsquemaPago'] = $CI->catalogos_model->opcionesCampo(12, $datosNuevo['idEsquemaPago'], 'descripcion');
        $dat['idPersona']       = $idPersona;
        $dat['tipoAcceso']      = $this->obtenTipoAcceso($idUnicoMembresia);

        $membresiaDatos = $CI->membresia_model->obtenerTipoMembresia($idUnicoMembresia);
        $tipoMembresia  = $membresiaDatos['nombre'];
        $meses          = meses();
        $mesActual      = (int)date('m');

        /**********************************/

        $formato = 0;
        $idEmpresa = $CI->un_model->obtenerEmpresa($club);
        $edad = $CI->persona_model->edad($idPersona);
        $datosPersona = $CI->persona_model->datosGenerales($idPersona);
        $dat['edad']               = $edad;
        $dat['fecha']              = $datosPersona['fecha'];
        $json['docAutoriacionMed'] = 0;

        $datosUn                 = $CI->un_model->obtenDatosUn($club);
        $datosOperador           = $CI->operadores_model->obtenOperadorInfo($datosUn['idOperador']);
        $dat['logo']             = $datosOperador[0]['logo'];
        $dat['razonSocial']      = $datosOperador[0]['razonSocial'];
        $dat['clubes']           = $datosOperador[0]['clubes'];
        $dat['responsable']      = $datosOperador[0]['responsable'];
        $dat['firmaResponsable'] = $datosOperador[0]['firmaResponsable'];
        $dat['direccionSocial']  = $datosOperador[0]['direccionSocial'];

        /*if ($edad >= 65) {
            if (file_exists(verificaRuta(RUTA_LOCAL.'/system/application/views/socio/HTML/CotitularMayor65_'.$datosUn['idOperador'].'.php'))) {
                $html2                     = $this->load->view('socio/HTML/CotitularMayor65_'.$datosUn['idOperador'], $dat, true);
                $idDocumento2              = $CI->documentos_model->insertaGeneralHTML($html2, TIPO_AUTORIZACION_MEDICA, $idPersona, $idUnicoMembresia );
                $json['docAutoriacionMed'] = $idDocumento2;
            }
        }*/

        $movimiento=12;

        $dat['digital']       = $CI->digital_model->validaAutorizacionDigital($idUnicoMembresia) ? 1 : 0;
        $dat['fechaRegistro'] = date('Y-m-d');

         if (file_exists(verificaRuta(RUTA_LOCAL.'/system/application/views/socio/HTML/ReporteAltaIntegrante_'.$datosUn['idOperador'].'.php'))) {
            $dat['tipoRolSocio'] = $datosNuevo['tipoRolCliente'];
            $html = $this->load->view('socio/HTML/ReporteAltaIntegrante_'.$datosUn['idOperador'], $dat, true);

            $datosRes = $CI->digital_model->guardaDocumentoDigital($idPersona, TIPO_DOCUMENTO_ALTA_INTEGRANTE, '', $html, 0, '', 'Frente', $idUnicoMembresia, 1, $dat['digital'], 0);
            $idDocumento = $datosRes['idDocumento'];

            $uno = $idDocumento."::".$club."::".$idPersona."::".$movimiento."::".$formato;
            $json['uno'] = $uno;
         }
         #}
         $idProductoUn = $CI->membresia_model->obtenIdProductoUn($datos['generales'][0]->idUnicoMembresia, $datos['generales'][0]->idUn);
         $datosTipoMem = $CI->membresia_model->obtenDatosTipoMembresia($idProductoUn);

        $listaTelefonos = $CI->persona_model->listaTelefonos($titular);
        if ($listaTelefonos != null) {
            $datosTelefonos = $CI->persona_model->datosTelefono($titular, $listaTelefonos[0]['idTelefono']);

            $datos['telefono'] = $datosTelefonos['telefono'];
            $lt1 = $CI->persona_model->listaTelefonos($titular, '30');
            if ($lt1 == null) {
                $datos['telefono_casa'] = "";
            } else {
                $datos['telefono_casa']    = $lt1[0]['telefono'];
            }
        }

        $listaMails  = $CI->persona_model->listaMails($titular);

        if ($listaMails != null) {
            $datosMail = $CI->persona_model->datosMail($titular, $listaMails[0]['idMail']);

            $datos['idMail']     = $listaMails[0]['idMail'];
            $datos['mail']       = $datosMail['mail'];
            $datos['idTipoMail'] = $datosMail['tipoMail'];

            $lm1 = $CI->persona_model->listaMails($titular, '34');
            if ($lm1 == null) {
                $datos['mail_personal'] = "";
            } else {
                $datos['mail_personal'] = $lm1[0]['mail'];
            }
        }

         if ($idTipoSocio == ROL_CLIENTE_NIETO_SOBRINO and $datosTipoMem['idTipoMembresia'] == TIPO_MEMBRESIA_FAMILIAR) {
            if (file_exists(verificaRuta(RUTA_LOCAL.'/system/application/views/documentos/HTML/'.TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_SOBRINO.'_'.$datosUn['idOperador'].'.php'))) {
                if ($edad < 18) {
                    $datosResponsiva['tipoMembresia'] = $tipoMembresia;
                    $datosResponsiva['nuevoSocio']    = $dat['nombreSocio'];
                    $datosResponsiva['meses']         = $meses;
                    $datosResponsiva['mesActual']     = $mesActual;
                    $datosResponsiva['idMembresia']   = $datos['generales'][0]->idMembresia;
                    $datosResponsiva['titular']       = $CI->persona_model->nombre($datos['idTitular']['idPersona']);
                    $datosResponsiva['digital']       = $CI->digital_model->validaAutorizacionDigital($idUnicoMembresia) ? 1 : 0;
                    $datosResponsiva['fechaRegistro'] = date('Y-m-d');

                    $datosResponsiva['logo']             = $dat['logo'];
                    $datosResponsiva['razonSocial']      = $dat['razonSocial'];
                    $datosResponsiva['clubes']           = $dat['clubes'];
                    $datosResponsiva['responsable']      = $dat['responsable'];
                    $datosResponsiva['firmaResponsable'] = $dat['firmaResponsable'];

                    $html = $this->load->view('documentos/HTML/'.TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_SOBRINO.'_'.$datosUn['idOperador'], $datosResponsiva, true);
                    $datosRes = $CI->digital_model->guardaDocumentoDigital($idPersona, TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_SOBRINO, '', $html, 0, '', 'Frente', $idUnicoMembresia, 1, 0, 0);
                    $documento = $datosRes['idDocumento'];
                    $datos['id'][]           = TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_SOBRINO;
                    $datos['doc'][]          = $documento;
                    $datos['concepto'][]     = $CI->documentos_model->nombreTipoDocumento(TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_SOBRINO);
                    $json['idDocResponsiva'] = $documento;
                }
            }
         }
         if ($edad < 18 and $datosTipoMem['idTipoMembresia'] == TIPO_MEMBRESIA_GRUPAL) {
            if (file_exists(verificaRuta(RUTA_LOCAL.'/system/application/views/documentos/HTML/'.TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_GRUPAL.'_'.$datosUn['idOperador'].'.php'))) {
                if ($edad < 18) {
                    $datosResponsiva['tipoMembresia'] = $tipoMembresia;
                    $datosResponsiva['nuevoSocio']    = $dat['nombreSocio'];
                    $datosResponsiva['meses']         = $meses;
                    $datosResponsiva['mesActual']     = $mesActual;
                    $datosResponsiva['idMembresia']   = $datos['generales'][0]->idMembresia;
                    $datosResponsiva['titular']       = $CI->persona_model->nombre($datos['idTitular']['idPersona']);
                    $datosResponsiva['bloqueoMail']   = $datosPersona['bloqueo'];
                    $datosResponsiva['digital']       = $CI->digital_model->validaAutorizacionDigital($idUnicoMembresia) ? 1 : 0;
                    $datosResponsiva['fechaRegistro'] = date('Y-m-d');

                    $datosResponsiva['logo']             = $dat['logo'];
                    $datosResponsiva['razonSocial']      = $dat['razonSocial'];
                    $datosResponsiva['clubes']           = $dat['clubes'];
                    $datosResponsiva['responsable']      = $dat['responsable'];
                    $datosResponsiva['firmaResponsable'] = $dat['firmaResponsable'];
                    $datosResponsiva['direccionSocial']  = $dat['direccionSocial'];

                    $html = $this->load->view('documentos/HTML/'.TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_GRUPAL.'_'.$datosUn['idOperador'], $datosResponsiva, true);
                    $datosRes = $CI->digital_model->guardaDocumentoDigital($idPersona, TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_GRUPAL, '', $html, 0, '', 'Frente', $idUnicoMembresia, 1, 0, 0);
                    $documento = $datosRes['idDocumento'];
                    $datos['id'][]           = TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_GRUPAL;
                    $datos['doc'][]          = $documento;
                    $datos['concepto'][]     = $CI->documentos_model->nombreTipoDocumento(TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_GRUPAL);
                    $json['idDocResponsiva'] = $documento;
                }
            }
         }
         if ( ! $datos['generales'][0]->idConvenioDetalle and ($idTipoSocio == ROL_CLIENTE_COTITULAR or $idTipoSocio == ROL_CLIENTE_COTITULAR_GRUPAL) and strtotime($datos['generales'][0]->fechaRegistro) >= strtotime(date('2014-05-01')) and $datos['generales'][0]->idUn != 68 and $datos['generales'][0]->idUn != 7) {
            if (file_exists(verificaRuta(RUTA_LOCAL.'/system/application/views/documentos/HTML/'.TIPO_DOCUMENTO_IRL.'.php'))) {
                $this->load->library('documentos');
                $this->documentos->formatoIRL($idUnicoMembresia);
            }
         }
         $datosView = json_encode_sw($json);
         return $datosView;
    }

    /*
     * Genera PDf de lta de cargo automatico
     *
     * @author  Antonio Sixtos
     *
     * @return int
     */
    public function generaPdfAltaCargoAutomatico($idPersona, $idUnicoMembresia, $persona, $digital = 0)
    {
        settype($idPersona, 'integer');
        settype($idUnicoMembresia, 'integer');
        settype($persona, 'integer');

        $ci =& get_instance();
        $ci->load->model('membresia_model');
        $ci->load->model('persona_model');
        $ci->load->model('un_model');
        $ci->load->model('empleados_model');
        $ci->load->model('mantenimientos_model');
        $ci->load->model('documentos_model');
        $ci->load->model('digital_model');
        $ci->load->model('operadores_model');

        $datos['idPersona']        = $idPersona;
        $datos['idUnicoMembresia'] = $idUnicoMembresia;
        $datos['persona']          = $persona;
        /*******Creacion del archivo HTML-pdf******/
        $doc['tipoEmpresa']=$this->tipoEmpresa($datos['idPersona']);

        $datos['idTitular'] = $ci->membresia_model->obtenerTitular($datos['idUnicoMembresia']);
        $titular = $datos['idTitular']['idPersona'];
        $club = $ci->membresia_model->club($datos['idUnicoMembresia']);
        $tm = $ci->membresia_model->obtenerTipoMembresia($datos['idUnicoMembresia']);
        $idEmpresaGrupo = $ci->un_model->obtenerEmpresaGrupo($club);

        $dat['nombreTitular'] = $ci->persona_model->nombre($titular);

        $d = $ci->membresia_model->obtenerDatosGeneralesMem($datos['idUnicoMembresia']);
        foreach($d as $fila) {
            $dat['idMembresia']     = $fila->idMembresia;
            $dat["idTipoMembresia"] = $fila->idTipoMembresia;
            $dat["idTipoFidelidad"] = $fila->idTipoFidelidad;
        }

        $dat['nombreUnidad'] = $ci->un_model->nombre($club);
        $datosUn = $ci->un_model->obtenDatosUn($club);
        $domicilio = $ci->socio_model->datosDomicilioTitular($datos['idUnicoMembresia']);
        if ($domicilio > 0 ) {
            foreach ($domicilio as $fila1) {
                $dato['calle']     = $fila1->calle;
                $dato['numero']    = $fila1->numero;
                $dato['colonia']   = $fila1->colonia;
                $dato['estado']    = $fila1->estado;
                $dato['municipio'] = $fila1->municipio;
                $dato['cp']        = $fila1->cp;
            }
            $dat['domicilio'] = $dato['calle']." #".$dato['numero']." ".$dato['colonia']." C.P.".$dato['cp'].", ".$dato['municipio'].", ".$dato['estado'];
        } else {
            $dat['domicilio'] = '';
        }

        $dat['domicilio'] = strtoupper($dat['domicilio']);

        $dat['cargoPrimeros'] = 0;
        $dat['cargoUltimos'] = 0;

        $dt = $this->obtenDatosTarjeta($datos['idUnicoMembresia'], $titular);
        foreach ($dt as $fila2){
            $dat['banco']            = $fila2->banco;
            $dat['numeroTarjetaCta'] = $fila2->numeroTarjetaCta;
            $dat['nombreTarjeta']    = $fila2->nombreTarjeta;
            $dat['tipoTarjeta']      = $fila2->tipoTarjeta;
            $dat['mesExpiracion']    = $fila2->mesExpiracion;
            $dat['anioExpiracion']   = $fila2->anioExpiracion;
            $dat['diaCargo']         = $fila2->diaCargo;
        }
        $idMantenimiento = $ci->mantenimientos_model->activoFecha($datos['idUnicoMembresia'], date('Y-m-d H:i:s'));

        $z1 = 0;
        $z2 = 0;
        $iA1 = 0;
        $iA2 = 0;
        $idPers = $this->obtenSocios($idUnicoMembresia);
        $numIntegrantes = count($idPers);
        $nI = 0;
        foreach ($idPers as $renglones) {
            if ($renglones->idTipoRolCliente!=ROL_CLIENTE_AGREGADO and $renglones->idTipoRolCliente != ROL_CLIENTE_2X1) {
                if ($renglones->idTipoRolCliente!=ROL_CLIENTE_BEBE) {
                    $nI++;
                }
                $contI = $nI;
                if ($dat["idTipoMembresia"]>=TIPO_MEMBRESIA_GRUPAL) {
                    $contI = 0;
                }
                $fechaMtto = date('Y-m').'-01';
                //if (strtotime($datosUn['fechaApertura']) > strtotime(date('Y-m-d')) and strtotime($datosUn['fechaApertura'])) {
                //    $fechaMtto = date("Y-m", strtotime($datosUn['fechaApertura']))."-01";
                //}
                $y1 = $ci->mantenimientos_model->precioMttoDatos2(
                    $renglones->idPersona,
                    ESQUEMA_PAGO_CARGOAUTOMATICO,
                    $dat['idMembresia'],
                    $club,
                    $fechaMtto,
                    $renglones->idMantenimiento,
                    0,
                    0, //$contI,
                    $dat["idTipoFidelidad"]
                );

                $y2 = $ci->mantenimientos_model->precioMttoDatos2(
                    $renglones->idPersona,
                    ESQUEMA_MENSUAL,
                    $dat['idMembresia'],
                    $club,
                    $fechaMtto,
                    $renglones->idMantenimiento,
                    0,
                    0, //$contI,
                    $dat["idTipoFidelidad"]
                );

                $z1 += floatval($y1['importe']);
                $z2 += floatval($y2['importe']);
            }
        }

        if ( (int)$z1 > 0 ) {
            $dat['cargoP']        = number_format($z1, 2, '.', ',');
            $dat['cargoU']        = number_format($z2, 2, '.', ',');
            $dat['nombreGerente'] = $ci->un_model->obtenGerenteGeneral($club);
            if (isset($dat['nombreGerente']['nombre'])) {
                $dat['nombreGerente'] = $dat['nombreGerente']['nombre'];
            } else {
                $dat['nombreGerente'] = 'GERENTE GENERAL DEL CLUB';
            }
            /**********************************/
            $movimiento = 17;

            $fechas = $this->obtenFechasUn($club);
            foreach ($fechas as $fila3) {
                $f['fechaApertura'] = $fila3->fechaApertura;
                $f['fechaPreventa'] = $fila3->fechaPreventa;
            }

            if ($f['fechaApertura']!='0000-00-00' && $f['fechaPreventa']!='0000-00-00' ) {
                $ahora = time();
                $apertura = strtotime($f['fechaApertura']);
                $preventa = strtotime($f['fechaPreventa']);

                if ($preventa<$ahora && $ahora<$apertura) {
                    if ($this->session->userdata('idUn')!=$club) {
                        $fin = 2;
                    } else {
                        $fin = 1;
                    }
                } else {
                    $fin = 0;
                }
            } else {
                $fin = 0;
            }
            $dat['digital']          = $ci->digital_model->validaAutorizacionDigital($datos['idUnicoMembresia']) ? 1 : 0;
            $datosOperador           = $ci->operadores_model->obtenOperadorInfo($datosUn['idOperador']);
            $dat['logo']             = $datosOperador[0]['logo'];
            $dat['razonSocial']      = $datosOperador[0]['razonSocial'];
            $dat['clubes']           = $datosOperador[0]['clubes'];
            $dat['responsable']      = $datosOperador[0]['responsable'];
            $dat['firmaResponsable'] = $datosOperador[0]['firmaResponsable'];

            if (file_exists(verificaRuta(RUTA_LOCAL.'/system/application/views/socio/HTML/alta_cargoautomatico_'.$idEmpresaGrupo.'.php'))) {
                $html = $this->load->view('socio/HTML/alta_cargoautomatico_'.$idEmpresaGrupo, $dat, true);
                $datosRes = $ci->digital_model->guardaDocumentoDigital($titular, TIPO_DOCUMENTO_ALTA_CARGO_AUTOMATICO, '', $html, 0, '', 'Frente', $datos['idUnicoMembresia'], 1, $dat['digital'], 0);
                $idDocumento = $datosRes['idDocumento'];
                $uno = $idDocumento.'::'.$doc['tipoEmpresa'].'::'.$datos['idPersona'].'::'.$movimiento;
                return $uno;
            }
        } else {
            $movimiento = 17;
            $dat['digital'] = $ci->digital_model->validaAutorizacionDigital($datos['idUnicoMembresia']) ? 1 : 0;

            $datosRes = $ci->digital_model->guardaDocumentoDigital(
                $titular,
                TIPO_DOCUMENTO_ALTA_CARGO_AUTOMATICO,
                '',
                '<center><p><br><br><br><br><br><br><br><br><h1>Error al obtener precio de mantenimiento</h1><p><br><br><br><br><br><br><br><br></center>',
                0,
                '',
                'Frente',
                $datos['idUnicoMembresia'],
                1,
                $dat['digital'],
                0
            );

            return;
        }
    }

    /**
     * [guardaDatosTarjeta description]
     *
     * @param  [type] $datos [description]
     *
     * @return [type]        [description]
     */
    function guardaDatosTarjeta($datos)
    {
        $sql1 = "CALL crm.spGuardaDatosCAT(".$datos['idUnicoMembresia'].", '".$datos['datosTarjeta']."', ".$datos['idEsquemaPago'].",".$this->db->escape(utf8_decode($datos['motivoCanc'])).",".$datos['idTipoDocumento'].",".$datos['idConvenioDetalle'].",".$datos['idpersonasess'].",".$datos['idusuariosess'].", ".$datos['guardaocancela'].", ".$datos['procedencia'].", @respuesta)";

        $query1 = $this->db->query($sql1);
        $sql2 = "SELECT IF((@respuesta IS NOT NULL OR @respuesta!=0),1,0)  AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * Guarda el precio especial por socio
     *
     * @author Santa Garcia
     *
     * @return integer
     */
    function guardaPrecioEspecial($idSocio, $idUnicoMembresia, $inicio, $fin, $precio=0, $descuento=0, $rolCliente, $mantenimiento, $idEsquemaPago)
    {
        settype($idSocio,'integer');
        settype($rolCliente,'integer');
        settype($idUnicoMembresia,'integer');
        settype($precio,'float');
        settype($descuento,'float');
        settype($mantenimiento,'integer');
        settype($idEsquemaPago,'integer');
        $ci =& get_instance();
        $ci->load->model('empleados_model');

        $datos = array(
           'idSocio'          => $idSocio,
           'idTipoRolCliente' => $rolCliente,
           'idMantenimiento'  => $mantenimiento,
           'idEsquemaPago'    => $idEsquemaPago,
           'idPersona'        => $ci->empleados_model->obtenIdEmpleado($this->session->userdata('idPersona')),
           'fechaInicio'      => $inicio,
           'fechaFin'         => $fin,
           'importe'          => $precio,
           'porcentaje'       => $descuento
        );

        $data = array(
           'idPersona'   => $ci->empleados_model->obtenIdEmpleado($this->session->userdata('idPersona')),
           'fechaInicio' => $inicio,
           'fechaFin'    => $fin,
           'importe'     => $precio,
           'porcentaje'  => $descuento
        );

        $this->db->select('idSocioPrecioMtto');
        $this->db->from(TBL_SOCIOPRECIOMTTO);
        $this->db->where('idSocio', $idSocio,'idTipoRolCliente', $rolCliente,'idMantenimiento', $mantenimiento);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idSocioPrecioMtto', $fila['idSocioPrecioMtto']);
            $this->db->update(TBL_SOCIOPRECIOMTTO, $data);
            $this->permisos_model->log(utf8_decode("Se actualiz� precio espacial para el socio ($idSocio)"), LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        } else {
            $this->db->insert(TBL_SOCIOPRECIOMTTO, $datos);
            $this->permisos_model->log(utf8_decode("Se insert� precio espacial para el socio ($idSocio)"), LOG_MEMBRESIA, $idUnicoMembresia);
            return true;
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        }
    }

    /**
    *   FUNCION OBTENER CLUB
    *   @author Ruben Alcocer
    *
    *   @return idUN
    *
    **/
    function obtenerclub($idunicomembresia)
    {
        $query = $this->db->query('select m.idUn from membresia m where m.idUnicoMembresia='.$idunicomembresia.' LIMIT 1;');
        $row = $query->row();
        return $row->idUn;
    }

    /**
     * funcion para guardar nuevas personas que se anexen a una membresia
     *
     * @author Antonio Sixtos
     *
     * @return array data
     */
    function guardaNuevoSocio($datos)
    {
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $ci->load->model('membresia_model');
        $this->load->model('socio_model');
        if(!isset($datos['idTipoPaquete'])) {
            $datos['idTipoPaquete'] = 0;
        }
        if(!isset($datos['memnueva'])) {
            $datos['memnueva'] = 0;
        }
        if(!isset($datos['fechamtto'])) {
            $datos['fechamtto'] = date('Y-m-d');
        }
        if(!isset($datos['idEsquemaPago'])) {
            $datos['idEsquemaPago'] = '0';
        }
        if(!isset($datos['idMantenimiento'])) {
            $datos['idMantenimiento'] = '0';
        }

        $info = array(
            $datos['idUnicoMembresia'],
            $datos['idPersona'],
            $datos['idTipoSocio'],
            $datos['idMantenimiento'],
            $datos['idEsquemaPago'],
            $datos['fechamtto'],
            $this->session->userdata('idUsuario'),
            $this->session->userdata('idPersona'),
            $datos['memnueva'],
            $datos['idTipoPaquete']
        );

        $sql1 = "CALL crm.spGuardarNuevoSocio(?, ?, ?, ?, ?, ?, ?, ?, ?, 0, @respuesta, ?)";
        $query1 = $this->db->query($sql1, $info);

        $sql2 = "SELECT @respuesta AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();

        $movimientos = $this->insertaCargoCredencial($datos);

        #   ENVIO A SS
        if($ci->persona_model->edad($datos['idPersona']) < 15){
            $tipoMembresia = $ci->membresia_model->obtenerTipoMembresia($datos['idUnicoMembresia']);
            if($tipoMembresia['idProducto'] == 7 || $tipoMembresia['idProducto'] == 43){
                $origen = 'mf';
            }else if($tipoMembresia['idProducto'] == 1 ){
                $origen = 'fk';
            }
            $idUn = $this->obtenerclub($datos['idUnicoMembresia']);       #       No existe la funcion
            $personaTitular = $ci->membresia_model->obtenerTitular($datos['idUnicoMembresia']);

            $idMembresia=$ci->membresia_model->getIdMembresia($datos['idUnicoMembresia']);
            $socioSafe = $this->socio_model->AddBoySafeSplash($personaTitular['idPersona'],$datos['idPersona'],$origen,$idUn,'yes',$idMembresia);
        }
        return $row->resp;
    }

    /**
     * [guardaRespuestaMotivoBaja description]
     * @param  [type]  $unico                     [description]
     * @param  [type]  $idSocio                   [description]
     * @param  [type]  $idPersona                 [description]
     * @param  [type]  $idOrigenTramite           [description]
     * @param  [type]  $idTipoTramite             [description]
     * @param  [type]  $idRelacionRespuesta       [description]
     * @param  [type]  $idRespuestaUsuario        [description]
     * @param  [type]  $idRespuestaUsuarioDetalle [description]
     * @param  [type]  $motivo                    [description]
     * @param  integer $bandera                   [description]
     * @return [type]                             [description]
     */
    function guardaRespuestaMotivoBaja($unico, $idSocio, $idPersona, $idOrigenTramite, $idTipoTramite, $idRelacionRespuesta,
            $idRespuestaUsuario, $idRespuestaUsuarioDetalle, $motivo, $bandera=1)
    {
        settype($unico, 'integer');
        settype($idSocio, 'integer');
        settype($idOrigenTramite, 'integer');
        settype($idTipoTramite, 'integer');
        settype($idRelacionRespuesta, 'integer');
        settype($idRespuestaUsuario, 'integer');
        settype($idRespuestaUsuarioDetall3, 'integer');
        settype($bandera, 'integer');

        $sql1 = "CALL crm.spGuardaRespuestaMotivoBaja(".$unico.", '".$idSocio."', ".$idPersona.",".$idOrigenTramite.",".$idTipoTramite.",".$idRelacionRespuesta.",".$idRespuestaUsuario.",".$idRespuestaUsuarioDetalle.", '".$motivo."', ".$bandera.",  @respuesta)";
        $query1 = $this->db->query($sql1);
        $sql2 = "SELECT @respuesta AS resp";
        $query2 = $this->db->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * Replica los datos de la tarjeta del titular en el nuevo socio
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    function guardarDatosTarjetaNuevoSocio($idSocio, $idNuevoSocio)
    {
        settype($idSocio, 'integer');
        settype($idNuevoSocio, 'integer');

        $sql = "INSERT INTO  sociodatostarjeta (idSocio,idBanco, numeroTarjetaCta, nombreTarjeta, tipoPago, TipoTarjeta,
                mesExpiracion, anioExpiracion, diaCargo, motivoCancelacion, activo)
            SELECT '".$idNuevoSocio."',idBanco, numeroTarjetaCta, nombreTarjeta, tipoPago,
                TipoTarjeta, mesExpiracion, anioExpiracion, diaCargo, motivoCancelacion, activo
            FROM sociodatostarjeta
            WHERE idSocio='".$idSocio."' and fechaEliminacion='0000-00-00 00:00:00'";
        $query=$this->db->query($sql);
        $this->permisos_model->log('Replica los datos de la tarjeta del titular', LOG_SISTEMAS);
        if ($this->db->affected_rows() > 0) {
           $res=1;
        } else {
           $res=0;
        }

       return $res;
    }

    /**
     * Actualiza motivo cancelacion en el ultimo reguistro insertado del idSocio
     *
     * @param integer $idSocio
     * @param string  $motivoCanc
     *
     * @return integer
     */
    function insertaMotivoCancelacion ($idSocio = 0,$motivoCanc = '', $id = 0, $idConvenioDetalle = 0)
    {
        $m = '';
        $motivoCanc = utf8_decode($motivoCanc);

        if ($idConvenioDetalle) {
            $sql = "update ".TBL_CONVENIODATOSTARJETA." set motivoCancelacion='".$motivoCanc."'
                  where fechaEliminacion='0000-00-00 00:00:00' and idConvenioDatosTarjeta=".$id;
            $this->permisos_model->log('Se cancela tarjeta asociada a convenio', LOG_SISTEMAS);
        } else {
            if ($id == 0) {
                $m = ' and idSocio='.$idSocio;
            } else {
                $m = ' and idSocioDatosTarjeta='.$id;
            }
            $sql = "update ".TBL_SOCIODATOSTARJETA." set motivoCancelacion='".$motivoCanc."'
                  where fechaEliminacion='0000-00-00 00:00:00' $m ";
            $this->permisos_model->log('Se cancela tarjeta asociada a socio', LOG_SISTEMAS);
        }
        $query=$this->db->query($sql);


        if ($this->db->affected_rows() > 0) {
            if ($id == 0) {
                $z = 'sdt.idSocio IN ('.$idSocio.')';
            } else {
                $z = 'sdt.idSocioDatosTarjeta IN ('.$id.')';
            }
            $query2 = $this->db->query('SELECT sdt.idSocioDatosTarjeta FROM crm.sociodatostarjeta sdt WHERE '.$z.' ORDER BY sdt.fechaActualizacion DESC LIMIT 1;');
            $row = $query2->row();
            return $row->idSocioDatosTarjeta;
            #return $data=1;
        } else {
           return false;
        }
    }

    /**
     * Inserta un pago mtto
     *
     * @param array $datos Array con datos del movimiento para pagomtto
     *                       fechaInicio Fecha de inicio de mantenimiento
     *                       fechaFin    Fecha de fin de mantenimiento
     *                       descripcion Descripcion del pago mtto a ingresar
     *                       importe     Importe del del pago mtto
     *                       iva         Iva
     *                       membresia   Identificador unico de membresia
     *                       producto    Identificador de producto
     *                       persona     Identificado de persona a la cual aplica el movimiento
     *                       origen      Descripcion corta de origen del movimiento
     *
     * @return integer
     */
    public function insertaPagoMtto($datos, $ausencia = false)
    {
        if (isset($datos['fechaInicio'])) {
            settype($datos['fechaInicio'], 'string');
        } else {
            $datos['fechaInicio'] = "";
        }

        if (isset($datos['fechaFin'])) {
            settype($datos['fechaFin'], 'string');
        } else {
            $datos['fechaFin'] = "";
        }

        if (isset($datos['idMovimiento'])) {
            settype($datos['idMovimiento'], 'integer');
        } else {
            $datos['idMovimiento'] = 0;
        }
        if (isset($datos['idPersona'])) {
            settype($datos['idPersona'], 'integer');
        } else {
            $datos['idPersona'] = 0;
        }
        if (isset($datos['idEsquemaPago'])) {
            settype($datos['idEsquemaPago'], 'integer');
        } else {
            $datos['idEsquemaPago'] = 0;
        }

        if (isset($datos['idUnicoMembresia'])) {
            settype($datos['idUnicoMembresia'], 'integer');
        } else {
            $datos['idUnicoMembresia'] = 0;
        }
        if (isset($datos['idMantenimiento'])) {
            settype($datos['idMantenimiento'], 'integer');
        } else {
            $datos['idMantenimiento'] = 0;
        }
        if (isset($datos['activo'])) {
            settype($datos['activo'], 'integer');
        } else {
            $datos['activo'] = 0;
        }
        if (isset($datos['origen'])) {
        } else {
            $datos['origen'] = '';
        }
        $ci =& get_instance();
        $ci->load->model('membresia_model');
        $ci->load->model('un_model');
        $datos['idSocio']=$this->obtenIdSocio($datos["idPersona"],$datos['idUnicoMembresia']);
        $datosGral=$ci->membresia_model->obtenerDatosGeneralesMem($datos['idUnicoMembresia']);
        $club=$ci->un_model->nombre($datosGral[0]->idUn);
        $membresia=$datosGral[0]->idMembresia;
        if (isset ($datos["porcentaje"])) {
        } else {
            $datos["porcentaje"] = 100;
        }
        $valores = array (
            'fechaInicio'      => $datos["fechaInicio"],
            'fechaFin'         => $datos["fechaFin"],
            'idMovimiento'     => $datos["idMovimiento"],
            'idPersona'        => $datos["idPersona"],
            'idEsquemaPago'    => $datos["idEsquemaPago"],
            'idUnicoMembresia' => $datos["idUnicoMembresia"],
            'idMantenimiento'  => $datos["idMantenimiento"],
            'activo'           => $datos["activo"],
            'origen'           => $datos["origen"],
            'porcentaje'       => $datos["porcentaje"],
            'idSocio'          => $datos["idSocio"]
        );

        $this->db->insert(TBL_SOCIOPAGOMTTO, $valores);
        $idPagoMtto = $this->db->insert_id();

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return (-5);
        }
        if ($ausencia) {
            $logMensaje = ("Proporcional por regreso de ausencia (".$membresia."  del club ".$club.")");
        } else {
            $logMensaje = ("Proporcional por reactivación de Membresía([".$datos['fechaInicio']."]-[".$datos["fechaFin"]."])(".$datos['idPersona'].")(".$membresia."  del club ".$club.")");
        }
        $this->permisos_model->log(utf8_decode($logMensaje), LOG_MEMBRESIA, $datos['idUnicoMembresia']);
        return $idPagoMtto;
    }

    /**
     * Inserta socio apago mtto disponible en la membresia y con estatus de activo
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function insertaSocioPagoMttoDisponible($idUnicoMembresia, $idPersona, $idSocio, $idMantenimiento)
    {
        settype($idUnicoMembresia, 'Integer');
        settype($idPersona, 'Integer');
        settype($idSocio, 'Integer');

        $CI =& get_instance();
        $CI->load->model('persona_model');

        $nombre = $CI->persona_model->nombre($idPersona);

        $this->db->select('idSocioPagoMtto');
        $this->db->from(TBL_SOCIOPAGOMTTO);

        $where = array('idUnicoMembresia' => $idUnicoMembresia,'idPersona'=>0,'activo'=>1,'fechaEliminacion'=>'0000-00-00 00:00:00');

        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idSocioPagoMtto', $fila->idSocioPagoMtto);
                if($idMantenimiento != 0){
                    $datos = array ('idPersona' => $idPersona,'idSocio' => $idSocio,'idMantenimiento' => $idMantenimiento);
                } else {
                    $datos = array ('idPersona' => $idPersona,'idSocio'=>$idSocio);
                }
                $this->db->update(TBL_SOCIOPAGOMTTO, $datos);
                break;
            }
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se registro en pago de mantenimiento a ('.$nombre.')('.date('Y-m-d').')', LOG_MEMBRESIA,$idUnicoMembresia);
            return true;
        }
    }

    /**
    * Obtiene la configuracion de edades por tipo de mantenimiento y concepto seleccionado
    *
    * @param integer $idTipoSocio identificador tipo rol cliente
    * @param integer $idClub      identificador de la unidad de negocio
    * @param integer $idProducto  identificador del producto
    *
    * @author Santa Garcia
    *
    * @return array
    */
    public function listaConfiguracionEdad($idTipoSocio,$idClub, $idProducto)
    {
        $this->db->select('idMantenimiento');
        $this->db->from(TBL_PRODUCTOMANTENIMIENTO);
        $where=array('idProducto'=>$idProducto);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $fila2) {
                $idMantenimiento = $fila2['idMantenimiento'];
            }
        }
        $this->db->select('idMantenimientoCliente,idTipoRolCliente,idUn,edadMinima,edadMaxima');
        $where=array('fechaEliminacion'=>'0000-00-00 00:00:00');
        $this->db->where($where);
        $this->db->from(TBL_MANTENIMIENTOCLIENTE);

        if ($idMantenimiento!=0) {
            $this->db->where('idMantenimiento', $idMantenimiento);
        }
        $this->db->where('idUn', $idClub);
        $this->db->where('idTipoRolCliente', $idTipoSocio);
        $this->db->where('activo', 1);
        $this->db->where('edadMinima >=', 0);
        $this->db->where('edadMaxima >=', 0);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $fila) {
                $datos = $fila;
            }
            return $datos;
        } else {
            return null;
        }
    }

    /**
     * lista los datos con cargo automatico de una persona
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaDatosCargoAutomatico($idUnicoMembresia,$idPersona,$idSocioDatosTarjeta=0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPersona, 'integer');

        $this->db->select('st.activo,st.tipoPago,st.nombreTarjeta,s.idPersona, st.idSocioDatosTarjeta AS id,st.numeroTarjetaCta, b.descripcion, st.diaCargo, b.idBanco, st.mesExpiracion, st.anioExpiracion', false);
        $this->db->from(TBL_SOCIODATOSTARJETA.' st');
        $this->db->join(TBL_SOCIO.' s', 's.idSocio=st.idSocio');
        $this->db->join(TBL_MEMBRESIA .' m', 'm.idUnicoMembresia=s.idUnicoMembresia');
        $this->db->join(TBL_BANCO .' b', 'b.idBanco=st.idBanco');
        if ($idSocioDatosTarjeta != 0) {
            $this->db->where('st.idSocioDatosTarjeta', $idSocioDatosTarjeta);
        } else {
            $this->db->where('m.idUnicoMembresia', $idUnicoMembresia);
            $this->db->where('s.idPersona', $idPersona);
        }
        $this->db->where('m.eliminado', 0);
        $this->db->where('s.eliminado', 0);
        $this->db->where('st.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return 0;
        }
    }

    /**
     * Lista estaus Socio
     *
     * @return array
     */
    function listaEstatusSocio($todos = false)
    {
        $data = array();

        if ($todos) {
            $data[] = 'Seleccione';
        } else {
            $this->db->where('activo',1);
        }
        $this->db->select('idTipoEstatusSocio, descripcion');
        $this->db->from(TBL_TIPOESTATUSSOCIO);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idTipoEstatusSocio] = $fila->descripcion;
            }
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Lista estaus Socio
     *
     * @return array
     */
    function listaEstatusSocioDirectorio($todos = false)
    {
        $data = array();

        $sql = "SELECT idEstatusEstadistica, nombre
            FROM crm_estadisticas.estatusestadistica
            WHERE idEstatusEstadistica IN (1,2,4,5,15,17)";
        $query=$this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idEstatusEstadistica] = $fila->nombre;
            }
            return $data;
        } else {
            return false;
        }
    }

    /**
     *
     * @return type
     */
    function listaFormaPagoSocioDirectorio()
    {
        $data = array();

        $sql="SELECT idEsquemaPago, descripcion
        FROM ".TBL_ESQUEMAPAGO." WHERE activo=1 AND idEsquemaPago IN (2,6)";
        $query=$this->db->query($sql);
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idEsquemaPago] = $fila->descripcion;
            }
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Lista estaus Socio
     *
     * @return array
     */
    function listaMantenimientoSocioDirectorio($idUn=0)
    {
        $data = array();

        $sql="SELECT pm.idmantenimiento, pr.nombre
        FROM producto pr
            INNER JOIN productomantenimiento pm ON pm.idproducto=pr.idproducto";
        if ($idUn!=0) {
             $sql.=" INNER JOIN productoun pu ON pu.idproducto=pr.idproducto";
        }
        $sql.=" WHERE pr.activo=1 AND pr.fechaEliminacion='0000-00-00 00:00:00'";
        if ($idUn!=0) {
             $sql.=" AND pu.idUn IN (".$idUn.")";
        }
        $sql.=" GROUP BY pr.idProducto";
        $query=$this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idmantenimiento] = $fila->nombre;
            }
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Lista estaus Socio
     *
     * @return array
     */
    function listaMembresiaSocioDirectorio($idUn=0)
    {
        $data = array();

        $sql="SELECT pr.idProducto, pr.nombre FROM producto pr";
        if ($idUn!=0) {
            $sql.=" JOIN productoun pu ON pu.idproducto=pr.idproducto";
        }
        $sql.=" WHERE pr.idcategoria IN (6, 8, 9) AND pr.activo=1";
        if ($idUn!=0) {
            $sql.=" AND pu.idUn IN (".$idUn.")";
        }
        $sql.=" GROUP BY pr.idProducto";
        $query=$this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idProducto] = $fila->nombre;
            }
            return $data;
        } else {
            return $data;
        }
    }

    /**
     *
     * @param type $opciones
     * @param type $totales
     * @param int $posicion
     * @param type $registros
     * @param type $orden
     * @return type
     */
    function listaSociosClub($opciones, $totales = 0, $posicion = 0, $registros = REGISTROS_POR_PAGINA, $orden = '')
    {
        set_time_limit(0);

        $uno = array("*");
        $dos   = array("%");
        $nombre = str_replace($uno, $dos, $opciones['nombre']);

        $data = Array();

        $sql = "SELECT
            u. nombre AS Club, u.clave, et.idMembresia, p.idPersona, CONCAT_WS(' ', p.nombre, p.paterno, p.materno) AS Nombre,
            trc.descripcion AS 'TipoSocio', p1.nombre AS 'TipoMembresia', tm.descripcion AS 'CategoriaMembresia',
            p2.nombre AS 'TipoMantenimiento', ep.descripcion AS 'FormaPago', ee.nombre AS 'Estatus', CONCAT_WS('<br />',
                   (SELECT tel.telefono FROM telefono  tel WHERE tel.idPersona=p.idPersona AND tel.eliminado=0 AND tel.idTipoTelefono=30 LIMIT 1),
                   (SELECT tel.telefono FROM telefono  tel WHERE tel.idPersona=p.idPersona AND tel.eliminado=0 AND tel.idTipoTelefono=31 LIMIT 1),
                   (SELECT tel.telefono FROM telefono  tel WHERE tel.idPersona=p.idPersona AND tel.eliminado=0 AND tel.idTipoTelefono=32 LIMIT 1),
                   (SELECT tel.telefono FROM telefono  tel WHERE tel.idPersona=p.idPersona AND tel.eliminado=0 AND tel.idTipoTelefono=33 LIMIT 1)
            ) AS 'Telefono'
               , CONCAT_WS('<br />',
                   (SELECT ma.mail FROM mail  ma WHERE ma.idPersona=p.idPersona AND ma.eliminado=0 AND ma.bloqueoMail=0 AND ma.idTipoMail=3 LIMIT 1),
                   (SELECT ma.mail FROM mail  ma WHERE ma.idPersona=p.idPersona AND ma.eliminado=0 AND ma.bloqueoMail=0 AND ma.idTipoMail=34 LIMIT 1),
                   (SELECT ma.mail FROM mail  ma WHERE ma.idPersona=p.idPersona AND ma.eliminado=0 AND ma.bloqueoMail=0 AND ma.idTipoMail=35 LIMIT 1)

            ) AS 'Mail'
                , tf.descripcion AS tipoFidelidad
                , mf.mesesConsecutivos
                , con.nombre AS convenioCorp
                , et.fechaInicio AS inicioMtto
                , et.fechaFin AS finMtto
            FROM crm_estadisticas.estadisticatemporal et
            INNER JOIN crm.membresiafidelidad mf ON mf.idUnicoMembresia=et.idUnicoMembresia AND mf.fechaEliminacion='0000-00-00 00:00:00'";
        if ($opciones['fidelidad']!='' ) {
            $sql.=" AND  mf.idTipoFidelidad IN (".$opciones['fidelidad'].")";
        }
        $sql .= " INNER JOIN crm.tipofidelidad tf ON tf.idTipoFidelidad=mf.idTipoFidelidad
            INNER JOIN crm.un u ON u.idUn=et.idUn
            INNER JOIN crm.empresa emp ON emp.idEmpresa=u.idEmpresa ";
        if ($opciones['idUn']==0 ) {
            $sql .= " AND emp.idEmpresaGrupo=".$this->session->userdata('idEmpresaGrupo');
        }
        if ($opciones['idEmpresa']!=0 ) {
            $sql.=" AND  u.idEmpresa IN (".$opciones['idEmpresa'].")";
        }
        if ($opciones['idUn']!=0 ) {
            $sql.=" AND  u.idUn IN (".$opciones['idUn'].")";
        }
        $sql.=" INNER JOIN crm.persona p ON p.idPersona=et.idPersona";
        if ($opciones['idPersona']!='') {
            $sql.=" AND p.idPersona=".$opciones['idPersona'];
        }
        if ($opciones['nombre']!='') {
            $sql.=" AND  CONCAT_WS(' ',p.nombre, p.paterno, p.materno) LIKE '%".$nombre."%'";
        }
        $sql.=" INNER JOIN crm.tiporolcliente trc ON trc.idTipoRolCliente = et.idTipoRolCliente
            INNER JOIN crm.producto p1 ON p1.idProducto = et.idProducto
            INNER JOIN crm.tipomembresia tm ON tm.idTipoMembresia=et.idTipoMembresia
            INNER JOIN crm.productomantenimiento pm ON pm.idMantenimiento=et.idMantenimiento
            INNER JOIN crm.producto p2 ON p2.idProducto=pm.idProducto
            INNER JOIN crm.esquemapago ep ON ep.idEsquemaPago=et.idEsquemaPago
            INNER JOIN crm_estadisticas.estatusestadistica ee ON ee.idEstatusEstadistica=et.idEstatusEstadistica
            INNER JOIN crm.conveniodetalle cd ON cd.idConvenioDetalle=et.idConvenioDetalle
            INNER JOIN crm.convenio con ON con.idConvenio=cd.idConvenio
            LEFT JOIN crm.membresiadigital md ON md.idUnicoMembresia = et.idUnicoMembresia AND md.fechaEliminacion='0000-00-00 00:00:00'
            WHERE
            et.fecha = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY))";
        if ($opciones['estatus']!='') {
            $sql.=" AND et.idEstatusEstadistica IN (".$opciones['estatus'].")";
        }
        if($opciones['membresia']!='') {
            $sql.=" AND et.idProducto IN (".$opciones['membresia'].")";
        }
        if($opciones['mantenimiento']!='') {
            $sql.=" AND et.idMantenimiento IN (".$opciones['mantenimiento'].")";
        }
        if($opciones['formapago']!='') {
            $sql.=" AND et.idEsquemaPago IN (".$opciones['formapago'].")";
        }
        if($opciones['corporativo']=='0') {
            $sql.=" AND et.idConvenioDetalle IN (".$opciones['corporativo'].")";
        }
        if($opciones['corporativo']=='1') {
            $sql.=" AND et.idConvenioDetalle  NOT IN (0)";
        }
        $sql.=" ORDER BY idMembresia DESC";
        if ($totales == 0) {
            if ($posicion == '') {
                $posicion = 0;
            }
            $sql.=" LIMIT ".$posicion.", ".$registros." ";
        }
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            if ($totales == 1) {
                return $query->num_rows();
            }
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return $query->num_rows();
            return $data;
        }
    }


    /**
     * Obtiene rol del cliente, edades, y nombre de la tabla tiporolcliente
     *
     * @param  [type] $idProducto               [description]
     * @param  [type] $idUn                     [description]
     * @param  [type] $idMantenimiento          [description]
     * @param  [type] $adultosact               [description]
     * @param  [type] $adultos                  [description]
     * @param  [type] $idUnicoMembresia         [description]
     * @param  [type] $agregados                [description]
     * @param  [type] $agregadoact              [description]
     * @param  [type] $integrantes              [description]
     * @param  [type] $AgregadosAdultos         [description]
     * @param  [type] $AgregadosAdultosActuales [description]
     *
     * @author Antonio Sixtos
     *
     * @return array data
     */
    public function listaTSocio($idProducto, $idUn, $idMantenimiento, $adultosact, $adultos, $idUnicoMembresia, $agregados, $agregadoact, $integrantes, $AgregadosAdultos, $AgregadosAdultosActuales)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        settype($idMantenimiento, 'integer');
        settype($idUnicoMembresia, 'integer');

        settype($adultosact, 'integer');
        settype($adultos, 'integer');
        settype($agregados, 'integer');
        settype($agregadoact, 'integer');
        settype($integrantes, 'integer');
        settype($AgregadosAdultos, 'integer');
        settype($AgregadosAdultosActuales, 'integer');

        $lista = array();
        $intactual = $this->cuentaSocios($idUnicoMembresia);

        $resta = $adultos-$adultosact;
        #$restagre = $agregados-($agregadoact+$AgregadosAdultosActuales);
        $restagre = $agregados-($agregadoact);
        #$sumatotal = $integrantes+$agregados;
        $sumatotal = $integrantes;#+$agregados;
        $restaint = $sumatotal-$intactual;
        $restAgregadosAdultos = $AgregadosAdultos-$AgregadosAdultosActuales;

        settype($resta, 'integer');
        settype($restagre, 'integer');
        settype($sumatotal, 'integer');
        settype($restaint, 'integer');

        $sql = "SELECT trc.idTipoRolCliente, trc.descripcion, mtto.edadMinima, mtto.edadMaxima
            FROM membresiaconfiguracion mc
            INNER JOIN productoun pu on pu.idProductoUn=mc.idProductoUn
            INNER JOIN membresiatiposocio mts on mts.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
            INNER JOIN tiporolcliente trc on trc.idTipoRolCliente=mts.idTipoRolCliente
                AND trc.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN mantenimientocliente mtto on mtto.idTipoRolCliente=trc.idTipoRolCliente AND mtto.idUn=pu.idUn
                AND mtto.fechaEliminacion='0000-00-00 00:00:00'
            WHERE pu.idUn=$idUn AND pu.idProducto=$idProducto AND mts.activo=1
                AND pu.activo=1 AND mtto.idMantenimiento=$idMantenimiento";
        $titular = 0;
        $cotitular = 0;
        $sqlad = "SELECT idTipoRolCliente
            FROM socio
            WHERE idUnicoMembresia=$idUnicoMembresia AND eliminado=0";
        $query = $this->db->query($sqlad);
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                if($fila->idTipoRolCliente==1) {
                   $titular = 1;
                }
                if($fila->idTipoRolCliente==2) {
                   $cotitular = 1;
                }
            }
        }
        if ($titular==1) {
            #echo "1|||";
            $sql.=" AND trc.idTipoRolCliente!=1";
        }
        if ($cotitular==1 && $resta>1) {
            $sql.=" ";
        }
        if ($cotitular==1 && ($resta==0 || ($resta==1 && $titular==0) ) ) {
            #echo "2|||";
            $sql.=" AND trc.idTipoRolCliente!=2";
        }
        if ($titular==1 && $cotitular==1 && $resta==0 && $integrantes==$intactual && $sumatotal>$integrantes ) {
            #echo "3|||";
            $sql.=" AND trc.idTipoRolCliente!=1 AND trc.idTipoRolCliente!=2 AND trc.idTipoRolCliente!=3 AND trc.idTipoRolCliente!=4 AND trc.idTipoRolCliente!=5";
        }
        if ($titular==1 && $cotitular==0 && $resta==1 && $restaint==1 && $sumatotal>$integrantes ) {
            #echo "4|||";
            $sql.=" AND trc.idTipoRolCliente!=1  AND trc.idTipoRolCliente!=3 AND trc.idTipoRolCliente!=4 AND trc.idTipoRolCliente!=5";
        }
        if ($restagre==0) {
            #echo "5|||";
            $sql.=" AND trc.idTipoRolCliente!=10 AND trc.idTipoRolCliente!=11";
        }
        if ($restAgregadosAdultos == 0) {
            #echo "6|||";
            $sql.=" AND trc.idTipoRolCliente!=10";
        }
        $query = $this->db->query($sql);

        #echo "<pre>".$this->db->last_query()."</pre>";
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[0] = '';
                $lista[$fila->idTipoRolCliente] = $fila->descripcion;
            }
        }
        return $lista;
    }

    /**
     * Obtiene la lista del tipo de cliente que existe
     *
     * @param integer $idTipoRolCliente Identificador de tiporolcliente
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function listaTipoSocio($idTipoRolCliente = 0)
    {
        settype($idTipoRolCliente, 'integer');

        $this->db->select('idTipoRolCliente, descripcion');
        $this->db->from(TBL_TIPOROLCLIENTE);
        $this->db->where('idTipoCliente', 1);
        $this->db->where('base', 0);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('activo', 1);
        if ($idTipoRolCliente>0) {
            $this->db->where('idTipoRolCliente', $idTipoRolCliente);
        }
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $fila) {
                $datos[0] = '';
                $datos[] = $fila;
            }
            return $datos;
        } else {
            return $datos;
        }
    }

    /**
     * Obtiene nombre de membresia
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    function nombreTipoEstatusMembresia($idUnicoMembresia)
    {
        settype($idUinocMembresia, 'integer');

        $query = $this->db->query(
            "SELECT tem.idTipoEstatusMembresia, tem.descripcion
            FROM tipoestatusmembresia tem
            JOIN membresia m ON m.idTipoEstatusMembresia=tem.idTipoEstatusMembresia
            WHERE m.idUnicoMembresia=".$idUnicoMembresia
        );
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
     * [obtenAsignacionLealtad description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    function obtenAsignacionLealtad($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $query  = $this->db->query(
            "SELECT s.asignarLealtad
                FROM socio s
            WHERE s.idUnicoMembresia IN ($idUnicoMembresia) AND s.idTipoRolCliente=1 AND s.eliminado=0");
        if( $query->num_rows>0 ){
            $row = $query->row();
            return $row->asignarLealtad;
        }
        return 0;
    }

    /**
     * [obtenCatalogoEstatusSocioEstadistica description]
     *
     * @return [type] [description]
     */
    function obtenCatalogoEstatusSocioEstadistica()
    {
        $datos = array();

        $query  = $this->db->query(
            "SELECT idEstatusEstadistica, nombre, mantenimiento
            FROM crm_estadisticas.estatusestadistica ee
            WHERE ee.fechaEliminacion='0000-00-00 00:00:00'
            ORDER BY ee.mantenimiento DESC"
        );
        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                if($fila->mantenimiento==1) {
                    $datos[$fila->idEstatusEstadistica] = $fila->nombre."*";
                } else {
                    $datos[$fila->idEstatusEstadistica] = $fila->nombre;
                }
            }
        }
        return $datos;
    }

    /**
     * Obtiene los datos de un determinado socio
     *
     * @param integer $idSocio
     *
     * @author Jonathan Alcantara
     *
     * @return object
     */
    function obtenDatosSocio ($idSocio = 0, $verificaAusencia = false)
    {
        settype($idSocio, 'integer');
        $datos          = array();
        $selectAusencia = "";
        $whereAusencia  = "";

        if ($idSocio == 0) {
            return $datos;
        }
        if ($verificaAusencia) {
            $selectAusencia = " , sa.fechaAusencia, m.idUn ";
            $whereAusencia = " AND (sa.fechaEliminacion = '0000-00-00 00:00:00' OR sa.fechaEliminacion IS NULL) ";
        }
        $sql = "
            SELECT s.idTipoEstatusSocio,s.idSocio, p.idPersona, m.idUnicoMembresia, m.idMembresia, CONCAT_WS(' ', p.nombre, p.paterno, p.materno) AS nombre,
            un.nombre AS club, t.telefono, mail.mail, trc.descripcion AS rolCliente, s.idMantenimiento, s.idEsquemaPago ".$selectAusencia."
            FROM ".TBL_SOCIO." s
            INNER JOIN ".TBL_PERSONA." p ON s.idPersona = p.idPersona
            INNER JOIN ".TBL_MEMBRESIA." m ON s.idUnicoMembresia = m.idUnicoMembresia
            INNER JOIN ".TBL_UN." ON un.idUn = m.idUn
            LEFT JOIN ".TBL_TELEFONO." t ON p.idPersona = t.idPersona
            LEFT JOIN ".TBL_TIPOTELEFONO." tt ON t.idTipoTelefono = tt.idTipoTelefono
            LEFT JOIN ".TBL_MAIL." ON p.idPersona = mail.idPersona
            INNER JOIN ".TBL_TIPOROLCLIENTE." trc ON s.idTipoRolCliente = trc.idTipoRolCliente
            LEFT JOIN ".TBL_SOCIOAUSENCIA." sa ON s.idSocio = sa.idSocio and sa.fechaRegresoAusencia='0000-00-00 00:00:00'
            WHERE s.idSocio = ?
            ".$whereAusencia.";";
        $query = $this->db->query($sql, array($idSocio));

        if ($query->num_rows > 0) {
            $datos = $query->row_object();
        }
        return $datos;
    }

    /**
     * Obtiene datos de la tarjeta
     *
     * @param $idSocio
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function obtenDatosTarjeta($idUnicoMembresia, $idPersona, $idSocioDatosTarjeta=0, $numTarjeta = 0, $activo=0)
    {
        $this->db->select('st.idSocioDatosTarjeta, s.idPersona,st.idBanco, b.descripcion as banco, st.numeroTarjetaCta, st.nombreTarjeta, st.tipoTarjeta, st.mesExpiracion, st.anioExpiracion, st.diaCargo');
        $this->db->from(TBL_SOCIODATOSTARJETA.' st');
        $this->db->join(TBL_BANCO.' b', 'b.idBanco=st.idBanco', 'LEFT');
        $this->db->join(TBL_SOCIO.' s', 's.idSocio=st.idSocio', 'LEFT');
        if ($idSocioDatosTarjeta >0) {
            $this->db->where('st.idSocioDatosTarjeta', $idSocioDatosTarjeta);
        } else {
            $this->db->where('s.idPersona', $idPersona);
            $this->db->where('s.idUnicoMembresia', $idUnicoMembresia);
        }
        if ($numTarjeta != 0) {
            $this->db->where('st.numeroTarjetaCta', $numTarjeta);
        }
        if($activo != 0){
            $this->db->where('st.activo', 1);
        }
        $this->db->where('st.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('s.eliminado', 0);
        $query = $this->db->get();

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
     * Obtiene datos de la tarjeta en base a idpersona y fecha de registro
     *
     * @param $idSocio
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function obtenDatosTarjetaAltaRegenerar($idPersona, $fechaRegistro)
    {
        $this->db->select('st.idSocioDatosTarjeta, s.idPersona,st.idBanco, b.descripcion as banco, st.numeroTarjetaCta, st.nombreTarjeta, st.tipoTarjeta, st.mesExpiracion, st.anioExpiracion, st.diaCargo');
        $this->db->from(TBL_SOCIODATOSTARJETA.' st');
        $this->db->join(TBL_BANCO.' b', 'b.idBanco=st.idBanco', 'LEFT');
        $this->db->join(TBL_SOCIO.' s', 's.idSocio=st.idSocio', 'LEFT');
        $this->db->join(TBL_DOCUMENTOPERSONA.' dp', 'dp.idPersona=s.idPersona', 'LEFT');
        $this->db->join(TBL_DOCUMENTO.' d', 'd.idDocumento=dp.idDocumento', 'LEFT');
        $this->db->where('s.idPersona', $idPersona);
        $this->db->where('d.fechaRegistro', $fechaRegistro);
        $this->db->where('st.fechaRegistro <=', $fechaRegistro);

        $this->db->order_by('st.fechaRegistro', 'desc');
        $this->db->limit(1);

        $query = $this->db->get();

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
     * Obtiene datos de la tarjeta cancelada
     *
     * @param $idSocio
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function obtenDatosTarjetaCancelada($idPersona)
    {
        settype($idPersona, 'integer');

        $sql = "SELECT b.descripcion as banco, st.numeroTarjetaCta, st.nombreTarjeta, st.tipoTarjeta
              FROM ".TBL_SOCIODATOSTARJETA." st
              LEFT JOIN ".TBL_BANCO." b on b.idBanco=st.idBanco
              LEFT JOIN ".TBL_SOCIO." s on s.idSocio=st.idSocio
              WHERE s.idPersona=".$idPersona." and st.fechaeliminacion!='0000-00-00 00:00:00'
              ORDER BY st.idSocioDatosTarjeta desc limit 1";
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
     * Obtiene datos de la tarjeta cancelada
     *
     * @param $idSocio
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function obtenDatosTarjetaCanceladaRegenerar($idPersona, $fechaRegistro)
    {
       $sql = "SELECT b.descripcion as banco, st.numeroTarjetaCta, st.nombreTarjeta, st.tipoTarjeta, st.motivoCancelacion
            FROM sociodatostarjeta st
            LEFT JOIN banco b on b.idBanco=st.idBanco
            LEFT JOIN socio s on s.idSocio=st.idSocio
            WHERE s.idPersona=".$idPersona." and st.fechaEliminacion BETWEEN SUBTIME('".$fechaRegistro."', '00:00:05') AND ADDTIME('".$fechaRegistro."', '00:00:05')
            ORDER BY st.idSocioDatosTarjeta desc limit 1";
       $query=$this->db->query($sql);

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
     * Obtiene estadisticas de un determinado socio
     *
     * @param type $idUnicoMembresia Identificador unico de membresia
     * @param type $idPersona        Identificador de persona
     * @param type $idSocio          Identificador de socio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function obtenEstadisticas ($idUnicoMembresia, $idPersona = 0, $idSocio = 0)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPersona, 'integer');
        settype($idSocio, 'integer');

        $datos = array(
            'mensaje'              => 'Error faltan datos',
            'error'                => 1,
            'idEstatusEstadistica' => ESTATUSESTADISTICA_INACTIVO
        );
        if ( ! $idUnicoMembresia or ( ! $idPersona and ! $idSocio)) {
            return $datos;
        }
        $datos['mensaje'] = '';
        $datos['error']   = 0;
        $where            = array('e.idUnicoMembresia' => $idUnicoMembresia);

        if ($idPersona) {
            $where['e.idPersona'] = $idPersona;
        }
        if ($idSocio) {
            $where['e.idSocio'] = $idSocio;
        }
        $query = $this->db->select("e.idEstatusEstadistica", false)->get_where(TBL_ESTADISTICAS.' e', $where);

        if ($query->num_rows) {
            $datos['idEstatusEstadistica'] = $query->row()->idEstatusEstadistica;
        }

        return $datos;
    }

    /**
     * Regresa la fecha de ausencia de un socio
     *
     * @param integer $idSocio Identificador del socio
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    function obtenFechaAusencia ($idSocio)
    {
        settype($idSocio, 'integer');
        $fechaAusencia = '';

        if ($idSocio == 0) {
            return false;
        }
        $where = array (
            'idSocio'          => $idSocio,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = $this->db->select('fechaAusencia')->get_where(TBL_SOCIOAUSENCIA, $where);

        if ($query->num_rows > 0) {
            $fechaAusencia = $query->row()->fechaAusencia;
        }
        return $fechaAusencia;
    }

    /**
     * Obtiene fechas de apertura y preventa de las unidades de negocio
     *
     * @param $idUn
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function obtenFechasUn($club)
    {
        $this->db->select('fechaApertura, fechaPreventa');
        $this->db->from('un');
        $this->db->where('idUn',$club );
        $query = $this->db->get();

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
     * Obtiene lista completa de formas de pago
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    function obtenFormasdePago()
    {
        $datos = array();
        $this->db->select('idEsquemaPago , descripcion');
        $this->db->from(TBL_ESQUEMAPAGO);
        $this->db->where('activo', '1');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $temp['text']  = $fila->idEsquemaPago;
                $temp['value'] = utf8_encode($fila->descripcion);
                $datos[]       = $temp;
            }
        }
        return $datos;
    }

    /**
     * [obtenIdPersona description]
     *
     * @param  [type] $idSocio [description]
     *
     * @return [type]          [description]
     */
    function obtenIdPersona ($idSocio)
    {
        settype($idSocio, 'integer');
        $idPersona = 0;

        if ($idSocio == 0) {
            return $idPersona;
        }

        $this->db->select('idPersona');
        $this->db->from(TBL_SOCIO);
        $this->db->where('idSocio', $idSocio);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $idPersona = $query->row()->idPersona;
        }
        return $idPersona;
    }

    /**
     * Busca el idSocio con el idPersona
     *
     * @param integer $idPersona Id de persona a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public static function obtenIdSocio ($idPersona = 0, $idUnicoMembresia = 0)
    {
        settype($idPersona, 'integer');
        settype($idUnicoMembresia, 'integer');
        $idSocio = 0;

        if ($idPersona == 0) {
            return $idSocio;
        }
        $query = DB::connection('crm')->table(TBL_SOCIO)
        ->select('idSocio')
        ->where('idPersona', $idPersona)
        ->where('eliminado', 0);
        if ($idUnicoMembresia > 0) {
            $query = $query->where('idUnicoMembresia', $idUnicoMembresia);
        }
        $query = $query->get();

        if (count($query) > 0) {
            $idSocio = $query[0]->idSocio;
        }
        return $idSocio;
    }

    /**
     * Regresa el tipo de mantenimiento que tiene asignado el socio indicado
     *
     * @param  integer $idSocio Identificador de socio
     *
     * @author Jorge Cruz <jorge.cruz@sportsworld.com.mx>
     *
     * @return integer          Identificador de mantenimiento
     */
    function obtenMtto ($idSocio)
    {
        settype($idSocio, 'integer');
        $idMantenimiento = 0;

        if ($idSocio == 0) {
            return $idMantenimiento;
        }

        $this->db->select('idMantenimiento');
        $this->db->from(TBL_SOCIO);
        $this->db->where('idSocio', $idSocio);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $idMantenimiento = $query->row()->idMantenimiento;
        }
        return $idMantenimiento;
    }

    /**
     * Obtiene nombre de socios con el idSocio
     *
     * @author Antonio Sixtos
     *
     * @return variable
     */
    function obtenNombre($socio)
    {
        $this->db->select('p.nombre, p.paterno, p.materno');
        $this->db->from(TBL_PERSONA.' p');
        $this->db->join(TBL_SOCIO.' s', 's.idPersona=p.idPersona');
        $this->db->where('s.idSocio', $socio);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila['nombre'].' '.$fila['paterno'].' '.$fila['materno'];
        } else {
            return '';
        }
    }

    /**
     * Obtiene nombre de mtto de tabla mesesLealtad de tabla socio
     *
     * @author Antonio Sixtos
     *
     * @param integer $idMtto
     *
     * @return array
     */
    function obtenNombreMttoLealtad($idUnicoMembresia)
    {
        $nvoMtto= array();

        $this->db->select('ml.idMantenimientoNuevo, p.nombre');
        $this->db->from('mantenimientolealtad'.' ml');
        $this->db->join(TBL_PRODUCTOMANTENIMIENTO.' pm', 'pm.idMantenimiento=ml.idMantenimientoNuevo','inner');
        $this->db->join(TBL_PRODUCTO.' p', 'p.idProducto=pm.idProducto','inner');
        $this->db->join(TBL_SOCIO.' s', 's.idMantenimiento=ml.idMantenimiento AND s.idTipoRolCliente=1','inner');
        $this->db->where('s.idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();

        if ($query->num_rows >0) {
            foreach ($query->result() as $fila) {
                $nvoMtto[] = $fila;
            }
        }

        return $nvoMtto;
    }

    /**
     * Obtiene nuevo rol de cliente cuando se cambia el tipo de membresia
     *
     * @param integer $idProducto       Identificador de producto
     * @param integer $idUn             Identificador de unidad de negocio
     * @param integer $idMantenimiento  Identificador de mantenimiento
     * @param integer $idTipoRolCliente Identificador de tiporolcliente
     * @param integer $edad             Edad del socio
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    function obtenNuevoRolSocio ($idProducto, $idUn, $idMantenimiento, $idTipoRolCliente, $idSocio, $edad, $validaEdad = true)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        settype($idMantenimiento, 'integer');
        settype($idTipoRolCliente, 'integer');
        settype($edad, 'integer');

        $datosSocio = $this->obtenDatosSocio($idSocio);
        $datosRolCliente = $datosRolCliente = array('idTipoRolCliente' => $idTipoRolCliente, 'descripcion' => $datosSocio->rolCliente);

        if ($idTipoRolCliente == ROL_CLIENTE_TITULAR or ( ! $idProducto or ! $idUn or ! $idMantenimiento or ! $idTipoRolCliente)) {
            return $datosRolCliente;
        }
        $where = array(
            'pu.idUn'                   => $idUn,
            'pu.idProducto'             => $idProducto,
            'mts.activo'                => 1,
            'pu.activo'                 => 1,
            'mttoc.idMantenimiento'     => $idMantenimiento,
            'mttoc.idTipoRolCliente <>' => ROL_CLIENTE_TITULAR,
            'pu.fechaEliminacion'       => '0000-00-00 00:00:00',
            'mc.fechaEliminacion'       => '0000-00-00 00:00:00',
            'trc.fechaEliminacion'      => '0000-00-00 00:00:00',
            'mttoc.fechaEliminacion'    => '0000-00-00 00:00:00'
        );
        if ($validaEdad) {
            $this->db->where($edad.' BETWEEN mttoc.edadMinima AND mttoc.edadMaxima', null, false);
        }
        $this->db->join(TBL_PRODUCTOUN.' pu', 'pu.idProductoUn = mc.idProductoUn', 'inner');
        $this->db->join(TBL_MEMBRESIATIPOSOCIO.' mts', 'mts.idMembresiaConfiguracion = mc.idMembresiaConfiguracion', 'inner');
        $this->db->join(TBL_TIPOROLCLIENTE.' trc', "trc.idTipoRolCliente = mts.idTipoRolCliente AND trc.fechaEliminacion = '0000-00-00 00:00:00'", 'inner');
        $this->db->join(TBL_MANTENIMIENTOCLIENTE.' mttoc', "mttoc.idTipoRolCliente = trc.idTipoRolCliente AND mttoc.idUn = pu.idUn AND mttoc.fechaEliminacion = '0000-00-00 00:00:00'", 'inner');

        $query = $this->db->select(
            'trc.idTipoRolCliente, trc.descripcion, mttoc.edadMinima, mttoc.edadMaxima'
        )->order_by('mttoc.idTipoRolCliente')->get_where(TBL_MEMBRESIACONFIGURACION.' mc', $where);

        if ($query->num_rows) {
            $rows = $query->result_array();
            $datosRolCliente = $rows[0];
        } else {
            if ($validaEdad) {
                $datosRolCliente = $this->obtenNuevoRolSocio($idProducto, $idUn, $idMantenimiento, $idTipoRolCliente, $idSocio, $edad, false);
            }
        }
        return $datosRolCliente;
    }

    /**
     * Obtiene el primer titular de una membresia
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function obtenPrimerMttoTitular ($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $idMantenimiento = 0;

        if ($idUnicoMembresia<=0) {
            return $idMantenimiento;
        }

        $this->db->select('idSocio, idMantenimiento');
        $this->db->from(TBL_SOCIO);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->limit(1);
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $idSocio = $fila['idSocio'];
            $idMantenimiento = $fila['idMantenimiento'];

            $this->db->select('idMantenimiento');
            $this->db->from(TBL_SOCIOMANTENIMIENTO);
            $this->db->where('idSocio', $idSocio);
            $this->db->limit(1);
            $query2 = $this->db->get();

            if ($query2->num_rows > 0) {
                $fila2 = $query2->row_array();
                $idMantenimiento = $fila2['idMantenimiento'];
            }
        }

        return $idMantenimiento;
    }

    /**
     * Qry que muestra las bajas programadas para el dia de hoy
     *
     * @author  Antonio Sixtos
     *
     * @return int
     */
    function obtenRelacionAlCaBaPro()
    {
        $data = Array();
        $sql = "SELECT s.idUnicoMembresia, s.idPersona
            FROM socio s
            INNER JOIN socioBaja sb ON sb.idSocio=s.idSocio
            WHERE s.eliminado=0 AND sb.fechaEliminacion='0000-00-00 00:00:00' AND DATE(sb.fechaBaja)=DATE(NOW())";
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
     * Obtiene lista de socios
     *
     * @param type $unico
     * @param type $idPersona
     * @param type $todos
     * @return boolean
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    function obtenSocios($unico, $idPersona=0, $todos=0)
    {
        settype($unico, 'integer');
        settype($idPersona, 'integer');
        settype($todos, 'integer');

        $m = '';
        if ($idPersona > 0) {
            $m = ' AND s.idPersona='.$idPersona;
        }
        $h = '';
        $g = '';
        $i = '';
        if ($todos == 0) {
          $h = " AND s.eliminado=0 AND s.idTipoEstatusSocio!=".ESTATUS_SOCIO_BAJA." ";
          $i = " AND soc.eliminado=0 ";
        }

        $sql = "DROP TEMPORARY TABLE IF EXISTS tmpSocioModel_obtenSocios";
        $this->db->query($sql);

        $sql = "CREATE TEMPORARY TABLE tmpSocioModel_obtenSocios
            SELECT MAX(idSocio) AS idSocio
            FROM crm.socio soc
            WHERE soc.idUnicoMembresia=$unico $i
            GROUP BY soc.idPersona";
        $this->db->query($sql);

        $sql = " SELECT
                s.idSocio, s.idPersona, CONCAT(p.nombre,' ',p.paterno,' ',p.materno) as nombrecompleto,
                trc.descripcion, trc.idTipoRolCliente, pro.nombre AS mantenimiento,
                tss.descripcion as tipoEstatus, pma.idMantenimiento, ep.descripcion as esqpago,
                s.idEsquemaPago, s.idTipoEstatusSocio, s.nuevo, s.numeroAusencias, s.fechaEliminacion,
                tss.orden AS o1, if(sa.fechaRegresoAusencia <> '0000-00-00 00:00:00',1,0) as datoAusencia,
                ph.idPersonaHealthy, YEAR(s.fechaEliminacion)AS eliminado, ph.programa,
                IFNULL(sap.fechaAusencia, '') AS ausenciaProgramada,
                IF(sap.fechaAusencia IS NULL, 0, PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM sap.fechaAusencia), EXTRACT(YEAR_MONTH FROM NOW()))) AS difMesesAusProg
            FROM tmpSocioModel_obtenSocios a
            INNER JOIN crm.socio s ON s.idSocio=a.idSocio $h
            INNER JOIN crm.persona p on s.idpersona=p.idpersona $m
            INNER JOIN crm.tiporolcliente trc on s.idtiporolcliente=trc.idtiporolcliente
            INNER JOIN crm.productomantenimiento pma on s.idmantenimiento=pma.idmantenimiento
            INNER JOIN crm.producto pro on pma.idproducto=pro.idproducto
            INNER JOIN crm.tipoestatussocio tss on tss.idTipoEstatusSocio=s.idTipoEstatusSocio
            INNER JOIN crm.esquemapago ep on s.idEsquemaPago=ep.idEsquemaPago
            LEFT JOIN crm.socioausencia sa on sa.idSocio = s.idSocio and sa.fechaEliminacion = '0000-00-00 00:00:00'
            LEFT JOIN crm.socioausencia sap ON sap.idSocio=s.idSocio AND YEAR(sap.fechaEliminacion)=0 AND YEAR(sap.fechaRegresoAusencia)=0
            LEFT JOIN crm.personahealthy ph on ph.idPersona = s.idPersona and ph.fechaEliminacion = '0000-00-00 00:00:00'
            WHERE s.idPersona>0
			GROUP BY s.idPersona
            ORDER BY s.idTipoEstatusSocio, tss.orden, trc.orden, s.numeroAusencias,
                s.fechaRegistro, s.idSocio";
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
     * Obtiene idPersonas a las que se les tiene que calcular mtto
     *
     * @author Antonio Sixtos
     *
     * @param integer $idUnicoMembresia
     *
     * @return array
     */
    function obtenSociosParaCalculoMtto($idUnicoMembresia)
    {

        $datos=array();
        $ids = array(81, 83);
        $this->db->select("idPersona", false);
        $this->db->from(TBL_SOCIO);
        $this->db->where("idUnicoMembresia", $idUnicoMembresia);
        $this->db->where("numeroAusencias <", "4");
        $this->db->where("eliminado", 0);
        $this->db->where_in("idTipoEstatusSocio",$ids);
        $rs=$this->db->get();
        if ($rs->num_rows > 0) {
            foreach ($rs->result() as $fila) {
                $datos[] = $fila;
            }
            return $datos;
        } else {
            return $datos;
        }
    }

    /**
     * Obtiene la descripcion del tipo de acceso
     *
     * @param $idUnicoMembresia
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function obtenTipoAcceso($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT t.descripcion
            FROM tipoAcceso t
            LEFT JOIN mantenimiento m ON m.idTipoAcceso=t.idTipoAcceso
            LEFT JOIN socio s ON s.idMantenimiento=m.idMantenimiento
            WHERE s.idUnicoMembresia=".$idUnicoMembresia." LIMIT 1";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->descripcion;
        } else {
            return false;
        }
    }

    /**
     * Obtiene lista completa de tipo Estatus
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    function obtenTipoEstatus()
    {
        $datos = array();
        $this->db->select('idTipoEstatusMembresia , descripcion');
        $this->db->from(TBL_TIPOESTATUSMEMBRESIA);
        $this->db->where('activo', '1');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $temp['text']  = $fila->idTipoEstatusMembresia;
                $temp['value'] = utf8_encode($fila->descripcion);
                $datos[]       = $temp;
            }
        }
        return $datos;
    }

    /**
     * [obtenTipoEstatusSocio description]
     * @return [type] [description]
     */
    function obtenTipoEstatusSocio()
    {
        $datos = array();
        $this->db->select('idTipoEstatusSocio , descripcion');
        $this->db->where('activo',1);
        $this->db->from(TBL_TIPOESTATUSSOCIO);
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $temp['text']  = $fila->idTipoEstatusSocio;
                $temp['value'] = utf8_encode($fila->descripcion);
                $datos[]       = $temp;
            }
        }
        return $datos;
    }

    /**
     * Obtiene tipoEstatusSocioTitular
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    function obtenTipoEstatusSocioTitular($unico)
    {
        settype($unico, 'integer');
        $idTipoEstatusSocio = ESTATUS_SOCIO_AUSENCIA;

        if (! $unico) {
           return $idTipoEstatusSocio;
        }
        $query = $this->db->query("
            SELECT idTipoEstatusSocio
            FROM socio
            WHERE idUnicoMembresia = $unico
                AND idTipoRolcliente = '".ROL_CLIENTE_TITULAR."'
                AND eliminado = 0",
            false
        );
        if($query->num_rows) {
           $idTipoEstatusSocio = $query->row()->idTipoEstatusSocio;
        }
        return $idTipoEstatusSocio;
    }

    /**
     * [obtenTipoSocio description]
     *
     * @param  [type] $idProducto       [description]
     * @param  [type] $idUn             [description]
     * @param  [type] $idMantenimiento  [description]
     * @param  [type] $adultosact       [description]
     * @param  [type] $adultos          [description]
     * @param  [type] $idUnicoMembresia [description]
     * @param  [type] $agregados        [description]
     * @param  [type] $agregadoact      [description]
     * @param  [type] $integrantes      [description]
     * @param  [type] $tSocioactual     [description]
     *
     * @return [type]                   [description]
     */
    public function obtenTipoSocio($idProducto, $idUn, $idMantenimiento, $adultosact, $adultos, $idUnicoMembresia, $agregados, $agregadoact, $integrantes, $tSocioactual)
    {
        settype($adultosact, 'integer');
        settype($adultos, 'integer');
        settype($agregados, 'integer');
        settype($agregadoact, 'integer');
        settype($integrantes, 'integer');
        settype($tSocioactual, 'integer');
        settype($idUnicoMembresia, 'integer');
        settype($idProducto, 'integer');
        settype($idMantenimiento, 'integer');
        settype($idUn, 'integer');

        $intactual = $this->cuentaSocios($idUnicoMembresia);

        $resta = $adultos-$adultosact;
        $restagre = $agregados-$agregadoact;
        $sumatotal = $integrantes+$agregados;
        $restaint = $sumatotal-$intactual;

        settype($resta,'integer');
        settype($restagre,'integer');
        settype($sumatotal,'integer');
        settype($restaint,'integer');

        $datos = array();

        $sql = "SELECT trc.idTipoRolCliente, trc.descripcion, mtto.edadMinima, mtto.edadMaxima
            FROM membresiaconfiguracion mc
            INNER JOIN productoun pu on pu.idProductoUn=mc.idProductoUn
            INNER JOIN membresiatiposocio mts on mts.idMembresiaConfiguracion=mc.idMembresiaConfiguracion
            INNER JOIN tiporolcliente trc on trc.idTipoRolCliente=mts.idTipoRolCliente
                AND trc.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN mantenimientocliente mtto on mtto.idTipoRolCliente=trc.idTipoRolCliente
                AND mtto.idUn=pu.idUn AND mtto.fechaEliminacion='0000-00-00 00:00:00'
            WHERE pu.idUn=$idUn AND pu.idProducto=$idProducto AND mts.activo=1 AND pu.activo=1
                AND mtto.idMantenimiento=$idMantenimiento";
        $titular = 0;
        $cotitular = 0;
        $sqlad = "SELECT idTipoRolCliente
            FROM socio
            WHERE idUnicoMembresia=$idUnicoMembresia AND eliminado=0";
        $query=$this->db->query($sqlad);
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                if ($fila->idTipoRolCliente==1) {
                   $titular = 1;
                }
                if ($fila->idTipoRolCliente==2) {
                   $cotitular = 1;
                }
            }
        }

        if ($titular==1 && $tSocioactual!=1 ) {
            $sql .= " AND trc.idTipoRolCliente!=1";
        }
        $query=$this->db->query($sql);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $temp['text']  = $fila->idTipoRolCliente;
                $temp['value'] = utf8_encode($fila->descripcion);
                $datos[] = $temp;
            }
        }
        return $datos;
    }

    /**
     * Obten total de socios activos en un club
     *
     * @param int $idUn Identificador de club
     *
     * @author Jonathan Alcantara
     *
     * @return int
     */
    public function obtenTotalSociosActivosClub($idUn)
    {
        settype($idUn, 'integer');

        $total = 0;

        if ( ! $idUn) {
            return $total;
        }
        $where = array('idUn' => $idUn, 'idEstatusEstadistica' => 1);
        $query = $this->db->select(
            "COUNT(DISTINCT idSocio)AS total", false
        )->get_where(TBL_ESTADISTICAS, $where);

        if ($query->num_rows) {
            $total = $query->row()->total;
        }
        return $total;
    }

    /**
     * Regresa el identificador unico de membresia
     *
     * @param  integer $idSocio Identificador de socio
     *
     * @author Jorge Cruz <jorge.cruz@sportsworld.com.mx>
     *
     * @return integer          Identificador unico de membresia
     */
    public static function obtenUnicoMembresia ($idSocio)
    {
        settype($idSocio, 'integer');
        $idUnicoMembresia = 0;

        if ($idSocio == 0) {
            return $idUnicoMembresia;
        }
        
        $query = DB::connection('crm')->table(TBL_SOCIO)
        ->select('idUnicoMembresia')
        ->where('idSocio', $idSocio)
        ->where('eliminado', 0)
        if ($query->count() > 0) {
            $query = $query->get();
            $idUnicoMembresia = $query[0]->idUnicoMembresia;
        }
        return $idUnicoMembresia;
    }

    /**
     * Obtiene ultimo acceso de un socio
     *
     * @param integer $idPersona Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function obtenUltimoAcceso ($idPersona)
    {
        settype($idPersona, 'integer');

        $datos = array();

        if ( ! $idPersona) {
            return $datos;
        }
        $where = array(
            'ra.idPersona' => $idPersona,
            'ra.direccion' => 'Entrada'
        );
        $query = $this->db->select(
            'MAX(ra.fecha)AS fechaUlitmoAcceso', false
        )->get_where(TBL_REGISTROACCESO.' ra', $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Obtiene forma de pago con el idPersona
     *
     * @author Antonio Sixtos
     *
     * @param integer $idPersona  identificador persona
     *
     * @return array
     */
    function obtenerEsquemaFormaPago($idPersona, $idEsquemaPago = 0)
    {
        $this->db->select('e.descripcion');
        $this->db->from(TBL_ESQUEMAPAGO.' e');
        if ($idEsquemaPago >0){
            $this->db->where('e.idEsquemaPago', $idEsquemaPago);
        } else {
            $this->db->join(TBL_SOCIO.' s', 's.idEsquemaPago=e.idEsquemaPago');
            $this->db->where('s.idPersona', $idPersona);
            $this->db->where('s.eliminado', 0);
        }
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $esquemaPago = $fila->descripcion;
            }
            return $esquemaPago;
        } else {
            return 0;
        }
    }

    /**
     * Obtiene datos de la membresia en base a idunicoMembresia
     *
     * @author Antonio Sixtos
     *
     * @return array data
     */
    function obtenerDatosMembresia($idUnicoMembresia)
    {
        $this->db->select('idMantenimiento, idEsquemaPago');
        $this->db->from(TBL_SOCIO);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        }else{
           return false;
        }
    }

    /**
     * Verifica si la tarjeta ha sido catalogada como Hard Decline
     *
     * @author Gustavo Bonilla
     *
     * @return array
     */
    function tarjetaBloqueda($numeroTarjeta)
    {
        $this->db->select("tb.numeroTarjeta, IFNULL(tb.tipoBloqueo, '') AS tipoBloqueo", false);
        $this->db->from("crm.finanzascatarjetasbloquedas tb");
        $this->db->where("tb.numeroTarjeta", $numeroTarjeta);
        $this->db->where("YEAR(tb.fechaEliminacion)", 0);

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return 1;
        } else {
            return 0;
        }
    }


    /**
     * Verifica si existe una tarjeta registrada de esta persona
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function tarjetaRepetidaSocio($idUnicoMembresia,$idPersona,$numeroTarjeta)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPersona, 'integer');

        $this->db->select('st.idSocioDatosTarjeta,st.idSocio');
        $this->db->from(TBL_SOCIODATOSTARJETA.' st');
        $this->db->join(TBL_SOCIO.' s', 's.idSocio=st.idSocio');
        $this->db->where('s.idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('s.idPersona', $idPersona);
        $this->db->where('s.eliminado', 0);
        $this->db->where('st.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('st.numeroTarjetaCta', $numeroTarjeta);

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        } else {
            return 0;
        }
    }

    /**
     * Regresa un valor booleano indicado si la membresia tiene registrado un usuario 2x1
     *
     * @param  integer $idUnicoMembresia Identificador de membresia
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function tiene2x1($idUnicoMembresia, $validaEliminado = true)
    {
        settype($idUnicoMembresia, 'integer');

        $resultado = false;

        $eliminado  = '';
        $eliminado2 = '';
        if ($validaEliminado) {
            $eliminado  = " AND s.eliminado=0";
            $eliminado2 = " AND s.idTipoEstatusSocio<>82";
        }
        $sql = "SELECT COUNT(*) AS total
            FROM socio s
            WHERE s.idUnicoMembresia=$idUnicoMembresia ".$eliminado."
                AND s.idTipoRolCliente=18 ".$eliminado2;
        $query = $this->db->query($sql);
        $fila = $query->row_array();

        if ($fila['total']>0) {
            $resultado = true;
        }

        return $resultado;
    }

    /**
     * [tieneAgregado description]
     *
     * @param  integer $idUnicoMembresia [description]
     *
     * @author Jorge Cruz <jorge.cruz@sportsworld.com.mx>
     *
     * @return boolean
     */
    public function tieneAgregado($idUnicoMembresia, $validaEliminado = true)
    {
        settype($idUnicoMembresia, 'integer');

        $this->db->from(TBL_SOCIO);
        $this->db->where('idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('idTipoRolCliente', 17);
        if ($validaEliminado) {
            $this->db->where('eliminado', 0);
            $this->db->where('idTipoEstatusSocio <>', ESTATUS_SOCIO_BAJA);
        }
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Regresa un valor booleano indicado si la membresia tiene registrado un usuario 2x1
     *
     * @param  integer $idUnicoMembresia Identificador de membresia
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function tieneWeekend($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $resultado = false;

        $sql = "SELECT COUNT(*) AS total
            FROM socio s
            WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.eliminado=0
                AND s.idMantenimiento=105 AND s.idTipoEstatusSocio<>82";
        $query = $this->db->query($sql);
        $fila = $query->row_array();

        if ($fila['total']==1) {
            $resultado = true;
        }

        return $resultado;
    }

    /**
     * Obtiene el tipo de empresa en base al idPersona
     *
     * @author Antonio Sixtos
     *
     * @return variable fechabaja
     */
    function tipoEmpresa($idPersona) 
    {
        $sql = "SELECT u.idEmpresa
            FROM un u
            INNER JOIN membresia m ON u.idun=m.idun
                AND m.eliminado=0
            INNER JOIN socio s ON m.idUnicoMembresia=s.idUnicoMembresia
                AND s.eliminado=0
            WHERE s.idPersona=".$idPersona;
        $query=$this->db->query($sql);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->idEmpresa;
        } else {
            return false;
        }
    }

    /**
     * Obtiene mesesConsecutivos de tabla socio
     *
     * @author Antonio Sixtos
     *
     * @param integer $idUnicoMembresia
     *
     * @return array
     */
    function totalMesesConsecutivos($idUnicoMembresia)
    {
        $mesesconsecutivos = 0;

        $this->db->select('s.mesesconsecutivos');
        $this->db->from(TBL_SOCIO.' s');
        $this->db->where('s.idUnicoMembresia', $idUnicoMembresia);
        $this->db->where('s.idTipoRolCliente', '1');
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $mesesconsecutivos = $fila->mesesconsecutivos;
            }
        }

        return $mesesconsecutivos;
    }

    /**
     * Devuelve el idUnico de Membresia del socio solicitado
     *
     * @param in $idSocio Identificador de socio
     *
     * @author Jorge Cruz
     *
     * @return int
     */
    function unico($idSocio)
    {
        settype($idSocio, 'integer');

        if ($idSocio > 0) {
            $this->db->select('idUnicoMembresia');
            $where = array(
                'idSocio'   => $idSocio,
                'eliminado' => 0
            );
            $this->db->where($where);
            $this->db->from(TBL_SOCIO);
            $query = $this->db->get();
            if ($query->num_rows() > 0) {
                foreach ($query->result() as $fila) {
                    return $fila->idUnicoMembresia;
                }
            }
        }

        return 0;
    }

    /**
     * Valida si socio tiene anualidad pagada
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     * @param integer $idPersona        Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    function validaAnualidad($idUnicoMembresia, $idPersona , $pagada = 1)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idPersona, 'integer');
        $res = false;

        if ( ! $idUnicoMembresia or ! $idPersona) {
            return $res;
        }
        $where = array(
            'spm.activo'                => $pagada,
            'm.idTipoEstatusMovimiento' => MOVIMIENTO_PAGADO,
            'spm.eliminado'             => 0,
            'm.eliminado'               => 0
        );
        $this->db->join(TBL_MOVIMIENTO.' m', 'spm.idMovimiento = m.idMovimiento AND spm.idUnicoMembresia = '.$idUnicoMembresia.' AND spm.idPersona = '.$idPersona, 'inner');
        $this->db->having('PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM spm.fechaFin), EXTRACT(YEAR_MONTH FROM spm.fechaInicio)) >= 10');
        $query = $this->db->select(
            "spm.fechaInicio, spm.fechaFin",
            false
            )->order_by('spm.idSocioPagoMtto', 'DESC')->get_where(TBL_SOCIOPAGOMTTO.' spm', $where);

        if ($query->num_rows) {
            $res = true;
        }
        return $res;
    }

    /**
     * Funci�n que valida la edad de cada socio para llevar acabo el traspaso
     *
     * @param integer $idUnNuevo        Identificador de la nueva Unidad de Negocio
     * @param integer $idMantenimiento  Identificador del tipo de mantenimiento
     * @param integer $idTipoRolCliente Identificador del tipo rol cliente
     * @param integer $edad             Edad
     *
     * @author Santa Garcia
     *
     * @return array
     */
    function validaEdad($idUnNuevo, $idMantenimiento, $idTipoRolCliente, $edad)
    {
        settype($idUnNuevo, 'integer');
        settype($idMantenimiento, 'integer');
        settype($idTipoRolCliente, 'integer');
        settype($edad, 'integer');

        $this->db->select('idMantenimientoCliente');
        $this->db->from(TBL_MANTENIMIENTOCLIENTE);
        $this->db->where('idUn', $idUnNuevo);
        $this->db->where('idMantenimiento', $idMantenimiento);
        $this->db->where('fechaEliminacion ', '0000-00-00 00:00:00');
        $this->db->where('idTipoRolCliente ', $idTipoRolCliente);
        $this->db->where('edadMinima <=', $edad);
        $this->db->where('edadMaxima >=', $edad);

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * Valida que el esquema de pago sea Cargo Autmatico y que tenga tarjetas de credito vigentes y activas
     *
     * @author  Antonio Sixtos
     *
     * @return int
     */
    function validaEsquemaPagoyTarjetaActiva($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT sdt.idSocioDatosTarjeta
            FROM socio s
            INNER JOIN socioDatosTarjeta sdt ON sdt.idSocio=s.idSocio  AND sdt.fechaEliminacion='0000-00-00 00:00:00'
                AND sdt.activo=1
            WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.idTipoRolCliente=1
                AND s.eliminado=0 AND s.idEsquemaPago=2";
        $query = $this->db->query($sql);
        if ($query->num_rows() == 1) {
            $res='1';
        } else {
            $res='0';
        }

        return $res;
    }

    /**
     * Valida si el mes actual esta registrado como periodo vacacional
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    function validaPeriodoVacacional()
    {
        $datos = array('fecha' => '0000-00-00');
        $where = array('fecha >=' => date('Y-m').'-01', 'fecha <= ' => date('Y').'-12-01');
        $query = $this->db->select('fecha')->order_by('fecha', 'ASC')->get_where(TBL_PERIODOVACACIONAL, $where);

        if ($query->num_rows) {
            $datos['fecha']   = $query->row()->fecha;

            if ($datos['fecha'] == date('Y-m').'-01') {
                $datos['error']   = 0;
                $datos['mensaje'] = 'Periodo vacacional valido';
            } else {
                $datos['error']   = 5;
                $datos['mensaje'] = 'Imposible aplicar ausencia extemporaneaSALTOSALTOSALTONota: El mes actual no esta configurado como periodo vacacional.';
            }
        } else {
            $datos['error']   = 4;
            $datos['mensaje'] = 'Error no ha sido configurado el periodo vacacional en el Sistema';
        }
        return $datos;
    }

    /**
     * Valida si el titular de la membresia esta ausente
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    function validaTitularAusente ($idUnicoMembresia, $idSocio)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idSocio, 'integer');

        if (($idUnicoMembresia == 0) or ($idSocio == 0)) {
            return 0;
        }
        $CI =& get_instance();
        $CI->load->model('membresia_model');

        $datos['idTitular'] = $CI->membresia_model->obtenerTitular($idUnicoMembresia);
        $idPersona = $datos['idTitular']['idPersona'];
        $idSocioTitular = $this->obtenIdSocio($idPersona);

        if ($idSocioTitular == $idSocio) {
            return 0;
        }
        $where = array(
            'idSocio'              => $idSocioTitular,
            'fechaRegresoAusencia' => '0000-00-00 00:00:00',
            'fechaEliminacion'     => '0000-00-00 00:00:00'
        );
        $query = $this->db->select('COUNT(*)AS ausente')->get_where(TBL_SOCIOAUSENCIA, $where);
        return $query->row()->ausente;
    }

    /**
     * [verifActTipoSocio description]
     * @param  [type] $idUnicoMembresia [description]
     * @param  [type] $idTipoSocio      [description]
     * @return [type]                   [description]
     */
    function verifActTipoSocio($idUnicoMembresia, $idTipoSocio)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idTipoSocio, 'integer');

        $sql="SELECT s.idSocio, s.idUnicoMembresia, s.idPersona, s.idTipoRolCliente, trc.Descripcion
            FROM socio s
            LEFT JOIN tiporolcliente trc on trc.idTipoRolCliente=s.idTipoRolCliente
            WHERE s.idUnicoMembresia=$idUnicoMembresia and s.idTipoEstatusSocio!=82 and trc.idTipoRolCliente=".$idTipoSocio;
        $query=$this->db->query($sql);
        if ($query->num_rows() > 0) {
            $dat=1;
        } else {
            $dat=0;
        }
        return $dat;
    }

    /**
     * Verifica si un socio cuenta con un registro dentro de la baja programadas
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    function verifSocio($idSocio)
    {
        settype($idSocio, 'integer');
        if ($idSocio == 0) {
            return 0;
        }

        $this->db->from(TBL_SOCIOBAJA);
        $this->db->where('idSocio', $idSocio);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');

        return $this->db->count_all_results();
    }

    /**
     * Verifica si un socio ya tiene activada la ausencia
     *
     * @param integer $idSocio Identificador de socio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function verificaAusencia ($idSocio)
    {
        settype($idSocio, 'integer');
        $datos = array();
        $datos['idSocioAusencia'] = 0;
        $datos['fechaAusencia']   = '';

        if ($idSocio == 0) {
            return $datos;
        }

        $this->db->select('idSocioAusencia, fechaAusencia');
        $this->db->from(TBL_SOCIOAUSENCIA);
        $this->db->where('idSocio', $idSocio);
        $this->db->where('fechaRegresoAusencia', '0000-00-00');
        $this->db->where('fechaEliminacion', '0000-00-00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            $datos['idSocioAusencia'] = $query->row()->idSocioAusencia;
            $datos['fechaAusencia']   = $query->row()->fechaAusencia;
        }
        return $datos;
    }

    /**
     * Verifica si la persona a dar de alta, en realidad es un reingreso
     *
     * @author Antonio Sixtos
     *
     * @return array data
     */
    function verificaSiReingreso($idUnicoMembresia, $idPersona)
    {
        $this->db->select('fechaEliminacion');
        $this->db->from(TBL_SOCIO);
        $where=array('idUnicoMembresia'=>$idUnicoMembresia , 'idPersona'=>$idPersona, 'idTipoEstatusSocio'=>'82', );
        $this->db->where($where);
        $this->db->where('fechaEliminacion !=', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }

            return $data;
        } else {
           $data='';
           return $data;
        }
    }


    /**
     * [obtenSociosSinComprobante description]
     *
     * @param  integer $idUnicoMembresia  [description]
     * @param  integer $idTipoComprobante [description]
     *
     * @author Armando Paez
     *
     * @return array
     */
    public function obtenSociosSinComprobante($idUnicoMembresia,$idTipoComprobante)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idTipoComprobante, 'integer');


        $subquery =  "SELECT dp.idPersona
            FROM documentopersona dp
            JOIN documento d ON dp.idDocumento = d.idDocumento
            JOIN comprobantedocumento cd ON cd.idTipoDocumento=d.idTipoDocumento
            WHERE cd.idTipoComprobante = $idTipoComprobante";

        $query = $this->db->from('socio')
           ->where('idUnicoMembresia',$idUnicoMembresia)
           ->where('idPersona not in',$subquery);
        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return array();
        }
    }

    /**
     * [insertaCargoCredencial description]
     *
     * @param  array $datos [description]
     *
     * @author Armando Paez
     *
     * @return integer      Id del movimiento registrados en la BD
     */
    public function insertaCargoCredencial($datos)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');
        $CI->load->model('producto_model');
        $CI->load->model('movimientos_model');
        $CI->load->model('membresia_model');
        $CI->load->model('invitadosespeciales_model');

        $movimiento = 0;

        $tc = ROL_CLIENTE_NINGUNO;
        $idUn = $this->session->userdata('idUn');
        $iva = $CI->un_model->iva($idUn);
        if ($datos['idUnicoMembresia']>0) {
            $idUn  = $CI->membresia_model->club($datos['idUnicoMembresia']);
            $tc = ROL_CLIENTE_SOCIO;
        }

        $imp = $CI->producto_model->precio(CARGO_CREDENCIAL_PREMIER, $idUn, $tc);

        $status_mov = MOVIMIENTO_PENDIENTE;
        #if ($CI->invitadosespeciales_model->esInvitadoEspecialVIP($datos['idPersona'])) {
        #    $status_mov = MOVIMIENTO_EXCEPCION_PAGO;
        #}

        if ($this->session->userdata('idEmpresaGrupo')==1) {
            $cargoCredencial = array (
                'fecha'                   => date('Y-m-d'),
                'persona'                 => $datos['idPersona'],
                'tipo'                    => TIPO_MOVIMIENTO_OTROSINGRESOS,
                'descripcion'             => '1 x TARJETA DE BENEFICIOS BLACK PASS',
                'iva'                     => $iva,
                'importe'                 => $imp['monto'],
                'membresia'               => $datos['idUnicoMembresia'],
                'esquemaPago'             => ESQUEMA_PAGO_CONTADO,
                'producto'                => CARGO_CREDENCIAL_PREMIER,
                'msi'                     => 1,
                'origen'                  => 'socio_model',
                'idUn'                    => $idUn,
                'idTipoEstatusMovimiento' => $status_mov,
                'numeroCuenta'            => $imp['numCuenta'],
                'cantidad'                => 1,
                'cveProductoServicio'     => $CI->producto_model->cveProducto(CARGO_CREDENCIAL_PREMIER),
                'cveUnidad'               => $CI->producto_model->cveUnidad(CARGO_CREDENCIAL_PREMIER)
            );

            $movimiento = $CI->movimientos_model->inserta($cargoCredencial);
        }
        return $movimiento;
    }

    /**
     * [AddBoySafeSplash] AGREGA NIÑOS A AL WEB SERVER DE SS
     *
     * @param  idPersonaTutor,idPersonaHijo,origen,idUn
     *
     * @author Ruben Alcocer
     *
     * @return [type]            [description]
     */
    public function AddBoySafeSplash($idPersonaTutor,$idPersonaHijo,$origen,$idUn,$royalties,$idMembresia=0)
    {
        $nombreClubsSafe[31] = 'Altavista';
        $nombreClubsSafe[11] = 'Arboledas';
        $nombreClubsSafe[68] = 'Carmen';
        $nombreClubsSafe[5]= 'Centenario';
        $nombreClubsSafe[26]= 'Coacalco';
        $nombreClubsSafe[62]='Cuernavaca';
        $nombreClubsSafe[72]='Cumbres';
        $nombreClubsSafe[58]='Felix%20Cuevas';
        $nombreClubsSafe[13]='Hermosillo';
        $nombreClubsSafe[14]='Interlomas';
        $nombreClubsSafe[77]='Rioja';
        $nombreClubsSafe[61]='Leon';
        $nombreClubsSafe[59]='Loreto';
        $nombreClubsSafe[69]='Miguel%20Angel';
        $nombreClubsSafe[76]='Merida';
        $nombreClubsSafe[66]='Metepec';
        $nombreClubsSafe[10]='Monterrey';
        $nombreClubsSafe[75]='Obrero%20Mundial';
        $nombreClubsSafe[30]='Palmas';
        $nombreClubsSafe[8]='Patriotismo';
        $nombreClubsSafe[33]='Pedregal';
        $nombreClubsSafe[9]='Puebla';
        $nombreClubsSafe[3]='San%20Angel';
        $nombreClubsSafe[32]='San%20Jeronimo';
        $nombreClubsSafe[16]='Santa%20Fe';
        $nombreClubsSafe[4]='Satelite';
        $nombreClubsSafe[63]= 'Sonata';
        $nombreClubsSafe[6] = 'Tecamachalco';
        $nombreClubsSafe[56] ='Universidad';
        $nombreClubsSafe[2]= 'Valle';
        $nombreClubsSafe[36]='Veracruz';
        $nombreClubsSafe[64] = 'Xola';
        $nombreClubsSafe[67] = 'Zona%20Esmeralda';

        $nombreClubsSafe[89] = 'Crater';
        $nombreClubsSafe[86] = 'Manacar';
        $nombreClubsSafe[84] = 'Lindavista';
        $nombreClubsSafe[88] = 'Juriquilla%20Queretaro';
        $nombreClubsSafe[87] = 'Bernardo%20Quintana';
        $nombreClubsSafe[91] = 'Patio%20Tlalpan';
        $nombreClubsSafe[83] = 'Barranca';
        $nombreClubsSafe[81] = 'Citi%20Tower';
        $nombreClubsSafe[90] = 'PASEO%20INTERLOMAS';
        $nombreClubsSafe[85] = 'Cabo%20Norte';

        $ci =& get_instance();
        $ci->load->model('persona_model');
        $ci->load->model('un_model');

        $dataStudent = $ci->persona_model->datosGenerales($idPersonaHijo);
        $genderStudent = $ci->persona_model->sexo($idPersonaHijo);

        if ($genderStudent == 'Femenino') {
            $sexStudent = 0;
        } else if($genderStudent == 'Masculino') {
            $sexStudent = 1;
        }

        $nombreClub = $nombreClubsSafe[$idUn];

        $CustomerID = $this->AddResponsableSafeSplash($idPersonaTutor,$origen,$nombreClub,$idMembresia);

        if ($CustomerID > 0) {
            $url = "https://services.safesplash.com/api/customers/addCustomerStudent";
            $url .= "?";
            $url .= "customerID=".$CustomerID;
            $url .= "&studentFirstName=".urlencode($dataStudent['nombre']);
            $url .= "&studentLastName=".urlencode($dataStudent['paterno']." ".$dataStudent['materno']);
            $url .= "&notes=".urlencode("Test Safe Splash SW"); //Quitar comentario de test una vez liberado a prod
            $url .= "&DOB=".$dataStudent['fecha'];
            $url .= "&gender=".$sexStudent;
            $url .= "&origen=".$origen;
            $url .= "&club=".$nombreClub;
			$url .= "&royalties=".$royalties;
            $url .= "&personID=".$idPersonaHijo;
            $login = 'safesplashmx@safesplash.com';
            $password = 'Spl@shMX1';

            // $jsonService = curl_init($url);

            // curl_setopt($jsonService, CURLOPT_HEADER, 0);
            // curl_setopt($jsonService, CURLOPT_CONNECTTIMEOUT, 10);
            // curl_setopt($jsonService, CURLOPT_TIMEOUT,        10);
            // curl_setopt($jsonService, CURLOPT_RETURNTRANSFER,1);
            // curl_setopt($jsonService, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            // curl_setopt($jsonService, CURLOPT_SSL_VERIFYPEER, false);
            // curl_setopt($jsonService, CURLOPT_SSL_VERIFYHOST, false);
            // curl_setopt($jsonService, CURLOPT_USERPWD, $login.':'.$password);

            $jsonService=$this->curl_config($url,$login,$password);
            $jsonResponse = curl_exec($jsonService);
            if ($jsonResponse === false) {
                $err = 'Curl error: ' . curl_error($jsonService);
                curl_close($jsonService);
                $resp = $err;
                $customerStudentID = 0;
            } else {
                curl_close($jsonService);
                $resp = $jsonResponse;
                $respJSON = json_decode($resp);
                $customerStudentID =  $respJSON->customerStudentID;
            }

            if ($customerStudentID>0) {
                $this->SendSafeSplash($idPersonaHijo,$customerStudentID, 2);
            }
            $this->logEnvioSafeSplash($idPersonaHijo,$url,$resp,$jsonResponse,2);
            return  $resp;
        }
        return 0;
    }


    /**
     * [agregarSocioSafeSplash description]     NO FUNCIONA
     *
     * @param  [type] $idPersona [description]
     *
     * @author Armando Paez
     *
     * @return [type]            [description]
     */
    public function agregarSocioSafeSplash($idPersona,$idPersonaResponsable,$origen,$idUn)
    {
        #harcodeado para despues ingresarse a una tabla a peticion de ruben
        $nombreClubsSafe[31] = 'Altavista';
        $nombreClubsSafe[11] = 'Arboledas';
        $nombreClubsSafe[68] = 'Carmen';
        $nombreClubsSafe[5]= 'Centenario';
        $nombreClubsSafe[26]= 'Coacalco';
        $nombreClubsSafe[62]='Cuernavaca';
        $nombreClubsSafe[72]='Cumbres';
        $nombreClubsSafe[58]='Felix%20Cuevas';
        $nombreClubsSafe[13]='Hermosillo';
        $nombreClubsSafe[14]='Interlomas';
        $nombreClubsSafe[77]='Rioja';
        $nombreClubsSafe[61]='Leon';
        $nombreClubsSafe[59]='Loreto';
        $nombreClubsSafe[69]='Miguel%20Angel';
        $nombreClubsSafe[76]='Merida';
        $nombreClubsSafe[66]='Metepec';
        $nombreClubsSafe[10]='Monterrey';
        $nombreClubsSafe[75]='Obrero%20Mundial';
        $nombreClubsSafe[30]='Palmas';
        $nombreClubsSafe[8]='Patriotismo';
        $nombreClubsSafe[33]='Pedregal';
        $nombreClubsSafe[9]='Puebla';
        $nombreClubsSafe[3]='San%20Angel';
        $nombreClubsSafe[32]='San%20Jeronimo';
        $nombreClubsSafe[16]='Santa%20Fe';
        $nombreClubsSafe[4]='Satelite';
        $nombreClubsSafe[63]= 'Sonata';
        $nombreClubsSafe[6] = 'Tecamachalco';
        $nombreClubsSafe[56] ='Universidad';
        $nombreClubsSafe[2]= 'Valle';
        $nombreClubsSafe[36]='Veracruz';
        $nombreClubsSafe[64] = 'Xola';
        $nombreClubsSafe[67] = 'Zona%20Esmeralda';

        $nombreClubsSafe[89] = 'Crater';
        $nombreClubsSafe[86] = 'Manacar';
        $nombreClubsSafe[84] = 'Lindavista';
        $nombreClubsSafe[88] = 'Juriquilla%20Queretaro';
        $nombreClubsSafe[87] = 'Bernardo%20Quintana';
        $nombreClubsSafe[91] = 'Patio%20Tlalpan';
        $nombreClubsSafe[83] = 'Barranca';
        $nombreClubsSafe[81] = 'Citi%20Tower';
        $nombreClubsSafe[90] = 'PASEO%20INTERLOMAS';
        $nombreClubsSafe[85] = 'Cabo%20Norte';

        $ci =& get_instance();
        $ci->load->model('persona_model');
        $ci->load->model('un_model');
        $dataStudent = $ci->persona_model->datosGenerales($idPersona);
        $genderStudent = $ci->persona_model->sexo($idPersona);

        if ($genderStudent == 'Femenino') {
            $sexStudent = 0;
        } else if($genderStudent == 'Masculino') {
            $sexStudent = 1;
        }
        $nombreClub = $nombreClubsSafe[$idUn];
        $idCustomer = $this->agregarResponsableSafeSplash($idPersona, $idPersonaResponsable,$origen,$nombreClub);
        $url = "https://services.safesplash.com/api/customers/addCustomerStudent";

        $url .= "?";
        $url .= "customerID=".$idCustomer;
        $url .= "&studentFirstName=".urlencode($dataStudent['nombre']);
        $url .= "&studentLastName=".urlencode($dataStudent['paterno']." ".$dataStudent['materno']);
        $url .= "&notes=".urlencode("Test Safe Splash SW"); //Quitar comentario de test una vez liberado a prod
        $url .= "&DOB=".$dataStudent['fecha'];
        $url .= "&gender=".$sexStudent;
        $url .= "&origen=".$origen;
        $url .= "&club=".$nombreClub;
        $login = 'safesplashmx@safesplash.com';
        $password = 'Spl@shMX1';

        // $jsonService = curl_init($url);
        // //curl_setopt($jsonService, CURLOPT_URL, $url );
        // curl_setopt($jsonService, CURLOPT_HEADER, 0);
        // curl_setopt($jsonService, CURLOPT_CONNECTTIMEOUT, 10);
        // curl_setopt($jsonService, CURLOPT_TIMEOUT,        10);
        // curl_setopt($jsonService, CURLOPT_RETURNTRANSFER,1);
        // curl_setopt($jsonService, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($jsonService, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($jsonService, CURLOPT_SSL_VERIFYHOST, false);
        // curl_setopt($jsonService, CURLOPT_USERPWD, $login.':'.$password);
        $jsonService=$this->curl_config($url,$login,$password);
        $jsonResponse = curl_exec($jsonService);
        if ($jsonResponse === false) {
            $err = 'Curl error: ' . curl_error($jsonService);
            curl_close($jsonService);
            $resp = $err;
        } else {
            curl_close($jsonService);
            $resp = $jsonResponse;
        }
        $registrar =  $this->registrarEnvioSafeSplash($idPersona,$idCustomer, $resp);
        return  $resp;
    }

    /**
     * [AddResponsableSafeSplash]   AGREGA AL TUTOR A SS
     * @param  [type] $idPersonaTutor       [description]
     * @param  [type] $origen               [description]
     * @param  [type] $nombreClub           [description]
     *
     * @author Ruben Alcocer
     *
     * @return [type]                       [description]
     */
    public function AddResponsableSafeSplash($idPersonaTutor,$origen,$nombreClub,$idMembresia=0)
    {
        $idTutor =  $this->ValidaIdtutor($idPersonaTutor);

        if ($idTutor== 0) {
            $ci =& get_instance();
            $ci->load->model('persona_model');
            $data = $ci->persona_model->datosGenerales($idPersonaTutor);
            $mail = $ci->persona_model->mail($idPersonaTutor,34);

            $url = "https://services.safesplash.com/api/customers/addCustomer";

            $url .= "?";
            $url .= "&firstName=".urlencode($data['nombre']);
            $url .= "&lastName=".urlencode($data['paterno']." ".$data['materno']);
            $url .= "&email=".  urlencode($mail);
            $url .= "&origen=".  $origen;
            $url .= "&club=".$nombreClub;
            if($idMembresia!=0)
            {
                $url .= "&membershipNumber=".$idMembresia;
            }
            
            $login = 'safesplashmx@safesplash.com';
            $password = 'Spl@shMX1';

            // $jsonService = curl_init($url);

            // curl_setopt($jsonService, CURLOPT_HEADER, 0);
            // curl_setopt($jsonService, CURLOPT_CONNECTTIMEOUT, 10);
            // curl_setopt($jsonService, CURLOPT_TIMEOUT,        10);
            // curl_setopt($jsonService, CURLOPT_RETURNTRANSFER,1);
            // curl_setopt($jsonService, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            // curl_setopt($jsonService, CURLOPT_SSL_VERIFYPEER, false);
            // curl_setopt($jsonService, CURLOPT_SSL_VERIFYHOST, false);
            // curl_setopt($jsonService, CURLOPT_USERPWD, $login.':'.$password);
            $jsonService=$this->curl_config($url,$login,$password);
            $jsonResponse = curl_exec($jsonService);

            if ($jsonResponse === false) {
                $err = 'Curl error: ' . curl_error($jsonService);
                curl_close($jsonService);
                $resp = $err;
                $idCustomer = 0;
            } else {
                curl_close($jsonService);
                $resp = $jsonResponse;
                $respJSON = json_decode($resp);
                $idCustomer =  $respJSON->customerID;
            }

            $this->logEnvioSafeSplash($idPersonaTutor,$url,$resp,$jsonResponse,1);

            if ($idCustomer>0) {
               $this->SendSafeSplash($idPersonaTutor,$idCustomer, 1);
            }
        } else {
            $idCustomer = $idTutor;
        }
        return $idCustomer;
    }

    /**
     * [agregarResponsableSafeSplash description]       NO FUNCIONA
     * @param  [type] $idPersona            [description]
     * @param  [type] $idPersonaResponsable [description]
     * @param  [type] $origen               [description]
     * @param  [type] $nombreClub           [description]
     *
     * @author Armando Paez
     *
     * @return [type]                       [description]
     */
    public function agregarResponsableSafeSplash($idPersona, $idPersonaResponsable, $origen, $nombreClub)
    {
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $data = $ci->persona_model->datosGenerales($idPersonaResponsable);
        $mail = $ci->persona_model->mail($idPersonaResponsable,34);

        $url = "https://services.safesplash.com/api/customers/addCustomer";

        $url .= "?";
        $url .= "&firstName=".urlencode($data['nombre']);
        $url .= "&lastName=".urlencode($data['paterno']." ".$data['materno']);
        $url .= "&email=".  urlencode($mail);
        $url .= "&origen=".  $origen;
        $url .= "&club=".$nombreClub;
        $login = 'safesplashmx@safesplash.com';
        $password = 'Spl@shMX1';

        // $jsonService = curl_init($url);

        // curl_setopt($jsonService, CURLOPT_HEADER, 0);
        // curl_setopt($jsonService, CURLOPT_CONNECTTIMEOUT, 10);
        // curl_setopt($jsonService, CURLOPT_TIMEOUT,        10);
        // curl_setopt($jsonService, CURLOPT_RETURNTRANSFER,1);
        // curl_setopt($jsonService, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($jsonService, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($jsonService, CURLOPT_SSL_VERIFYHOST, false);
        // curl_setopt($jsonService, CURLOPT_USERPWD, $login.':'.$password);
        $jsonService=$this->curl_config($url,$login,$password);
        $jsonResponse = curl_exec($jsonService);

        if ($jsonResponse === false) {
            $err = 'Curl error: ' . curl_error($jsonService);
            curl_close($jsonService);
            $resp = $err;
        } else {
            curl_close($jsonService);
            $resp = $jsonResponse;
        }

        $respJSON = json_decode($resp);
        $idCustomer =  $respJSON->customerID;
        $registrar =  $this->registrarEnvioSafeSplash($idPersona,$idCustomer, $resp);
        return  $idCustomer;
    }


    /**
     * [registrarEnvioSafeSplash description]
     *
     * @param  [type] $idPersona [description]
     * @param  [type] $response  [description]
     *
     * @author Armando Paez
     *
     * @return [type]            [description]
     */
    public function registrarEnvioSafeSplash($idPersona,$idCustomer, $response)
    {
        $datosInsert = [
            'personaId'        => $idPersona,
            'idCustomer'       => $idCustomer,
            'descripcion'      => 'Registro de la persona con id: '.$idPersona.', fecha: '.date('Y-m-d H:i:s'),
            'mensajeRespuesta' => $response,
            'fechaRegistro'    => date('Y-m-d H:i:s')
        ];
        $this->db->insert('safesplashenvio',$datosInsert);
    }

    /**
     * [SendSafeSplash]         GUARDA RELACION DE TUTOR CON SS
     *
     * @param  [int] $idPersonaTutor
     * @param  [int] $idCustomer
     *
     * @author Ruben Alcocer
     *
     *
     */
    public function SendSafeSplash($idPersonaTutor,$idCustomer,$parentesco)
    {
        $datosInsert = [
            'idPersona'     => $idPersonaTutor,
            'idCustomer'    => $idCustomer,
            'parentesco'    => $parentesco,
            'fechaRegistro' => date('Y-m-d H:i:s')
        ];
        $this->db->insert('safesplashtutor',$datosInsert);
    }

    /**
     * Devuelve el idCustomer (id del tutor en SS)
     *
     * @param in $idPersona Identificador de persona
     *
     * @author Ruben Alcocer
     *
     * @return int
     */
    function ValidaIdtutor($idPersona)
    {
        settype($idPersona, 'integer');

        if ($idPersona > 0) {
            $this->db->select('idCustomer');
            $where = array(
                'idPersona' => $idPersona,
                'fechaEliminacion' => '0000-00-00 00:00:00',
                'parentesco' => 1,
            );
            $this->db->where($where);
            $this->db->from('safesplashtutor');
            $query = $this->db->get();
            if ($query->num_rows() > 0) {
                foreach ($query->result() as $fila) {
                    return $fila->idCustomer;
                }
            }
        }

        return 0;
    }

    /**
     * [logEnvioSafeSplash description]
     *
     * @param  [type] $idPersona    [description]
     * @param  [type] $url          [description]
     * @param  [type] $resp         [description]
     * @param  [type] $jsonResponse [description]
     * @param  [type] $parentesco   [description]
     *
     * @return [type]               [description]
     */
    function logEnvioSafeSplash($idPersona,$url,$resp,$jsonResponse,$parentesco)
    {
        $datosInsert = [
            'idPersona'     => $idPersona,
            'urlenvio'      => $url,
            'respuesta'     => $resp,
            'parentesco'    => $parentesco,
            'estatus'       => $jsonResponse,
            'fechaRegistro' => date('Y-m-d H:i:s')
        ];
        $this->db->insert('safesplashlogenvio',$datosInsert);
    }

    /**
     * credencialEntregada Devuelve si una credencial ya fué entregada
     *
     * @author David Arias
     *
     * @return bool
     */
    function credencialEntregada($idMovimiento,$idPersona)
    {
        settype($idMovimiento,'integer');
        settype($idPersona,'integer');

        $registros = $this->db->get_where('crm.credencialenvio',array(
            'idMovimiento' => $idMovimiento,
            'idPersona' => $idPersona,
        ));

        if ($registros->num_rows() > 0) {
            $registros = $registros->row_array();
            if ($registros['fechaEntrega']=='0000-00-00 00:00:00')
                return false;
        }
        return true;
    }

    /**
     * entregaCredencial Marcar como entregada una credencial
     *
     * @author David Arias
     *
     * @return bool
     */
    function entregaCredencial($idMovimiento,$idPersona)
    {
        settype($idMovimiento,'integer');
        settype($idPersona,'integer');

        $this->db->where(array(
            'idMovimiento' => $idMovimiento,
            'idPersona' => $idPersona
        ))->update('crm.credencialenvio',array(
            'fechaEntrega' => date('Y-m-d H:i:s'))
        );

        if($this->db->affected_rows()>0) return true;
        return false;
    }

    public function curl_config($url, $login, $password)
    {
        $jsonService = curl_init();

        curl_setopt_array($jsonService, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Basic ".base64_encode($login.':'.$password),
                "Cache-Control: no-cache",
            ),
        ));

        return $jsonService;
    }


    /**
     * Valida si la membresia cuenta con algun Invitado PartTime registrado
     * 
     * @param  integer $idUnicoMembresia Identeficador de membresia a validar
     * 
     * @return boolean
     */
    public function validaInvitadoPT($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $res = false;

        $sql = "SELECT idSocio 
            FROM socio 
            WHERE idUnicoMembresia=$idUnicoMembresia AND eliminado=0 AND idTipoEstatusSocio<>82
                AND idTipoRolCliente=".ROL_CLIENTE_PT;
        $query = $this->db->query($sql);

        if ($query->num_rows>0) {
            $res = true;
        }

        return $res;
    }


    /**
     * Valida si se puede activar el tipousuario
     *
     * @author David Arias
     *
     * @return bool
     */
    public function validaUusarioTipo($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT idSocio
            FROM socio
            WHERE idUnicoMembresia=$idUnicoMembresia
                AND idMantenimiento IN (
                    SELECT idMantenimiento 
                    FROM productomantenimiento 
                    WHERE idProducto IN (
                        SELECT idProducto 
                        FROM producto 
                        WHERE nombre LIKE '%weekend%')
                    ) AND idTipoRolCliente NOT IN (19,17)
                AND eliminado=0"; //idMantenimiento weeken
        $query = $this->db->query($sql);

        if ($query->num_rows>0) {
            return 1;
        }
        return 0;
    }
}
