<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use API_EPS\Models\CatRutinas;
use API_EPS\Models\MenuActividad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Un extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.Un';
    protected $primaryKey = 'idUn';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * actualiza registros de ungerente en base al idUnGerente
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function actualizaGerencias($idPuesto, $idPersona, $idUnGerente)
    {
        settype($idPuesto, 'integer');
        settype($idPersona, 'integer');
        settype($idUnGerente, 'integer');

        $this->db->select('codigo');
        $this->db->from(TBL_PUESTO);
        $this->db->where('idPuesto',$idPuesto);
        $query = $this->db->get();
        $row = $query->row();
        $codigo=$row->codigo;

        $data = array(
           'idPersona'          => $idPersona,
           'idPuesto'           => $idPuesto,
           'codigo'             => $codigo,
           'fechaActualizacion' => date('Y-m-d H:i:s')
        );

        $this->db->where('idUnGerente', $idUnGerente);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_UNGERENTE, $data);
        if($this->db->affected_rows()>0) {
            $regresa = 1;
        } else {
            $regresa = 0;
        }
        $this->permisos_model->log('Se actualiza datos gerente para el club ('.$idUn.')', LOG_UNIDADNEGOCIO);
        return $regresa;
    }

    /**
     * Actualiza la informacion de una determinada unidad de negocio
     *
     * @param array $post Array POST con la informacion a actualizar
     *
     * @author Jonathan Alcantara
     *
     * @return void
     */
    public function actualizaUn ($post = array())
    {
        $resultado = false;

        if (empty($post)) {
            return $resultado;
        }
        extract($post);

        $update = array(
            'nombre'        => utf8_decode($nombre),
            'clave'         => utf8_decode($clave),
            'idEmpresa'     => $idEmpresa,
            'idTipoUn'      => $idTipoUn,
            'fechaApertura' => $fechaApertura,
            'fechaPreVenta' => $fechaPreVenta,
            'activo'        => $activo,
            'idOperador'    => $idOperador,
            'calle'         => utf8_decode($calle),
            'numero'        => $numero,
            'colonia'       => utf8_decode($colonia),
            'cp'            => $cp,
            'idEstado'      => $idEstado,
            'idMunicipio'   => $idMunicipio,
            'idZonaHoraria' => $idZonaHoraria,
            'orden'         => $ordens,
            'bmv'           => $bmv,
            'disponibilidadLimite' => $disponibilidad
        );

        $this->db->where('idUn', $idUn);
        $resultado = $this->db->update('un', $update);
        $this->permisos_model->log('Actualiza unidad de negocio', LOG_UNIDADNEGOCIO);

        return $resultado;
    }

    /**
     * Obtiene las afiliaciones de una determinada unidad de negocio
     *
     * @param integer $idUn Id de unidad de negocio a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function afiliacionesUn ($idUn = 0)
    {
        $data = array();

        if ($idUn == 0) {
            return  $data;
        }

        $ci =& get_instance();
        $ci->load->model('afiliaciones_model');

        $tipoAfiliacTabla = $ci->afiliaciones_model->obtenNombreTabla();
        $this->db->select(
            'unafiliacion.idUnAfiliacion, unafiliacion.idTipoAfiliacion, '.'
            unafiliacion.numeroAfiliacion, untipoafiliacion.descripcion'
        );
        $this->db->where('unafiliacion.idUn', $idUn);
        $this->db->join($tipoAfiliacTabla, TBL_UNAFILIACION.'.idTipoAfiliacion = '.$tipoAfiliacTabla.'.idTipoAfiliacion', 'left');
        $queryAfiliac = $this->db->get(TBL_UNAFILIACION);

        if ($queryAfiliac->num_rows > 0 ) {
            foreach ($queryAfiliac->result() as $fila) {
                $data[] = $fila;
            }
        }
        return $data;
    }

    /**
     * Inserta registro de una afiliacion
     *
     * @param array $post Array POST que contiene la informacion a insertar
     *
     * @author Jonathan Alcantara
     *
     * @return void
     */
    public function agregaAfil ($post = array())
    {
        $resultado = false;

        if (empty($post)) {
            return $resultado;
        }

        extract($post);

        $insert = array(
            'idUn' => $idUn,
            'idTipoAfiliacion' => $tipoAfil,
            'numeroAfiliacion' => $numeroAfil
        );
        $resultado = $this->db->insert("unafiliacion", $insert);

        $this->permisos_model->log('Inserta nueva afiliacion de unidad de negocio', LOG_UNIDADNEGOCIO);

        return $resultado;
    }

    /**
     * Inserta un numero de telefono nuevo
     *
     * @param array $post Array POST que contiene la informacion a insertar
     *
     * @author Jonathan Alcantara
     *
     * @return void
     */
    public function agregaTel ($post = array())
    {
        $resultado = false;

        if (empty($post)) {
            return $resultado;
        }

        extract($post);

        $insert = array(
            'idUn' => $idUn,
            'idTipoTelefono' => $tipoTel,
            'numTelefono' => $telefono
        );

        $resultado = $this->db->insert('untelefono', $insert);

        $this->permisos_model->log('Inserta nuevo telefono de unidad de negocio', LOG_UNIDADNEGOCIO);

        return $resultado;
    }

    /**
     * Inserta una unidad de negocio nueva
     *
     * @param array $post Array POST que contiene la informacion a insertar
     *
     * @author Jonathan Alcantara
     *
     * @return void
     */
    public function agregaUn ($post = array())
    {
        $idUn = 0;

        if (empty($post)) {
            return $idUn;
        }

        extract($post);
        $insert = array(
            'nombre'        => utf8_decode($nombre),
            'clave'         => utf8_decode($clave),
            'idEmpresa'     => $idEmpresa,
            'idTipoUn'      => $idTipoUn,
            'fechaApertura' => $fechaApertura,
            'fechaPreVenta' => $fechaPreVenta,
            'activo'        => $activo,
            'idOperador'    => $idOperador,
            'calle'         => utf8_decode($calle),
            'numero'        => $numero,
            'colonia'       => utf8_decode($colonia),
            'cp'            => $cp,
            'idEstado'      => $idEstado,
            'idMunicipio'   => $idMunicipio,
            'idZonaHoraria' => $idZonaHoraria,
            'orden'         => $ordens,
            'disponibilidadLimite' => $disponibilidad
        );

        $query = $this->db->insert(TBL_UN, $insert);
        $idUn  = $this->db->insert_id();
        $this->permisos_model->log('Inserta nueva unidad de negocio', LOG_UNIDADNEGOCIO);

        return $idUn;
    }


    /**
     * [arrayRegiones description]
     *
     * @return array
     */
    public function arrayRegiones()
    {
        $res = array();

        $sql = 'SELECT r.idRegion, r.descripcion
            FROM region r
            WHERE r.activo=1';
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $res[$fila->idRegion] = $fila->descripcion;
            }
        }

        return $res;
    }


    /**
     * Obtiene información general de una aplicacion
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function busquedaCapacidades($opciones, $totales=0, $posicion=0, $registros=25, $orden='')
    {
        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }
        if ($orden == '') {
            $orden = 'tc.descripcion';
        }

        $b='';
        if($opciones['idUn'] > 0){
            $b= ' AND u.idUn= "'.$opciones['idUn'].'" ';
        }
        $p = '';
        if ($totales == 0) {
            if($posicion == null){
                $posicion = 0;
            }
            $p = ' LIMIT '.$posicion.', '.$registros.' ' ;
        }
        $sql = "select uc.capacidad,tc.descripcion as tipo, uc.idUnCapacidad
                from ".TBL_UN." u
                inner join ".TBL_UNCAPACIDAD." uc on uc.idUn = u.idUn and uc.fechaEliminacion='0000-00-00 00:00:00'
                inner join ".TBL_TIPOUNCAPACIDAD." tc on tc.idTipoUnCapacidad = uc.idTipoUnCapacidad and tc.activo = 1
                where u.activo = 1 $b ORDER BY $orden  $p";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            if ($totales == 1) {
                return $query->num_rows;
            }
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene informacion de la temporalidad configurada para un club
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function busquedaTemporalidad($opciones, $totales=0, $posicion=0, $registros=25, $orden='')
    {
        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }
        if ($orden == '') {
            $orden = 'ut.anio,ut.semana';
        }

        $b='';
        if($opciones['idUn'] > 0){
            $b= ' AND u.idUn= "'.$opciones['idUn'].'" ';
        }
        $p = '';
        if ($totales == 0) {
            if($posicion == null){
                $posicion = 0;
            }
            $p = ' LIMIT '.$posicion.', '.$registros.' ' ;
        }
        $sql = "SELECT ut.idUnTemporalidadSemana, ut.anio, ut.semana, ut.inicioVigencia, ut.finVigencia
                FROM ".TBL_UN." u
                INNER JOIN ".TBL_UNTEMPORALIDADSEMANA." ut ON ut.idUn = u.idUn and ut.fechaEliminacion='0000-00-00 00:00:00' and ut.activo = 1
                WHERE u.activo = 1 $b ORDER BY $orden desc $p";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            if ($totales == 1) {
                return $query->num_rows;
            }
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene los datos de conexion por unidad de negocio de unservidor
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function datosUnServidor($idUn)
    {
        settype($idUnServidor, 'integer');
        $where2 = array('u.idUn' => $idUn,'u.idTipoServicioIp' => 2);
        $where3 = array('u.idUn' => $idUn,'u.idTipoServicioIp' => 3);

        $select = 'u.idUnServidor, u.nombreBD, u.ip,u.idTipoServicioIp, u.usuario, u.password, '
                .'u.ultimaDescarga AS descarga,u.idUn, '
                .'ua.hora, ua.total, ua.actualizados, ua.huellas, ua.tags';

        $query2 = $this->db->select($select)
                ->from(TBL_UNSERVIDOR.' u')
                ->join(TBL_UNACCESORESUMEN.' ua', 'ua.idUn=u.idUn AND ua.fecha="'.DATE('Y-m-d').'"', 'LEFT')
                ->where($where2)
                ->get();

        $query3 = $this->db->select($select)
                ->from(TBL_UNSERVIDOR.' u')
                ->join(TBL_UNACCESORESUMEN.' ua', 'ua.idUn=u.idUn AND ua.fecha="'.DATE('Y-m-d').'"', 'LEFT')
                ->where($where3)
                ->get();
        if ( $query2->num_rows > 0 ) {
            $rs2 = $query2->result_array();
            $rs3 = $query3->result_array();
            $data =  array();
            foreach($rs2 as $i =>$row2){
                $row2['ipSalida'] = "&nbsp;";
                if($query3->num_rows > 0){
                    foreach($rs3 as $row3){
                        if($row2['idUn'] == $row3['idUn']){
                            $row2['ipSalida'] = $row3['ip'];
                        }
                    }
                }
                $data = $row2;
            }
            return $data;
            //return $query->row_array();
        } else {
            return false;
        }
    }

    /**
     * Actualiza la informacion de una determinada afiliacion
     *
     * @param integer $idUnAfil   Id de registro a actualizar
     * @param integer $tipoAfil   Id de tipo de afiliacion
     * @param integer $numeroAfil Numero de afiliacion
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function editaAfiliacion ($idUnAfil = 0, $tipoAfil = '', $numeroAfil = 0)
    {

        $result = false;

        if ($idUnAfil == 0) {
            return $result;
        }

        $update = array(
            'idTipoAfiliacion' => $tipoAfil,
            'numeroAfiliacion' => $numeroAfil
        );
        $this->db->where('idUnAfiliacion', $idUnAfil);
        $result = $this->db->update(TBL_UNAFILIACION, $update);

        $this->permisos_model->log('Actualiza afiliacion de unidad de negocio', LOG_UNIDADNEGOCIO);

        return $result;
    }

    /**
     * Actualiza la informacion de un determinado telefono
     *
     * @param integer $idUnTel   Id de registro a actualizar
     * @param integer $idTipoTel Id de tipo de telefono
     * @param integer $telefono  Numero telefonico
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function editaTel($idUnTel, $idTipoTel, $telefono)
    {

        $update = array(
            'idTipoTelefono' => $idTipoTel,
            'numTelefono' => $telefono
        );

        $this->db->where('idUnTelefono', $idUnTel);
        $result = $this->db->update(TBL_UNTELEFONO, $update);
        $this->permisos_model->log('Actualiza telefono de unidad de negocio', LOG_UNIDADNEGOCIO);

        return $result;
    }

    /**
     * Elimina fisicamente el registro de una afiliacion
     *
     * @param integer $idUnAfiliacion Id de la afiliacion a eliminar
     *
     * @author Jonathan Alcantara
     *
     * @return void
     */
    public function eliminaAfil ($idUnAfiliacion = 0)
    {
        $resultado = false;

        if ($idUnAfiliacion == 0) {
            return $resultado;
        }

        $this->db->where('idUnAfiliacion', $idUnAfiliacion);
        $resultado = $this->db->delete('unafiliacion');

        $this->permisos_model->log('Elimina afiliacion de unidad de negocio', LOG_UNIDADNEGOCIO);

        return $resultado;
    }

    /**
     * elimina Datos de nueva gerencia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function eliminaGerencias($idUnGerente)
    {
        $data = array(
           'fechaEliminacion' => date('Y-m-d H:i:s')
        );

        $this->db->where('idUnGerente', $idUnGerente);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_UNGERENTE, $data);
        if($this->db->affected_rows()>0) {
            $regresa=1;
        } else {
            $regresa=0;
        }

		$idUn	= 0;
		$this->db->select('idUn', false);
		$this->db->from(TBL_UNGERENTE);
		$this->db->where('idUnGerente', $idUnGerente);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
		$query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $idUn	= $fila['idUn'];
        }

        $this->permisos_model->log('Se elimina gerente para el club ('.$idUn.')', LOG_UNIDADNEGOCIO);
        return $regresa;
    }

    /**
     * Elimina la capacidad por tipo
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function eliminaUnCapacidad($idUnCapacidad)
    {
        settype($idUnCapacidad, 'integer');

        $datos = array (
            'fechaEliminacion'  => date('Y-m-d H:i:s')
        );

        $this->db->where('idUnCapacidad', $idUnCapacidad);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_UNCAPACIDAD, $datos);

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se elimino capacidad con ID ('.$idUnCapacidad.') ', LOG_UNIDADNEGOCIO);
            return true;
        }
    }

    /**
     * Elimina fisicamente el registro de un numero telefonico
     *
     * @param integer $idUnTelefono Id de registro del telefono a eliminar
     *
     * @author Jonathan Alcantara
     *
     * @return void
     */
    public function eliminaTel ($idUnTelefono = 0)
    {
        $resultado = false;

        if ($idUnTelefono == 0) {
            return $resultado;
        }

        $this->db->where('idUnTelefono', $idUnTelefono);
        $resultado = $this->db->delete('untelefono');

        $this->permisos_model->log('Elimina telefono de unidad de negocio', LOG_UNIDADNEGOCIO);

        return $resultado;
    }

    /**
     * Elimina temporalidad
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function eliminaTemporalidad($idUnTemporalidadSemana)
    {
        settype($idUnTemporalidadSemana, 'integer');

        $datos = array (
            'activo'    => 0
        );

        $this->db->where('idUnTemporalidadSemana', $idUnTemporalidadSemana);
        $this->db->where('activo', '1');
        $this->db->update(TBL_UNTEMPORALIDADSEMANA, $datos);

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se elimino temporalidad ('.$idUnTemporalidadSemana.') ', LOG_ACTIVIDAD_DEPORTIVA);
            return true;
        }
    }

    /**
     * Elimina logicamente una determinada unidad de negocio
     *
     * @param integer $idUn Id de la unidad de negocio a eliminar
     *
     * @author Jonathan Alcantara
     *
     * @return void
     */
    public function eliminaUn ($idUn = 0)
    {
        $result = false;

        if ($idUn == 0) {
            return $result;
        }

        $update = array(
            'fechaEliminacion' => date("Y-m-d H:i:s")
        );

        $this->db->where('idUn', $idUn);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $result = $this->db->update('un', $update);
        $this->permisos_model->log('Elimina unidad de negocio', LOG_UNIDADNEGOCIO);

        return $result;
    }

    /**
     * Devuelve el idUn de donde es el empleado
     *
     * @param integer $idEmpleado Id del empleado
     *
     * @author Diego Zambrano <diego.zambrano@sportsworld.com.mx>
     *
     *
     * @return integer idUn
     */
    public function empleadoUn($persona)
    {
        $idUnInstalacion = 0;

        $this->db->select('ep.idUn');
        $this->db->from(TBL_EMPLEADO.' e');
        $this->db->join(TBL_EMPLEADOPUESTO.' ep', 'ep.idEmpleado=e.idEmpleado', 'INNER');
        $this->db->where('ep.idTipoEstatusEmpleado', 196);
        $this->db->where('ep.idPersona', $persona);
        $this->db->where('e.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('ep.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila["idUn"];
        } else {
            return null;
        }
    }

    /**
     * [empresa description]
     *
     * @param  integer $idEmpresa [description]
     *
     * @return [type]             [description]
     */
    public function empresa ($idEmpresa = 0)
    {
        settype($idEmpresa, 'integer');
        if ($idEmpresa == 0) {
            return null;
        }

        $this->db->select('razonSocial');
        $this->db->where('idEmpresa', $idEmpresa);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_EMPRESA);

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['razonSocial'];
        }

        return null;
    }

    /**
     * Verifica si existe informacion delservidor de una unidad de negocio
     *
     * @param integer $idUn Id de unidad de negocio a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function existeRegistroServidor ($idUn)
    {
        $idUnServidorLocal = 0;

        if ($idUn == 0) {
            return $idUnServidorLocal;
        }

        $this->db->select('idUnServidorLocal')->where('idUn', $idUn);
        $query = $this->db->get(TBL_UNSERVIDORLOCAL);

        if ($query->num_rows > 0) {
            $row = $query->row();
            $idUnServidorLocal = $row->idUnServidorLocal;
        }
        return $idUnServidorLocal;
    }

    /**
     * Guarda Datos de nueva gerencia
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function guardaGerencias($idPuesto, $idPersona, $idUn)
    {
        settype($idPuesto, 'integer');
        settype($idPersona, 'integer');
        settype($idUn, 'integer');

        $this->db->select('codigo');
        $this->db->from(TBL_PUESTO);
        $this->db->where('idPuesto',$idPuesto);
        $query = $this->db->get();
        $row = $query->row();
        $codigo=$row->codigo;

        $datos = array (
            'idUn'      => $idUn,
            'idPersona' => $idPersona,
            'idPuesto'  => $idPuesto,
            'codigo'    => $codigo
        );
        $this->db->insert(TBL_UNGERENTE, $datos);

        if ( $this->db->affected_rows() > 0 ) {
            $regresa=1;
            $this->permisos_model->log('Se registra gerente para el club ('.$idUn.')', LOG_UNIDADNEGOCIO);
        } else {
            $regresa=0;
        }
        return $regresa;
    }

    /**
     * Cambia la IP de conexion para el club indicado
     *
     * @param  integer $idUn Identificador de club
     * @param  string  $ip   Ip a asignar
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function guardarIP($idUn, $ip,$idTipoServicioIp)
    {
        settype($idUn, 'integer');

        if ($idUn>0) {
            $set   = array('ip' => $ip);
            $where = array('idUn' => $idUn,'idTipoServicioIp' => $idTipoServicioIp);
            if($idTipoServicioIp == 3){
                $dataUnServidor = $this->obtenerDatosUnServidor($idUn, $idTipoServicioIp);
                if(count($dataUnServidor) > 0){
                    $this->db->update(TBL_UNSERVIDOR, $set, $where);
                    if ($this->db->affected_rows()>0) {
                        $this->permisos_model->log(utf8_decode("Se actualizo IP para el club (".$idUn.")"),
                        LOG_UNIDADNEGOCIO);
                        return true;
                    } else {
                        return false;
                    }
                }else{
                    $dataUnServidor = array(
                        'idCategoriaIp' => 1,
                        'idTipoServicioIp'  => $idTipoServicioIp,
                        'idUn' => $idUn,
                        'ip' => $ip
                    );
                    $this->db->insert(TBL_UNSERVIDOR,$dataUnServidor);
                    $idUnservidor = $this->db->insert_id();
                    if($idUnservidor > 0){
                        $this->permisos_model->log(utf8_decode("Se Inserto IP para el club (".$idUn.")"),LOG_UNIDADNEGOCIO);
                        return true;
                    }
                }
            }else{
                $this->db->update(TBL_UNSERVIDOR, $set, $where);

                if ($this->db->affected_rows()>0) {
                    $this->permisos_model->log(utf8_decode("Se actualizo IP para el club (".$idUn.")"),
                    LOG_UNIDADNEGOCIO);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Registra una instalacion en una unidad de negocio
     *
     * @param integer $idUn          Id de la unidad de negocio
     * @param integer $idInstalacion Id de la instalación
     * @param integer $estatus       Estatus para el registro
     *
     * @author Diego Zambrano <diego.zambrano@sportsworld.com.mx>
     *
     *
     * @return boolean
     */
    public function guardaInstalacionUn($idUn = 0, $idInstalacion = 0, $estatus = 1)
    {
        settype($idUn, 'integer');
        settype($idInstalacion, 'integer');
        $res = false;

        if (! $idUn or ! $idInstalacion) {
            return $res;
        }

        $this->db->select('idUnInstalacion, activo');
        $this->db->from(TBL_UNINSTALACION);
        $this->db->where('idInstalacion', $idInstalacion);
        $this->db->where('idUn', $idUn);;
        $query = $this->db->get();

        if ($query->num_rows) {
            $fila = $query->row_array();
            $idUnInstalacion = $fila["idUnInstalacion"];
            $estatusActual   = $fila["activo"];

            if ($estatusActual == 0 and $estatus == 1) {
                $set   = array('activo' => $estatus);
                $where = array('idUnInstalacion' => $idUnInstalacion);
                $res   = $this->db->update(TBL_UNINSTALACION, $set, $where);
                $this->permisos_model->log('Se modifico el estatus activo de la instalacion ('.$idInstalacion.') en unidad ('.$idUn.')', LOG_UNIDADNEGOCIO);
            } elseif ($estatusActual == 1 and $estatus == 0) {
                $set   = array('activo' => $estatus);
                $where = array('idUnInstalacion' => $idUnInstalacion);
                $res   = $this->db->update(TBL_UNINSTALACION, $set, $where);
                $this->permisos_model->log('Se modifico el estatus activo de la instalacion ('.$idInstalacion.') en unidad ('.$idUn.')', LOG_UNIDADNEGOCIO);
            }
        } else {
            $set = array (
                'idUn'          => $idUn,
                'idInstalacion' => $idInstalacion,
                'activo'        => $estatus
            );
            $res = $this->db->insert(TBL_UNINSTALACION, $set);
            $this->permisos_model->log('Se agrego la instalacion ('.$idInstalacion.') a la unidad ('.$idUn.')', LOG_UNIDADNEGOCIO);
        }
        return $res;
    }

    /**
     * Guardar semana temporalidad
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function guardarSemanasTemporalidad($idUn, $anio ,$semana, $inicio,$fin)
    {
        settype($idUn, 'integer');
        settype($semana, 'integer');
        settype($anio, 'integer');

        $datos = array (
            'idUn'           => $idUn,
            'anio'           => $anio,
            'semana'         => $semana,
            'inicioVigencia' => $inicio,
            'finVigencia'    => $fin,
            'activo'         => 1
        );

        $this->db->select('idUnTemporalidadSemana');
        $this->db->from(TBL_UNTEMPORALIDADSEMANA);

        $where = array(
            'idUn'   =>$idUn,
            'anio'   => $anio,
            'semana' => $semana,
            'activo' => 1
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            return -1;
        } else {
            $this->db->insert(TBL_UNTEMPORALIDADSEMANA, $datos);
        }
        $id = $this->db->insert_id();
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se registro semana de temporalidad ('.$id.') ', LOG_UNIDADNEGOCIO);
            return $id;
        }
    }

    /**
     * Guarda la capacidad por tipo
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function guardarUnCapacidad($idUn, $tipo, $capacidad)
    {
        settype($idUn, 'integer');
        settype($tipo, 'integer');
        settype($capacidad, 'integer');

        $datos = array (
            'idUn'              => $idUn,
            'idTipoUnCapacidad' => $tipo,
            'capacidad'         => $capacidad
        );

        $this->db->select('idUnCapacidad');
        $this->db->from(TBL_UNCAPACIDAD);

        $where = array(
            'idUn'              => $idUn,
            'idTipoUnCapacidad' => $tipo,
            'fechaEliminacion'  => '0000-00-00 00:00:00'
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            return -1;
        } else {
            $this->db->insert(TBL_UNCAPACIDAD, $datos);
        }
        $id = $this->db->insert_id();
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return 0;
        } else {
            $this->permisos_model->log('Se registro una capacidad de  '.$capacidad.' del club '.$idUn.' con ID ('.$id.') ', LOG_UNIDADNEGOCIO);
            return $id;
        }
    }

    /**
     * Regresa el porcentaje de IVA que aplica para el club indicado
     *
     * @param integer $club Indentificador del club
     *
     * @author jorge Cruz
     *
     * @return float
     */
    public function iva($club = 0)
    {
        settype($club, 'integer');
        if ($club == 0) {
            return 0;
        }

        $this->db->select('iva');
        $this->db->where('idUn', $club);
        $query = $this->db->get(TBL_UNCONFIGURACIONFINANZAS);
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['iva'];
        }

        return 0;
    }

    /**
     * Busca los clubs con estatus de activos y regresa un array con el id y el nombre del club
     *
     * @param  integer $idEmpresa Id de la empresa a filtrar
     * @param  boolean $todos     Indica si se debe agrega administracion como opción de "Todos"
     * @param  string  $orden          [description]
     * @param  integer $admon          [description]
     * @param  integer $idOperador     [description]
     * @param  integer $tiposUn        [description]
     * @param  integer $validaPreventa [description]
     *
     * @author Jorge Cruz
     *
     * @return array - Regresa un array con la lista de clubs activos
     */
    public function listaActivos(
        $idEmpresa = 0,
        $todos = false,
        $orden = "nombre",
        $admon = 1,
        $idOperador = 0,
        $tiposUn = 2,
        $validaPreventa = 0,
        $idEmpresaGrupo = 0,
        $idRegion = 0)
    {
        settype($idEmpresa, 'integer');
        settype($admon, 'integer');
        settype($idOperador, 'integer');
        settype($idEmpresaGrupo, 'integer');

        $lista = array();
        $lista[''] = '[Seleccione un club]';

        if ($todos == true && $idEmpresa > 0) {
            $lista[0] = 'Todos';
        }
        if ($this->session->userdata('tipoClub')==1) {
            $this->db->select('un.idUn, un.nombre', false);
            $this->db->from(TBL_UN.' un');
            $this->db->where('activo', 1);
            $this->db->where('idTipoUN', 1);
            $this->db->where('fechaEliminacion', "0000-00-00 00:00:00");
            $this->db->order_by($orden);
            $rs = $this->db->get();
            if ($rs->num_rows>0) {
                foreach ($rs->result() as $fila) {
                    $lista[$fila->idUn] = $fila->nombre;
                }
            }
        }
        $this->db->select('u.idUn, u.nombre, e.razonSocial AS empresa');
        $this->db->join(TBL_EMPRESA.' e', 'e.idEmpresa=u.idEmpresa', 'INNER');
        $this->db->join(TBL_EMPRESAGRUPO.' eg', 'eg.idEmpresaGrupo=e.idEmpresaGrupo', 'INNER');
        if ($idEmpresa > 0) {
            $this->db->where('u.idEmpresa', $idEmpresa);
        }
        $this->db->where('u.activo', '1');
        if (is_array($tiposUn)) {
            $this->db->where_in('u.idTipoUn', $tiposUn);
        } else {
            $this->db->where('u.idTipoUn', $tiposUn);
        }
        $this->db->where('u.fechaEliminacion', '0000-00-00 00:00:00');

        if ($idOperador>0) {
            $this->db->where('u.idOperador', $idOperador);
        }
        if ($validaPreventa>0) {
            $this->db->where('u.fechaPreVenta<=CURDATE()');
        }
        if ($idRegion>0) {
            $this->db->where('u.idRegion', $idRegion);
        }

        if ($idEmpresaGrupo > 0) {
            $this->db->where('eg.idEmpresaGrupo', $idEmpresaGrupo);
        }
        $query = $this->db->order_by($orden)->get(TBL_UN.' u');

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idUn] = $fila->nombre;

                if ($idEmpresaGrupo!=0) {
                    $lista[$fila->idUn] = $lista[$fila->idUn].' ('.$fila->empresa.')';
                }
            }
        }

        if ($admon==0) {
            unset($lista[1]);
            unset($lista[28]);
            unset($lista[29]);
            unset($lista[5]);
        }

        if ($this->session->userdata('idEmpresaGrupo')!=1) {
            unset($lista[11]);
            unset($lista[35]);
        }

        return $lista;
    }

    /**
     * Busca los clubs administrativos
     *
     * @author Santa Garcia
     *
     * @return array - Regresa un array con la lista de clubs activos
     */
    public function listaActivosAdministrativos()
    {
        $lista = array();
        $lista['0'] = '';

        $this->db->select('idUn, nombre');
        $this->db->where('activo', '1');

        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->order_by('idTipoUn,idOperador,idEmpresa,orden,idUn')->get(TBL_UN);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idUn] = $fila->nombre;
            }
        }
        return $lista;
    }

    /**
     * Busca los clubs con estatus de activos y regresa un array con el id y el nombre del club concatenado con prefijofactura
     *
     * @param integer $idEmpresa Id de la empresa a filtrar
     * @param boolean $todos     Indica si se debe agrega administracion como opción de "Todos"
     *
     * @author Antonio Sixtos
     *
     * @return array - Regresa un array con la lista de clubs activos
     */
    public function listaActivosFinanzas()
    {
        $lista = array();
        $lista['0'] = '[Seleccione un club]';

        $sql="SELECT u.idUn, CONCAT_WS('-', u.nombre, ucf.prefijoFactura) AS nombre
            FROM un u
            INNER JOIN unconfiguracionfinanzas ucf ON ucf.idUn=u.idUn
            WHERE u.activo=1 ORDER BY nombre ASC";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idUn] = utf8_encode($fila->nombre);
            }
        }

        return $lista;
    }

    /**
     * Busca los clubs con estatus de activos y regresa un array con el id y el nombre del club
     *
     * @param integer $idEmpresa Id de la empresa a filtrar
     * @param boolean $todos     Indica si se debe agrega administracion como opción de "Todos"
     * @param string  $order
     *
     * @author Antonio Sixtos
     *
     * @return array - Regresa un array con la lista de clubs activos
     */
    public function listaActivosMultipleSelect($idEmpresa, $bandera= 0)
    {
        if($idEmpresa!='') {
            $idEmpresa = explode(',', $idEmpresa);
        }

        $lista = array();
        $this->db->select('idUn, nombre');
        $this->db->where('activo', '1');
        if ($bandera==0) { #nuevo
            $this->db->where('idTipoUn !=', '1');
        }

        $this->db->where('idTipoUn !=', '28');
        $this->db->where('idTipoUn !=', '29');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        if ($idEmpresa!='') {
            $this->db->where_in('idEmpresa', $idEmpresa);
        }
        $query = $this->db->get(TBL_UN);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idUn] = $fila->nombre;
            }
        }
        return $lista;
    }

    /**
     * [listaActivosTodos description]
     * @param  integer $idEmpresa [description]
     * @param  boolean $todos     [description]
     * @return [type]             [description]
     */
    public function listaActivosTodos($idEmpresa = 0, $todos = false, $idEmpresaGrupo=0)
    {
        settype($idEmpresa, 'integer');
        settype($idEmpresaGrupo, 'integer');

        $lista = array();
        $lista['0'] = '';

        $this->db->select('u.idUn, u.nombre');
        $this->db->from(TBL_UN.' u');
        $this->db->join(TBL_EMPRESA.' e', 'e.idEmpresa = u.idEmpresa');
        if ($idEmpresa > 0) {
            $this->db->where('u.idEmpresa', $idEmpresa);
        }

        if ($idEmpresaGrupo>0) {
            $this->db->where('e.idEmpresaGrupo', $idEmpresaGrupo);
        }

        $this->db->where('u.activo', '1');
        $this->db->where('u.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->order_by('u.fechaApertura, u.orden');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idUn] = $fila->nombre;
            }
        }
        return $lista;
    }

    /**
     * Busca los clubs con estatus de activos y regresa un array con el id y el nombre del club
     *
     * @param integer $idEmpresa Id de la empresa a filtrar
     * @param boolean $todos     Indica si se debe agrega administracion como opción de "Todos"
     *
     * @author Jorge Cruz
     *
     * @return array - Regresa un array con la lista de clubs activos
     */
    public function listaActivosxOperador($operador = 0, $idEmpresa = 0, $todos = false, $idEmpresaGrupo=0)
    {
        $lista = array();
        $lista['0'] = '[Seleccione un club]';

        if ($todos == true && $idEmpresa > 0) {
            $lista[0] = 'Todos';
        }

        if ($this->session->userdata('tipoClub')==1) {
            $this->db->select('un.idUn, nombre', false);
            $this->db->from(TBL_UN.' un');
            $this->db->join(TBL_EMPRESA.' e', 'e.idEmpresa=un.idEmpresa', 'INNER');
            $this->db->where('un.activo', 1);
            $this->db->where('un.idTipoUN', 1);
            $this->db->where('un.fechaEliminacion', '0000-00-00 00:00:00');
            $this->db->order_by('un.idUn');
            $rs = $this->db->get();

            if ($rs->num_rows>0) {
                foreach( $rs->result() as $fila ){
                    $lista[$fila->idUn] = $fila->nombre;
                }
            }
        }


        $this->db->select('un.idUn, un.nombre');
        if ($idEmpresa > 0) {
            $this->db->where('un.idEmpresa', $idEmpresa);
        }
        if ($operador > 0) {
            $this->db->where('un.idOperador', $operador);
        }
        if ($idEmpresaGrupo > 0) {
            $this->db->where('e.idEmpresaGrupo', $idEmpresaGrupo);
        }
        $this->db->from(TBL_UN.' un');
        $this->db->join(TBL_EMPRESA.' e', 'e.idEmpresa=un.idEmpresa', 'INNER');
        $this->db->where('un.activo', 1);
        $this->db->where('un.idTipoUn !=', 1);
        $this->db->where('un.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->order_by('orden');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idUn] = ($fila->nombre);
            }
        }

        return $lista;
    }

    /**
    * Lista Categoria de Ip
    *
    * @author Santa Garcia
    *
    * @return array
    */
    public function listaCategoriaIp()
    {
        $lista = array();
        $lista['0'] = '';

        $this->db->select('idCategoriaIp, nombre');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->order_by('nombre')->get(self::CATEGORIAIP);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idCategoriaIp] = $fila->nombre;
            }
        }

        return $lista;
    }

    /**
    * Lista las unidades de negocio por matenimiento
    *
    * @param integer $idEmpresa   identificador del concepto seleccionado
    * @param integer $idProducto  identificador del producto
    *
    * @author Santa Garcia
    *
    * @return array
    */
    public function listaClubMantenimiento($idEmpresa = 0, $idProducto = 0)
    {
        $this->db->distinct();
        $this->db->select('pu.idUn,u.nombre');
        $this->db->from(TBL_PRODUCTO.' p');
        $this->db->join(TBL_PRODUCTOMANTENIMIENTO.' pm', 'pm.idProducto=p.idProducto');
        $this->db->join(TBL_MANTENIMIENTO.' m', 'm.idMantenimiento=pm.idMantenimiento');
        $this->db->join(TBL_PRODUCTOUN.' pu', 'pu.idProducto=p.idProducto');
        $this->db->join(TBL_UN.' u', 'u.idUn=pu.idUn');
        $this->db->where('p.activo', 1);
        $this->db->where('u.idEmpresa', $idEmpresa);
        $this->db->where('pm.idProducto', $idProducto);
        $this->db->where('pu.activo', 1);
        $this->db->where('pu.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('u.activo', 1);
        $this->db->where('u.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->order_by('u.orden')->get();
        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $dato[0] = '';
                $dato[] = $fila;
            }
        }
        if (isset($dato)) {
             return $dato;
        } else {
            return null;
        }
    }

    /**
    * Lista Gerencias de clubs
    *
    * @author Santa Garcia
    *
    * @return array
    */
    public function listaGerencias($idUn)
    {
        $lista = array();
        $lista['0'] = 'Selecciona un puesto...';

        $this->db->select('idPuesto');
        $this->db->from(TBL_UNGERENTE);
        $this->db->where('idUn', $idUn);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $querypu = $this->db->get();

        $this->db->select('p.idPuesto, p.descripcion');
        $this->db->distinct();
        $this->db->from(TBL_PUESTO.' p');
        $this->db->join(TBL_EMPLEADOPUESTO.' ep', 'ep.idPuesto=p.idPuesto AND ep.idUn<>1', 'INNER');
        $this->db->like('p.descripcion', 'GERENTE', 'after');
        $this->db->where('p.idPuesto <>', 102);
        $this->db->where('p.idPuesto <>', 441);
        $this->db->where('p.idPuesto <>', 450);
        if($querypu->num_rows > 0) {
            foreach ($querypu->result() as $row) {
                $this->db->where('p.idPuesto <>', $row->idPuesto);
            }
        }
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idPuesto] = $fila->descripcion;
            }
        }

        return $lista;
    }

    /**
     * Lista Gerencias de clubs
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function listaPuestosRH()
    {
        $lista = array();

        $this->db->select('p.idPuesto, p.descripcion');
        $this->db->distinct();
        $this->db->from(TBL_PUESTO.' p');
        $this->db->join(TBL_EMPLEADOPUESTO.' ep', 'ep.idPuesto=p.idPuesto and ep.fechaEliminacion = \'0000-00-00 00:00:00\'', 'INNER');
        $puestos = array('223', '177', '443','786','597','157');
        $this->db->where_in('p.idPuesto ', $puestos);
        $this->db->where('p.fechaEliminacion ', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idPuesto] = $fila->descripcion;
            }
        }
        return $lista;
    }

    /**
    * Lista Servidor de Ip
    *
    * @author Santa Garcia
    *
    * @return array
    */
    public function listaServicioIp()
    {
        $lista = array();
        $lista['0'] = '';

        $this->db->select('idTipoServicioIp, nombre');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->order_by('nombre')->get(self::TIPOSERVICIOIP);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idTipoServicioIp] = $fila->nombre;
            }
        }

        return $lista;
    }

    /**
    * Lista tipo capacidad
    *
    * @author Santa Garcia
    *
    * @return array
    */
    public function listaTipoCapacidad()
    {
        $lista = array();

        $this->db->select('idTipoUnCapacidad, descripcion');
        $this->db->from(TBL_TIPOUNCAPACIDAD);
        $this->db->where('activo', '1');
        $query = $this->db->order_by('orden')->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idTipoUnCapacidad] = $fila->descripcion;
            }
        }
        return $lista;
    }

    /**
     * Obtiene una lista de todos los clubs incluyendo los de administracion
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function listaTodos ($administracion = true)
    {
        $datos = array();
        $where = array(
            'u.activo' => 1,
            'e.activo' => 1,
            'u.fechaEliminacion' => '0000-00-00 00:00:00',
            'e.fechaEliminacion' => '0000-00-00 00:00:00',
        );
        if ( ! $administracion) {
            $this->db->where('u.idTipoUn', 2);
        }
        $this->db->join(TBL_EMPRESA." e", "u.idEmpresa = e.idEmpresa", "inner");
        $query = $this->db->select(
            "u.idUn, u.idEmpresa, u.nombre AS nombre",
            false
        )->order_by('u.nombre')->get_where(TBL_UN." u", $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene una lista de todos los clubs incluyendo los de administracion
     *
     *
     * @return array
     */
    public function listaTodosClubes ()
    {
        $datos = array();

        $where = array(
            'u.activo' => 1,
            'e.activo' => 1,
            'u.fechaEliminacion' => '0000-00-00 00:00:00',
            'e.fechaEliminacion' => '0000-00-00 00:00:00',
        );
        $this->db->join(TBL_EMPRESA.' e', 'u.idEmpresa = e.idEmpresa', 'inner');
        $query = $this->db->select(
            'u.idUn, u.idEmpresa, u.nombre AS nombre',
            false
        )->order_by('u.nombre')->get_where(TBL_UN.' u', $where);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idUn] = $fila->nombre;
            }
        }
        return $lista;
    }

    /**
     * Procesa la tabla solicitada regresando el listado de Unidades de Negocio
     *
     * @param integer $orden     Campo sobre el cual se realizara el ordenamiento
     * @param integer $posicion  Especifica la posicion sobre
     * @param integer $elementos Define el numero de elementos a regresar en el array
     * @param integer $estatus   Tipo de estatus de unidad de negocio para filtrar
     * @param integer $empresa   Id de empresa para filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function listaUn($orden, $posicion = 0, $elementos = 25, $estatus = '', $empresa = '0', $nombre='', $clave='')
    {
        if($orden=='')
            $orden='un.orden';

        $this->db->select('un.idUn, un.nombre, un.clave, un.orden, un.activo AS estatus, e.razonSocial as empresa');
        $this->db->join(TBL_EMPRESA." e", 'un.idEmpresa = e.idEmpresa', 'left');
        $this->db->where('un.fechaEliminacion', '0000-00-00 00:00:00');
        if($estatus != '') {
            $this->db->where('un.activo', $estatus);
        }
        if($empresa != '0') {
            $this->db->where('un.idEmpresa', $empresa);
        }
         if ( $nombre != '' ) {
            $this->db->like('nombre', $nombre);
        }
        if ( $clave != '' ) {
            $this->db->like('clave', $clave);
        }
        $this->db->order_by($orden);
        $query = $this->db->get(TBL_UN." un", $elementos, $posicion);

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }


    /**
     * obtenCorreo - Regresa la cuenta de correo que tiene asignado el club
     *
     * @param  integer $idUn Identificador de club
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function obtenCorreo($idUn)
    {
        settype($idUn, 'integer');

        $correo = '';

        if ($idUn == 0) {
            return $correo;
        }

        $this->db->select('correo');
        $this->db->where('idUn', $idUn);
        $query = $this->db->get(TBL_UN);

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $correo = $fila['correo'];
        }
        return $correo;
    }


    /**
     * Obtiene clubs por region
     *
     * @param integer $idRegion Identificador de region
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenClubsRegion ($idRegion)
    {
        settype($idRegion, 'integer');

        $datos = array();

        if ( ! $idRegion) {
            return $datos;
        }
        $where = array(
            'u.fechaEliminacion'       => '0000-00-00 00:00:00',
            'u.idRegion'               => $idRegion,
            'u.idTipoUn'               => 2,
            'u.activo'                 => 1,
            'DATE(u.fechaApertura) <=' => 'DATE(NOW())'
        );
        $query = $this->db->select("u.idUn, u.nombre", false)->get_where(TBL_UN.' u', $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene los datos generales de ungerente
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function obtenDatosGerencia($idUnGerente)
    {
        settype($idUnGerente, 'integer');

        $this->db->from(TBL_UNGERENTE);
        $this->db->where('idUnGerente', $idUnGerente);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
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
     * Obtiene la informacion general de la unidad de negocio
     *
     * @param integer $idUn Id de unidad de negocio a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenDatosUn($idUn = 0)
    {
        settype($idUn, 'integer');

        $data = array();

        if ($idUn == 0) {
            return  $data;
        }

        $this->db->select(
            'idUn, nombre, clave, calle, numero, colonia, cp, '.
            'idEstado, idMunicipio, idEmpresa, idTipoUn, '.
            'fechaApertura, fechaPreVenta, activo, '.
            'idOperador, idTipoUn, idZonaHoraria, orden AS ordens, '.
            'bmv, disponibilidadLimite as disponibilidad, logo'
        );
        $this->db->where('idUn', $idUn);
        $queryDatos = $this->db->get(TBL_UN);

        foreach ($queryDatos->result_array() as $fila) {
            $data = $fila;
        }
        return $data;
    }

    /**
     * Obtiene datos de gerente general de un club
     *
     * @param integer $idUn Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenGerenteGeneral ($idUn)
    {
        settype($idUn, 'integer');
        $datos = array();

        if ( ! $idUn) {
            return $datos;
        }
        $where = array(
            'ug.idUn'              => $idUn,
            'ug.fechaEliminacion'  => '0000-00-00 00:00:00',
            'u.fechaEliminacion'   => '0000-00-00 00:00:00',
            'per.fechaEliminacion' => '0000-00-00 00:00:00',
            'per.fallecido'        => 0,
            'ug.idPuesto'          => PUESTO_GERENTE_GENERAL,
            'ug.codigo'            => CODIGO_GERENTE_GENERAL
        );
        $this->db->join(TBL_UNGERENTE.' ug', 'ug.idUn = u.idUn','inner');
        $this->db->join(TBL_PUESTO.' p', 'p.idPuesto = ug.idPuesto AND p.codigo = ug.codigo','inner');
        $this->db->join(TBL_PERSONA.' per', 'per.idPersona = ug.idPersona','inner');
        $query = $this->db->select(
            "per.idPersona, CONCAT_WS(' ',per.nombre, per.paterno, per.materno)AS nombre", false
        )->get_where(TBL_UN." u", $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Obtiene idUnInstalacion
     *
     * @param integer $idInstalacion Id de instalacion
     * @param integer $idUn          Id de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function obtenIdUnInstalacion($idInstalacion, $idUn)
    {
        settype($idInstalacion, 'integer');
        settype($idUn, 'integer');
        $idUnInstalacion = 0;

        if (($idInstalacion == 0) or ($idUn == 0)) {
            return $idUnInstalacion;
        }
        $where = array(
            "idInstalacion" => $idInstalacion,
            "idUn"          => $idUn
        );
        $this->db->select("idUnInstalacion");
        $query = $this->db->get_where(TBL_UNINSTALACION, $where);

        if ($query->num_rows > 0) {
            $idUnInstalacion = $query->row()->idUnInstalacion;
        }
        return $idUnInstalacion;
    }

    /**
     * Obtiene Nombre de Gerente administrativo
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenNombreGteAdmin ($idUn, $id=0)
    {
        settype($idUn, 'integer');
        $nombre = '';

        $sql="SELECT p.idPersona, concat_ws(' ',p.nombre, p.paterno, p.materno)as nombre from persona p
            LEFT JOIN ungerente ug on ug.idpersona=p.idpersona
            WHERE ug.idUn=".$idUn." and ug.idpuesto=100 and ug.fechaEliminacion='0000-00-00 00:00:00'";

        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            if( $id==0 ){
                $nombre = $query->row()->nombre;
            }else{
                $nombre = $query->row()->idPersona;
            }
        }
        return $nombre;
    }

    /**
     * Obtiene Nombre de Operador
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenNombreOperador ($idOperador)
    {
        settype($idOperador, 'integer');
        $nombre = '';

        if ($idOperador == 0) {
            return $nombre;
        }
        $where = array('idOperador' => $idOperador);
        $query = $this->db->select('razonSocial')->get_where(TBL_OPERADOR, $where);

        if ($query->num_rows > 0) {
            $nombre = $query->row()->razonSocial;
        }
        return $nombre;
    }

    /**
     * Obtiene Nombre de Unidad Deportiva
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenNombreUn ($idUn)
    {
        settype($idUn, 'integer');
        $nombre = '';

        if ($idUn == 0) {
            return $nombre;
        }
        $where = array('idUn' => $idUn);
        $query = $this->db->select('nombre')->get_where(TBL_UN, $where);

        if ($query->num_rows > 0) {
            $nombre = $query->row()->nombre;
        }
        return $nombre;
    }

    /**
     * Obtiene operadores de unidades de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenOperadores ()
    {
        $datos = array();

        $this->db->select('idOperador, razonSocial', false)->where('activo', 1);
        $this->db->where('fechaEliminacion', '0000-00-00');
        $query = $this->db->get(TBL_OPERADOR);

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $datos[$fila['idOperador']] = $fila['razonSocial'];
            }
        }
        return $datos;
    }

    /**
     * [obtenOrdenConsecutivo description]
     *
     * @return [type] [description]
     */
    public function obtenOrdenConsecutivo()
    {
        $orden = '';

        $sql = "SELECT un.orden+2 AS orden FROM un
            WHERE fechaEliminacion='0000-00-00 00:00:00'
            ORDER BY 1 DESC LIMIT 1";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            $orden = $query->row()->orden;
        }
        return $orden;
    }

    /**
     * Obtiene regiones
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenRegiones ()
    {
        $datos = array();
        $where = array('r.activo' => 1, 'r.idRegion > ' => 0);
        $query = $this->db->select(
            "r.idRegion, r.descripcion AS region", false
        )->order_by('r.orden')->get_where(TBL_REGION.' r', $where);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idRegion] = $fila->region;
            }
        }
        return $datos;
    }

    /**
     * Obtiene los tipos de unidades de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTiposUn()
    {
        $this->db->select('idTipoUn, descripcion');
        $query = $this->db->get(TBL_UNTIPO);

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $data[$fila['idTipoUn']] = $fila['descripcion'];
            }
        }
        return $data;
    }

    /**
     * Obtiene el id de una unidad de negocio tipo administracion segun la empresa
     *
     * @param integer $idEmpresa Id de la empresa a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public static function obtenUnAdiministracion ($idEmpresa = 0)
    {
        $idUn = 0;

        if ($idEmpresa == 0) {
            return $idUn;
        }
        
        $query = DB::connection('crm')->table(TBL_UN)
        ->select('idUn')
        ->where('idEmpresa', $idEmpresa)
        ->where('idTipoUn', 1)
        ->where('fechaEliminacion', '0000-00-00 00:00:00')->get()->toArray();
        
        if (count($query) > 0) {
            $idUn = $query[0]->idUn;
        }
        return $idUn;
    }

    /**
     * Obtiene la zona horaria de un club
     *
     * @param integer $idUn Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenZonaHoraria ($idUn)
    {
        settype($idUn, 'integer');

        $datos = array();
        $datos['idZonaHoraria'] = ID_ZONA_HORARIA_DEFAULT;
        $datos['zonaHoraria']   = ZONA_HORARIA_DEFAULT;

        if (! $idUn) {
            return $datos;
        }
        $this->db->join(TBL_ZONAHORARIA." zh", "u.idZonaHoraria = zh.idZonaHoraria", "inner");
        $where = array(
            'u.activo'           => 1,
            'u.fechaEliminacion' => '0000-00-00 00:00:00',
            'u.idUn'             => $idUn
        );
        $query = $this->db->select(
            "u.idZonaHoraria, zh.zonaHoraria"
        )->get_where(TBL_UN." u", $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Obtiene zonas horarias
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenZonasHorarias()
    {
        $datos = array();
        $query = $this->db->get(TBL_ZONAHORARIA);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * [obtenerClub description]
     *
     * @param  integer $idUn [description]
     *
     * @return [type]        [description]
     */
    public function obtenerClub($idUn = 0)
    {
        if ($idUn == 0) {
            return null;
        }

        $this->db->select('nombre');
        $this->db->where('idUn', $idUn);
        $query = $this->db->get(TBL_UN);

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $club = $fila['nombre'];
        }
        return $club;
    }

    /**
     * Obtiene datos del servidor por unidad de negocio
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerDatosServidor($idUn)
    {
        settype($idUn, 'integer');

        $this->db->select('ip, usuario, password, nombreBD, puerto');
        $this->db->from(TBL_UNSERVIDOR);
        $where = array('idUn' => $idUn);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows()>0) {
            return $query->row_array();
        } else {
            return false;
        }
    }

    /**
     * Obtiene id de empresa dependiendo de id de unidad de negocio
     *
     * @param integer $idUn Id de unidad de negocio a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public static function obtenerEmpresa($idUn = 0)
    {
        $idEmpresa = 0;

        if ($idUn == 0) {
            return $idEmpresa;
        }
        $query = DB::connection('crm')->table(TBL_UN)
        ->select('idEmpresa')
        ->where('idUn', $idUn)->get()->toArray();
        
        if (count($query) > 0) {
            $fila = $query[0];
            $idEmpresa = $fila->idEmpresa;
        }
        return $idEmpresa;
    }

    /**
     * [obtenerEmpresaGrupo]
     *
     * @param integer $idUn
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function obtenerEmpresaGrupo($idUn = 0)
    {
        $idEmpresaGrupo = 0;

        if ($idUn == 0) {
            return $idEmpresaGrupo;
        }

        $this->db->select('eg.idEmpresaGrupo');
        $this->db->join(TBL_EMPRESA.' e', 'e.idEmpresa=u.idEmpresa');
        $this->db->join(TBL_EMPRESAGRUPO.' eg', 'eg.idEmpresaGrupo=e.idEmpresaGrupo');
        $this->db->where('u.idUn', $idUn);
        $this->db->where('u.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_UN.' u');

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $idEmpresaGrupo = $fila['idEmpresaGrupo'];
        }
        return $idEmpresaGrupo;
    }

    /**
     * Obtiene información general de una aplicacion
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtenerInfoAplicacion($idUn, $aplicacion = '')
    {
        settype($idUn, 'integer');

        $this->db->select('a.idAplicacion,ta.descripcion,a.version,a.fechaRegistro,a.fechaActualizacion');
        $this->db->from(TBL_APLICACION.' a');
        $this->db->join(TBL_TIPOAPLICACION .' ta', 'ta.idTipoAplicacion = a.idTipoAplicacion');
        if($aplicacion != ''){
            $this->db->where('a.idTipoAplicacion', $aplicacion);
        }
        if($idUn > 0){
            $this->db->where('a.idUn', $idUn);
        }
        $this->db->where('ta.activo', '1');
        $this->db->where('a.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obtiene id del operado dependiendo de id de unidad de negocio
     *
     * @param integer $idUn Id de unidad de negocio a filtrar
     *
     * @author Gustavo Bonilla
     *
     * @return array
     */
    public function obtenerOperador($idUn = 0)
    {
        $idOperador = 0;

        if ($idUn == 0) {
            return $idOperador;
        }

        $this->db->select('idOperador');
        $this->db->where('idUn', $idUn);
        $query = $this->db->get(TBL_UN);

        if ($query->num_rows > 0) {
            $fila      = $query->row_array();
            $idOperador = $fila['idOperador'];
        }
        return $idOperador;
    }

    /**
     * Busca el club administrativo del operador
     *
     * @author Santa Garcia
     *
     */
    public function obtieneClubAdministrativo($operador)
    {
        $lista = array();
        $lista['0'] = '';

        $this->db->select('idUn, nombre');
        $this->db->where('activo', '1');
        $this->db->where('idTipoUn', '1');
        $this->db->where('idOperador', $operador);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->order_by('nombre')->get(TBL_UN);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
               return $fila->idUn;
            }
        } else{
            if ($operador == 5) {
                return 11;
            }
            if ($operador == 9) {
                return 7;
            }

            return 0;
        }
    }

    /**
     * Obtiene todas las instalaciones para una unidad de negocio
     *
     * @param integer $idUn Id de la unidad de negocio a buscar
     *
     * @author Diego Zambrano <diego.zambrano@sportsworld.com.mx>
     *
     *
     * @return array
     */
    public function obtieneUnInstalaciones ($idUn = 0)
    {
        $unArray = array();

        if ($idUn == 0) {
            return $unArray;
        }

        $this->db->select('idInstalacion');
        $this->db->where('idUn', $idUn);
        $this->db->where('activo', 1);
        $query = $this->db->get(TBL_UNINSTALACION);

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $unArray[] = $fila['idInstalacion'];
            }
        }
        return $unArray;
    }

    /**
     * Obtiene todas las unidades de negocio que no son admin
     * de una determinada empresa
     *
     * @param integer $idEmpresa Id de la unidad de negocio a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtieneUnTipoClubs ($idEmpresa = 0)
    {
        $unArray = array();

        if ($idEmpresa == 0) {
            return $unArray;
        }

        $this->db->select('idUn');
        $this->db->where('idEmpresa', $idEmpresa);
        $this->db->where('idTipoUn', 2);
        $this->db->where('activo', 1);
        $query = $this->db->get(TBL_UN);

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $unArray[] = $fila['idUn'];
            }
        }
        return $unArray;
    }

    /**
    * Muestra las gerencias ligadas a la inudad de negocio
    *
    * @author Antonio Sixtos
    *
    * @return array
    */
    public function muestraGerencias($idUn)
    {
        $sql = "SELECT g.idUnGerente, CONCAT(p.nombre,' ',p.paterno,' ',p.materno) AS nombre, pu.descripcion
            FROM ungerente g
            LEFT JOIN persona p ON p.idPersona=g.idPersona
            LEFT JOIN puesto pu ON pu.idPuesto=g.idPuesto
            WHERE g.fechaEliminacion='0000-00-00 00:00:00' AND idUn=".$idUn;

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

    public function obtenerDatosUnServidor($idUn,$idTipoServicioIp){
        $query = $this->db->from(TBL_UNSERVIDOR)
                ->where('idUn',$idUn)
                ->where('idTipoServicioIp',$idTipoServicioIp)
                ->get();
        return $query->result();
    }

    /**
     * Regresa el nombre del club solicitado
     *
     * @param integer $club Identificador del club
     *
     * @return string
     */
    public static function nombre($club = 0)
    {
        settype($club, 'integer');
        if ($club == 0) {
            return null;
        }
        $query = DB::connection('crm')->table(TBL_UN)
        ->select('nombre')
        ->where('idUn', $club);
        if ($query->count() > 0) {
            $fila = ($query->get()->toArray())[0];
            return $fila['nombre'];
        }
        return null;
    }


    /**
     * [nombreEmpresaGrupo description]
     *
     * @param  [type] $idEmpresaGrupo [description]
     *
     * @return [type]                 [description]
     */
    public function nombreEmpresaGrupo($idEmpresaGrupo)
    {
        settype($idEmpresaGrupo, 'integer');

        $nombre = '';
        if($idEmpresaGrupo==0) {
            return $nombre;
        }

        $this->db->select('descripcion');
        $this->db->where('idEmpresaGrupo', $idEmpresaGrupo);
        $query = $this->db->get(TBL_EMPRESAGRUPO);
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['descripcion'];
        }

        return $nombre;
    }

    /**
     * [region description]
     *
     * @param  integer $idUn Identificado de club
     *
     * @author Jorge Cruz
     *
     * @return integer       [description]
     */
    public function region($idUn)
    {
        settype($idUn, 'integer');

        $region = 0;
        if ($idUn>0) {
            $this->db->select('idRegion');
            $this->db->from(TBL_UN);
            $this->db->where('idUn', $idUn);
            $query = $this->db->get();
            if ($query->num_rows > 0) {
                $fila = $query->row_array();
                return $fila['idRegion'];
            }
        }

        return $region;
    }



    /**
     * [responsableRegion description]
     *
     * @param  [type] $idPersona [description]
     *
     * @return [type]            [description]
     */
    public function responsableRegion($idPersona)
    {
        settype($idPersona, 'integer');

        $region = 0;
        if ($idPersona>0) {
            $this->db->select('idRegion');
            $this->db->from(TBL_REGION);
            $this->db->where('idPersonaGerente', $idPersona);
            $query = $this->db->get();
            if ($query->num_rows > 0) {
                $fila = $query->row_array();
                $region = $fila['idRegion'];
            }
        }

        return $region;
    }


	/**
     * Valida si un club esta en preventa
     *
     * @param integer $idUn Id de la unidad de negocio a buscar
     *
     * @author Santa Garcia
     *
     * @return integer
     */
    public function unPreventa ($idUn = 0)
    {
        $query = $this->db->select('idUn,nombre');
        if($idUn > 0){
            $query = $this->db->where('idUn', $idUn);
        }
        $query = $this->db->where('idTipoUn', 2);
        $query = $this->db->where('activo', 1);
        $query = $this->db->where('fechaApertura >', date('Y-m-d'));
        $query = $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_UN);

        if ($query->num_rows > 0) {
            return $query->result_array();
        }
        return 0;
    }

    /**
     * Obtiene los numeros telefonicos de una determinada unidad de negocio
     *
     * @param integer $idUn Id de unidad de negocio a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function telefonosUn ($idUn = 0)
    {
        $data = array();

        if ($idUn == 0) {
            return  $data;
        }

        $ci =& get_instance();
        $ci->load->model('telefonos_model');
        $tipoTelefonoTabla = $ci->telefonos_model->obtenNombreTabla();
        $this->db->select(
            'untelefono.idUnTelefono, tipotelefono.descripcion,'.'
            untelefono.numTelefono, tipotelefono.idTipoTelefono'
        );
        $this->db->where('untelefono.idUn', $idUn);
        $this->db->join($tipoTelefonoTabla, TBL_UNTELEFONO.'.idTipoTelefono = '.$tipoTelefonoTabla.'.idTipoTelefono', 'left');
        $queryTels = $this->db->get(TBL_UNTELEFONO);

        if ($queryTels->num_rows > 0 ) {
            foreach ($queryTels->result() as $fila) {
                    $data[] = $fila;
            }
        }
        return $data;
    }

    /**
     * Obtiene el total de afiliaciones de una determinada unidad de negocio
     *
     * @param integer $idUn Id de unidad de negocio a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function totalAfiliaciones ($idUn = 0)
    {
        $totalTels = 0;

        if ($idUn == 0) {
            return  $totalTels;
        }

        $this->db->select("idUnAfiliacion");
        $this->db->where("idUn", $idUn);
        $queryTotal = $this->db->get(TBL_UNAFILIACION);

        $totalTels = $queryTotal->num_rows;

        return $totalTels;
    }

    /**
     * Obtiene el total de registros de telefonos de las unidades de negocio
     *
     * @param integer $idUn Id de unidad de negocio a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function totalTelefonos ($idUn = 0)
    {
        $totalTels = 0;

        if ($idUn == 0) {
            return  $totalTels;
        }

        $this->db->select("idUnTelefono");
        $this->db->where("idUn", $idUn);
        $queryTotal = $this->db->get(TBL_UNTELEFONO);

        $totalTels = $queryTotal->num_rows;

        return $totalTels;
    }

    /**
     * Obtiene el total de Unidades de Negocio
     *
     * @param integer $estatus Tipo de estatus de unidad de negocio para filtrar
     * @param integer $empresa Id de empresa para filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function totalUn($estatus = '', $empresa = '0', $nombre='', $clave='')
    {
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        if($estatus != '') {
            $this->db->where('activo', $estatus);
        }
        if ( $empresa != '0' ) {
            $this->db->where('idEmpresa', $empresa);
        }
        if ( $nombre != '' ) {
            $this->db->like('nombre', $nombre);
        }
        if ( $clave != '' ) {
            $this->db->like('clave', $clave);
        }
        $query = $this->db->get(TBL_UN);

        return $query->num_rows;
    }

    /**
     * Valida si una unidad de negocion es tipo adminsitracion
     *
     * @param integer $idUn Id de la unidad de negocio a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function validaUnAdiministracion ($idUn)
    {
        $idEmpresa = 0;

        if ($idUn == 0) {
            return $idEmpresa;
        }

        $query = $this->db->select('idEmpresa');
        $query = $this->db->where('idUn', $idUn);
        $query = $this->db->where('idTipoUn', 1);
        $query = $this->db->where('activo', 1);
        $query = $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_UN);

        if ($query->num_rows > 0) {
            $row = $query->row_array();
            $idEmpresa = $row['idEmpresa'];
        }
        return $idEmpresa;
    }

    public function obtenerGerente($idUn,$claveGerente)
    {
        $query = $this->db->select('ug.idPersona, un.idUn, p.nombre,p.paterno,p.materno, m.mail')
                ->from('crm.ungerente ug')
                ->join('crm.un','un.idUn =  ug.idUn and year(un.fechaEliminacion) = 0')
                ->join('crm.persona p','p.idPersona =  ug.idPersona and year(p.fechaEliminacion) = 0')
                ->join('crm.mail m','p.idPersona = m.idPersona and m.idTipoMail =  37 and year(m.fechaEliminacion) = 0')
                ->where('year(ug.fechaEliminacion)','0')
                ->where('ug.codigo',$claveGerente)
                ->where('ug.idUn',$idUn)
                ->get();
        $rs = $query->row_array();
        $rs['nombreGerente'] =  $rs['nombre']." ".$rs['paterno']." ".$rs['materno'];
        return $query->row_array();
    }
}
