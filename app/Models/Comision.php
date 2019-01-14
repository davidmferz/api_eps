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

class Comision extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.comision';
    protected $primaryKey = 'idComision';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * Actualiza responsable de comision
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function actaluzaResponsable($idPersona, $idComision)
    {
        settype($idComision, 'integer');
        settype($idPersona, 'integer');

        $ci =& get_instance();
        $ci->load->model('persona_model');
        $nombrePersona = $ci->persona_model->nombre($this->session->userdata('idPersona'));

        $this->db->select('idComision');
        $this->db->from(TBL_COMISION);
        $where = array(
            'idComision' => $idComision,
            'eliminado'  => 0
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idComision', $fila->idComision);
                $datos = array ('idPersona' => $idPersona);
                $this->db->update(TBL_COMISION, $datos);
                $this->permisos_model->log("Actualizando responsable de comisiones, realizado por ($nombrePersona) (".date('Y-m-d').")", LOG_COMISIONES);
                return true;
            }
        } else {
            return 0;
        }
    }

    /**
     * Cambia fecha de comision, fecha de liberacion, emision, aplica, etc.
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function actualizafechaComision($idComision, $fecha, $tipoFecha)
    {
        settype($idComision, 'integer');

        $ci =& get_instance();
        $ci->load->model('persona_model');
        $nombrePersona = $ci->persona_model->nombre($this->session->userdata('idPersona'));

        $this->db->select('idComision');
        $this->db->from(TBL_COMISION);
        $where = array(
            'idComision' => $idComision,
            'eliminado'  => 0
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idComision', $fila->idComision);
                $datos = array ($tipoFecha => $fecha);
                $this->db->update(TBL_COMISION, $datos);
                $this->permisos_model->log("Actualizando fecha de comision, realizado por ($nombrePersona) (".date('Y-m-d').")", LOG_COMISIONES);
                return true;
            }
        } else {
            return 0;
        }
    }

    /**
     * Actualiza el estatus de la comision, reasigna  o elimina
     *
     * @param string $datos Contiene un array con los datos a actualizar
     *
     * @return boolean
     */
    public function actualizaEstatus($comision, $estatus, $comentario = '', $origen='')
    {
        settype($comision, 'integer');

        if ($comision ==0) {
            return false;
        }
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $nombrePersona = $ci->persona_model->nombre($this->session->userdata('idPersona'));

        $datos['idTipoEstatusComision'] = $estatus;
        if ($estatus==4) {
            $datos['fechaEliminacion'] = date("Y-m-d H:i:s");
            if ($comentario=='') {
                $comentario = 'Se elimina comision';
            }
        }

        $this->db->where('idComision', $comision);
        $this->db->where('idTipoEstatuscomision <>', ESTATUS_COMISION_PAGADA);
        $this->db->where('eliminado', 0);
        $this->db->update(TBL_COMISION, $datos);
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        }

        $estatus = 0;
        $this->db->select('idTipoEstatusComision');
        $this->db->from(TBL_COMISION);
        $this->db->where('idComision', $comision);
        $this->db->where('eliminado', 0);
        $query2 = $this->db->get();
        if ($query2->num_rows > 0) {
            $estatus = $query2->row()->idTipoEstatusComision;
        }

        if ($estatus!=ESTATUS_COMISION_PAGADA) {
            unset($datos);
            $datos = array (
                'idComision'            => $comision,
                'idPersona'             => $this->session->userdata('idPersona'),
                'idTipoEstatusComision' => $estatus,
                'comentario'            => $comentario.' '.$origen
            );
            $this->db->insert(TBL_COMISIONDETALLE, $datos);
            $this->permisos_model->log("Actualizando estatus de comisiones, realizado por ($nombrePersona) (".date('Y-m-d').")", LOG_COMISIONES);

            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * @param type $opciones
     * @return int
     */
    public function  actualizaPeriodos($opciones)
    {
        $sql="SELECT fechaInicio, fechaFin FROM ".TBL_COMISIONPERIODO." WHERE fechaEliminacion='0000-00-00 00:00:00'";
        $query = $this->db->query($sql);

        $fi= strtotime($opciones['fechainicio']);
        $ff= strtotime($opciones['fechafin']);
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $fechaI = $fila->fechaInicio;
                $fechaF = $fila->fechaFin;

                $fIbd = strtotime($fechaI);
                $fFbd = strtotime($fechaF);

                if ($fi > $fIbd && $fi < $fFbd) {
                    return 2;
                }
                if ($ff > $fIbd && $ff < $fFbd) {
                    return 3;
                }
            }

            $data = array(
                'idPersona'   => $this->session->userdata('idPersona'),
                'fechaInicio' => $opciones['fechainicio'],
                'fechaFin'    => $opciones['fechafin'],
                'fechaCorte'  => $opciones['fechacorte']
             );

            $this->db->where('idComisionPeriodo', $opciones['idComisionPeriodo']);
            $this->db->update('comisionperiodo', $data);

            if ($this->db->affected_rows()>0) {
                return 1;
            } else {
                return 1;
            }
        }
    }

    /**
     *
     * @param type $comision
     * @param type $regla
     * @return boolean
     */
    public function actualizaRegla($comision, $regla)
    {
        settype($comision, 'integer');
        if ($comision ==0) {
            return false;
        }
        $ci =& get_instance();
        $ci->load->model('persona_model');
        $nombrePersona = $ci->persona_model->nombre($this->session->userdata('idPersona'));

        $datos['regla'] = $regla;

        $this->db->where('idComision', $comision);
        $this->db->where('eliminado', 0);
        $this->db->update(TBL_COMISION, $datos);
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        }

        $this->permisos_model->log("Actualizando regla de comisiones, realizado por ($nombrePersona) (".date('Y-m-d').")", LOG_COMISIONES);
        return true;
    }

    /**
     * Agrega una comision
     *
     * @param integer $idPersona             Identificador de persona
     * @param integer $idUn                  Identificador de club
     * @param string  $descripcion           Descripcion de la comicion
     * @param string  $fechaAplica           Fecha aplica comision
     * @param integer $idTipoEstatusComision Estatus de la comision
     * @param string  $importe               Importe de la comision
     * @param string  $porcentaje            Porcentaje de la comision
     * @param string  $montoComision         Monto de la comision
     * @param integer $idTipoComision        Tipo de comision
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function agregarComision ($idPersona, $idUn, $descripcion, $fechaAplica, $idTipoEstatusComision,
        $importe, $porcentaje, $montoComision, $idTipoComision)
    {
        settype($idPersona, 'integer');
        settype($idUn, 'integer');
        settype($descripcion, 'string');
        settype($fechaAplica, 'string');
        settype($idTipoEstatusComision, 'integer');
        settype($importe, 'string');
        settype($porcentaje, 'string');
        settype($montoComision, 'string');
        settype($idTipoComision, 'integer');

        $importe = number_format($importe, 2, '.', '');
        $porcentaje = number_format($porcentaje, 2, '.', '');
        $montoComision = number_format($montoComision, 2, '.', '');

        $datos = array(
            'error'      => 1,
            'mensaje'    => 'Error faltan datos',
            'idComision' => 0
        );

        if (!$idPersona or !$idUn or !$fechaAplica or !$idTipoComision) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';
        $set = array(
            'idPersona'             => $idPersona,
            'idUn'                  => $idUn,
            'descripcion'           => $descripcion,
            'fechaAplica'           => $fechaAplica,
            'idTipoEstatusComision' => $idTipoEstatusComision,
            'importe'               => $importe,
            'porcentaje'            => $porcentaje,
            'montoComision'         => $montoComision,
            'idTipoComision'        => $idTipoComision,
            'manual'                => 1
        );
        if ($this->db->insert(TBL_COMISION, $set)) {
            $datos['idComision'] = $this->db->insert_id();
            $this->permisos_model->log('Se inserto Comision', LOG_COMISIONES);
        }
        return $datos;
    }

    /**
     * Funcion que devuelve los datos de un detalle o de los detalles de una ReglaComision
     *
     * @param integer $idComisionRegla Es el id de la Regla comision
     *
     * @return $id del registro que se inserto/actualizo
     */
    public function arrayDetalles($idComisionRegla)
    {
        $data = array();

        $this->db->select('idComisionReglaDetalle, numeroMaximo, numeroMinimo, montoMinimo, montoMaximo, '.
            'usarPorcentaje, porcentaje, monto, porcentajeMeta, objetivo',
            false
        );
        $this->db->from(TBL_COMISIONREGLADETALLE);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idComisionRegla', $idComisionRegla);
        $query = $this->db->order_by('numeroMinimo, montoMinimo')->get('');
        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
            return $data;
        }

        return $data;
    }

    /**
     * [arrayMeta description]
     * @param  integer $idUn [description]
     * @param  integer $anio [description]
     * @param  integer $mes  [description]
     * @return array         [description]
     */
    public function arrayMeta ($idUn, $anio, $mes, $idTipoComision)
    {
        settype($idUn, 'integer');

        $sql = "SELECT crd.numeroMinimo AS numero, crd.montoMinimo AS monto, crd.q1, crd.q2
            FROM un u
            INNER JOIN comisionregla cr ON cr.idUn=u.idUn AND cr.anio=$anio AND cr.idTipoQuincena=3
                AND cr.idTipoComision=".$idTipoComision." AND cr.mes=$mes
            INNER JOIN comisionregladetalle crd ON crd.idComisionRegla=cr.idComisionRegla
                AND crd.fechaEliminacion='0000-00-00 00:00:00' AND objetivo=1
            WHERE u.activo=1 AND u.idUn=$idUn";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            return $query->row_array();
        } else {
            $datos['numero'] = 0;
            $datos['monto'] = 0;
            $datos['q1'] = 0;
            $datos['q2'] = 0;

            return $datos;
        }
    }


    /**
     * [arrayMetasMes description]
     *
     * @param  [type] $idRegion [description]
     * @param  [type] $anio     [description]
     *
     * @return [type]           [description]
     */
    public function arrayMetasMes($idRegion, $anio, $idTipoComision)
    {
        settype($idRegion, 'integer');
        settype($anio, 'integer');

        $datos = array();

        $sql = "CREATE TEMPORARY TABLE tmptotcomclub_x
            SELECT u.idUn, cr.mes, crd.numeroMinimo AS numero, crd.montoMinimo AS monto
            FROM un u
            INNER JOIN empresa e ON e.idEmpresa=u.idEmpresa
            LEFT JOIN comisionregla cr ON cr.idUn=u.idUn AND cr.anio=$anio AND cr.idTipoQuincena=3
                AND cr.idTipoComision=".$idTipoComision."
            LEFT JOIN comisionregladetalle crd ON crd.idComisionRegla=cr.idComisionRegla
                AND crd.fechaEliminacion='0000-00-00 00:00:00' AND objetivo=1
            WHERE u.idTipoUn IN (2,4) AND u.activo=1";
        if ($idRegion>0) {
            $sql .= " AND u.idRegion=".$idRegion;
        }
		if ($this->session->userdata('idEmpresaGrupo')>0) {
            $sql .= " AND e.idEmpresaGrupo=".$this->session->userdata('idEmpresaGrupo');
        }
        //echo $sql;
        $this->db->query($sql);

        $sql = "CREATE TEMPORARY TABLE tmptotcomclub
            SELECT t.idUn, u.nombre,
            SUM(t.numero*(1-ABS(SIGN(mes-1)))) AS 'num1',
            SUM(t.numero*(1-ABS(SIGN(mes-2)))) AS 'num2',
            SUM(t.numero*(1-ABS(SIGN(mes-3)))) AS 'num3',
            SUM(t.numero*(1-ABS(SIGN(mes-4)))) AS 'num4',
            SUM(t.numero*(1-ABS(SIGN(mes-5)))) AS 'num5',
            SUM(t.numero*(1-ABS(SIGN(mes-6)))) AS 'num6',
            SUM(t.numero*(1-ABS(SIGN(mes-7)))) AS 'num7',
            SUM(t.numero*(1-ABS(SIGN(mes-8)))) AS 'num8',
            SUM(t.numero*(1-ABS(SIGN(mes-9)))) AS 'num9',
            SUM(t.numero*(1-ABS(SIGN(mes-10)))) AS 'num10',
            SUM(t.numero*(1-ABS(SIGN(mes-11)))) AS 'num11',
            SUM(t.numero*(1-ABS(SIGN(mes-12)))) AS 'num12',
            SUM(t.monto*(1-ABS(SIGN(mes-1)))) AS 'imp1',
            SUM(t.monto*(1-ABS(SIGN(mes-2)))) AS 'imp2',
            SUM(t.monto*(1-ABS(SIGN(mes-3)))) AS 'imp3',
            SUM(t.monto*(1-ABS(SIGN(mes-4)))) AS 'imp4',
            SUM(t.monto*(1-ABS(SIGN(mes-5)))) AS 'imp5',
            SUM(t.monto*(1-ABS(SIGN(mes-6)))) AS 'imp6',
            SUM(t.monto*(1-ABS(SIGN(mes-7)))) AS 'imp7',
            SUM(t.monto*(1-ABS(SIGN(mes-8)))) AS 'imp8',
            SUM(t.monto*(1-ABS(SIGN(mes-9)))) AS 'imp9',
            SUM(t.monto*(1-ABS(SIGN(mes-10)))) AS 'imp10',
            SUM(t.monto*(1-ABS(SIGN(mes-11)))) AS 'imp11',
            SUM(t.monto*(1-ABS(SIGN(mes-12)))) AS 'imp12'
            FROM tmptotcomclub_x t
            INNER JOIN un u ON u.idUn=t.idUn
            GROUP BY idUn
            ORDER BY u.nombre";
        $query = $this->db->query($sql);

        $this->db->from('tmptotcomclub');
        $query = $this->db->get();

        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idUn]['nombre'] = $fila->nombre;

                $array = array();
                foreach($fila as $member=>$data) {
                    $array[$member] = $data;
                }

                for($i=1; $i<=12; $i++) {
                    $sql_q1 = "SELECT COUNT(*) AS total
                        FROM ".TBL_COMISIONPERIODO."
                        WHERE DATE('$anio-$i-15') BETWEEN fechainicio AND fechafin AND DATE(NOW())<=DATE_ADD(fechaCorte, INTERVAL 3 DAY)
                        AND fechaEliminacion='0000-00-00 00:00:00'";
                    $query_q1 = $this->db->query($sql_q1);
                    $datos[$fila->idUn]['q1_'.$i] = $query_q1->row()->total;

                    $sql_q2 = "SELECT COUNT(*) AS total
                        FROM ".TBL_COMISIONPERIODO."
                        WHERE LAST_DAY(DATE('$anio-$i-15')) BETWEEN fechainicio AND fechafin AND DATE(NOW())<=DATE_ADD(fechaCorte, INTERVAL 3 DAY)
                        AND fechaEliminacion='0000-00-00 00:00:00'";
                    $query_q2 = $this->db->query($sql_q2);
                    $datos[$fila->idUn]['q2_'.$i] = $query_q2->row()->total;

                    $datos[$fila->idUn]['num'.$i] = $array['num'.$i];
                    $datos[$fila->idUn]['imp'.$i] = $array['imp'.$i];
                }
                unset($array);
            }
        }

        return $datos;
    }

    /**
     *
     * @param type $datos
     * @return int
     */
    public function buscaComision($datos)
    {
        $where = array (
            'idTipoComision' => $datos['idTipoComision'],
            'anio'           => $datos['anio'],
            'mes'            => $datos['mes'],
            'idTipoQuincena' => $datos['idTipoQuincena'],
            'idUn'           => $datos['idUn']
        );
        $this->db->select('idComisionRegla', false);
        $this->db->from('comisionregla');
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $resp=$fila->idComisionRegla;
            }
        } else {
            $resp=0;
        }
        return $resp;
    }

    /**
     *
     *
     * @param integer $idDetalle
     *
     * @return $id del registro que se inserto/actualizo
     */
    public function datosDetalle($idDetalle)
    {
        $data = array();

        $this->db->select('idComisionReglaDetalle, numeroMaximo, numeroMinimo, montoMinimo, montoMaximo, usarPorcentaje, porcentaje, monto, porcentajeMeta, objetivo', false);
        $this->db->from(TBL_COMISIONREGLADETALLE);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idComisionReglaDetalle', $idDetalle);
        $query = $this->db->get('');
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $data['idDetalle'] = $fila['idComisionReglaDetalle'];
            $data['numMaximo'] = $fila['numeroMaximo'];
            $data['numMinimo'] = $fila['numeroMinimo'];
            $data['montoMaximo'] = $fila['montoMaximo'];
            $data['montoMinimo'] = $fila['montoMinimo'];
            if ($fila['usarPorcentaje']==1) {
                $data['usar'] = 1;
            } else {
                $data['usar'] = 0;
            }
            $data['cantidad'] = 0;
            if ($fila['usarPorcentaje']==1) {
                $data['cantidad'] = $fila['porcentaje'];
            }
            if ($fila['usarPorcentaje']==2) {
                $data['cantidad'] = $fila['monto'];
            }
            $data['porcentajeMeta'] = $fila['porcentajeMeta'];
            $data['objetivo']       = $fila['objetivo'];
        }

        return $data;
    }

    /**
     * Elimina periodos
     *
     * @author Antonio Sixtos
     *
     * @return string
     */
    public function eliminaPeriodo($idComisionPeriodo)
    {
        $data = array( 'fechaEliminacion' => date('Y-m-d H:i:s') );
        $this->db->where('idComisionPeriodo', $idComisionPeriodo);
        $this->db->update('comisionperiodo', $data);
        if ($this->db->affected_rows()>0) {
            return 1;
        } else {
            return false;
        }
    }

    /**
     * Elimina la comision
     *
     * @param integer $id Indica el id de la comision
     *
     * @return boolean
     */
    public function eliminarComision($id)
    {
        $datos = array (
            'fechaEliminacion' => date('Y-m-d H:i:s')
        );
        $this->db->select('idComision');
        $this->db->where('idComision', $id);
        $query = $this->db->get(TBL_COMISION);
        if ($query->num_rows > 0) {
            $this->db->where('idComision', $id);
            $this->db->update(TBL_COMISION, $datos);
            $this->permisos_model->log('Se elimina la comision (' . $id . ')', LOG_COMISIONES);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Elimina la comision
     *
     * @param integer $id Indica el id de la comision
     *
     * @return boolean
     */
    public function eliminarDetalle($id)
    {
        $datos = array (
            'fechaEliminacion' => date("Y-m-d 00:00:00")
        );

        $this->db->where('idComisionReglaDetalle', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_COMISIONREGLADETALLE, $datos);

        $total = $this->db->affected_rows();

        if ($total>0) {
            $this->permisos_model->log('Se elimina detalle de comision (' . $id . ')', LOG_COMISIONES);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Inserta datos en tabla comisiones
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public static function guardaComision($opciones)
    {
        $valores = array (
            'idTipoEstatusComision' => $opciones['idTipoEstatusComision'],
            'idUn'                  => $opciones['idUn'],
            'idTipoComision'        => $opciones['idTipoComision'],
            'idPersona'             => $opciones['idPersona'],
            'importe'               => number_format($opciones['importe'], 2, '.', ''),
            'descripcion'           => $opciones['descripcion'],
            'montoComision'         => $opciones['montoComision'],
            'porcentaje'            => $opciones['porcentaje'],
            'manual'                => $opciones['manual']
        );
        $idComision = DB::connection('crm')->table(TBL_COMISION)->insertGetId($valores);
        
        $valores1 = array (
            'idComision'   => $idComision,
            'idMovimiento' => $opciones['movimiento']
        );
        $idComisionMovimiento = DB::connection('crm')->table(TBL_COMISIONMOVIMIENTO)->insertGetId($valores1);
        return $idComisionMovimiento;
    }

    /**
     * Guarda importe de comision cambiado
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function guardaImporteComision($idComision, $importe, $porcentaje, $manual)
    {
        settype($idComision, 'integer');
        $importe = number_format($importe, 2, '.', '');
        $porcentaje = number_format($porcentaje, 2, '.', '');

        $ci =& get_instance();
        $ci->load->model('persona_model');
        $nombrePersona = $ci->persona_model->nombre($this->session->userdata('idPersona'));

        $this->db->select('idComision');
        $this->db->from(TBL_COMISION);
        $where = array(
            'idComision' => $idComision,
            'eliminado'  => 0);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idComision', $fila->idComision);
                $datos = array ('montoComision' => $importe, 'porcentaje'=>$porcentaje, 'manual'=>$manual);
                $this->db->update(TBL_COMISION, $datos);
                $this->permisos_model->log("Actualizo importe de comision $idComision ($nombrePersona) (".date('Y-m-d').")", LOG_COMISIONES);
                return true;
            }
        } else {
            return 0;
        }
    }

    /**
     * Funcion que inserta o actualiza un detalle de la regla comision
     *
     * @param array   $datos    Contiene los datos a insertar/actualizar
     * @param integer $idRegion Identificador de region a replicar
     *
     * @return $id del registro que se inserto/actualizo
     */
    public function guardarDetalle($info, $idRegion = 0)
    {
        settype($info['comision'], 'integer');
        settype($info['detalle'], 'integer');

        if ($info['comision'] == 0) {
            return 0;
        }

        $datos = array (
            'idComisionRegla'=> $info['comision'],
            'numeroMinimo'   => $info['numMinimo'],
            'numeroMaximo'   => $info['numMaximo'],
            'montoMinimo'    => $info['montoMinimo'],
            'montoMaximo'    => $info['montoMaximo'],
            'usarPorcentaje' => $info['usar'],
            'porcentaje'     => $info['porcentaje'],
            'monto'          => $info['monto'],
            'porcentajeMeta' => $info['porcentajeMeta'],
            'objetivo'       => $info['objetivo'],
        );
        if ($info['detalle'] == 0) {
            if ($idRegion > 0) {
                $id = $this->replicarDetalleRegion($info, $idRegion);
            } else {
                $this->db->insert(TBL_COMISIONREGLADETALLE, $datos);
                $id = $this->db->insert_id();
            }
        } else {
            $this->db->where('idComisionReglaDetalle', $info['detalle']);
            $this->db->update(TBL_COMISIONREGLADETALLE, $datos);
            $id = $info['detalle'];
        }
        $this->permisos_model->log('Actualizando información de comisiones', LOG_COMISIONES);

        return $id;
    }

    /**
     * [guardarMeta description]
     * @param  [type] $idUn    [description]
     * @param  [type] $anio    [description]
     * @param  [type] $mes     [description]
     * @param  [type] $numero  [description]
     * @param  [type] $importe [description]
     * @param  [type] $q1      [description]
     * @param  [type] $q2      [description]
     * @return [type]          [description]
     */
    public function guardarMeta($idUn, $anio, $mes, $numero, $importe, $q1, $q2, $idTipoComision)
    {
        settype($idUn, 'integer');
        settype($anio, 'integer');
        settype($mes, 'integer');
        settype($numero, 'integer');
        settype($importe, 'float');
        settype($q1, 'integer');
        settype($q2, 'integer');
        settype($idTipoComision, 'integer');

        $sql = "SELECT crd.idComisionReglaDetalle
            FROM un u
            INNER JOIN comisionregla cr ON cr.idUn=u.idUn AND cr.anio=$anio AND cr.idTipoQuincena=3
                AND cr.idTipoComision=$idTipoComision AND cr.mes=$mes
            INNER JOIN comisionregladetalle crd ON crd.idComisionRegla=cr.idComisionRegla
                AND crd.fechaEliminacion='0000-00-00 00:00:00' AND objetivo=1
            WHERE u.activo=1 AND u.idUn=$idUn;";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $id = $fila['idComisionReglaDetalle'];

            $update = array(
                'numeroMinimo'   => $numero,
                'numeroMaximo'   => 0,
                'montoMinimo'    => $importe,
                'montoMaximo'    => 0,
                'usarPorcentaje' => 0,
                'monto'          => 0,
                'meta'           => 0,
                'porcentajeMeta' => 100,
                'objetivo'       => 1,
                'q1'             => $q1,
                'q2'             => $q2
            );

            $this->db->where("idComisionReglaDetalle", $id);
            $resultado = $this->db->update(TBL_COMISIONREGLADETALLE, $update);
            $this->permisos_model->log('Actualizado meta de comisiones', LOG_COMISIONES);
        } else {
            $sql = "SELECT cr.idComisionRegla
                FROM un u
                INNER JOIN comisionregla cr ON cr.idUn=u.idUn AND cr.anio=$anio AND cr.idTipoQuincena=3
                    AND cr.idTipoComision=".$idTipoComision." AND cr.mes=$mes
                WHERE u.activo=1 AND u.idUn=$idUn";
            $query = $this->db->query($sql);
            if ($query->num_rows() > 0) {
                $fila = $query->row_array();
                $id = $fila['idComisionRegla'];
            } else {
                $insert = array (
                    'idTipoComision' => $idTipoComision,
                    'idTipoQuincena' => 3,
                    'idUn'           => $idUn,
                    'anio'           => $anio,
                    'mes'            => $mes,
                    'esquema'        => 'Base',
                    'revisado'       => 1
                );
                $resultado = $this->db->insert(TBL_COMISIONREGLA, $insert);
                $id = $this->db->insert_id();
                unset($insert);
            }

            $insert = array (
                'idComisionRegla' => $id,
                'numeroMaximo'    => 0,
                'numeroMinimo'    => $numero,
                'montoMinimo'     => $importe,
                'montoMaximo'     => 0,
                'usarPorcentaje'  => 0,
                'porcentaje'      => 0,
                'monto'           => 0,
                'meta'            => 0,
                'porcentajeMeta'  => 100,
                'objetivo'        => 1,
                'q1'             => $q1,
                'q2'             => $q2
            );
            $resultado = $this->db->insert(TBL_COMISIONREGLADETALLE, $insert);
            $id = $this->db->insert_id();
        }

        $sql = "COMMIT";
        $query = $this->db->query($sql);

        $sql = "CALL spComisionesRango($idUn, $anio, $mes, $idTipoComision)";
    }

    /**
     * guarda nuevos periodos
     *
     * @author Antonio Sixtos
     *
     * @return string
     */
    public function guardarPeriodos($opciones)
    {
        $sql="SELECT fechaInicio, fechaFin FROM ".TBL_COMISIONPERIODO." WHERE fechaEliminacion='0000-00-00 00:00:00'";
        $query = $this->db->query($sql);
        $fi= strtotime($opciones["fechainicio"]);
        $ff= strtotime($opciones["fechafin"]);
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $fechaI = $fila->fechaInicio;
                $fechaF = $fila->fechaFin;

                $fIbd = strtotime($fechaI);
                $fFbd = strtotime($fechaF);

                if ($fi > $fIbd && $fi < $fFbd) {
                    return 2;
                }
                if ($ff > $fIbd && $ff < $fFbd) {
                    return 3;
                }
            }
        }
        $data = array(
            'idPersona'   => $this->session->userdata('idPersona'),
            'fechaInicio' => $opciones["fechainicio"],
            'fechaFin'    => $opciones["fechafin"],
            'fechaCorte'  => $opciones["fechacorte"],
        );

        $this->db->insert('comisionperiodo', $data);
        if ($this->db->affected_rows()>0) {
            return 1;
        } else {
            return false;
        }
    }

    /**
     *
     * @author Jorge Cruz
     *
     * @param integer $tipoComision
     * @param integer $quincena
     * @param integer $un
     * @param integer $anio
     * @param integer $mes
     * @return integer
     */
    public function guardarRegla($tipoComision, $quincena, $un, $anio, $mes, $esquema)
    {
        settype($tipoComision, 'integer');
        settype($quincena, 'integer');
        settype($un, 'integer');
        settype($anio, 'integer');
        settype($mes, 'integer');

        if ($tipoComision==0 || $quincena==0 || $un==0 || $anio==0 || $mes==0 || $esquema=='0') {
            return 0;
        }

        $this->db->select('idComisionRegla');
        $this->db->from(TBL_COMISIONREGLA);
        $this->db->where('idTipoComision', $tipoComision);
        $this->db->where('idTipoQuincena', $quincena);
        $this->db->where('idUn', $un);
        $this->db->where('anio', $anio);
        $this->db->where('mes', $mes);
        $this->db->where('esquema', $esquema);
        $query = $this->db->get();
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila['idComisionRegla'];
        } else {
            $datos = array (
                'idTipoComision' => $tipoComision,
                'idTipoQuincena' => $quincena,
                'idUn'           => $un,
                'anio'           => $anio,
                'mes'            => $mes,
                'esquema'        => $esquema
            );
            $this->db->insert(TBL_COMISIONREGLA, $datos);
            $regla = $this->db->insert_id();
        }

        if ($regla > 0) {
            $this->permisos_model->log('Se agrega nueva regla de comisiones', LOG_COMISIONES);
        }

        return $regla;
    }

    /**
     * Habilita el CAT de la comision de venta para la membresia indicada
     *
     * @param  integer $idUnicoMembresia Identificador unico de membresia
     *
     * @return [type]                   [description]
     */
    public function habilitaCAT($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia>0) {
            $sql = "UPDATE membresia m
                INNER JOIN movimiento mov ON m.idUnicoMembresia=mov.idUnicoMembresia
                INNER JOIN comisionmovimiento cm ON cm.idMovimiento=mov.idMovimiento
                INNER JOIN comision c ON c.idComision=cm.idComision AND c.idTipoComision=1
                    AND c.eliminado=0 AND c.idTipoEstatusComision<>2
                    AND c.regla='Falta CAT'
                LEFT JOIN comisionperiodo cp ON c.fechaAplica BETWEEN cp.fechaInicio AND cp.fechaFin
                    AND cp.fechaEliminacion='0000-00-00 00:00:00' AND cp.fechaCorte>=DATE(NOW())
                SET c.regla='CAT'
                WHERE m.idUnicoMembresia=$idUnicoMembresia AND (c.fechaAplica='0000-00-00' OR cp.idComisionPeriodo IS NOT NULL)";
            $query = $this->db->query($sql);

            if ($this->db->affected_rows()>0) {
                $this->permisos_model->log('Cambio regla CAT para comision venta membresia', LOG_COMISIONES);
            }
        }
    }

    /**
     * Funcion que ejecuta los updates para el recalculo de comisiones
     *
     * @param integer $num                Contiene el numero de comisiones
     * @param integer $monto              Contiene la suma de los importes
     * @param array   $comisionesEmpleado Contiene las configuraciones de comisiones que podrian aplicar
     * @param array   $detallesComisiones Contiene las comisiones a revisar.
     * @param integer $idUn               Contiene las  comisiones a revisar.
     *
     * @return void
     */
    public function identificaDetalleComision($num, $monto, $comisionesEmpleado, $detallesComisiones, $idUn)
    {
        $monto = number_format($monto, 2, '.', '');

        $ci =& get_instance();
        $ci->load->model('un_model');

        $w = 0;
        for ($i=0; $i<count($detallesComisiones); $i++) {
            $bln=0;
            if ($detallesComisiones[$i]->numeroMinimo<=$num
                && $detallesComisiones[$i]->numeroMaximo>=$num
                && $detallesComisiones[$i]->montoMinimo<=$monto
                && $detallesComisiones[$i]->montoMaximo>=$monto) {
                $bln=1;
            } elseif ($detallesComisiones[$i]->numeroMinimo<=$num &&
                $detallesComisiones[$i]->numeroMaximo>=$num &&
                $detallesComisiones[$i]->montoMaximo<=$monto) {
                $bln=1;
            } elseif ($detallesComisiones[$i]->montoMinimo<=$monto &&
                $detallesComisiones[$i]->montoMaximo>=$monto &&
                $detallesComisiones[$i]->numeroMaximo<=$num) {
                $bln=1;
            }

            if ($detallesComisiones[$i]->usarPorcentaje==1 && $bln==1) {
                $calcular[$w]['tipo']=1;
                $calcular[$w]['monto']=$detallesComisiones[$i]->porcentaje;
                $w=$w+1;
            } elseif ($detallesComisiones[$i]->usarPorcentaje==2 && $bln==1) {
                $calcular[$w]['tipo']=2;
                $calcular[$w]['monto']=$detallesComisiones[$i]->monto;
                $w=$w+1;
            }

            $calcular_aux[$i]['tipo']=$detallesComisiones[$i]->usarPorcentaje;

            if ($detallesComisiones[$i]->usarPorcentaje==1 && $bln==1) {
                $calcular_aux[$i]['monto']=$detallesComisiones[$i]->porcentaje;
            } else {
                $calcular_aux[$i]['monto']=$detallesComisiones[$i]->monto;
            }
        }

        if (isset($calcular) && count($calcular)>0) {
            for ($i=0; $i<count($calcular); $i++) {
                $calcular[$i]['comision']=0;
                for ($j=0; $j<count($comisionesEmpleado); $j++) {
                    if ($comisionesEmpleado[$j]->idtipoEstatusComision==1 || $comisionesEmpleado[$j]->idtipoEstatusComision==0) {
                        $resultado=0;
                        if ($calcular[$i]['tipo']==1) {
                            $iva=$ci->un_model->iva($idUn);
                            $iva=$iva/100;
                            $iva+=1;
                            $resultado=($comisionesEmpleado[$j]->importe/$iva) * ($calcular[$i]['monto']/100);
                        } else {
                            $resultado=$calcular[$i]['monto'];
                        }
                        $calcular[$i]['comision']+=$resultado;
                    }
                }
            }
        } else {
            $calcular=array();
            for ($i=0; $i<count($calcular_aux); $i++) {
                $calcular[$i]['comision']=0;
                for ($j=0; $j<count($comisionesEmpleado); $j++) {
                    if ($comisionesEmpleado[$j]->idtipoEstatusComision==1 || $comisionesEmpleado[$j]->idtipoEstatusComision==0) {
                        $resultado=0;
                        if ($calcular_aux[$i]['tipo']==1) {
                            $iva=$ci->un_model->iva($idUn);
                            $iva=$iva/100;
                            $iva+=1;
                            $resultado=($comisionesEmpleado[$j]->importe/$iva) * ($calcular_aux[$i]['monto']/100);
                        } else {
                            $resultado=$calcular_aux[$i]['monto'];
                        }
                        $calcular[$i]['comision']+=$resultado;
                        $calcular[$i]['tipo']=$calcular_aux[$i]['tipo'];
                        $calcular[$i]['monto']=$calcular_aux[$i]['monto'];
                    }
                }
            }
        }

        for ($i=0; $i<count($calcular); $i++) {
            if ($i==0) {
                $tipo=$calcular[$i]['tipo'];
                $monto=$calcular[$i]['monto'];
                $comision=$calcular[$i]['comision'];
            } else {
                if ($calcular[$i]['comision']<$comision) {
                    $tipo=$calcular[$i]['tipo'];
                    $monto=$calcular[$i]['monto'];
                    $comision=$calcular[$i]['comision'];
                }
            }
        }

        for ($j=0; $j<count($comisionesEmpleado); $j++) {
            if ($comisionesEmpleado[$j]->idtipoEstatusComision==1 || $comisionesEmpleado[$j]->idtipoEstatusComision==0) {
                $resultado=0;
                if ($tipo==1) {
                    $iva=$ci->un_model->iva($idUn);
                    $iva=$iva/100;
                    $iva+=1;
                    $resultado=($comisionesEmpleado[$j]->importe/$iva) * ($monto/100);
                } else {
                    $resultado=$monto;
                }
                $datoss = array ('montoComision' => $resultado);
                $this->db->where('idComision', $comisionesEmpleado[$j]->idComision);
                $this->db->update(TBL_COMISION, $datoss);
            }
        }
    }

    /**
     * Inserta en una comision tabla de comision y en la tabla de comision movimiento
     *
     * @param integer $tipocomision es el id del tipo de comision
     * @param integer $concepto     es la descripción de la comision
     * @param integer $persona      es el id de la persona a la que le toca la comision
     * @param integer $club         es el id del club donde se genero la comision
     * @param integer $movimientos  Contiene un arreglo con el movimiento o movmientos de la comision
     * @param integer $precio       contiene el importe de la operacion
     * @param string  $regla        especifica la regla de pago
     *
     * @return idComision si procedio la inserción, 0 si hubo un error
     */
    public function insertaComision($tipocomision, $concepto, $persona, $club, $movimientos, $precio = 0, $regla = 'N/A')
    {
        $precio = number_format($precio, 2, '.', '');

        $datoss = array (
            'idTipoComision' => $tipocomision,
            'idPersona'      => $persona,
            'descripcion'    => $concepto,
            'idUn'           => $club,
            'importe'        => $precio,
            'fechaAplica'    => '0000-00-00',
            'regla'          => $regla
        );

        $this->db->insert(TBL_COMISION, $datoss);

        $idComision = $this->db->insert_id();

        $this->permisos_model->log('Se inserto Comision', LOG_COMISIONES, 0, 0, true);

        if ($idComision) {
            $datoss = array (
                'idComision'   => $idComision,
                'idMovimiento' => $movimientos);
            $this->db->insert(TBL_COMISIONMOVIMIENTO, $datoss);

            return $idComision;
        } else {
            return 0;
        }
    }

    /**
     * Genera el query y devuelve datos de las comisiones
     *
     * @param string  $tipo           1 Todos los registros 2 no. de registros
     * @param string  $orden          Contiene el campo a ordenar
     * @param array   $whereadicional Se utiliza para otras restricciones
     * @param string  $inicio         Sirve para saber en que registro esta el paginado
     * @param integer $cantidad       Indica el no. de registros por pagina
     * @param string  $in             Contiene concatenadas las unidades de negocio 1,2,3,4
     *
     * @return boolean
     */
    public function listadoTabla($tipo = 1, $orden = "u.nombre", $whereadicional = "", $inicio = 0, $cantidad = 25, $in = '')
    {
        $campos="cr.idComisionRegla,u.nombre,tc.descripcion,tq.descripcion as quin,";
        $campos.="cr.anio,cr.mes";

        if ($whereadicional<>"") {
            foreach ($whereadicional as $indice => $lista) {
                $where[$indice] = $lista;
            }
        }

        $tabla1 = TBL_COMISIONREGLA." cr";
        $tabla3 = self::TABLACTIPCM." tc";
        $tabla4 = TBL_UN." u";
        $tabla5 = TBL_TIPOQUINCENA." tq";

        $this->db->select($campos, false);
        $this->db->from($tabla1);
        $this->db->join($tabla3, 'tc.idTipoComision=cr.idTipoComision');
        $this->db->join($tabla4, 'cr.idUn=u.idUn');
        $this->db->join($tabla5, 'cr.idTipoQuincena=tq.idTipoQuincena');

        if (isset($where) && count($where)>0) {
            $this->db->where($where);
        }
        if ($in<>'') {
            $this->db->where_in('cr.idUn', $in);
        }
        if ($tipo==1) {
            $query = $this->db->order_by($orden)->get('', $cantidad, $inicio);
            if ($query->num_rows>0) {
                foreach ($query->result() as $fila) {
                    $data[] = $fila;
                }
                return $data;
            }
        } elseif ($tipo==2) {
            $query = $this->db->order_by($orden)->get();
            return $query->num_rows;
        }
    }




    /**
     * Obtiene el valor de campo solicitado dentro de una tabla
     *
     * @param integer $idtabla            es el id del registro
     * @param integer $campoid            es el nombre del campo ID con el que se compara el valor $idtabla
     * @param integer $campovalor         contiene el nombre del campo del que se requiere el valor
     * @param integer $tablaactualizacion contiene el nombre de la tabla a actualizar
     *
     * @return string
     */
    public function opcionesCampo($idtabla, $campoid, $campovalor, $tablaactualizacion)
    {
        if ($this->db->field_exists($campovalor, $tablaactualizacion) == false) {
            return null;
        }

        $this->db->select($campovalor);
        $query = $this->db->where($campoid, $idtabla)->get($tablaactualizacion);
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila[$campovalor];
        }
        return null;
    }

    /**
     * Obtiene capacidad de la tabla eventouncapacidad en base al idUn
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public static function obtenCapacidad($club, $tipoEvento=0)
    {
        settype($tipoEvento, 'integer');
        settype($club, 'integer');
        
        $data = "";
        $sql = "SELECT e.capacidad FROM ".TBL_EVENTOUNCAPACIDAD." e
            INNER JOIN ".TBL_EVENTOUN." eu ON eu.idEventoUn=e.idEventoUn
            WHERE e.activo=1  AND eu.idun=".$club." and e.fechaEliminacion='0000-00-00 00:00:00'";
        if ($tipoEvento > 0) {
            $sql .= ' AND e.idTipoEventoCapacidad= '.$tipoEvento;
        } else {
            $sql .= ' AND e.idTipoEventoCapacidad=8 ';
        }
        
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $row = $query[0];
            $data= $row->capacidad;
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Obtiene las comisiones para revision
     *
     * @param string $periodo Fecha correspondiente al periodo
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenComisionesRevision ($periodo)
    {
        settype($periodo, 'string');

        $datos = array();
        $datos[TIPO_COMISION_VENTA_VENDEDOR] = array();
        $datos[TIPO_COMISION_GERENTE_VENTAS] = array();
        $datos[TIPO_COMISION_GERENTENACIONAL] = array();

        if (!$periodo) {
            return $datos;
        }
        $datosPeriodo = explode('-', $periodo);
        $anio = $datosPeriodo[0];
        $mes = $datosPeriodo[1];
        $dia = $datosPeriodo[2];

        if ($dia == 15) {
            $fechaInicio = $anio.'-'.$mes.'-01';
            $fechaFin    = $periodo;
        } else {
            $fechaInicio = $anio.'-'.$mes.'-16';
            $fechaFin    = $periodo;
        }
        $fechaInicioMes = $anio.'-'.$mes.'-01';
        $fechaSigMes    = date('Y-m-d', strtotime($fechaInicioMes.' +1 month'));
        $fechaFinMes    = date('Y-m-d', strtotime($fechaSigMes.' -1 day'));

        $this->db->where('c.idTipoComision', TIPO_COMISION_VENTA_VENDEDOR);
        $this->db->where('c.eliminado', 0);
        $this->db->where("c.fechaAplica BETWEEN '".$fechaInicio."' AND '".$fechaFin."'");
        $this->db->join(TBL_COMISIONMOVIMIENTO." cm", "cm.idComision=c.idComision", "INNER");
        $this->db->join(TBL_MOVIMIENTO." m", "m.idMovimiento=cm.idMovimiento", "INNER");
        $this->db->join(TBL_UN." u", "u.idUn = c.comisionUnRegla", "INNER");
        $this->db->join(TBL_PERSONA." p", "p.idPersona = c.idPersona", "INNER");

        $query = $this->db->select("
            c.comisionUnRegla AS idUn, u.nombre AS club, c.idPersona, CONCAT_WS(' ', p.nombre, p.paterno, p.materno)AS nombre, SUM((c.importe/(1+(m.iva/100)))) AS importe, COUNT(*)AS ventas, c.porcentaje, SUM( (c.importe/(1+(m.iva/100)))*(c.porcentaje/100) )AS comision",
            false
        )->order_by('c.comisionUnRegla, nombre')->group_by('c.idPersona')->get(TBL_COMISION.' c');

        if ($query->num_rows) {
            $datos[TIPO_COMISION_VENTA_VENDEDOR] = $query->result_array();
        }
        if ($dia != 15) {
            $sql = "
                SELECT a.idUn, a.club, b.idPersona, CONCAT_WS(' ', b.nombre, b.paterno, b.materno)AS nombre,
                a.siniva AS importe, a.total AS ventas, b.porcentaje, round(a.siniva*(b.porcentaje/100),2)AS comision
                FROM (
                    SELECT c.comisionUnRegla AS idUn, u.nombre AS club, COUNT(*) AS total, SUM((c.importe/(1+(m.iva/100)))) AS siniva
                    FROM ".TBL_COMISION." c
                    INNER JOIN ".TBL_COMISIONMOVIMIENTO." cm ON cm.idComision=c.idComision
                    INNER JOIN ".TBL_MOVIMIENTO." m ON m.idMovimiento=cm.idMovimiento
                    INNER JOIN ".TBL_FACTURAMOVIMIENTO." fm ON fm.idMovimiento=m.idMovimiento
                    INNER JOIN ".TBL_FACTURA." f ON f.idFactura=fm.idFactura
                    LEFT JOIN ".TBL_UN." u ON u.idUn = f.idUn
                    WHERE c.idTipoComision = ? AND c.fechaAplica BETWEEN ? AND ? AND c.eliminado=0
                    GROUP BY c.comisionUnRegla
                    ORDER BY c.comisionUnRegla
                ) a
                LEFT JOIN (
                    SELECT c.comisionUnRegla AS idUn, c.idPersona, p.nombre, p.paterno, p.materno, c.porcentaje
                    FROM ".TBL_COMISION." c
                    INNER JOIN ".TBL_PERSONA." p ON p.idPersona = c.idPersona
                    WHERE c.idTipoComision = ? AND c.eliminado=0 AND c.fechaAplica = ?
                    GROUP BY c.comisionUnRegla
                ) b ON b.idUn=a.idUn
                HAVING idUn>0
                ORDER BY a.idUn, nombre;";

            $query = $this->db->query($sql, array(TIPO_COMISION_VENTA_VENDEDOR, $fechaInicioMes, $fechaFinMes, TIPO_COMISION_GERENTE_VENTAS, $fechaFinMes));

            if ($query->num_rows) {
                $datos[TIPO_COMISION_GERENTE_VENTAS] = $query->result_array();
            }
            $sql = "
                SELECT a.idUn, a.club, b.idPersona, CONCAT_WS(' ', b.nombre, b.paterno, b.materno)AS nombre,
                a.siniva AS importe, a.total AS ventas, b.porcentaje, round(a.siniva*(b.porcentaje/100),2)AS comision
                FROM (
                    SELECT c.comisionUnRegla AS idUn, u.nombre AS club, COUNT(*) AS total, SUM((c.importe/(1+(m.iva/100)))) AS siniva
                    FROM ".TBL_COMISION." c
                    INNER JOIN ".TBL_COMISIONMOVIMIENTO." cm ON cm.idComision=c.idComision
                    INNER JOIN ".TBL_MOVIMIENTO." m ON m.idMovimiento=cm.idMovimiento
                    INNER JOIN ".TBL_FACTURAMOVIMIENTO." fm ON fm.idMovimiento=m.idMovimiento
                    INNER JOIN ".TBL_FACTURA." f ON f.idFactura=fm.idFactura
                    LEFT JOIN ".TBL_UN." u ON u.idUn = f.idUn
                    WHERE c.idTipoComision = ? AND c.fechaAplica BETWEEN ? AND ? AND c.eliminado=0
                    GROUP BY c.comisionUnRegla
                    ORDER BY c.comisionUnRegla
                ) a
                LEFT JOIN (
                    SELECT c.comisionUnRegla AS idUn, c.idPersona, p.nombre, p.paterno, p.materno, c.porcentaje
                    FROM ".TBL_COMISION." c
                    INNER JOIN ".TBL_PERSONA." p ON p.idPersona = c.idPersona
                    WHERE c.idTipoComision = ? AND c.eliminado=0 AND c.fechaAplica = ?
                    GROUP BY c.comisionUnRegla
                ) b ON b.idUn=a.idUn
        HAVING idUn>0
                ORDER BY a.idUn, nombre;";
            $query = $this->db->query($sql, array(TIPO_COMISION_VENTA_VENDEDOR, $fechaInicioMes, $fechaFinMes, TIPO_COMISION_GERENTENACIONAL, $fechaFinMes));

            if ($query->num_rows) {
                $datos[TIPO_COMISION_GERENTENACIONAL] = $query->result_array();
            }
        }
        return $datos;
    }

    /**
     * Obtiene conceptos
     *
     * @param integer $idUn Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    function obtenConceptos($idUn)
    {
        settype($idUn, 'integer');

        $datos = array();

        if ( ! $idUn) {
            return $datos;
        }

        $where = array('cc.idUn' => $idUn);
        $query = $this->db->select(
            "cc.idComisionConcepto, cc.descripcion, cc.concepto", false
        )->order_by("cc.descripcion")->get_where(TBL_COMISIONCONCEPTO." cc", $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene datos de comisionregla
     *
     * @param type $idComisionRegla
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenDatosRegla ($idComisionRegla)
    {
        settype($idComisionRegla, 'integer');

        $datos = array();

        if (!$idComisionRegla) {
            return $datos;
        }
        $where = array('idComisionRegla' => $idComisionRegla);
        $query = $this->db->select(
            "idTipoComision, idTipoQuincena, idUn, anio, mes, esquema, revisado", false
        )->get_where(TBL_COMISIONREGLA, $where, 1);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Obtiene La fecha de corte en base a si la fechaActual(Hoy)
     * esta dentro de la fechaFin y fechaCorte de la tabla comisionPeriodo
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function obtenFechaCorte()
    {
        $data="";
        $sql = "SELECT fechaCorte FROM ".TBL_COMISIONPERIODO." WHERE fechaFin<DATE(NOW()) AND fechaCorte>=DATE(NOW())";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $data= $row->fechaCorte;
            return $data;
        } else {
            return $data;
        }
    }


    /**
     * Obtiene La fecha de corte en base a si la fechaActual(Hoy)
     * esta dentro de la fechaFin y fechaCorte de la tabla comisionPeriodo
     * valida que este dentro de la fecha de calculo para activar solo el cambio de CAT a este periodo
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function obtenPeriodoActualCAT($fechainiciocorte,$fechaCorte)
    {
        $data="";
        $sql = "SELECT fechaInicio FROM ".TBL_COMISIONPERIODO." WHERE fechaCorte='".$fechaCorte."'";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $data= $row->fechaInicio;
            if ($data == $fechainiciocorte)
                return 1;
            else
                return 0;
        }
    }

    /**
     * Obtiene La fecha de corte en base a si la fechaActual(Hoy)
     * esta dentro de la fechaFin y fechaCorte de la tabla comisionPeriodo
     *
     * @author Gustavo Bonilla
     *
     * @return void
     */
    public function obtenFechaCorteEntregaNomina()
    {
        $data = '';
        $sql = "SELECT fechaRevision FROM ".TBL_COMISIONPERIODO." WHERE fechaFin<DATE(NOW()) AND fechaCorte>=DATE(NOW())";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $data= $row->fechaRevision;
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Obtiene fecha de corte del periodo
     *
     * @param string $periodo Fecha final del periodo
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function obtenFechaCorteRevision ($periodo)
    {
        settype($periodo, 'string');

        $fechaCorte = '';

        if (!$periodo) {
            return $fechaCorte;
        }
        $where = array(
            'fechaFin'         => $periodo,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = $this->db->select("fechaCorte", false)->get_where(TBL_COMISIONPERIODO, $where);

        if ($query->num_rows) {
            $fechaCorte = $query->row()->fechaCorte;
        }
        return $fechaCorte;
    }

    /**
     * Muestra perriodos y fechas de las comisiones
     *
     * @author antonio Sixtos
     *
     * @return string
     */
    public function obtenFechas($posicion, $registros)
    {
        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }
        if ($posicion == '') {
                $posicion = 0;
        }

        $sql="SELECT idComisionPeriodo, fechaInicio, fechaFin, fechaCorte ".
            "FROM ".TBL_COMISIONPERIODO." WHERE fechaEliminacion='0000-00-00 00:00:00' ORDER BY fechaInicio desc ".
            "LIMIT ".$posicion." , ".$registros." ";
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
     * Regresar el Id de comision asignado a una venta de membresia
     *
     * @param  integer $idUnicoMembresia Identificador unico de membresia
     *
     * @return integer
     */
    public function obtenIdComisionVentaMembresia($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $id = 0;

        if ($idUnicoMembresia>0) {
            $sql = "SELECT DISTINCT c.idComision
                FROM membresia m
                INNER JOIN movimiento mov ON mov.idUnicoMembresia=m.idUnicoMembresia
                    AND mov.eliminado=0
                INNER JOIN comisionmovimiento cm ON cm.idMovimiento=mov.idMovimiento
                INNER JOIN comision c ON c.idComision=cm.idComision AND c.idTipoComision=1
                    AND c.eliminado=0
                WHERE m.idUnicoMembresia=$idUnicoMembresia AND m.eliminado=0
                ORDER BY 1 DESC LIMIT 1";
            $query = $this->db->query($sql);

            if ($query->num_rows > 0) {
                $fila = $query->row_array();
                $id = $fila['idComision'];
            }
        }

        return $id;
    }

    /**
     * Obtiene los meses de pago de mtto de socios por vendedor, club y fecha de inicio de mtto
     *
     * @param integer $idUn              Identificador de unidad de negocio
     * @param integer $idPersonaVendedor Identificador de persona vendedor
     * @param string  $fechaInicio       Fecha de inicio de mtto
     * @param integer $finalizado        Estatus de los pagos de mtto
     * @param integer $tipoComision      Tipo de comision, fija o variable
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenPagosMtto ($idUn = 0, $idPersonaVendedor = 0, $fechaInicio = '', $finalizado = 3, $tipoComision = 0)
    {
        settype($idUn, 'integer');
        settype($idPersonaVendedor, 'integer');
        settype($fechaInicio, 'string');
        settype($finalizado, 'integer');
        settype($tipoComision, 'integer');

        $membresias = array();
        $datos      = array(
            'error'     => 1,
            'mensaje'   => 'Error faltan datos',
            'pagosMtto' => array()
        );
        $datos['error']     = 0;
        $datos['mensaje']   = '';
        $filtroIdUn         = '';
        $filtroFechaInicio  = '';
        $filtroPersona      = '';
        $filtroFinalizado   = '';
        $filtroTipoComision = '';
        $filtros            = array();

        if ($idUn) {
            $filtroIdUn = "AND m.idUn = ? ";
            $filtros[]  = $idUn;
        }
        if ($idPersonaVendedor) {
            $filtroPersona = "AND c.idPersona = ? ";
            $filtros[]  = $idPersonaVendedor;
        }
        if ($fechaInicio) {
            $filtroFechaInicio = "AND DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY) = ? ";
            $filtros[]  = $fechaInicio;
        }
        if ($finalizado != 3) {
            $filtroFinalizado = "AND c.finalizado = ? ";
            $filtros[]  = $finalizado;
        }
        $filtroTipoComision = "AND c.fijo = ? ";
        $filtros[]          = $tipoComision;

        $sql = "
            SELECT DISTINCT m.idUnicoMembresia, per.idPersona AS idPersonaTitular, CONCAT_WS(' ', per.nombre, per.paterno, per.materno)AS titular,
            CONCAT_WS(' ', per2.nombre, per2.paterno, per2.materno)AS vendedor, c.fechaAplica AS fechaFacturacion, c.fijo,
            DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY) AS mes1,
            IFNULL(
                (
                    SELECT mp.idMembresia
                    FROM membresiatraspaso mp
                    WHERE mp.idUnicoMembresia = m.idUnicoMembresia
                    AND mp.fechaEliminacion = '0000-00-00 00:00:00'
                    ORDER BY mp.idMembresiaTraspaso
                    LIMIT 1
                ), m.idMembresia
            )AS idMembresia,
            IFNULL(
                (
                    SELECT u.nombre
                    FROM membresiatraspaso mp
                    INNER JOIN un u ON u.idUn = mp.idUn
                    WHERE mp.idUnicoMembresia = m.idUnicoMembresia
                    AND mp.fechaEliminacion = '0000-00-00 00:00:00'
                    ORDER BY mp.idMembresiaTraspaso
                    LIMIT 1
                ), u.nombre
            )AS club,
            (
                SELECT IF(GROUP_CONCAT(spm.idSocioPagoMtto) IS NULL,0,1)
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia = m.idUnicoMembresia
                AND DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.eliminado = 0
                AND spm.activo = 1
            )AS m1Pagado,
            DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 1 MONTH) AS mes2,
            (
                SELECT IF(GROUP_CONCAT(spm.idSocioPagoMtto) IS NULL,0,1)
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia = m.idUnicoMembresia
                AND DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 1 MONTH) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.eliminado = 0
                AND spm.activo = 1
            )AS m2Pagado,
            DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 2 MONTH) AS mes3,
            (
                SELECT IF(GROUP_CONCAT(spm.idSocioPagoMtto) IS NULL,0,1)
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia = m.idUnicoMembresia
                AND DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 2 MONTH) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.eliminado = 0
                AND spm.activo = 1
            )AS m3Pagado,
            DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 3 MONTH) AS mes4,
            (
                SELECT IF(GROUP_CONCAT(spm.idSocioPagoMtto) IS NULL,0,1)
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia = m.idUnicoMembresia
                AND DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 3 MONTH) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.eliminado = 0
                AND spm.activo = 1
            )AS m4Pagado,
            DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 4 MONTH) AS mes5,
            (
                SELECT IF(GROUP_CONCAT(spm.idSocioPagoMtto) IS NULL,0,1)
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia = m.idUnicoMembresia
                AND DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 4 MONTH) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.eliminado = 0
                AND spm.activo = 1
            )AS m5Pagado,
            DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 5 MONTH) AS mes6,
            (
                SELECT IF(GROUP_CONCAT(spm.idSocioPagoMtto) IS NULL,0,1)
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia = m.idUnicoMembresia
                AND DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 5 MONTH) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.eliminado = 0
                AND spm.activo = 1
            )AS m6Pagado,
            DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 6 MONTH) AS mes7,
            (
                SELECT IF(GROUP_CONCAT(spm.idSocioPagoMtto) IS NULL,0,1)
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia = m.idUnicoMembresia
                AND DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 6 MONTH) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.eliminado = 0
                AND spm.activo = 1
            )AS m7Pagado,
            DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 7 MONTH) AS mes8,
            (
                SELECT IF(GROUP_CONCAT(spm.idSocioPagoMtto) IS NULL,0,1)
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia = m.idUnicoMembresia
                AND DATE_ADD(DATE_ADD(LAST_DAY(IF(m.fechaInicioMtto='0000-00-00',m.fechaRegistro,m.fechaInicioMtto)), INTERVAL 1 DAY), INTERVAL 7 MONTH) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.eliminado = 0
                AND spm.activo = 1
            )AS m8Pagado
            FROM membresia m
            INNER JOIN membresiaventa mv ON mv.idUnicoMembresia = m.idUnicoMembresia
            INNER JOIN movimiento mov ON mov.idUnicoMembresia = m.idUnicoMembresia AND mov.eliminado = 0
            INNER JOIN comisionmovimiento cm ON cm.idMovimiento = mov.idMovimiento
            INNER JOIN comision c ON c.idComision = cm.idComision AND (c.fechaAplica >= '".date('Y')."-01-01' OR (c.importe=0  AND DATE(c.fechaRegistro) >= '".date('Y')."-01-01'))
            INNER JOIN persona per ON per.idPersona = m.idPersona AND per.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN persona per2 ON per2.idPersona = c.idPersona AND per2.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN telefono tel ON tel.idPersona = m.idPersona AND tel.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN un u ON u.idUn = m.idUn
            WHERE m.eliminado = 0
            AND m.idTipoEstatusMembresia = ".ESTATUS_MEMBRESIA_ACTIVA."
            ".$filtroIdUn."
            ".$filtroPersona."
            ".$filtroFechaInicio."
            ".$filtroFinalizado."
            ".$filtroTipoComision."
            ORDER BY vendedor;
        ";
        $query = $this->db->query($sql, $filtros);

        if ($query->num_rows) {
            $datos['pagosMtto'] = $query->result_array();
        }
        return $datos;
    }

    /**
     * obtiene periodos especificos en base al idComisionPeriodo
     *
     * @author Antonio Sixtos
     *
     * @return string
     */
    public function obtenPeriodosEspecificos($idComisionPeriodo)
    {
        $sql="SELECT idComisionPeriodo, fechaInicio, fechaFin, fechaCorte FROM ".TBL_COMISIONPERIODO.
            " WHERE idComisionPeriodo=".$idComisionPeriodo;
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
     * Busca su el registro de la comision es de pesas
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function obtenSiPesas($idComision)
    {
        $data=0;
        $sql="SELECT *
            FROM ".TBL_COMISION." c
            LEFT JOIN ".TBL_COMISIONMOVIMIENTO." cm ON cm.idComision=c.idComision and cm.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN ".TBL_EVENTOMOVIMIENTO." em ON em.idMovimiento=cm.idMovimiento
            LEFT JOIN ".TBL_EVENTOINSCRIPCION." ei ON ei.idEventoInscripcion=em.idEventoInscripcion
            LEFT JOIN ".TBL_EVENTOUN." eu ON eu.idEventoUn=ei.idEventoUn
            LEFT JOIN ".TBL_EVENTOUNCAPACIDAD." euc ON euc.idEventoUn=eu.idEventoUn
            WHERE  c.idcomision=".$idComision." AND euc.idTipoEventoCapacidad=8 AND c.eliminado=0";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $data= 1;
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Obtiene Total de periodos y fechas de las comisiones en la tabla comisionperiodo
     *
     * @author antonio Sixtos
     *
     * @return string
     */
    public function obtenTotalFechas()
    {
        $sql="SELECT COUNT(*) AS total FROM ".TBL_COMISIONPERIODO." WHERE fechaEliminacion='0000-00-00 00:00:00'";
        $query = $this->db->query($sql);

        $row = $query->row();
        if ($query->num_rows() > 0) {
            return $row->total;
        } else {
            return false;
        }
    }

    /**
     * Funcion que devuelve los idUn de las comisiones de un empleado
     *
     * @param integer $idMes      Contiene el año/mes 201001
     * @param integer $idQuincena Contiene el id del tipo de quincena
     * @param integer $tipoFecha  Contiene el id del tipo fecha aplica/liberacion
     * @param integer $idEmpleado Contiene el ID del empleado
     *
     * @return arreglo con datos
     */
    public function obtenUnComisiones($idMes, $idQuincena, $tipoFecha, $idEmpleado=0)
    {
        $numero_dias=date('t', mktime(0, 0, 0, substr($idMes, 4, 2), 1, substr($idMes, 0, 4)));

        if ($idQuincena==1) {
            $fechaInicio=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-01";
            $fechaFin=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-15";
        } elseif ($idQuincena==2) {
            $fechaInicio=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-16";
            $fechaFin=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-" . $numero_dias;
        } else {
            $fechaInicio=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-01";
            $fechaFin=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-" . $numero_dias;
        }
        $campos="c.idUn";

        $this->db->where('c.eliminado', 0);

        if ($tipoFecha==1) {
            $this->db->where("c.fechaAplica >=", $fechaInicio);
            $this->db->where("c.fechaAplica <=", $fechaFin);
            $campoFecha="c.fechaAplica";
        } elseif ($tipoFecha==2) {
            $this->db->where("c.fechaLiberado >=", $fechaInicio);
            $this->db->where("c.fechaLiberado <=", $fechaFin);
            $campoFecha="c.fechaAplica";
        }

        if ($idEmpleado<>0) {
            $this->db->where('c.idPersona', $idEmpleado);
        }
        $this->db->where("c.eliminado", 0);

        $tabla1 = TBL_COMISION." c";
        $tabla2 = TBL_TIPOESTATUSCOMISION." tc";
        $tabla3 = TBL_COMISIONMOVIMIENTO." m";
        $tabla4 = TBL_UN." u";
        $tabla5 = TBL_PERSONA." p";

        $this->db->select($campos, false);
        $this->db->from($tabla1);
        $this->db->join($tabla2, 'c.idTipoEstatusComision=tc.idTipoEstatusComision');
        $this->db->join($tabla3, 'c.idComision=m.idComision', 'left');
        $this->db->join($tabla4, 'c.idUn=u.idUn');
        $this->db->join($tabla5, 'c.idPersona=p.idPersona');

        $query = $this->db->group_by('c.idUn')->order_by("p.paterno, p.materno, p.nombre,tc.orden,".$campoFecha)->get();

        if ($query->num_rows>0) {
            $i=0;
            foreach ($query->result() as $fila) {
                $data[$i] = $fila->idUn;
                $i+=1;
            }
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Funcion que devuelve si hay 1 o 2 quincenas para un empleado
     *
     * @param integer $idMes      Contiene el año/mes 201001
     * @param integer $tipoFecha  Contiene el id del tipo fecha aplica/liberacion
     * @param integer $idEmpleado Contiene el ID del empleado
     *
     * @return arreglo con datos
     */
    public function obtenQuincenaComisiones($idMes, $tipoFecha, $idEmpleado=0)
    {
        $numero_dias=date('t', mktime(0, 0, 0, substr($idMes, 4, 2), 1, substr($idMes, 0, 4)));

        $fechaInicio=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-01";
        $fechaFin=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-" . $numero_dias;
        $fechaIntermedia1=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-15";
        $fechaIntermedia2=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-16";

        $campos="SUM(IF(c.fechaAplica <= '".$fechaIntermedia1."', 1,0)) AS primera,
                 SUM(IF(c.fechaAplica >= '".$fechaIntermedia1."', 1,0)) AS segunda";

        $this->db->where("c.eliminado", 0);

        if ($tipoFecha==1) {
            $this->db->where("c.fechaAplica >=", $fechaInicio);
            $this->db->where("c.fechaAplica <=", $fechaFin);
            $campoFecha="c.fechaAplica";
        } elseif ($tipoFecha==2) {
            $this->db->where("c.fechaLiberado >=", $fechaInicio);
            $this->db->where("c.fechaLiberado <=", $fechaFin);
            $campoFecha="c.fechaAplica";
        }

        if ($idEmpleado<>0) {
            $this->db->where('c.idPersona', $idEmpleado);
        }

        $tabla1 = TBL_COMISION." c";
        $tabla2 = TBL_TIPOESTATUSCOMISION." tc";
        $tabla3 = TBL_COMISIONMOVIMIENTO." m";
        $tabla6 = TBL_MOVIMIENTO." mm";
        $tabla4 = TBL_UN." u";
        $tabla5 = TBL_PERSONA." p";

        $this->db->select($campos, false);
        $this->db->from($tabla1);
        $this->db->join($tabla2, 'c.idTipoEstatusComision=tc.idTipoEstatusComision');
        $this->db->join($tabla3, 'c.idComision=m.idComision');
        $this->db->join($tabla6, 'm.idMovimiento=mm.idMovimiento');
        $this->db->join($tabla4, 'c.idUn=u.idUn');
        $this->db->join($tabla5, 'c.idPersona=p.idPersona');
        $this->db->where('mm.idTipoEstatusMovimiento', 66);
        $query = $this->db->order_by("p.paterno, p.materno, p.nombre,tc.orden,".$campoFecha)->get();

        if ($query->num_rows>0) {
            $i=0;
            foreach ($query->result() as $fila) {
                if ($fila->primera>0) {
                    $data[$i]=1;
                    $i+=1;
                }
                if ($fila->segunda>0) {
                    $data[$i]=2;
                    $i+=1;
                }
            }
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Funcion que devuelve los tipos de comisiones de un empleados
     *
     * @param integer $idMes      Contiene el año/mes 201001
     * @param integer $idQuincena Contiene el id del tipo de quincena
     * @param integer $tipoFecha  Contiene el id del tipo fecha aplica/liberacion
     * @param integer $idEmpleado Contiene el ID del empleado
     *
     * @return arreglo con datos
     */
    public function obtenTiposComisiones($idMes, $idQuincena, $tipoFecha, $idEmpleado=0)
    {
        $numero_dias=date('t', mktime(0, 0, 0, substr($idMes, 4, 2), 1, substr($idMes, 0, 4)));

        if ($idQuincena==1) {
            $fechaInicio=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-01";
            $fechaFin=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-15";
        } elseif ($idQuincena==2) {
            $fechaInicio=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-16";
            $fechaFin=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-" . $numero_dias;
        } else {
            $fechaInicio=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-01";
            $fechaFin=substr($idMes, 0, 4)."-" . substr($idMes, 4, 2) . "-" . $numero_dias;
        }
        $campos="c.idTipoComision";

        $this->db->where("c.eliminado", 0);

        if ($tipoFecha==1) {
            $this->db->where("c.fechaAplica >=", $fechaInicio);
            $this->db->where("c.fechaAplica <=", $fechaFin);
            $campoFecha="c.fechaAplica";
        } elseif ($tipoFecha==2) {
            $this->db->where("c.fechaLiberado >=", $fechaInicio);
            $this->db->where("c.fechaLiberado <=", $fechaFin);
            $campoFecha="c.fechaAplica";
        }
        $this->db->where("c.eliminado", 0);

        if ($idEmpleado<>0) {
            $this->db->where('c.idPersona', $idEmpleado);
        }

        $this->db->select($campos, false);
        $this->db->from(TBL_COMISION." c");
        $this->db->join(TBL_TIPOESTATUSCOMISION." tc", 'c.idTipoEstatusComision=tc.idTipoEstatusComision');
        $this->db->join(TBL_COMISIONMOVIMIENTO." m", 'c.idComision=m.idComision');
        $this->db->join(TBL_MOVIMIENTO." mm", 'm.idMovimiento=mm.idMovimiento');
        $this->db->join(TBL_UN." u", 'c.idUn=u.idUn');
        $this->db->join(TBL_PERSONA." p", 'c.idPersona=p.idPersona');
        $this->db->where('mm.idTipoEstatusMovimiento', 66);
        $query = $this->db->group_by('c.idTipoComision')->order_by("p.paterno, p.materno, p.nombre,tc.orden,".$campoFecha)->get();
        if ($query->num_rows>0) {
            $i=0;
            foreach ($query->result() as $fila) {
                $data[$i] = $fila->idTipoComision;
                $i+=1;
            }
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Obtiene las siguientes fechas de inicio y final en los periodos
     *
     * @author antonio Sixtos
     *
     * @return string
     */
    public function obtenUltimasFechas()
    {
        $data = Array();

        $sql="SELECT ADDDATE(fechaFin,1) AS fechauno, IF(RIGHT(fechaFin,2)=15, (LAST_DAY(fechaFin)), ADDDATE(fechafin,15)) as fechados
            FROM ".TBL_COMISIONPERIODO." WHERE fechaEliminacion='0000-00-00 00:00:00' ORDER BY fechaFin DESC LIMIT 1";
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
     * Obtiene datos comision
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function obtieneDatosComision($idComision)
    {
        settype($idComision, 'integer');

        $res = array();

        $this->db->select(
            'c.idComision, c.idTipoEstatusComision, tec.descripcion AS estatus, c.idPersona, c.importe, c.descripcion, '.
            'c.montoComision, c.porcentaje, c.idTipoComision, tc.descripcion AS tipoComision, c.regla'
        );
        $this->db->from(TBL_COMISION.' c');
        $this->db->join(TBL_TIPOCOMISION.' tc', 'tc.idTipoComision=c.idTipoComision');
        $this->db->join(TBL_TIPOESTATUSCOMISION.' tec', 'tec.idTipoEstatusComision=c.idTipoEstatusComision');
        $where = array(
            'c.idComision' => $idComision,
            'c.eliminado' => 0
        );
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            $res['idComision']            = $query->row()->idComision;
            $res['idTipoEstatusComision'] = $query->row()->idTipoEstatusComision;
            $res['estatus']               = $query->row()->estatus;
            $res['idPersona']             = $query->row()->idPersona;
            $res['importe']               = $query->row()->importe;
            $res['descripcion']           = $query->row()->descripcion;
            $res['montoComision']         = $query->row()->montoComision;
            $res['porcentaje']            = $query->row()->porcentaje;
            $res['idTipoComision']        = $query->row()->idTipoComision;
            $res['tipoComision']          = $query->row()->tipoComision;
            $res['regla']                 = $query->row()->regla;
        }

        return $res;
    }


    /**
    * Obtiene movimiento de una comision en especial
    *
    * @author Santa Garcia
    *
    * @return void
    */
    public function obtieneComisionMovimiento($idComision)
    {
        settype($idComision, 'integer');

        $this->db->select('idComision, idMovimiento');
        $this->db->from(TBL_COMISIONMOVIMIENTO);
        $where = array(
            'idComision'      => $idComision,
            'fechaEliminacion'=>'0000-00-00 00:00:00'
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
     * Obtiene Estatus  y comentario de una comision
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function obtieneEstatusComentarioComision($idComision, $idTipoEstatus)
    {
        settype($idComision, 'integer');

        $this->db->select('idComision,idComisionDetalle, comentario, idPersona, idTipoEstatusComision');
        $this->db->from(TBL_COMISIONDETALLE);
        $where = array('idComision' => $idComision,'idTipoEstatusComision'=>$idTipoEstatus);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }


    /**
     * Lista estaus Socio
     *
     * @return array
     */
    public function listaEstatusComisiones()
    {
        $data = array();
        $this->db->select('idTipoEstatusComision, descripcion');
        $this->db->from(TBL_TIPOESTATUSCOMISION);
        $this->db->where('activo', 1);
        $query = $this->db->get();
        $data['todos']='Todos';
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idTipoEstatusComision] = $fila->descripcion;
            }
            return $data;
        } else {
            return false;
        }
    }

    /**
     * obtiene puesto que autoriza comision
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function obtienePuestoAutoriza($idPuesto, $tipoComision)
    {
        settype($idPuesto, 'integer');
        settype($tipoComision, 'integer');

        $this->db->select('idTipoComisionPuestoAutoriza');
        $this->db->from(TBL_TIPOCOMISIONPUESTOAUTORIZA);
        $this->db->where('idPuesto', $idPuesto);
        $this->db->where('idTipoComision', $tipoComision);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return false;
        }
    }

    /**
     * Lista tipo de comisiones por permiso
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function listaTipoComisionXpermiso()
    {
        $sql = "SELECT distinct tc.idTipoComision, tc.descripcion FROM ".TBL_TIPOCOMISION." tc
            INNER JOIN ".TBL_OBJETO." o on o.orden = tc.idTipoComision and o.tipoObjeto=1 and o.estatus=1
            INNER JOIN ".TBL_PERMISOPUESTO." pp on pp.idObjeto=o.idObjeto and pp.estatus=1
            INNER JOIN ".TBL_EMPLEADOPUESTO." ep on ep.idPuesto = pp.idPuesto and ep.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN ".TBL_EMPLEADO." e on e.idEmpleado=ep.idEmpleado and e.idTipoEstatusEmpleado=196 and ep.fechaEliminacion='0000-00-00 00:00:00'
            WHERE o.idObjetoPadre = 532 AND e.idPersona = ".$this->session->userdata('idPersona')."
            UNION ALL
            SELECT distinct tc.idTipoComision, tc.descripcion FROM ".TBL_TIPOCOMISION." tc
            INNER JOIN ".TBL_OBJETO." o on o.orden = tc.idTipoComision and o.tipoObjeto=1 and o.estatus=1
            INNER JOIN ".TBL_PERMISOUSARIO." pu on pu.idObjeto = o.idObjeto and pu.estatus=1 and pu.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN ".TBL_USUARIOS." u on u.IdUsuario=pu.idUsuario and u.Estatus=1 and u.fechaEliminacion='0000-00-00 00:00:00'
            WHERE o.idObjetoPadre = 532 AND u.idPersona = ".$this->session->userdata('idPersona')."
            GROUP BY tc.idTipoComision";
        $query = $this->db->query($sql);
        $lista = array();
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idTipoComision] = utf8_encode($fila->descripcion);
            }
        }
        return $lista;
    }


    /**
    * Lista permisos para autizar y reasignar por tipo de comision y por persona
    *
    * @author Santa Garcia
    *
    * @return void
    */
    public function listaPermisosAutorizaReasigna($tipoComision)
    {
		settype($tipoComision, 'integer');

		$idPersona = $this->session->userdata('idPersona');
        $sql = "SELECT * FROM (
				SELECT o.orden, o.idObjeto, e.idPersona, o2.nombreObjeto, o2.estatus
				FROM objeto o
				INNER JOIN objeto o2 ON o2.idObjetoPadre=o.idObjeto AND o2.estatus=1
				INNER JOIN permisopuesto pp ON pp.idObjeto=o2.idObjeto AND pp.estatus=1
				INNER JOIN empleadopuesto ep ON ep.idPuesto=pp.idPuesto AND ep.fechaEliminacion='0000-00-00 00:00:00'
				INNER JOIN empleado e ON e.idEmpleado=ep.idEmpleado AND e.idTipoEstatusEmpleado=196 AND e.fechaEliminacion='0000-00-00 00:00:00'
					AND e.idPersona=$idPersona
				WHERE o.idObjetoPadre=532 AND o.estatus=1
					AND o.orden=$tipoComision
				UNION ALL
				SELECT o.orden, o.idObjeto, u.idPersona, o2.nombreObjeto, o2.estatus
				FROM objeto o
				INNER JOIN objeto o2 ON o2.idObjetoPadre=o.idObjeto AND o2.estatus=1
				INNER JOIN permisousuario pu ON pu.idObjeto=o2.idObjeto AND pu.estatus=1 AND pu.fechaEliminacion='0000-00-00 00:00:00'
				INNER JOIN usuarios u ON u.IdUsuario=pu.idUsuario
					AND u.idPersona=$idPersona
				WHERE o.idObjetoPadre=532 AND o.estatus=1
					AND o.orden=$tipoComision
			) a
			GROUP BY a.idObjeto, a.nombreObjeto, a.idPersona";
        $query = $this->db->query($sql);

        $lista = array();
        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     *
     * @return string
     */
    public function periodosReporte()
    {
        $fechaOrigen = date('Y-m-d');
        $super = $this->permisos_model->validaTodosPermisos(PER_SUPERUSUARIO);
        $meses[0] = "Seleccione un mes";

        for ($i=1; $i<13; $i++) {
            $fechaInicioMes     = date('Y-m', strtotime($fechaOrigen)).'-01';
            $fechaQuincena      = date('Y-m', strtotime($fechaOrigen)).'-15';
            $fechaInicioProxMes = date('Y-m-d', strtotime($fechaInicioMes.' +1 month'));
            $fechaFinMes        = date('Y-m-d', strtotime($fechaInicioProxMes.' -1 day'));
            if ($i == 1) {
                $fecha = date('Y-m-d', strtotime($fechaFinMes));
                $datosFecha = explode('-', $fecha);
                if((int)$datosFecha[0].$datosFecha[1]>=201209 || $super==true) {
                    $meses[$fecha] = $datosFecha[2].' - '.obtenNombreMes($datosFecha[1]).' - '.$datosFecha[0];
                }

                $fecha = date('Y-m-d', strtotime($fechaQuincena));
                $datosFecha = explode('-', $fecha);
                if((int)$datosFecha[0].$datosFecha[1]>=201209 || $super==true) {
                    $meses[$fecha] = $datosFecha[2].' - '.obtenNombreMes($datosFecha[1]).' - '.$datosFecha[0];
                }
            } else {
                $fecha = date('Y-m-d', strtotime($fechaFinMes));
                $datosFecha = explode('-', $fecha);
                if((int)$datosFecha[0].$datosFecha[1]>=201209 || $super==true) {
                    $meses[$fecha] = $datosFecha[2].' - '.obtenNombreMes($datosFecha[1]).' - '.$datosFecha[0];
                }

                $fecha = date('Y-m-d', strtotime($fechaQuincena));
                $datosFecha = explode('-', $fecha);
                if((int)$datosFecha[0].$datosFecha[1]>=201209 || $super==true) {
                    $meses[$fecha] = $datosFecha[2].' - '.obtenNombreMes($datosFecha[1]).' - '.$datosFecha[0];
                }
            }
            $fechaOrigen = date('Y-m-d', strtotime($fechaOrigen.' -1 month'));
        }
        return $meses;
    }

    /**
     * Funcion que recalcula las comisiones para una persona
     *
     * @param string  $idEmpleado     Contiene el ID del empleado
     * @param string  $idMes          Contiene el año/mes 201001
     * @param array   $idUn           Contiene el ID de la unidad
     * @param string  $idTipoComision Contiene el id del tipo comision
     * @param integer $idTipoQuincena Contiene el id del tipo de quincena
     *
     * @return $mensaje de resultado de recalculo
     */
    public function recalculo($idEmpleado, $idMes, $idUn = 0, $idTipoComision = 0, $idTipoQuincena = 1)
    {
        $trazar = 0;
        $anio = substr($idMes, 0, 4);
        $mes = substr($idMes, 4, 2);
        $mensaje = "";
        $actualizo = 0;

        if ($idTipoQuincena==3) {
            $quincena=$this->obtenQuincenaComisiones($idMes, 1, $idEmpleado);
        } else {
            $quincena[0]=$idTipoQuincena;
        }

        if ($idTipoComision==0) {
            $tipos=$this->obtenTiposComisiones($idMes, $idTipoQuincena, 1, $idEmpleado);
        } else {
            $tipos[0]=$idTipoComision;
        }

        for ($contQuin=0; $contQuin<count($quincena); $contQuin++) {
            if ($quincena[$contQuin]==1) {
                $quin_nombre="primera";
            } else {
                $quin_nombre="segunda";
            }
            for ($contTipos=0; $contTipos<count($tipos); $contTipos++) {
                $comisiones = $this->reporte($idMes, $quincena[$contQuin], 1, 0, $tipos[$contTipos], $idEmpleado, array(0, 1, 2), 66);
                $idComision = 0;
                $monto = 0;
                if (count($comisiones)>0) {
                    $datoss = array (
                        'idTipoComision' => $tipos[$contTipos],
                        'anio'           => $anio,
                        'mes'            => $mes,
                        'idTipoQuincena' => $quincena[$contQuin],
                        'idUn'           => $idUn
                    );
                    $this->db->select('idComisionRegla');
                    $this->db->from('comisionregla');
                    $this->db->where($datoss);
                    $query = $this->db->get();
                    if ($query->num_rows>0) {
                        foreach ($query->result() as $fila) {
                            $idComision=$fila->idComisionRegla;
                        }
                        $detallesComisiones = $this->detallesComision($idComision);

                        if (count($detallesComisiones)<1) {
                            $mensaje.="No existen detalles para la comision  de la ". $quin_nombre ." quincena del mes " . $mes . " del club " . $comisiones[0]->club . "\\n";
                        } else {
                            for ($i=0; $i<count($comisiones); $i++) {
                                $monto+=$comisiones[$i]->importe;
                            }

                            $this->identificaDetalleComision(
                                count($comisiones),
                                $monto,
                                $comisiones,
                                $detallesComisiones,
                                $idUn
                            );
                            $actualizo=1;
                        }
                    } else {
                        $ci =& get_instance();
                        $ci->load->model('un_model');

                        $club=$ci->un_model->nombre($idUn);
                        $mensaje.="No existe comision para la ". $quin_nombre ." quincena del mes " . $mes . " del club " . $club . "\\n";
                    }
                }
            }
        }

        if ($mensaje=="") {
            if ($actualizo==1) {
                $mensaje = "Se actualizaron correctamente las comisiones";
            } else {
                $mensaje = "No se actualizo ninguna comision";
            }
        }
        return utf8_encode($mensaje);
    }

    /**
     * Recalculode comisiones de venta
     *
     * @param  integer $club    [description]
     * @param  integer $anio    [description]
     * @param  integer $mes     [description]
     * @param  integer $periodo [description]
     *
     * @author Jorge Cruz
     *
     * @return boolean          [description]
     */
    public function recalculaComision($club, $anio, $mes, $periodo)
    {
        settype($club, 'integer');
        settype($anio, 'integer');
        settype($mes, 'integer');
        settype($periodo, 'integer');

        if ($periodo==1) {
            $sql = "SELECT COUNT(*) AS total
                FROM ".TBL_COMISIONPERIODO."
                WHERE DATE('$anio-$mes-15') BETWEEN fechainicio AND fechafin AND DATE(NOW())<=DATE_ADD(fechaCorte, INTERVAL 1 DAY)
                AND fechaEliminacion='0000-00-00 00:00:00'";
        } else {
            $sql = "SELECT COUNT(*) AS total
                FROM ".TBL_COMISIONPERIODO."
                WHERE LAST_DAY(DATE('$anio-$mes-15')) BETWEEN fechainicio AND fechafin AND DATE(NOW())<=DATE_ADD(fechaCorte, INTERVAL 1 DAY)
                AND fechaEliminacion='0000-00-00 00:00:00'";
        }
        $query = $this->db->query($sql);
        $row = $query->row();

        if ($row->total > 0) {
            $sql = "CALL spComisionesRecalcula(".$club.", ".$anio.", ".$mes.", ".$periodo.")";
            $this->db->query($sql);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Replica detalle de comisonregladetalle a toda una region
     *
     * @param array   $info     Datos a insertar en comisionregladetalle
     * @param integer $idRegion Identificador de region
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function replicarDetalleRegion($info, $idRegion)
    {
        $CI =& get_instance();

        $CI->load->model('un_model');

        $idComisionRegla = $info['comision'];
        $clubs           = $CI->un_model->obtenClubsRegion($idRegion);

        if ($clubs) {
            $datosRegla = $this->obtenDatosRegla($info['comision']);

            if ($datosRegla) {
                foreach ($clubs as $idRow => $datosClub) {
                    if ($datosClub['idUn'] != $datosRegla['idUn']) {
                        $where = array(
                            'cr.idTipoComision'     => $datosRegla['idTipoComision'],
                            'cr.idTipoQuincena'     => $datosRegla['idTipoQuincena'],
                            'cr.idUn'               => $datosClub['idUn'],
                            'cr.anio'               => $datosRegla['anio'],
                            'cr.mes'                => $datosRegla['mes'],
                            'cr.esquema'            => $datosRegla['esquema'],
                            'cr.revisado'           => $datosRegla['revisado'],
                            'cr.idComisionRegla <>' => $info['comision']
                        );
                        $query = $this->db->select("cr.idComisionRegla", false)->get_where(TBL_COMISIONREGLA.' cr', $where);

                        if ($query->num_rows) {
                            $info['comision'] = $query->row()->idComisionRegla;
                        } else {
                            $info['comision'] = $this->guardarRegla($datosRegla['idTipoComision'],
                                $datosRegla['idTipoQuincena'], $datosClub['idUn'], $datosRegla['anio'],
                                $datosRegla['mes'], $datosRegla['esquema']);
                        }
                        $this->guardarDetalle($info, 0);
                    }
                }
            }
        }
        $info['comision'] = $idComisionRegla;

        return $this->guardarDetalle($info, 0);
    }

    /**
     * Genera el query de las comisiones de los vendedores, totales o individuales
     *
     * @param string  $idMes          Contiene la fecha en formato 201004
     * @param integer $idQuincena     Contiene a) "1" Quincena del 1 al 15 o b)"2" del 16 - 31
     * @param integer $tipoFecha      Contiene el campo de fecha para buscar a)fechaAplica, b)fechaliberado
     * @param integer $idClub         Contiene el id de la unidad de negocio a buscar
     * @param integer $idTipoComision Contiene el id del tipo de comision
     * @param integer $idEmpleado     Contiene el id del empleado a buscar
     * @param integer $estatus        Indica si se requiere de un solo status en especifico
     *
     * @return boolean
     */
    public function reporte($mes, $idQuincena, $tipoFecha, $idClub=0, $idTipoComision=0, $idPersona=0,
        $estatus=0, $estatusMovimiento=0, $tipoEstatus, $excel=0, $tipoComisiones = '', $idEmpleado = '',
        $idMembresia = 0)
    {
        settype($idQuincena, 'integer');
        settype($tipoFecha, 'integer');
        settype($idClub, 'integer');
        settype($idTipoComision, 'integer');
        settype($idPersona, 'integer');
        settype($estatus, 'integer');
        settype($estatusMovimiento, 'integer');
        settype($tipoComisiones, 'string');
        settype($idEmpleado, 'integer');
        settype($idMembresia, 'integer');

        if (is_date($mes.'-01')===false) {
            $mes = date('Y-m');
        }
        $hora = date('His');

        $this->db->select('LAST_DAY("'.$mes.'-01") AS final');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $fechaFin = $fila['final'];
        }

        if ($idQuincena==1) {
            $fechaInicio = $mes.'-01';
            $fechaFin = $mes.'-15';
        } elseif ($idQuincena==2) {
            $fechaInicio = $mes.'-16';
        } elseif ($idQuincena==99) {
            $q = 'SELECT DATE_SUB(DATE(NOW()), INTERVAL 6 MONTH) AS inicio';
            $query = $this->db->query($q);
            if ($query->num_rows > 0) {
                $fila = $query->row_array();
                $fechaInicio = $fila['inicio'];
            }
            $fechaFin = date('Y-m-d');
        } else {
            $fechaInicio = $mes.'-01';
        }

        $sql = 'CREATE TEMPORARY TABLE tmp_socioscomisiones_'.$hora.'
            SELECT s.idUnicoMembresia, s.idEsquemaPago, s.idPersona
            FROM '.TBL_SOCIO.' s
            WHERE s.eliminado=0 AND s.idEsquemaPago=2 AND s.idTipoEstatusSocio<>82
            GROUP BY s.idUnicoMembresia';
        $this->db->query($sql);

        $sql = 'CREATE INDEX idx_tmp_socioscomisiones_'.$hora.' ON tmp_socioscomisiones_'.$hora.' (idUnicoMembresia)';
        $this->db->query($sql);

        $sql = 'CREATE TEMPORARY TABLE tmp_comisiones_'.$hora.' '.
            'SELECT c.idComision, m.idMovimiento, c.descripcion, c.importe, a.idPersona as titular, '.
            'a.idEsquemaPago,mm.idUnicoMembresia, c.idtipoEstatusComision, tc.descripcion AS estatus, c.idPersona, '.
            'CONCAT_WS(\' \',p.paterno,p.materno,p.nombre) AS nombre, u.nombre AS club, '.
            'c.montoComision, DATE(c.fechaRegistro) AS fechaRegistro, DATE(c.fechaAplica) AS fechaAplica, DATE(c.fechaLiberado) AS fechaLiberado, DATE(f.fecha) AS fechaFactura , '.
            'c.fechaEmision, c.porcentaje, IF(cp.fechaCorte >= date(now()),1,0) as reasignar, c.regla, c.idTipoComision, '.
            'cn.inicial*IF(c.regla="CAT", 1, 0.5) AS inicial, cn.bonos*IF(c.regla="CAT", 1, 0.5) AS bonos ';

        $sql2 ="SELECT CONCAT_WS(' ',p.paterno,p.materno,p.nombre) AS nombre, c.descripcion,  c.importe, ".
            "tc.descripcion AS estatus, u.nombre AS club, c.montoComision, c.idComision, c.regla ";

        $cond = 'FROM '.TBL_COMISION.' c '.
            'INNER JOIN '.TBL_TIPOESTATUSCOMISION.' tc ON c.idTipoEstatusComision=tc.idTipoEstatusComision '.
            'INNER JOIN '.TBL_UN.' u ON u.idUn = c.idUn AND u.fechaEliminacion=\'0000-00-00\' AND u.activo=1 '.
            'INNER JOIN '.TBL_PERSONA.' p ON p.idPersona=c.idPersona AND p.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'LEFT JOIN '.TBL_EMPLEADO.' e ON e.idPersona=p.idPersona AND p.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'LEFT JOIN '.TBL_COMISIONNETO.' cn ON c.idComision=cn.idComision '.
            'LEFT JOIN '.TBL_COMISIONMOVIMIENTO.' m ON c.idComision=m.idComision AND m.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'LEFT JOIN '.TBL_MOVIMIENTO.' mm ON m.idMovimiento=mm.idMovimiento AND mm.eliminado=0 '.
            'LEFT JOIN tmp_socioscomisiones_'.$hora.' AS a ON a.idUnicoMembresia=mm.idUnicoMembresia '.
            'LEFT JOIN '.TBL_FACTURAMOVIMIENTO.' fm ON fm.idMovimiento=mm.idMovimiento '.
            'LEFT JOIN '.TBL_FACTURA.' f ON f.idFactura=fm.idFactura '.
            'LEFT JOIN '.TBL_COMISIONPERIODO.' cp ON c.fechaAplica BETWEEN cp.fechaInicio and cp.fechaFin '.
            'WHERE c.eliminado=0 ';

        if (!$idMembresia) {
            switch ($tipoEstatus) {
                case 2: // Facturacion
                    $cond .= ' AND DATE(c.fechaEmision)>=\''.$fechaInicio.'\' AND DATE(c.fechaEmision)<=\''.$fechaFin.'\' ';
                    $sql2 .= " DATE(f.fecha) AS fecha";
                    break;
                default:
                    if ($tipoFecha == 1) {
                        $cond .= ' AND DATE(c.fechaAplica)>=\''.$fechaInicio.'\' AND DATE(c.fechaAplica)<=\''.$fechaFin.'\' ';
                        $sql2 .= " DATE(c.fechaAplica) AS fecha";
                    } else {
                        $cond .= ' AND DATE(c.fechaAplica)>=\''.$fechaInicio.'\' AND DATE(c.fechaAplica)<=\''.$fechaFin.'\' ';
                        $sql2 .= " DATE(c.fechaRegistro) AS fecha";
                    }
                    break;
            }
        }
        if ($idClub > 0) {
            if ($tipoFecha == 1) {
                if ($idTipoComision==1) {
                    $cond .= ' AND (f.idUn = '.$idClub.' OR (c.idUn = '.$idClub.' AND c.idTipoComision=20) )';
                } else {
                    $cond .= ' AND c.idUn = '.$idClub;
                }
            } else {
                $cond .= ' AND c.idUn = '.$idClub;
            }
        }
        if ($idPersona > 0 && $excel==0) {
            $cond .= ' AND c.idPersona = '.$idPersona;
        }
        if ($idEmpleado > 0 && $excel==0) {
            $cond .= ' AND e.idEmpleado = '.$idEmpleado;
        }
        if ($tipoEstatus != 'todos') {
            $t = (int)$tipoEstatus;
            if ($t>0) {
                $cond .= ' AND tc.idTipoEstatuscomision = '.$tipoEstatus;
            } else {
                $cond .= ' AND tc.idTipoEstatuscomision IN ('.$tipoEstatus.') ';
            }
        }
        if ($idTipoComision == 0) {
            if ($tipoComisiones != '') {
                $cond .= ' AND c.idTipoComision IN('.$tipoComisiones.') ';
            }
        } else {
            if ($idTipoComision==1) {
                $cond .= ' AND c.idTipoComision IN (1,20) ';
            } else {
                $cond .= ' AND c.idTipoComision = '.$idTipoComision;
            }
        }
        if ($idMembresia) {
            $cond.= " AND c.descripcion LIKE '%# ".$idMembresia."%' ";
        }
        $sql = $sql.' '.$cond.' GROUP BY c.idComision';
        $sql2 = $sql2.' '.$cond.' GROUP BY c.idComision';

        if ($excel==1) {
            return $sql2;
        }

        $this->db->query($sql);
        $this->db->from('tmp_comisiones_'.$hora);
        $query = $this->db->get();

        $sql = 'CREATE TEMPORARY TABLE tmp_comisiones_persona_'.$hora.' '.
            'SELECT DISTINCT idPersona, nombre FROM tmp_comisiones_'.$hora.' ORDER BY nombre';
        $this->db->query($sql);
        $this->db->from('tmp_comisiones_persona_'.$hora);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $data=array();
            foreach ($query->result_array() as $fila) {
                $this->db->from('tmp_comisiones_'.$hora);
                $this->db->where('idPersona', $fila['idPersona']);
                $this->db->order_by('descripcion');
                $query_x = $this->db->get();
                if ($query_x->num_rows>0) {
                    $comisiones = array();
                    foreach ($query_x->result_array() as $fila_x) {
                        unset($fila_x['idPersona']);
                        unset($fila_x['nombre']);
                        $comisiones[] = $fila_x;
                    }
                    $fila['comisiones'] = $comisiones;
                    unset($comisiones);
                }

                $data[] = $fila;
            }
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Obtiene detalle de acelerador
     *
     * @param integer $idPersona
     * @param integer $idUn
	 * @param integer $quincena
	 * @param integer $periodo
	 *
     * @author Antonio Sixtos
     *
     * @return array
     */
    function reporteComisionAcelerador($idPersona, $idUn, $quincena, $periodo)
    {
        settype($idPersona, 'integer');
        settype($idUn, 'integer');
        settype($quincena, 'integer');

        if($quincena==1) {
            $rango1= $periodo.'-01';
            $rango2= $periodo.'-15';
        }
        if($quincena==2) {
			$mes = substr($periodo, 5,2);
			$mes = intval($mes);
			$ultimodia = intval(date("t", $mes))-1;
            $rango1= $periodo.'-16';
            $rango2= $periodo.'-'.$ultimodia;
        }
        if($quincena==3 || $quincena==0) {
			$mes = substr($periodo, 5,2);
			$mes = intval($mes);
			$ultimodia = intval(date("t", $mes))-1;
            $rango1= $periodo.'-01';
            $rango2= $periodo.'-'.$ultimodia;
        }

        $datos = array();
        $sql = "SELECT p.nombre, ep.descripcion, ca.integrante, ca.importe, IF(ca.idConvenioDetalle=0,'No','Si') AS 'corporativa', CONCAT(SUBSTRING(con.nombre,1,10),'...') AS 'nombreconvenio'
                FROM crm.comision c
                INNER JOIN crm.comisionacelerador ca ON ca.idComision=c.idComision
                INNER JOIN crm.productomantenimiento pm ON pm.idMantenimiento=ca.idMantenimiento
                INNER JOIN crm.producto p ON p.idProducto=pm.idProducto
                INNER JOIN crm.esquemapago ep ON ep.idEsquemaPago=ca.idEsquemaPago
                INNER JOIN crm.conveniodetalle cd ON cd.idConvenioDetalle=ca.idConvenioDetalle
                INNER JOIN crm.convenio con ON con.idConvenio=cd.idConvenioDetalle
                WHERE c.idTipoComision IN (1) AND c.fechaAplica BETWEEN '".$rango1."' AND '".$rango2."'
                    AND c.idPersona IN (".$idPersona.")";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $datos = $query->result_array();
        }

        return $datos;
    }

    /**
     * Actualiza comisiones revisadas en base de datos
     *
     * @param string  $periodo        Fecha de periodo de pago de comision
     * @param integer $idUn           Identificador de unidad de negocio
     * @param integer $idTipoComision Identificador de tipo de comision
     * @param integer $revisado       Bandera de accion de estatus de revision
     * @param integer $revisadoActual Bandera de revision actual de comision, en caso de ser 3 el registo no existe y se inserta
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function revisa ($periodo, $idUn, $idTipoComision, $revisado, $revisadoActual)
    {
        settype($periodo, 'string');
        settype($idUn, 'integer');
        settype($idTipoComision, 'integer');

        $datos = array(
            'mensaje'  => 'Error faltan datos',
            'error'    => 1,
            'revisado' => 0
        );
        if (!$periodo or ! $idUn or ! $idTipoComision) {
            return $datos;
        }
        $datosPeriodo     = explode('-', $periodo);
        $anio             = $datosPeriodo[0];
        $mes              = $datosPeriodo[1];
        $dia              = $datosPeriodo[2];

        if ($dia == 15) {
            $idTipoQuincena = 1;
        } else {
            $idTipoQuincena = 2;
        }
        if ($idTipoComision == TIPO_COMISION_GERENTE_VENTAS or $idTipoComision == TIPO_COMISION_GERENTENACIONAL) {
            $idTipoQuincena = 3;
        }
        $where            = array(
            'idTipoComision' => $idTipoComision,
            'idTipoQuincena' => $idTipoQuincena,
            'idUn'           => $idUn,
            'anio'           => $anio,
            'mes'            => $mes
        );
        if ($revisadoActual == 3) {
            $set             = $where;
            $set['revisado'] = $revisado;
            if ($this->db->insert(TBL_COMISIONREGLA, $set)) {
                $datos['mensaje']  = '';
                $datos['error']    = 0;
                $datos['revisado'] = $revisado;

                if ($revisado) {
                    $this->permisos_model->log('Inserta marca de revisado 1 en periodo '.$periodo.' con tipo comision '.$idTipoComision.' y idUn '.$idUn, LOG_COMISIONES);
                } else {
                    $this->permisos_model->log('Inserta marca de revisado 0 en periodo '.$periodo.' con tipo comision '.$idTipoComision.' y idUn '.$idUn, LOG_COMISIONES);
                }
            } else {
                $datos['mensaje'] = 'Error al insertar informacion';
                $datos['error']   = 2;
            }
        } else {
            $set = array('revisado' => $revisado);

            if ($this->db->update(TBL_COMISIONREGLA, $set, $where)) {
                $datos['mensaje']  = '';
                $datos['error']    = 0;
                $datos['revisado'] = $revisado;

                if ($revisado) {
                    $this->permisos_model->log('Actualiza marca de revisado a 1 en periodo '.$periodo.' con tipo comision '.$idTipoComision.' y idUn '.$idUn, LOG_COMISIONES);
                } else {
                    $this->permisos_model->log('Actualiza marca de revisado a 0 en periodo '.$periodo.' con tipo comision '.$idTipoComision.' y idUn '.$idUn, LOG_COMISIONES);
                }
            } else {
                $datos['mensaje'] = 'Error al actualizar informacion';
                $datos['error']   = 3;
            }
        }
        return $datos;
    }

    /**
     * Valida si ya fue revisada una comision
     *
     * @param string  $periodo        Periodo de comision
     * @param integer $idUn           Identificador de unidad de negocio
     * @param integer $idTipoComision Identificador de tipo de comision
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaRevision ($periodo, $idUn, $idTipoComision)
    {
        settype($periodo, 'string');
        settype($idUn, 'integer');
        settype($idTipoComision, 'integer');

        $datos = array(
            'mensaje'          => 'Error faltan datos',
            'error'            => 1,
            'revisado'         => 0,
            'disabled'         => '',
            'fechaCorteLimite' => ''
        );
        if (!$periodo or ! $idUn or ! $idTipoComision) {
            return $datos;
        }
        $datos['mensaje'] = '';
        $datos['error']   = 0;
        $datosPeriodo     = explode('-', $periodo);
        $anio             = $datosPeriodo[0];
        $mes              = $datosPeriodo[1];
        $dia              = $datosPeriodo[2];
        $fechaCorte       = $this->obtenFechaCorteRevision($periodo);
        $fechaCorteLimite = '';

        if ($fechaCorte) {
            $fechaCorteLimite = date('Y-m-d', strtotime($fechaCorte.' + 1 day'));
        }
        if ($dia == 15) {
            $idTipoQuincena = 1;
        } else {
            $idTipoQuincena = 2;
        }
        if ($idTipoComision == TIPO_COMISION_GERENTE_VENTAS or $idTipoComision == TIPO_COMISION_GERENTENACIONAL) {
            $idTipoQuincena = 3;
        }
        $where            = array(
            'idTipoComision' => $idTipoComision,
            'idTipoQuincena' => $idTipoQuincena,
            'idUn'           => $idUn,
            'anio'           => $anio,
            'mes'            => $mes
        );
        $query = $this->db->select("IFNULL(GROUP_CONCAT(revisado), 3)AS revisado", false)->get_where(TBL_COMISIONREGLA, $where);

        if ($query->num_rows) {
            $row = $query->row();
            $pos = strpos($row->revisado, ',');

            if ($pos === false) {
                $datos['revisado'] = $row->revisado;
            } else {
                $arregloRevisado = explode(',', $row->revisado);

                if (in_array(1, $arregloRevisado) and in_array(0, $arregloRevisado)) {
                    $datos['revisado'] = 0;
                } else {
                    $datos['revisado'] = $arregloRevisado[0];
                }
            }
            if ($datos['revisado'] == 1 and $fechaCorteLimite and (strtotime(date('Y-m-d')) > strtotime($fechaCorteLimite) )) {
                $datos['disabled'] = 'disabled';
            }
        }
        $datos['fechaCorteLimite'] = $fechaCorteLimite;

        return $datos;
    }
}
