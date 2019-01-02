<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use API_EPS\Models\CatRutinas;
use API_EPS\Models\MenuActividad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use API_EPS\Models\Empleado;
use API_EPS\Models\Permiso;
use API_EPS\Models\Persona;


use API_EPS\Models\Permiso;

class Evento extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.evento';
    protected $primaryKey = 'idEvento';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     *
     *
     * @param integer $id
     *
     * @return boolean
     */
    public function activaOpcion($id)
    {
        $datos = array (
            'fechaEliminacion' => date("Y-m-d H:i:s")
        );
        $where = array('idEventoFecha'=> $id);
        DB::connection('crm')->where($where);
        DB::connection('crm')->update(TBL_EVENTOFECHA, $datos);

        $this->permisos_model->log('Se elimina fecha clase para evento', LOG_EVENTO);

        return true;
    }

    /**
     * Activa un producto asociado a un club
     *
     * @param integer $evento Producto a ser utlizado
     * @param integer $un     Unidad de negocio asociada
     *
     * @return boolean
     */
    public function activoEventoClub($evento, $un)
    {
        settype($evento, 'integer');
        settype($un, 'integer');
        if ($evento == 0) {
            return false;
        }
        if ($un == 0) {
            return false;
        }

        DB::connection('crm')->select('activo');
        DB::connection('crm')->from(TBL_EVENTOUN);
        DB::connection('crm')->where('idEvento', $evento);
        DB::connection('crm')->where('idUn', $un);
        DB::connection('crm')->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = DB::connection('crm')->get();
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            if ($fila['activo']==1) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Guarda coordinador de evento
     *
     * @param integer $idEventoUn   Identificador de eventoun
     * @param string  $inicioEvento Fecha inicio de evento
     * @param string  $finEvento    Fecha fin de evento
     * @param integer $idPersona    Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function actualizaCoordinador ($idEventoUn, $inicioEvento, $finEvento, $idPersona = 0)
    {
        settype($idEventoUn, 'integer');
        settype($inicioEvento, 'string');
        settype($finEvento, 'string');
        settype($idPersona, 'integer');

        $datos = array(
            'error'                 => 1,
            'mensaje'               => 'Error faltan datos',
            'idEventoUnCoordinador' => 0
        );
        if (!$idEventoUn or !$inicioEvento or !$finEvento) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $where = array(
            'idEventoUn'       => $idEventoUn,
            'inicioEvento'     => $inicioEvento,
            'finEvento'        => $finEvento,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select("DISTINCT idEventoUnCoordinador", false)->get_where(TBL_EVENTOUNCOORDINADOR, $where);

        if ($query->num_rows) {
            $set   = array('fechaEliminacion' => date('Y-m-d H:i:s'));

            if (!DB::connection('crm')->update(TBL_EVENTOUNCOORDINADOR, $set, $where)) {
                $datos['error']   = 2;
                $datos['mensaje'] = 'Error al eliminar coordinadores anteriores';
            }
        }
        if ($idPersona) {
            $set = $where;
            $set['idPersona'] = $idPersona;

            unset($set['fechaEliminacion']);

            if (!DB::connection('crm')->insert(TBL_EVENTOUNCOORDINADOR, $set)) {
                $datos['error']   = 2;
                $datos['mensaje'] = 'Error al insertar nuevo coordinador';
            } else {
                $datos['idEventoUnCoordinador'] = DB::connection('crm')->insert_id();
            }
        }
        return $datos;
    }

    /**
     * Actualiza configuracion de talla en eventouncategoria
     *
     * @param integer $idEventoUnCategoria Identificador de eventouncategoria
     * @param integer $activo          Estatus de la configuracion
     *
     * @return array
     */
    public function actualizaConfigCategoria($idEventoUnCategoria, $activo)
    {
        settype($activo, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );
        if ($idEventoUnCategoria=='') {
            return $datos;
        }
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al actualizar categoria';
        $where = array('idEventoUnCategoria' => $idEventoUnCategoria);
        $set = array('activo' => $activo);
        if (DB::connection('crm')->update(TBL_EVENTOUNCATEGORIA, $set, $where)) {
            $datos['error']    = 0;
            $datos['mensaje'] = '';
        }
        return $datos;
    }

    /**
     * Actualiza configuracion de talla en eventountalla
     *
     * @param integer $idEventoUnTalla Identificador de eventountalla
     * @param integer $activo          Estatus de la configuracion
     *
     * @return array
     */
    public function actualizaConfigTalla($idEventoUnTalla, $activo)
    {
        settype($idEventoUnTalla, 'integer');
        settype($activo, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );
        if (!$idEventoUnTalla) {
            return $datos;
        }
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al actualizar talla';
        $where = array('idEventoUnTalla' => $idEventoUnTalla);
        $set = array('activo' => $activo);
        if (DB::connection('crm')->update(TBL_EVENTOUNTALLA, $set, $where)) {
            $datos['error']    = 0;
            $datos['mensaje'] = '';
        }
        return $datos;
    }

    /**
     * Actualiza configuracion de club en eventounentrega
     *
     * @param integer $idEventoUnEntrega Identificador de eventounentrega
     * @param integer $activo            Estatus de la configuracion
     *
     * @return array
     */
    public function actualizaConfigUnEntrega($idEventoUnEntrega, $activo)
    {
        settype($idEventoUnEntrega, 'integer');
        settype($activo, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );
        if (!$idEventoUnEntrega) {
            return $datos;
        }
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al actualizar club de entrega';
        $where = array('idEventoUnEntrega' => $idEventoUnEntrega);
        $set = array('activo' => $activo);
        if (DB::connection('crm')->update(TBL_EVENTOUNENTREGA, $set, $where)) {
            $datos['error']    = 0;
            $datos['mensaje'] = '';
        }
        return $datos;
    }

    /**
     * Actualiza el estatus de la inscripcion a un evento
     *
     * @param integer $idEventoInscripcion      Identificador de eventoinscripcion
     * @param integer $idTipoEstatusInscripcion Identificador de tipoestatusinscripcion
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaEstatusInscripcion ($idEventoInscripcion, $idTipoEstatusInscripcion)
    {
        settype($idEventoInscripcion, 'integer');
        settype($idTipoEstatusInscripcion, 'integer');

        if (! $idEventoInscripcion or ! $idTipoEstatusInscripcion) {
            return false;
        }
        $set = array('idTipoEstatusInscripcion' => $idTipoEstatusInscripcion);
        $where = array (
            'idEventoInscripcion' => $idEventoInscripcion,
            'fechaEliminacion'    => '0000-00-00 00:00:00'
        );
        $r = DB::connection('crm')->update(TBL_EVENTOINSCRIPCION, $set, $where);
        $this->permisos_model->log('Se cambia estatus de la inscripcion al evento', LOG_EVENTO);
        return $r;
    }

    /**
     * Actualiza a participante de un evento
     *
     * @param integer $idEventoPartcipante Identificador de eventoparticipante
     * @param integer $idEventoInscripcion Identificador de eventoinscripcion
     * @param integer $idPersona           Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaParticipante($idEventoPartcipante, $idEventoInscripcion, $idPersona)
    {
        settype($idEventoPartcipante, 'integer');
        settype($idEventoInscripcion, 'integer');
        settype($idPersona, 'integer');

        if (! $idEventoPartcipante or ! $idEventoInscripcion or ! $idPersona) {
            return false;
        }
        $where = array(
            'idEventoPartcipante' => $idEventoPartcipante,
            'idEventoInscripcion' => $idEventoInscripcion,
            'fechaEliminacion'    => '0000-00-00 00:00:00'
        );
        $set = array('idPersona' => $idPersona);

        $r = DB::connection('crm')->update(TBL_EVENTOPARTICIPANTE, $set, $where);
        $this->permisos_model->log('Se actualiza participante registrado en el evento', LOG_EVENTO);
        return $r;
    }

    /**
     * Actualiza estatus Evento Fecha
     *
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function actualizaTipoEstatusEventoFecha($idEventoFecha, $idEstatusEventoFecha)
    {
        $query = DB::connection('crm')->query("call spRevisionProgramaDeportivo($idEventoFecha);");

        $datos = array ('idTipoEstatusEventoFecha' => $idEstatusEventoFecha);

        DB::connection('crm')->where('idEventoFecha', $idEventoFecha);
        DB::connection('crm')->update(TBL_EVENTOFECHA, $datos);

        $this->permisos_model->log('Se cambia estatus de la clase', LOG_EVENTO);

        $total = DB::connection('crm')->affected_rows();
        if ($total == 0) {
            $res=0;
        } else {
            $res=1;
        }

        return $res;
    }


    /**
     * [arrayClasificacion description]
     * @return [type] [description]
     */
    public function arrayClasificacion()
    {
        $r = array();

        $sql = "SELECT idEventoClasificacion, descripcion
            FROM eventoclasificacion
            WHERE activo=1 ORDER BY orden";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $fila) {
                $r[$fila['idEventoClasificacion']] = $fila['descripcion'];
            }
        }

        return $r;
    }


    /**
     * [arrayClases description]
     *
     * @param  [type] $idPersona [description]
     *
     * @return [type]            [description]
     */
    public function arrayClases($idPersona, $dias)
    {
        settype($idPersona, 'integer');
        $res = array();

        $fecha = '';
        //var_dump($dias);
        foreach ($dias as $key => $value) {
            if ($fecha=='') {
                $fecha = "'".$value."'";
            } else {
                $fecha .= ",'".$value."'";
            }
        }

        $sql = "SELECT
                ef.idEventoFecha,
                UPPER(pr.nombre) AS evento,
                CONCAT(ef.fechaEvento,' ',ef.horaEvento) AS fechaInicio,
                DATE_ADD(CONCAT(ef.fechaEvento,' ',ef.horaEvento), INTERVAL 50 MINUTE) AS fechaFin,
                ef.idTipoEstatusEventoFecha,
                teef.descripcion AS estatusEvento,
                UPPER(GROUP_CONCAT(CONCAT_WS(' ', p.nombre, p.paterno, p.materno))) AS participantes,
                UPPER(GROUP_CONCAT(CONCAT_WS(' ', p2.nombre, p2.paterno, p2.materno))) AS entrenador
            FROM eventofecha ef
            INNER JOIN tipoestatuseventofecha teef ON teef.idTipoEstatusEventoFecha=ef.idTipoEstatusEventoFecha
            INNER JOIN eventoinscripcion ei ON ei.idEventoInscripcion=ef.idEventoInscripcion
            INNER JOIN eventoun eu ON eu.idEventoUn=ei.idEventoUn
            INNER JOIN evento e ON e.idEvento=eu.idEvento
                AND e.idTipoEvento=3
            INNER JOIN producto pr ON pr.idProducto=e.idProducto
            INNER JOIN eventoparticipante ep ON ep.idEventoInscripcion=ef.idEventoInscripcion
                AND ep.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN persona p ON p.idPersona=ep.idPersona
            INNER JOIN persona p2 ON p2.idPersona=ef.idPersona
            WHERE ef.idPersona=$idPersona
                AND ef.fechaEvento IN ($fecha)
                AND ef.fechaEliminacion='0000-00-00 00:00:00'
            GROUP BY ef.idEventoFecha
            ORDER BY ef.fechaEvento, ef.horaEvento";
        $query = DB::connection('crm')->query($sql);
        if ($query->num_rows) {
            $res = $query->result_array();
        }
        return $res;
    }

    /**
     * [arrayFacturas description]
     *
     * @param  [type] $idPersona [description]
     *
     * @return [type]            [description]
     */
    public function arrayFacturas($idPersona)
    {
        settype($idPersona, 'integer');

        $res = array();

        // Obtenemos inscripciones con estatus de activas y facturas asignadas al entrenador
        $sql = "CREATE TEMPORARY TABLE tmp_personal_1
            SELECT ei.idEventoInscripcion, CONCAT(f.prefijoFactura, f.folioFactura) AS factura, UPPER(p.nombre) AS evento,
                ein.idPersona, ei.participantes, ei.totalSesiones, COUNT(1) AS clases,
                CONCAT_WS(' ', per.nombre, per.paterno, per.materno) AS cliente
            FROM evento e
            INNER JOIN producto p ON p.idProducto=e.idProducto
            INNER JOIN eventoun eu on eu.idEvento=e.idEvento
                AND eu.activo=1 AND eu.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventouncapacidad euc on euc.idEventoUn=eu.idEventoUn
                AND euc.idTipoEventoCapacidad=26 AND euc.activo=1 AND euc.autorizado=1
                AND euc.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventoinscripcion ei ON ei.idEventoUn=eu.idEventoUn
                AND ei.idTipoEstatusInscripcion=1
                AND ei.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN persona per ON per.idPersona=ei.idPersona
            INNER JOIN eventoinvolucrado ein ON ein.idEventoInscripcion=ei.idEventoInscripcion
                AND ein.tipo='Entrenador' AND ein.fechaEliminacion='0000-00-00 00:00:00'
                AND ein.idPersona=$idPersona
            INNER JOIN eventomovimiento em ON em.idEventoInscripcion=ei.idEventoInscripcion
            INNER JOIN facturamovimiento fm ON fm.idMovimiento=em.idMovimiento
            INNER JOIN factura f ON f.idFactura=fm.idFactura
                AND f.fechaParticion>=20160504
            INNER JOIN eventofecha ef ON ef.idEventoInscripcion=ei.idEventoInscripcion
                AND ef.fechaEliminacion='0000:00:00 00:00:00'
            WHERE e.idTipoEvento=3 AND e.fechaEliminacion='0000-00-00 00:00:00'
            GROUP BY ei.idEventoInscripcion";
        DB::connection('crm')->query($sql);

        // Contamos los participanes registrados en cada inscripcion
        $sql = "CREATE TEMPORARY TABLE tmp_personal_2
            SELECT ep.idEventoInscripcion, COUNT(1) AS total
            FROM eventoparticipante ep
            INNER JOIN tmp_personal_1 t ON t.idEventoInscripcion=ep.idEventoInscripcion
            WHERE ep.fechaEliminacion='0000-00-00 00:00:00'
            GROUP BY ep.idEventoInscripcion";
        DB::connection('crm')->query($sql);

        // Revisamos si lo integrantes tienen mails registrados
        $sql = "CREATE TEMPORARY TABLE tmp_personal_3
            SELECT ep.idEventoInscripcion, COUNT(1) AS mails
            FROM eventoparticipante ep
            INNER JOIN tmp_personal_1 t ON t.idEventoInscripcion=ep.idEventoInscripcion
            LEFT JOIN persona p ON p.idPersona=ep.idPersona AND p.bloqueoMail=0
            LEFT JOIN mail m ON m.idPersona=p.idPersona AND m.bloqueoMail=0
                AND m.idTipoMail NOT IN (3,37)
                AND m.fechaEliminacion='0000-00-00 00:00:00'
            WHERE ep.fechaEliminacion='0000-00-00 00:00:00'
            GROUP BY ep.idEventoInscripcion";
        DB::connection('crm')->query($sql);

        $sql = "SELECT t1.*, IFNULL(t2.total,0) as registrados, IFNULL(t3.mails, 0) AS mails
            FROM tmp_personal_1 t1
            LEFT JOIN tmp_personal_2 t2 ON t2.idEventoInscripcion=t1.idEventoInscripcion
            LEFT JOIN tmp_personal_3 t3 ON t3.idEventoInscripcion=t1.idEventoInscripcion";
        $query = DB::connection('crm')->query($sql);
        if ($query->num_rows) {
            $res = $query->result_array();
        }
        return $res;
    }

    /**
     * Regresa el numero de niños cargados en la configuracion de precios del evento
     *
     * @param  integer $idEvento Identificador del evento
     * @param  integer $idUn     Identificador de club
     *
     * @author Jorge Cruz
     *
     * @return array           [description]
     */
    public function arrayNinosCurso($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $datos = array();

        $sql = "SELECT DISTINCT pp.unidades
            FROM evento e
            INNER JOIN productoun pu ON e.idProducto=pu.idProducto AND pu.activo=1
                AND pu.fechaEliminacion='0000-00-00 00:00:00' AND pu.idUn=$idUn
            INNER JOIN productoprecio pp on pp.idProductoUn=pu.idProductoUn
                AND pp.fechaEliminacion='0000-00-00 00:00:00' AND pp.activo=1
                AND pp.idEsquemaPago IN (1, 9, 10)
            WHERE e.idEvento=$idEvento
            ORDER BY unidades";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->unidades] = $fila->unidades;
            }
        }
        return $datos;
    }

    /**
     * Autoriza la configuracion de capacidad
     *
     * @param integer $idEventoUn Identificador de eventoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function autoriazaConfigCapacidad($idEventoUn)
    {
        settype($idEventoUn, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => '',
            'res'     => false
        );
        if ( ! $idEventoUn) {
            return $datos;
        }
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al autorizar configuracion de capacidad';

        $set = array('autorizado' => 1);
        $where = array(
            'idEventoUn'       => $idEventoUn,
            'autorizado'       => 0,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        if ($datos['res'] = DB::connection('crm')->update(TBL_EVENTOUNCAPACIDAD, $set, $where)) {
            $datos['error']   = 0;
            $datos['mensaje'] = 'Se autorizo exitosamente la configuracion';

            $this->permisos_model->log('Autoriza configuracion de idEventoUn='.$idEventoUn, LOG_EVENTO);
        }
        return $datos;
    }

   /**
     * Autoriza la configuracion de capacidad
     *
     * @param integer $idEventoUn Identificador de eventoun
     *
     * @author Ruben Alcocer
     *
     * @return array
     */
    public function QuitarautoriazaConfigCapacidad($idEventoUn)
    {
        settype($idEventoUn, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => '',
            'res'     => false
        );
        if ( ! $idEventoUn) {
            return $datos;
        }
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al Quitar autorizar la configuracion de capacidad';

        $set = array('autorizado' => 0);
        $where = array(
            'idEventoUn'       => $idEventoUn,
            #'autorizado'       => 1,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        if ($datos['res'] = DB::connection('crm')->update(TBL_EVENTOUNCAPACIDAD, $set, $where)) {
            $datos['error']   = 0;
            $datos['mensaje'] = 'Se Quito exitosamente la Autorizacion ';

            $this->permisos_model->log('Quita la Autoriza configuracion de idEventoUn='.$idEventoUn, LOG_EVENTO);
        }
        return $datos;
    }


    /**
     * Regresa la capacidad disponible de lugares del evento en el club solicitidado
     *
     * @param integer $idEvento Identificador del evento
     * @param integer $idUn     Identificador del club
     *
     * @author Jorge Cruz
     *
     * @return integer Numero de lugares disponibles
     *                 -1 Si no encuentra la configuracion dentro del sistema
     *                 -2 Si la capacidad se encuentra
     */
    public function capacidadDisponible($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        if ($idEvento==0 || $idUn==0) {
            return -1;
        }

        DB::connection('crm')->select('ec.capacidad');
        DB::connection('crm')->from(TBL_EVENTOUNCAPACIDAD.' ec');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'eu.idEventoUn=ec.idEventoUn AND eu.fechaEliminacion=\'0000-00-00 00:00:00\'', 'INNER');
        DB::connection('crm')->where('eu.idEvento', $idEvento);
        DB::connection('crm')->where('eu.idUn', $idUn);
        DB::connection('crm')->where('ec.idTipoEventoCapacidad', EVENTO_CAPACIDAD_MAXIMA);
        DB::connection('crm')->where('eu.activo', 1);
        DB::connection('crm')->where('ec.activo', 1);
        DB::connection('crm')->where('ec.fechaEliminacion', '0000-00-00 00:00:00');
        $query = DB::connection('crm')->get();
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $capacidad = $fila['capacidad'];
            if ($capacidad==0) {
                return 999999;
            }
        } else {
            return (-1);
        }

        DB::connection('crm')->from(TBL_EVENTOINSCRIPCION.' ei');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'eu.idEventoUn=ei.idEventoUn AND eu.inicioEvento = ei.inicioEvento AND eu.finEvento = ei.finEvento AND eu.fechaEliminacion=\'0000-00-00 00:00:00\'', 'INNER');
        DB::connection('crm')->where('eu.idEvento', $idEvento);
        DB::connection('crm')->where('eu.idUn', $idUn);
        DB::connection('crm')->where('ei.fechaEliminacion', '0000-00-00 00:00:00');
        DB::connection('crm')->where('ei.idtipoEstatusInscripcion <>', 3);
        $total = DB::connection('crm')->count_all_results();

        if ($total > $capacidad) {
            return (-2);
        }

        return $capacidad - $total;
    }


    /**
     * Regresa la capacidad disponible de lugares del evento en el club solicitidado
     *
     * @param integer $idEvento Identificador del evento
     * @param integer $idUn     Identificador del club
     */
    public function capacidadEvento($idEvento, $idUn, $tipo)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        if ($idEvento==0 || $idUn==0) {
            return -1;
        }
        
        $query = DB::connection('crm')->table(TBL_EVENTOUNCAPACIDAD.' ec')
        ->select('ec.capacidad')
        ->join(TBL_EVENTOUN.' eu', 'eu.idEventoUn=ec.idEventoUn AND eu.fechaEliminacion=\'0000-00-00 00:00:00\'', 'INNER')
        ->where('eu.idEvento', $idEvento)
        ->where('eu.idUn', $idUn)
        ->where('ec.idTipoEventoCapacidad', $tipo)
        ->where('eu.activo', 1)
        ->where('ec.autorizado', 1)
        ->where('ec.activo', 1)
        ->where('ec.fechaEliminacion', '0000-00-00 00:00:00')
        ->orderBy('ec.idEventoUnCapacidad', 'desc')
        
        if ($query->count() > 0) {
            $query = array_map(function($x){return (array)$x;},$query);
            $fila = $query[0];
            $capacidad = $fila['capacidad'];
            return $capacidad;
        } else {
            return false;
        }
    }

    /**
     * Obtiene el valor del campo solicitado para el catalogo indicado por medio de id y un Join
     *
     * @param integer $id    Id del producto
     * @param string  $campo Nombre del campo solicitado
     *
     * @return string
     */
    public function catalogoCampo($idEvento, $campo)
    {
        settype($idEvento, 'integer');

        if (! $idEvento) {
            return null;
        }
        $idUn = $this->session->userdata('idUn');
        $idEventoUn = $this->obtenIdEventoUn($idEvento, $idUn);
        if (! $idEvento) {
            return null;
        }
        if ($campo == "") {
            return null;
        }
        DB::connection('crm')->select($campo);
        $query = DB::connection('crm')->where('idEventoUn', $idEventoUn)->get(TBL_EVENTOUN);
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila[$campo];
        }
        return null;
    }

    /**
     * Obtiene el valor de campo solicitado dentro del catalogo referido
     *
     * @param integer $id    ID del catalogo a procesar
     * @param integer $campo Nombre del campo a devolver
     *
     * @return string
     */
    public function clasesPorEvento($idEvento, $idUn, $idTipoEventoCapacidad)
    {
        $where = array(
            'eu.idEvento'               => $idEvento,
            'eu.idUn'                   => 1,
            'euc.idTipoEventoCapacidad' => $idTipoEventoCapacidad,
            'euc.activo'                => 1,
            'eu.activo'                 => 1,
            'eu.fechaEliminacion'       => '0000-00-00 00:00:00',
            'euc.fechaEliminacion'      => '0000-00-00 00:00:00'
        );
        DB::connection('crm')->join(TBL_EVENTOUNCAPACIDAD." euc", "eu.idEventoUn = euc.idEventoUn", "inner");
        $query = DB::connection('crm')->select(
            "euc.capacidad"
        )->get_where(TBL_EVENTOUN." eu", $where);

        if ($query->num_rows) {
            $fila = $query->row_array();
            return $fila['capacidad'];
        } else {
            $where = array(
                'eu.idEvento'               => $idEvento,
                'eu.idUn'                   => $idUn,
                'euc.idTipoEventoCapacidad' => $idTipoEventoCapacidad,
                'euc.activo'                => 1,
                'eu.activo'                 => 1,
                'eu.fechaEliminacion'       => '0000-00-00 00:00:00',
                'euc.fechaEliminacion'      => '0000-00-00 00:00:00'
            );
            DB::connection('crm')->join(TBL_EVENTOUNCAPACIDAD." euc", "eu.idEventoUn = euc.idEventoUn", "inner");
            $query = DB::connection('crm')->select(
                "euc.capacidad"
            )->get_where(TBL_EVENTOUN." eu", $where);

            if ($query->num_rows) {
                $fila = $query->row_array();
                return $fila['capacidad'];
            }
        }
        return false;
    }

    /**
     * [ctaContable description]
     *
     * @param  [type] $idEvento [description]
     * @param  [type] $idun     [description]
     *
     * @return [type]           [description]
     */
    public static function ctaContable($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $ctaContable = '';

        $sql = "
SELECT cc.numCuenta
FROM evento e
INNER JOIN producto p ON p.idProducto=e.idProducto
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
    and pu.idUn={$idUn} and pu.activo=1
    and pu.fechaEliminacion='0000-00-00 00:00:00'
INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
    AND pp.activo=1 AND pp.fechaEliminacion='0000-00-00 00:00:00'
    AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
INNER JOIN cuentacontable cc ON cc.idCuentaContable=pp.idCuentaContable
WHERE e.idEvento={$idEvento}
ORDER BY pp.idProductoPrecio DESC
LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $query = array_map(function($x){return (array)$x;},$query);
            $fila = $query[0];
            $ctaContable = $fila['numCuenta'];
        }
        return $ctaContable;
    }

    /**
     * [ctaContable description]
     *
     * @param  [type] $idEvento [description]
     * @param  [type] $idun     [description]
     *
     * @return [type]           [description]
     */
    public static function ctaProducto($idEvento, $idun)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $ctaProducto = '';

        $sql = "
SELECT cc.cuentaProducto
FROM evento e
INNER JOIN producto p ON p.idProducto=e.idProducto
INNER JOIN productoun pu ON pu.idProducto=p.idProducto
    and pu.idUn={$idUn} and pu.activo=1
    and pu.fechaEliminacion='0000-00-00 00:00:00'
INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
    AND pp.activo=1 AND pp.fechaEliminacion='0000-00-00 00:00:00'
    AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
INNER JOIN cuentaproducto cc ON cc.idCuentaProducto=pp.idCuentaProducto
WHERE e.idEvento={$idEvento}
ORDER BY pp.idProductoPrecio DESC
LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $query = array_map(function($x){return (array)$x;},$query);
            $fila = $query[0];
            $ctaProducto = $fila['cuentaProducto'];
        }
        return $ctaProducto;
    }

    /**
     * Obtiene el valor de campo solicitado dentro del catalogo referido
     *
     * @param integer $id    ID del catalogo a procesar
     * @param integer $campo Nombre del campo a devolver
     *
     * @return array
     */
    public function cupoDisponible ($idEventoUn, $cupoActual, $idEvento, $idUn, $idTipoEventoCapacidad, $tipoEventoCapacidad = '', $admin = false)
    {
        settype($idEventoUn, 'integer');
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        settype($idTipoEventoCapacidad, 'integer');
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        if (! $idEventoUn or !$idEvento or ! $idUn or ! $idTipoEventoCapacidad) {
            $datos['error']   = 1;
            $datos['mensaje'] = "Faltan datos";
            return $datos;
        }
        $where = array(
            'eu.idEventoUn'             => $idEventoUn,
            'eu.idUn'                   => $idUn,
            'euc.idTipoEventoCapacidad' => $idTipoEventoCapacidad,
            'euc.activo'                => 1,
            'euc.autorizado'            => 1,
            'eu.activo'                 => 1,
            'e.fechaEliminacion'        => '0000-00-00 00:00:00',
            'eu.fechaEliminacion'       => '0000-00-00 00:00:00',
            'euc.fechaEliminacion'      => '0000-00-00 00:00:00'
        );
        DB::connection('crm')->join(TBL_EVENTOUN." eu", "e.idEvento = eu.idEvento", "inner");
        DB::connection('crm')->join(TBL_EVENTOUNCAPACIDAD." euc", "euc.idEventoUn = eu.idEventoUn", "inner");
        $query = DB::connection('crm')->select(
            "euc.capacidad"
        )->get_where(TBL_EVENTO." e", $where);

        if ($query->num_rows>0 ) {
            $fila = $query->row_array();
            $limite= $fila['capacidad'];
            if ($limite - $cupoActual > 0) {
                $datos['error']   = 0;
                $datos['mensaje'] = 'Cupo Disponible';
                return $datos;
            } else {
                $datos['error']   = 2;
                $datos['mensaje'] = "El limite para ".$tipoEventoCapacidad." de este evento ya ha sido alcanzado o no esta configurado";
                return $datos;
            }
        } else {
            $CI =& get_instance();
            $CI->load->model('un_model');

            $idEmpresa = $CI->un_model->obtenerEmpresa($idUn);
            $admin = $CI->un_model->obtenUnAdiministracion($idEmpresa);
            $where = array(
                'eu.idEventoUn'             => $idEventoUn,
                'eu.idEvento'               => $idEvento,
                'eu.idUn'                   => $admin,
                'euc.idTipoEventoCapacidad' => $idTipoEventoCapacidad,
                'euc.activo'                => 1,
                'eu.activo'                 => 1,
                'e.fechaEliminacion'        => '0000-00-00 00:00:00',
                'eu.fechaEliminacion'       => '0000-00-00 00:00:00',
                'euc.fechaEliminacion'      => '0000-00-00 00:00:00'
            );
            DB::connection('crm')->join(TBL_EVENTOUN." eu", "e.idEvento = eu.idEvento", "inner");
            DB::connection('crm')->join(TBL_EVENTOUNCAPACIDAD." euc", "euc.idEventoUn = eu.idEventoUn", "inner");
            $query = DB::connection('crm')->select(
                "euc.capacidad"
            )->get_where(TBL_EVENTO." e", $where);

            if ($query->num_rows) {
                $fila = $query->row_array();
                $limite= $fila['capacidad'];
                if ($limite - $cupoActual > 0) {
                    $datos['error']   = 0;
                    $datos['mensaje'] = 'Cupo Disponible';
                    return $datos;
                } else {
                    $datos['error']   = 3;
                    $datos['mensaje'] = "El limite para ".$tipoEventoCapacidad." de este evento ya ha sido alcanzado o no esta configurado";
                    return $datos;
                }
            }
        }
        $datos['error']   = 4;
        $datos['mensaje'] = "Error: No esta configurada la capacidad de participantes de este evento.";

        return $datos;
    }

    /**
     * Regresa datos del evento en el club solicitidado
     *
     * @param integer $idEvento Identificador del evento
     * @param integer $idUn     Identificador del club
     */
    public function datosEventoInscripcion($idEventoInscripcion)
    {
        settype($idEventoInscripcion, 'integer');

        if ($idEventoInscripcion==0) {
            return -1;
        }


        DB::connection('crm')->select('idEventoInscripcion, totalSesiones,idUn');
        DB::connection('crm')->from(TBL_EVENTOINSCRIPCION);
        DB::connection('crm')->where('idEventoInscripcion', $idEventoInscripcion);
        DB::connection('crm')->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = DB::connection('crm')->get();

        if ($query->num_rows>0) {
            return $query->result_array();
        } else {
            return false;
        }
    }

    /**
     * Obtiene los generales del evento solicitado dentro del club indicado
     *
     * @param integer $idEvento Identificador del evento
     * @param integer $idUn     Identificador del club
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public static function c($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        $datos = array();

        if ($idEvento == 0 || $idUn == 0) {
            return $datos;
        }

        /*$sql = "SELECT c.idCategoria
            FROM crm.evento e
            JOIN crm.producto p ON p.idProducto = e.idProducto
                AND p.eliminado = '0000-00-00 00:00:00'
            JOIN crm.categoria c ON c.idCategoria = p.idCategoria
            WHERE e.idEvento = ".$idEvento;
        $categoria = DB::connection('crm')->query($sql)->row_array();

        $cat = (intval($categoria["idCategoria"]) != 109) ? " and p.nombre LIKE '%2018%'" : "";
        */
        
        $cat = '';
        
        $query = DB::connection('crm')->table(TBL_EVENTO.' e')
        ->select('p.idProducto, p.nombre, eu.idEventoUn, e.idTipoEvento, eu.inicioRegistro, eu.finRegistro, eu.inicioEvento, eu.finEvento, eu.reservarInstalacion, eu.anticipo, eu.edadMinima, eu.edadMaxima')
        ->join(TBL_PRODUCTO.' p', 'p.idProducto=e.idProducto'.$cat.' and p.fechaEliminacion=\'0000-00-00 00:00:00\'', 'INNER')
        ->join(TBL_TIPOEVENTO.' te', 'te.idTipoEvento=e.idTipoEvento', 'INNER')
        ->join(TBL_EVENTOUN.' eu', 'eu.idEvento=e.idEvento and eu.fechaEliminacion=\'0000-00-00 00:00:00\'', 'INNER')
        ->where('e.idEvento', $idEvento)
        ->where('e.fechaEliminacion', '0000-00-00 00:00:00')
        ->where('eu.idUn', $idUn);
        
        if ($query->count() > 0) {
            $query = $query->get()->toArray();
            $fila = $query[0];
            $datos['idProducto'] = $fila['idProducto'];
            $datos['nombre'] = $fila['nombre'];
            $datos['idEventoUn'] = $fila['idEventoUn'];
            $datos['tipoEvento'] = $fila['idTipoEvento'];
            $datos['inicioRegistro'] = $fila['inicioRegistro'];
            $datos['finRegistro'] = $fila['finRegistro'];
            $datos['inicioEvento'] = $fila['inicioEvento'];
            $datos['finEvento'] = $fila['finEvento'];
            $datos['reservar'] = $fila['reservarInstalacion'];
            $datos['anticipo'] = $fila['anticipo'];
            $datos['minina'] = $fila['edadMinima'];
            $datos['maxima'] = $fila['edadMaxima'];
        }
        return $datos;
    }

    /**
     * Obtiene los datos de un inscrito al evento
     *
     * @param integer $idEventoInscripcion Identificador de eventoinscripcion
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function datosInscrito ($idEventoInscripcion)
    {
        settype($idEventoInscripcion, 'integer');
        $datos = array();

        if (!$idEventoInscripcion) {
            return $datos;
        }
        DB::connection('crm')->select(
            "ev.idTipoEvento, te.descripcion AS tipoEvento, ei.idPersona, ei.idUn,
            eu.inicioEvento, eu.finEvento, p.nombre, p.paterno, p.materno, eu.idEventoUn,
            un.nombre as club, ei.idTipoEstatusInscripcion, pr.nombre as producto,
            eu.reservarInstalacion, eu.edadMinima, eu.edadMaxima, ei.totalSesiones,
            ei.cantidad AS clasesCompradas, ei.totalSesiones, ei.fechaRegistro,
            eu.idEvento, ei.participantes,
            (
                SELECT euc.capacidad
                FROM ".TBL_EVENTOUNCAPACIDAD." euc
                WHERE euc.idTipoEventoCapacidad = ".EVENTO_CAPACIDAD_PARTICIPANTES."
                AND euc.idEventoUn = eu.idEventoUn
                AND euc.activo = 1
                AND euc.autorizado = 1
                AND euc.fechaEliminacion = '0000-00-00 00:00:00'
                ORDER BY euc.idEventoUnCapacidad DESC
                LIMIT 1
            )AS numIntegrantes,
            (
                SELECT euc.capacidad
                FROM ".TBL_EVENTOUNCAPACIDAD." euc
                WHERE euc.idTipoEventoCapacidad = ".EVENTO_CAPACIDAD_NUMEROCLASES."
                AND euc.idEventoUn = eu.idEventoUn
                AND euc.activo = 1
                AND euc.autorizado = 1
                AND euc.fechaEliminacion = '0000-00-00 00:00:00'
                ORDER BY euc.idEventoUnCapacidad DESC
                LIMIT 1
            )AS numClases,
            (
                SELECT euc.capacidad
                FROM ".TBL_EVENTOUNCAPACIDAD." euc
                WHERE euc.idTipoEventoCapacidad = ".EVENTO_CAPACIDAD_MINIMA_SALON."
                AND euc.idEventoUn = eu.idEventoUn
                AND euc.activo = 1
                AND euc.autorizado = 1
                AND euc.fechaEliminacion = '0000-00-00 00:00:00'
                ORDER BY euc.idEventoUnCapacidad DESC
                LIMIT 1
            )AS capacidadMinSalon,
            (
                SELECT euc.capacidad
                FROM ".TBL_EVENTOUNCAPACIDAD." euc
                WHERE euc.idTipoEventoCapacidad = ".EVENTO_CAPACIDAD_MAXIMA_SALON."
                AND euc.idEventoUn = eu.idEventoUn
                AND euc.activo = 1
                AND euc.autorizado = 1
                AND euc.fechaEliminacion = '0000-00-00 00:00:00'
                ORDER BY euc.idEventoUnCapacidad DESC
                LIMIT 1
            )AS capacidadMaxSalon", false
        );
        DB::connection('crm')->join(TBL_PERSONA.' p', 'ei.idPersona=p.idPersona');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'ei.idEventoUn=eu.idEventoUn');
        DB::connection('crm')->join(TBL_EVENTO.' ev', 'ev.idEvento=eu.idEvento');
        DB::connection('crm')->join(TBL_UN.' un', 'un.idUn=eu.idUn');
        DB::connection('crm')->join(TBL_PRODUCTO.' pr', 'pr.idProducto=ev.idProducto');
        DB::connection('crm')->join(TBL_TIPOEVENTO.' te', 'ev.idTipoEvento = te.idTipoEvento');
        DB::connection('crm')->where('ei.idEventoInscripcion', $idEventoInscripcion);
        DB::connection('crm')->where('ei.fechaEliminacion', '0000-00-00 00:00:00');

        $query = DB::connection('crm')->get(TBL_EVENTOINSCRIPCION.' ei');

        if ($query->num_rows) {
            $datos = $query->row_array();
        }

        $idCategoria = $this->obtenIdCategoria($datos['idEvento']);
        if ($idCategoria==CATEGORIA_SUMMERCAMP) {
            $datos['numIntegrantes'] = $datos['participantes'];
        }

        return $datos;
    }

    /**
     * Cambia el estatus de activo del registro indicado
     *
     * @param integer $id Id del registro a procesar
     *
     * @return boolean
     */
    public function desactivaOpcion($id)
    {
        if ($id == 0 || $id == null) {
            return false;
        }

        $tabla = TBL_EVENTOINSCRIPCION;

        $datos = array (
            'fechaEliminacion' => date("Y-m-d H:i:s")
        );

        DB::connection('crm')->where('idEventoInscripcion', $id);
        DB::connection('crm')->update($tabla, $datos);

        $this->permisos_model->log('Se elimina la opcion idEventoInscripcion ('.$id.')', LOG_INSCRIPCION);
        return true;
    }


    public static function descuentoAnualidad($idProducto, $idUn)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        $res = 0;
        
        if ($idProducto<=0 || $idUn<=1) {
            return $res;
        }
        
        $sql = "SELECT euc.capacidad
            FROM producto p
            INNER JOIN evento e ON e.idProducto=p.idProducto
                AND e.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
                AND eu.idUn=$idUn AND eu.activo=1 AND eu.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
                AND euc.idTipoEventoCapacidad=29 AND euc.activo=1
                AND euc.autorizado=1 AND euc.capacidad>0
            WHERE p.idProducto=$idProducto AND p.activo=1 AND p.fechaEliminacion='0000-00-00 00:00:00'
            ORDER BY euc.idEventoUnCapacidad DESC
            LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        if (countr($query) > 0) {
            $res = $query[0]->capacidad;
        }
        return $res;
    }


    /**
     * Regresa el listado de eventos disponibles
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function disponibles($un, $tipo = 0, $titulo = "")
    {
        settype($un, 'integer');
        settype($tipo, 'integer');

        $datos = array();

        if ($un==0) {
            return $datos;
        }

        if ($titulo != "") {
            $datos[0] = $titulo;
        }
        $w_clasificacion =  '';
        if ($this->session->userdata('idEmpresaGrupo')==1) {
            $w_clasificacion = ' AND e.idEventoClasificacion>0 ';
        }

        $sql = 'SELECT e.idEvento, UPPER(p.nombre) AS nombre FROM '.TBL_EVENTO.' e '.
            'INNER JOIN '.TBL_PRODUCTO.' p ON p.idProducto = e.idProducto AND p.activo=1 AND p.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'INNER JOIN '.TBL_PRODUCTOUN.' pu ON pu.idProducto = p.idProducto AND pu.activo=1 AND pu.fechaEliminacion=\'0000-00-00 00:00:00\' AND idUn='.$un.' '.
            'INNER JOIN '.TBL_EVENTOUN.' eu ON eu.idEvento = e.idEvento AND eu.activo=1 AND eu.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'LEFT JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn '.
                'AND euc.idTipoEventoCapacidad=26 '.
                'AND euc.activo=1 AND euc.autorizado=1 AND euc.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'WHERE e.bloqueoVenta=0 AND e.fechaEliminacion=\'0000-00-00 00:00:00\' AND \''.date('Y-m-d').'\' BETWEEN '.
            'eu.inicioRegistro AND eu.finRegistro AND euc.idEventoUnCapacidad IS NULL AND eu.idUn='.$un.$w_clasificacion;
        if ($tipo>0) {
            $sql .= ' AND e.idTipoEvento='.$tipo;
        }
        $sql .= ' GROUP BY e.idEvento';
        $sql .= ' ORDER BY p.nombre';

        $query = DB::connection('crm')->query($sql);
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idEvento] = $fila->nombre;
            }
        }
        return $datos;
    }

    /**
     * Regresa el listado de eventos disponibles para EP
     *
     * @author Ruben Alcocer
     *
     * @return array
     */
    public function disponiblesEP($un, $tipo = 0, $titulo = "")
    {
        settype($un, 'integer');
        settype($tipo, 'integer');

        $datos = array();

        if ($un==0) {
            return $datos;
        }

        if ($titulo != "") {
            $datos[0] = $titulo;
        }
        $w_clasificacion =  '';
        if ($this->session->userdata('idEmpresaGrupo')==1) {
            $w_clasificacion = ' AND e.idEventoClasificacion>0 ';
        }

        $sql = 'SELECT e.idEvento, UPPER(p.nombre) AS nombre FROM '.TBL_EVENTO.' e '.
            'INNER JOIN '.TBL_PRODUCTO.' p ON p.idProducto = e.idProducto AND p.activo=1 AND p.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'INNER JOIN '.TBL_PRODUCTOUN.' pu ON pu.idProducto = p.idProducto AND pu.activo=1 '.
                ' AND pu.fechaEliminacion=\'0000-00-00 00:00:00\' AND idUn='.$un.' '.
            'INNER JOIN '.TBL_EVENTOUN.' eu ON eu.idEvento = e.idEvento AND eu.activo=1 AND eu.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn '.
                'AND euc.idTipoEventoCapacidad=26 '.
                'AND euc.activo=1 AND euc.autorizado=1 AND euc.fechaEliminacion=\'0000-00-00 00:00:00\' '.
            'WHERE e.bloqueoVenta=0 AND e.fechaEliminacion=\'0000-00-00 00:00:00\' AND \''.date('Y-m-d').'\' BETWEEN '.
            'eu.inicioRegistro AND eu.finRegistro AND eu.idUn='.$un.$w_clasificacion;
        $sql .= ' GROUP BY e.idEvento';
        $sql .= ' ORDER BY p.nombre';

        $query = DB::connection('crm')->query($sql);
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idEvento] = $fila->nombre;
            }
        }
        return $datos;
    }

    /**
     * [eliminaClubEntregaTalla description]
     * @param  [type] $idUnEntrega         [description]
     * @param  [type] $idTalla             [description]
     * @param  [type] $idEventoInscripcion [description]
     * @param  [type] $idPersona           [description]
     * @param  [type] $idEventoCategoria   [description]
     *
     * @author Antonio Sixtos <antonio.sixtos@sportsworld.com.mx>
     *
     * @return [type]                      [description]
     */
    public function eliminaClubEntregaTalla($idUnEntrega, $idTalla, $idEventoInscripcion, $idPersona, $idEventoCategoria, $idDistanciaCarrera, $idOleadaCarrera)
    {
        $resultado = 0;
        #Actualizamos talla de enventoparticipante
        $sql = "UPDATE crm.eventoparticipante SET idTalla=0, idEventoCategoria='', idDistanciaCarrera=0, idOleadaCarrera=0, nombreEquipo=null
                WHERE idEventoInscripcion IN (".$idEventoInscripcion.") AND idPersona IN (".$idPersona.")";
        $query = DB::connection('crm')->query($sql);
        if (DB::connection('crm')->affected_rows()>0) {
            $sql2 = "UPDATE crm.eventounentregapaquete SET fechaEliminacion=NOW() WHERE idPersona IN (".$idPersona.") AND idUn IN (".$idUnEntrega.") AND idEventoInscripcion IN (".$idEventoInscripcion.")";
            $query2 = DB::connection('crm')->query($sql2);
            if (DB::connection('crm')->affected_rows()>0) {
                $resultado = 1;
            }
        }
        return $resultado;
    }

    /**
     * Elimina participante de un evento
     *
     * @param integer $idEventoInscripcion  Identificador de eventoinscripcion
     * @param integer $idEventoParticipante Identificador de eventoparticipante
     * @param integer $idTipoEvento         Identificador de tipoevento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function eliminaParticipante ($idEventoInscripcion, $idEventoParticipante, $idTipoEvento)
    {
        settype($idEventoInscripcion, 'integer');
        settype($idEventoParticipante, 'integer');
        settype($idTipoEvento, 'integer');

        $datos            = array();
        $datos['error']   = 1;
        $datos['mensaje'] = 'Error al ingresar informacion';

        if (! $idEventoInscripcion or ! $idEventoParticipante or ! $idTipoEvento) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = 'Se elimino exitosamente al participante';

        $where = array(
            'idEventoInscripcion'      => $idEventoInscripcion,
            'fechaEliminacion'         => '0000-00-00 00:00:00',
            'idTipoEstatusEventoFecha' => ESTATUS_CLASE_ASIGNADO
        );
        $query = DB::connection('crm')->select(
            "idEventoFecha"
        )->get_where(TBL_EVENTOFECHA, $where);

        if ($query->num_rows) {
            $datos['error']   = 2;
            $datos['mensaje'] = 'No se puede eliminar al participante si hay clases asignadas';
        } else {
            if ($idTipoEvento == EVENTO_CLASESPERSONALIZADAS) {
                $where = array(
                    'idEventoInscripcion'      => $idEventoInscripcion,
                    'fechaEliminacion'         => '0000-00-00 00:00:00',
                    'idTipoEstatusEventoFecha' => ESTATUS_CLASE_IMPARTIDO
                );
                $query = DB::connection('crm')->select(
                    "idEventoFecha"
                )->get_where(TBL_EVENTOFECHA, $where);

                if ($query->num_rows) {
                    $datos['error']   = 3;
                    $datos['mensaje'] = 'No se puede eliminar al participante si tiene clases impartidas y el tipo de evento es clase personalizada';
                }
            }
        }
        if ($datos['error'] == 0) {
            $set   = array('fechaEliminacion' => date("Y-m-d H:i:s"));
            $where = array('idEventoPartcipante' => $idEventoParticipante);
            $res   = DB::connection('crm')->update(TBL_EVENTOPARTICIPANTE, $set, $where);

            if (! $res) {
                $datos['error']   = 4;
                $datos['mensaje'] = 'Error al eliminar participante';
            } else {
                $this->permisos_model->log('Se elimina la opcion idEventoPartcipante ('.$idEventoParticipante.')', LOG_INSCRIPCION);
            }
        }
        return $datos;
    }

    /**
     * Elimina configuracion puesto comision
     *
     * @param integer $idEventoPuestoComision Identificador de eventopuestocomision
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function eliminaPuestoComision($idEventoPuestoComision)
    {
        settype($idEventoPuestoComision, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos',
            'res'     => false
        );
        if ( ! $idEventoPuestoComision) {
            return $datos;
        }
        $set   = array('fechaEliminacion' => date('Y-m-d'));
        $where = array('idEventoPuestoComision' => $idEventoPuestoComision);

        if ($datos['res'] = DB::connection('crm')->update(TBL_EVENTOPUESTOCOMISION, $set, $where)) {
            $datos['error']   = 0;
            $datos['mensaje'] = 'Se elimino exitosamente el registro';

            $this->permisos_model->log('Elimina configuracion de puesto comision', LOG_EVENTO);
        } else {
            $datos['error']   = 2;
            $datos['mensaje'] = 'Error al eliminar registro';
        }

        return $datos;
    }

    /**
     * Elimina configuracion eventounpuestoexcepcion
     *
     * @param integer $idEventoUnPuestoExcepcion Identificador de eventounpuestoexcepcion
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function eliminaPuestoComisionExcepcion($idEventoUnPuestoExcepcion)
    {
        settype($idEventoUnPuestoExcepcion, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos',
            'res'     => false
        );
        if ( ! $idEventoUnPuestoExcepcion) {
            return $datos;
        }
        $set   = array('fechaEliminacion' => date('Y-m-d'));
        $where = array('idEventoUnPuestoExcepcion' => $idEventoUnPuestoExcepcion);

        if ($datos['res'] = DB::connection('crm')->update(TBL_EVENTOUNPUESTOEXCEPCION, $set, $where)) {
            $datos['error']   = 0;
            $datos['mensaje'] = 'Se elimino exitosamente el registro';

            $this->permisos_model->log('Elimina configuracion de puesto comision excepcion', LOG_EVENTO);
        } else {
            $datos['error']   = 2;
            $datos['mensaje'] = 'Error al eliminar registro';
        }

        return $datos;
    }

    /**
     * Elimina el evento indicado de manera logica en el sistema
     *
     * @param integer $id Id de la categoria a eliminar
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function eliminar($id)
    {
        settype($id, 'integer');

        if ($id == 0) {
            return false;
        }
        $datos = array ('fechaEliminacion' => date("Y-m-d H:i:s"));

        DB::connection('crm')->where('idEvento', $id);
        DB::connection('crm')->where('fechaEliminacion', '0000-00-00 00:00:00');
        DB::connection('crm')->update(TBL_EVENTO, $datos);

        $total = DB::connection('crm')->affected_rows();
        if ($total == 0) {
            return false;
        }
        $this->permisos_model->log('Se elimino el evento con ID '.$id, LOG_EVENTO);

        return true;
    }

    /**
     * [eliminarClase description]
     *
     * @param  [type] $idEventoFecha [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]                [description]
     */
    public static function eliminarClase($idEventoFecha)
    {
        settype($idEventoFecha, 'integer');
        $res = false;
        if ($idEventoFecha > 0) {
            $query = DB::connection('crm')->table(TBL_EVENTOFECHA)
            ->select('idEventoInscripcion')
            ->where('idEventoFecha', $idEventoFecha);
            
            if ($query->count() == 0) {
                return $res;
            }
            
            $fila = $query->get()->toArray();
            $fila = (array_map(function($x){return (array)$x;},$fila))[0];
            
            $idEventoInscripcion = $fila['idEventoInscripcion'];
            if ($idEventoInscripcion > 0) {
                $datos = array ('fechaEliminacion' => date("Y-m-d H:i:s"));
                
                $affected_rows = DB::connection('crm')->table(TBL_EVENTOFECHA)
                ->where('idEventoFecha', $idEventoFecha)
                ->where('fechaEliminacion', '0000-00-00 00:00:00')
                ->where('idTipoEstatusEventoFecha', '<>', 2)
                ->where('idEventoFecha', $idEventoFecha)
                ->where('fechaEvento', '>', date('Y-m-d'))
                ->update($datos);
                $total = $affected_rows;
                
                if ($total > 0) {
                    Permiso::log('Se elimino clase para la inscripcion ('.$idEventoInscripcion.')', LOG_EVENTO);
                    $res = true;
                }
            }
        }
        return $res;
    }

    /**
     * Devuelve si un producto es evento
     *
     * @param integer $idProd  Producto a ser utlizado
     *
     * @return boolean
     */
    public function esEvento($idProducto)
    {
        settype($idProducto, 'integer');
        if ($idProducto == 0) {
            return false;
        }

        DB::connection('crm')->select('idEvento');
        DB::connection('crm')->from(TBL_EVENTO);
        DB::connection('crm')->where('idProducto', $idProducto);
        $query = DB::connection('crm')->get();
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila['idEvento'];
        }
    }

    /**
     * Cambia el estatus de activo del registro indicado
     *
     * @param integer $id Id del registro a procesar
     *
     * @return boolean
     */
    public function eventoTotales($idEventoInscripcion)
    {
        DB::connection('crm')->select('sum(m.importe) as total');
        DB::connection('crm')->join('movimiento m', 'em.idMovimiento=m.idMovimiento', 'inner');
        DB::connection('crm')->where("idEventoInscripcion", $idEventoInscripcion);
        DB::connection('crm')->where_in("m.idTipoEstatusMovimiento", array(65, 66));
        $query = DB::connection('crm')->get('eventoMovimiento em');

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila['total'];
        }
    }

    /**
     * Valida si se debe generar comision por venta en base a la configuracion
     *
     * @param  integer $idEvento [description]
     * @param  integer $idUn     [description]
     *
     * @return boolean
     */
    public static function generarComisionVenta($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        $res = false;
        
        if ($idEvento==0 || $idUn==0) {
            return $res;
        }
        
        $qry = "SELECT eu.idEventoUn
            FROM eventoun eu
            INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
                AND euc.idTipoEventoCapacidad IN (8, 10, 13) AND euc.eliminado=0
                AND euc.activo=1 AND euc.autorizado=1
            WHERE eu.eliminado=0 AND eu.activo=1
                AND eu.idEvento=$idEvento AND eu.idUn=$idUn";
        $query = DB::connection('crm')->select($qry);

        if (count($query) > 0) {
            $res = true;
        }
        
        return $res;
    }


    /**
     * Guarda capacidad de un evento
     *
     * @param integer $idEventoUnCapacidad Identificador de eventouncapacidad
     * @param integer $valor               Valor a actualizar
     * @param integer $campo               Campo a actualizar
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function guardaCapacidad($idEventoUnCapacidad, $valor, $campo)
    {
        settype($idEventoUnCapacidad, 'integer');

        if (! $idEventoUnCapacidad or ! $campo) {
            return false;
        }
        $set   = array($campo => $valor, 'autorizado' => 0);
        $where = array('idEventoUnCapacidad' => $idEventoUnCapacidad);

        DB::connection('crm')->update(TBL_EVENTOUNCAPACIDAD, $set, $where);
        $this->permisos_model->log('Se actualiza capacidad del evento', LOG_EVENTO);

        return true;
    }

     /**
     * Valida si un movimiento va a ser devengado
     *
     * @param integer $idMovimiento Identificador de movimientos
     *
     * @author Ivan Mendoza
     *
     * @return Object  Activo y Autorizado de EventoUnCapacidad
     */
    public static function movientoDevengado($idMovimiento){
        $query = DB::connection('crm')->table(TBL_MOVIMIENTO.' `mov`')
        ->select('eucap.activo AS activo, eucap.autorizado AS autorizado')
        ->from(TBL_MOVIMIENTO.' `mov`')
        ->join('crm.movimientoctacontable mcc','mov.idMovimiento = mcc.idMovimiento')
        ->join('crm.eventomovimiento emov','mov.idMovimiento = emov.idMovimiento')
        ->join('crm.eventoinscripcion eins','emov.idEventoInscripcion = eins.idEventoInscripcion')
        ->join('crm.eventoun eun','eun.idEventoUn = eins.idEventoUn')
        ->join('crm.eventouncapacidad eucap','eun.idEventoUn = eucap.idEventoUn')
        ->join('crm.tipoeventocapacidad tec','tec.idTipoEventoCapacidad = eucap.idTipoEventoCapacidad')
        ->where('eucap.idTipoEventoCapacidad', 30)
        ->where('mov.idMovimiento', $idMovimiento)
        ->distinct()
        ->get();
        $rs = $query[0];
        
        return $rs;
    }

     /**
     * Aplica el Devengado 60 20 20 Al Movimiento Contable
     *
     * @param integer $idMovimiento Identificador de movimientos
     *
     * @author Ivan Mendoza
     *
     * @return  Object con Id´s de Movimientos contables Insertados y Fechas de Aplicacion
     */
    public static function devengarMovimientoContable($idMovimiento){
        $queryMovimiento = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
        ->where('idMovimiento', $idMovimiento);
        
        //Obtenemos datos de Movimiento
            if ($queryMovimiento->count() > 0) {
                $queryMovimiento = $queryMovimiento->get()->toArray();
                $movimientoctacontable = $queryMovimiento[0];
            }

        //Actualizamos Registro Contable del 60
        $whereCtaContable = array(
            'idMovimiento' => $idMovimiento
        );
        $set = array(
            'importe' => ($movimientoctacontable->importe * 0.60)
        );

        $result = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
        ->where($whereCtaContable)
        ->update($set);
        
        if($result){
            $insertTblDevengado = [
               'idMovimientoCtaContable' => $movimientoctacontable->idMovimientoCtaContable,
               'idTipoDevengadoProducto' => '668',
               'idTipoDevengado'         => '4',
               'numeroAplicaciones'      => '1'
            ];
           //Insert a Tabla Movimientos Devengados
           // DB::connection('crm')->insert(TBL_MOVIMIENTODEVENGADO, $insertTblDevengado); No es Necesario
           $datos['60'] = "Movimientyo Actualizado Correctamente al 60 $" .($movimientoctacontable->importe * 0.60);
        }
        //Iniciamilamos Arreglo para el Primer Insert
        $nuevafecha = strtotime ( '+1 month' , strtotime ( $movimientoctacontable->fechaAplica ) ) ;
        $nuevafecha = date ( 'Y-m-j' , $nuevafecha );
        $datosDevengado = array (
            'idMovimiento'          =>  $idMovimiento ,
            'idTipoMovimiento'      => $movimientoctacontable->idTipoMovimiento ,
            'idUn'                  => $movimientoctacontable->idUn ,
            'numeroCuenta'          => $movimientoctacontable->numeroCuenta ,
            'cuentaProducto'        => $movimientoctacontable->cuentaProducto ,
            'fechaAplica'           =>  $nuevafecha ,
            'importe'               => ( $movimientoctacontable->importe * 0.20 ) ,
            'cveProductoServicio'   => $movimientoctacontable->cveProductoServicio ,
            'cveUnidad'             => $movimientoctacontable->cveUnidad ,
            'cantidad'              => $movimientoctacontable->cantidad
        );
        
        $idMovimientoCuentaCont = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
        ->insertGetId($datosDevengado);
        $idMovimientoCuentaCont = DB::connection('crm')->insert_id();
        
        if($idMovimientoCuentaCont > 0 ){
            $sql = "UPDATE `movimientoctacontable` SET fechaAplica = adddate(last_day('".$movimientoctacontable->fechaAplica."'), 1) WHERE `idMovimientoCtaContable` =". $idMovimientoCuentaCont;
             DB::connection('crm')->select($sql);

            $insertTblDevengado['idMovimientoCtaContable'] =  $idMovimientoCuentaCont ;
            //Insert a Tabla Movimientos Devengados
            DB::connection('crm')->table(TBL_MOVIMIENTODEVENGADO)
            ->insert($insertTblDevengado);
            $datos['80'] = "Movimientyo Creado Correctamente al 20 2o Mes $" .($movimientoctacontable->importe * 0.20);
        }
        //Inicializamos Arreglo para segundo Insert
        $nuevafecha = strtotime ( '+1 month' , strtotime ( $movimientoctacontable->fechaAplica) ) ;
        $nuevafecha = date ( 'Y-m-j' , $nuevafecha );
        $idMovimientoCuentaCont = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)->insertGetId($datosDevengado);
        $devengadoSegundoMes = $idMovimientoCuentaCont;
        if($devengadoSegundoMes > 0){
            $sql = "UPDATE `movimientoctacontable` SET fechaAplica = adddate(last_day('".$nuevafecha."'), 1) WHERE `idMovimientoCtaContable` =". $idMovimientoCuentaCont;
            DB::connection('crm')->select($sql);
            $insertTblDevengado['idMovimientoCtaContable'] = $idMovimientoCuentaCont;
            //Insert a Tabla Movimientos Devengados
            DB::connection('crm')->table(TBL_MOVIMIENTODEVENGADO)->insert($insertTblDevengado);
            $datos['100'] = "Movimientyo Ceado Correctamente al  20 3er Mes $" .($movimientoctacontable->importe * 0.20);
        }
        //Actualizamos Log
        Permiso::log(utf8_decode("Actualiza importe del Movimiento Contable 60 20 20 (".$idMovimiento.")"), LOG_SISTEMAS);
        return $datos ;
    }


    /**
     * [guardaClasificacion description]
     *
     * @param  [type] $idEvento              [description]
     * @param  [type] $idEventoClasificacion [description]
     *
     * @return [type]                        [description]
     */
    public function guardaClasificacion ($idEvento, $idEventoClasificacion)
    {
        settype($idEvento, 'integer');
        settype($idEventoClasificacion, 'integer');

        $res = false;

        if ($idEvento==0 || $idEventoClasificacion==0) {
            return $res;
        }
        $set   = array('idEventoClasificacion' => $idEventoClasificacion);
        $where = array('idEvento' => $idEvento);
        $res   = DB::connection('crm')->update(TBL_EVENTO, $set, $where);

        $this->permisos_model->log('Se actualiza clasificacion del evento', LOG_EVENTO);

        return $res;
    }


    /**
     * Actualiza e inserta en eventoparticipante y eventounentregapaquete talla y club
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idUn     Identificador de unidad de negocio
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function guardaClubEntregaTalla($idUnEntrega, $idTalla, $idEventoInscripcion, $idPersona, $idEventoCategoria, $idDistanciaCarrera, $idOleadaCarrera, $nombreEquipo)
    {
        $resultado=0;
        if($nombreEquipo=='')
        {
            $nombreEquipo = null;
        }
        #Actualizamos talla de enventoparticipante
        $sql = "UPDATE crm.eventoparticipante SET idTalla=".$idTalla.", idEventoCategoria=".$idEventoCategoria.", idDistanciaCarrera=".$idDistanciaCarrera.", idOleadaCarrera=".$idOleadaCarrera.", nombreEquipo = '".$nombreEquipo."'
                WHERE idEventoInscripcion IN (".$idEventoInscripcion.") AND idPersona IN (".$idPersona.")";
        $query = DB::connection('crm')->query($sql);
        if (DB::connection('crm')->affected_rows()>0) {
            $sql2 = "INSERT INTO crm.eventounentregapaquete (idPersona, idUn, idEventoInscripcion, fechaRegistro) VALUES(".$idPersona.", ".$idUnEntrega.", ".$idEventoInscripcion.", NOW())";
            $query2 = DB::connection('crm')->query($sql2);
            if (DB::connection('crm')->affected_rows()>0) {
                $resultado=1;
            }
        }
        return $resultado;
    }

    /**
     * Guarda relacion de evento con producto
     *
     * @param integer $idProducto   Identificador de producto
     * @param integer $idTipoEvento Identificador de tipoevento
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function guardaEvento ($idProducto, $idTipoEvento = 0)
    {
        settype($idProducto, 'integer');
        $idEvento = 0;

        if (! $idProducto) {
            return $idEvento;
        }
        $idEvento = $this->validaEventoProducto($idProducto);

        if (! $idEvento) {
            $idEvento = $this->insertaEvento($idProducto, $idTipoEvento);
        }
        return $idEvento;
    }

    /**
     * Guarda configuracion de evento
     *
     * @param integer $idEventoUn
     * @param string $campo
     * @param integer/string $valor
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function guardaEventoConfig ($idEventoUn, $campo, $valor)
    {
        settype($idEventoUn, 'integer');
        $res = false;

        if (! $idEventoUn) {
            return $res;
        }
        $set   = array($campo => $valor);
        $where = array('idEventoUn' => $idEventoUn);
        $res   = DB::connection('crm')->update(TBL_EVENTOUN, $set, $where);

        $this->permisos_model->log('Se actualiza configuracion del evento por club', LOG_EVENTO);

        return $res;
    }

    /**
     * Guarda configuracion de eventoApp
     *
     * @param integer $idEvento
     * @param string $campo
     * @param integer/string $valor
     *
     * @author Oscar Sanchez Villavicencio
     * 2018-11-29 18:31:22
     *
     * @return boolean
     */
    public function guardaEventoAppChk ($idEvento, $campo, $valor)
    {
        settype($idEvento, 'integer');
        $res = false;

        if (! $idEvento) {
            return $res;
        }
        $set   = array($campo => $valor);
        $where = array('idEvento' => $idEvento);
        $res   = DB::connection('crm')->update(TBL_EVENTO, $set, $where);

        $this->permisos_model->log('Se actualiza configuracion del evento por club (eventoApp)', LOG_EVENTO);

        return $res;
    }

    /**
     * Registra el valor de un combo box en la base de datos
     *
     * @param integer $evento   Id del registro a procesar
     * @param integer $club     Valor del club seleccionado
     * @param integer $estatus  Status para el registro
     *
     * @return boolean
     */
    public function guardaEventoUn($evento, $club, $estatus)
    {
        $EventoUn = 0;

        DB::connection('crm')->select('idEventoUn');
        DB::connection('crm')->from(TBL_EVENTOUN);
        DB::connection('crm')->where('idEvento', $evento);
        DB::connection('crm')->where('idUn', $club);
        DB::connection('crm')->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = DB::connection('crm')->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $EventoUn = $fila["idEventoUn"];
        }

        if ($EventoUn == 0) {
            $datos = array (
                'idEvento' => $evento,
                'idUn'     => $club,
                'activo'   => $estatus
            );
            DB::connection('crm')->insert(TBL_EVENTOUN, $datos);

            $this->permisos_model->log('Se agrego el evento ('.$evento.') al club ('.$club.')', LOG_EVENTO);
        } else {
            $set   = array('activo' => $estatus);
            $where = array('idEventoUn' => $EventoUn);
            DB::connection('crm')->update(TBL_EVENTOUN, $set, $where);

            $this->permisos_model->log('Se modifico el estatus activo del evento ('.$evento.') en club ('.$club.')', LOG_EVENTO);
        }
        $total = DB::connection('crm')->affected_rows();
        if ($total == 0) {
            return false;
        }

        return true;
    }

    /**
     * Guarda el inicio folio del evento
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idURL    Identificador de URL de evento
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function guardaFinFolio($idEvento, $finFolio)
    {
        settype($idEvento, 'integer');
        settype($finFolio, 'string');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );
        if (!$idEvento) {
            return $datos;
        }
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al actualizar Identificador de URL';

        $where            = array('idEvento' => $idEvento);
        $set              = array('finFolio' => $finFolio);
        if (DB::connection('crm')->update(TBL_EVENTO, $set, $where)) {
            $datos['error']   = 0;
            $datos['mensaje'] = '';
        }
        return $datos;
    }

    /**
     * Guarda el identificador de URL del evento
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idURL    Identificador de URL de evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function guardaIdentificadorURL($idEvento, $idURL)
    {
        settype($idEvento, 'integer');
        settype($idURL, 'string');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );
        if (!$idEvento) {
            return $datos;
        }
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al actualizar Identificador de URL';

        $where            = array('idEvento' => $idEvento);
        $set              = array('idURL' => $idURL);
        if (DB::connection('crm')->update(TBL_EVENTO, $set, $where)) {
            $datos['error']   = 0;
            $datos['mensaje'] = '';
        }
        return $datos;
    }

    /**
     * Guarda el inicio folio del evento
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idURL    Identificador de URL de evento
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function guardaInicioFolio($idEvento, $inicioFolio)
    {
        settype($idEvento, 'integer');
        settype($inicioFolio, 'string');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );
        if (!$idEvento) {
            return $datos;
        }
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al actualizar Identificador de URL';

        $where            = array('idEvento' => $idEvento);
        $set              = array('inicioFolio' => $inicioFolio);
        if (DB::connection('crm')->update(TBL_EVENTO, $set, $where)) {
            $datos['error']   = 0;
            $datos['mensaje'] = '';
        }
        return $datos;
    }

    /**
     * Inserta inscripcion al evento envaido
     *
     * @param integer $idProd  Producto a ser utlizado
     *
     * @return boolean
     */
    public function guardaParticipante($idInscripcion, $idPersona, $idEvento = 0)
    {
        settype($idInscripcion, 'integer');
        settype($idPersona, 'integer');
        settype($idEvento, 'integer');

        if (!$idInscripcion or !$idPersona) {
            return 0;
        }

        $idCategoria = 0;
        if ($idEvento>0) {
            $idCategoria = $this->obtenIdCategoria($idEvento);
        }
        if ($idCategoria==CATEGORIA_CARRERAS) {
            $sql = "
SELECT IF(MAX(ep.numfolio) IS NULL,0, MAX(ep.numfolio)) AS ultimoFolio
FROM crm.eventoparticipante ep
INNER JOIN crm.eventoinscripcion ei ON ei.idEventoInscripcion=ep.idEventoInscripcion AND ei.monto=ei.pagado
INNER JOIN crm.eventoun eu ON eu.idEventoUn=ei.idEventoUn
INNER JOIN crm.evento e ON e.idEvento=eu.idEvento AND e.idEvento IN (".$idEvento.")
WHERE ep.fechaEliminacion='0000-00-00 00:00:00'
            ";
            $query = DB::connection('crm')->select($sql);
            $query = array_map(function($x){return (array)$x;},$query);
            $row = $query[0];
            $ultimoFolio = $row->ultimoFolio;
            $ultimoFolio = 0;

            $set = array (
                'idEventoInscripcion' => $idInscripcion,
                'idPersona'           => $idPersona,
                'numFolio'            => $ultimoFolio
            );
        } else {
            $set = array (
                'idEventoInscripcion' => $idInscripcion,
                'idPersona'           => $idPersona
            );
        }

        $id = DB::connection('crm')->table(TBL_EVENTOPARTICIPANTE)->insertGetId($set);
        Permiso::log('Se asigna persona al evento ('.$idPersona.')', LOG_EVENTO);

        return $id;
    }

    /**
     * Guarda configuracion de puesto comision
     *
     * @param integer $idEventoPuestoComision Identificador de puesto comision
     * @param integer $idPuesto               Identificador de puesto
     * @param string  $tipoPuesto             Identificador de tipo de puesto
     * @param integer $idEvento               Identificador de evento
     * @param integer $activo                 Estatus de la configuracion
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function guardaPuestoComision($idEventoPuestoComision, $idPuesto, $tipoPuesto, $idEvento, $activo, $orden)
    {
        settype($idEventoPuestoComision, 'integer');
        settype($idPuesto, 'integer');
        settype($tipoPuesto, 'string');
        settype($idEvento, 'integer');
        settype($activo, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos',
            'res'     => false
        );
        $valido = false;
        if ( ! $idPuesto or ! $tipoPuesto or ! $idEvento) {
            return $datos;
        }
        $where = array(
            'orden'            => $orden,
            'tipoPuesto'       => $tipoPuesto,
            'idEvento'         => $idEvento,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        if ($idEventoPuestoComision) {
            $where['idEventoPuestoComision <>'] = $idEventoPuestoComision;
        }
        if (DB::connection('crm')->select('COUNT(idEventoPuestoComision)AS total', false)->get_where(TBL_EVENTOPUESTOCOMISION, $where)->row()->total == 0) {
            $where = array(
                'idEvento'         => $idEvento,
                'idPuesto'         => $idPuesto,
                'tipoPuesto'       => $tipoPuesto,
                'fechaEliminacion' => '0000-00-00 00:00:00'
            );
            if ($idEventoPuestoComision) {
                $where['idEventoPuestoComision <>'] = $idEventoPuestoComision;
            }
            if (DB::connection('crm')->select('COUNT(idEventoPuestoComision)AS total', false)->get_where(TBL_EVENTOPUESTOCOMISION, $where)->row()->total == 0) {

                if ($idEventoPuestoComision) {
                    $set = array(
                        'idPuesto'   => $idPuesto,
                        'tipoPuesto' => $tipoPuesto,
                        'activo'     => $activo,
                        'orden'      => $orden
                    );
                    $where = array('idEventoPuestoComision' => $idEventoPuestoComision);

                    $datos['res'] = DB::connection('crm')->update(TBL_EVENTOPUESTOCOMISION, $set, $where);

                    $this->permisos_model->log('Actualiza configuracion de puesto comision', LOG_EVENTO);
                } else {
                    $set = array(
                        'idPuesto'   => $idPuesto,
                        'tipoPuesto' => $tipoPuesto,
                        'activo'     => $activo,
                        'idEvento'   => $idEvento,
                        'orden'      => $orden
                    );
                    $datos['res'] = DB::connection('crm')->insert(TBL_EVENTOPUESTOCOMISION, $set);

                    $this->permisos_model->log('Inserta configuracion de puesto comision', LOG_EVENTO);
                }
                if ($datos['res']) {
                    $datos['error']   = 0;
                    $datos['mensaje'] = 'Se guardaron exitosamente los cambios';
                } else {
                    $datos['error']   = 2;
                    $datos['mensaje'] = 'Error al guardar los cambios';
                }
            } else {
                $datos['error']   = 3;
                $datos['mensaje'] = 'Error, La configuarcion ingresada ya existe, seleccione otra';
            }
        } else {
            $datos['error']   = 4;
            $datos['mensaje'] = 'Error, el numero de orden "'.$orden.'" para "'.$tipoPuesto.'" ya esta registrado.';
        }
        return $datos;
    }

    /**
     * Guarda configuracion de puesto comision excepcion
     *
     * @param integer $idEventoUnPuestoExcepcion Identificador de eventounpuestoexcepcion
     * @param integer $idPuesto                  Identificador de puesto
     * @param integer $idPersonaEmpleado         Identificador de persona del empleado
     * @param string  $tipoPuesto                Identificador de tipo de puesto
     * @param integer $idEvento                  Identificador de evento
     * @param integer $idUn                      Identificador de unidad de negocio
     * @param integer $activo                    Estatus de la configuracion
     * @param integer $orden                     Orden de la configuracion
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function guardaPuestoComisionExcepcion($idEventoUnPuestoExcepcion, $idPuesto, $idPersonaEmpleado, $tipoPuesto, $idEvento, $idUn, $activo, $orden)
    {
        $ci =& get_instance();

        $ci->load->model('empleados_model');

        settype($idEventoUnPuestoExcepcion, 'integer');
        settype($idPuesto, 'integer');
        settype($idPersonaEmpleado, 'integer');
        settype($tipoPuesto, 'string');
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        settype($activo, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos',
            'res'     => false
        );
        $valido = false;
        if ( ! $tipoPuesto or ! $idEvento or ! $idUn) {
            return $datos;
        }
        $idEmpleado = $idPersonaEmpleado ? $ci->empleados_model->obtenIdEmpleado($idPersonaEmpleado) : 0;
        $idEventoUn = $this->obtenIdEventoUn($idEvento, $idUn);

        if ($idEventoUn) {
            $where = array(
                'orden'            => $orden,
                'tipoPuesto'       => $tipoPuesto,
                'idEventoUn'       => $idEventoUn,
                'fechaEliminacion' => '0000-00-00 00:00:00'
            );
            if ($idEventoUnPuestoExcepcion) {
                $where['idEventoUnPuestoExcepcion <>'] = $idEventoUnPuestoExcepcion;
            }
            if (DB::connection('crm')->select('COUNT(idEventoUnPuestoExcepcion)AS total', false)->get_where(TBL_EVENTOUNPUESTOEXCEPCION, $where)->row()->total == 0) {
                $where = array(
                    'idEventoUn'       => $idEventoUn,
                    'idPuesto'         => $idPuesto,
                    'idEmpleado'       => $idEmpleado,
                    'tipoPuesto'       => $tipoPuesto,
                    'fechaEliminacion' => '0000-00-00 00:00:00'
                );
                if ($idEventoUnPuestoExcepcion) {
                    $where['idEventoUnPuestoExcepcion <>'] = $idEventoUnPuestoExcepcion;
                }
                if (DB::connection('crm')->select('COUNT(idEventoUnPuestoExcepcion)AS total', false)->get_where(TBL_EVENTOUNPUESTOEXCEPCION, $where)->row()->total == 0) {
                    if ($idEventoUnPuestoExcepcion) {
                        $set = array(
                            'idEmpleado' => $idEmpleado,
                            'idPuesto'   => $idPuesto,
                            'tipoPuesto' => $tipoPuesto,
                            'activo'     => $activo,
                            'orden'      => $orden
                        );
                        $where = array('idEventoUnPuestoExcepcion' => $idEventoUnPuestoExcepcion);

                        $datos['res'] = DB::connection('crm')->update(TBL_EVENTOUNPUESTOEXCEPCION, $set, $where);

                        $this->permisos_model->log('Actualiza configuracion de puesto comision', LOG_EVENTO);
                    } else {
                        $set = array(
                            'idEmpleado' => $idEmpleado,
                            'idPuesto'   => $idPuesto,
                            'tipoPuesto' => $tipoPuesto,
                            'activo'     => $activo,
                            'idEventoUn' => $idEventoUn,
                            'orden'      => $orden
                        );
                        $datos['res'] = DB::connection('crm')->insert(TBL_EVENTOUNPUESTOEXCEPCION, $set);

                        $this->permisos_model->log('Inserta configuracion de puesto comision', LOG_EVENTO);
                    }
                    if ($datos['res']) {
                        $datos['error']   = 0;
                        $datos['mensaje'] = 'Se guardaron exitosamente los cambios';
                    } else {
                        $datos['error']   = 2;
                        $datos['mensaje'] = 'Error al guardar los cambios';
                    }
                } else {
                    $datos['error']   = 3;
                    $datos['mensaje'] = 'La configuarcion ingresada ya existe, seleccione otra';
                }
            } else {
                $datos['error']   = 5;
                $datos['mensaje'] = 'Error, el numero de orden "'.$orden.'" para "'.$tipoPuesto.'" ya esta registrado.';
            }
        } else {
            $datos['error']   = 4;
            $datos['mensaje'] = 'Error, no se encontro empleado o configuracion del evento en este club';
        }
        return $datos;
    }

    /**
     * Actualiza o inserta los datos de la clase personalizada
     *
     * @param integer $id    Id del eventoFecha
     * @param array   $datos Arreglo con los datos a insertar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function guardarOpcionClase($id = 0, $datos = array())
    {
        $idInsertado = 0;

        if (empty($datos)) {
            return $idInsertado;
        }
        if ($id != 0) {
            DB::connection('crm')->where('idEventoFecha', $id);
            $resultado = DB::connection('crm')->update(TBL_EVENTOFECHA, $datos);
            $this->permisos_model->log('Se actualiza fecha clase para evento', LOG_EVENTO);

            if ($resultado == true) {
                $idInsertado = $id;
            }
        } else {
            $resultado = DB::connection('crm')->insert(TBL_EVENTOFECHA, $datos);
            $this->permisos_model->log('Se inserta fecha clase para evento', LOG_EVENTO);

            if ($resultado == true) {
                $idInsertado = DB::connection('crm')->insert_id();
            }
        }
        return $idInsertado;
    }

    /**
     * Guarda tipo de evento
     *
     * @param integer $idEvento     Identificador de evento
     * @param integer $idTipoEvento Identificador de tipo de evento
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function guardaTipoEvento ($idEvento, $idTipoEvento)
    {
        settype($idEvento, 'integer');
        settype($idTipoEvento, 'integer');
        $res = false;

        if (! $idEvento or ! $idTipoEvento) {
            return $res;
        }
        $set   = array('idTipoEvento' => $idTipoEvento);
        $where = array('idEvento' => $idEvento);
        $res   = DB::connection('crm')->update(TBL_EVENTO, $set, $where);

        $this->permisos_model->log('Se actualiza configuracion del tipo de evento', LOG_EVENTO);

        return $res;
    }

    /**
     * Genera una inscripcion para el evento solicitado
     *
     * @param  integer  $idEvento         Identificador del evento
     * @param  integer  $idUn             Identificador del club
     * @param  integer  $idPersona        Identificador de la persona
     * @param  integer $idPersonaRespVta  Identificador de persona responsable de la venta
     * @param  float $monto               Monto por cuota de inscripcion
     * @param  float $pagado              Monto pagado de la cuota de inscripcion
     * @param  integer $membresia         [description]
     * @param  integer $cantidad          [description]
     * @param  integer $totalSesion       [description]
     * @param  integer $idTipoCliente     [description]
     * @param  integer $descQuincenas     [description]
     * @param  integer $informativo       [description]
     *
     * @author Jorge Cruz
     *
     * @return integer                    [description]
     */
    public static function inscripcion($idEvento, $idUn, $idPersona, $idPersonaRespVta = 0, $monto = 0,
        $pagado = 0, $membresia = 0, $cantidad = 0, $totalSesion = 0, $idTipoCliente = TIPO_CLIENTEEXTERNO,
        $descQuincenas = 1, $informativo = 0, $participantes = 0, $idPersonaRespVta1 = 0)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        settype($idPersona, 'integer');
        settype($membresia, 'integer');
        settype($monto, 'float');
        settype($pagado, 'float');
        settype($cantidad, 'float');
        settype($totalSesion, 'float');
        settype($idTipoCliente, 'integer');
        settype($descQuincenas, 'integer');
        settype($informativo, 'integer');

        if ($idEvento == 0 || $idUn == 0 || $idPersona == 0 or $idPersonaRespVta == 0) {
            return 0;
        }
        
        $query = DB::connection('crm')->table(TBL_EVENTOUN)
        ->select('idEventoUn, edadMinima, edadMaxima')
        ->where('idUn', $idUn)
        ->where('idEvento', $idEvento)
        ->where('activo', 1)
        ->where('fechaEliminacion', '0000-00-00 00:00:00');
        
        if ($query->count() > 0) {
            $fila = ($query->get()->toArray())[0];
            $idEventoUn = $fila['idEventoUn'];
            $edadMinima = $fila['edadMinima'];
            $edadMaxima = $fila['edadMaxima'];
        } else {
            return (-1);
        }
        
        $query = DB::connection('crm')->table(TBL_EVENTO.' e')
        ->select('p.nombre')
        ->join(TBL_PRODUCTO.' p', 'e.idProducto=p.idProducto')
        ->where('e.idEvento', $idEvento)
        
        
        if ($query->count() > 0) {
            $fila = ($query->get()->toArray())[0];
            $nombre = $fila['nombre'];
        } else {
            $nombre = '';
        }

        $idCategoria = $this->obtenIdCategoria($idEvento);
        if ($participantes == 0) {
            $participantes = $this->capacidadEvento($idEvento, $idUn, TIPO_NUMERO_PARTICIPANTES);
        }

        $unSession = (int)$_SESSION['idUn'];
        if ($unSession==0) {
            $unSession = $idUn;
        }

        $empleadoSession = (int)$_SESSION['idEmpleado'];
        if ($empleadoSession==0) {
            $empleadoSession = Empleado::obtenIdEmpleado($idPersonaRespVta);
            
        }

        $reg = array (
            'idEventoUn'              => $idEventoUn,
            'idPersona'               => $idPersona,
            'idUn'                    => $unSession,
            'idEmpleado'              => $empleadoSession,
            'idTipoEstatusInscripcion'=> 1,
            'monto'                   => $monto,
            'pagado'                  => $pagado,
            'cantidad'                => $cantidad,
            'totalSesiones'           => $totalSesion,
            'idTipoCliente'           => $idTipoCliente,
            'descQuincenas'           => $descQuincenas,
            'informativo'             => $informativo,
            'participantes'           => $participantes
        );

        $inscripcion = DB::connection('crm')->table(TBL_EVENTOINSCRIPCION)->insertGetId($reg);
        
        Permiso::log(
            'Se realiza incripcion al evento '.$nombre.' (Num. Inscripcion '.$inscripcion.')',
            LOG_EVENTO,
            $membresia,
            $idPersona
        );

        $edad = Persona::edad($idPersona);

        if ( ( ($edadMinima == 0 && $edadMaxima == 0) || ($edad >= $edadMinima && $edad <= $edadMaxima)) && $inscripcion > 0 ) {
            if ($idCategoria==CATEGORIA_CARRERAS) {
                $this->guardaParticipante($inscripcion, $idPersona, $idEvento);
            } else {
                if ($idCategoria!=CATEGORIA_SUMMERCAMP) {
                    $this->guardaParticipante($inscripcion, $idPersona);
                }
            }
        }

        $set = array(
            'idEventoInscripcion' => $inscripcion,
            'idPersona'           => $_SESSION['idPersona'],
            'tipo'                => 1
        );
        $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);

        if ($res) {
            $set = array(
                'idEventoInscripcion' => $inscripcion,
                'idPersona'           => $idPersonaRespVta,
                'tipo'                => 2
            );
            $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);

            if ($idPersonaRespVta1>0) {
                $set = array(
                    'idEventoInscripcion' => $inscripcion,
                    'idPersona'           => $idPersonaRespVta1,
                    'tipo'                => 3
                );
                $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);
            }
        }
        return $inscripcion;
    }

    /**
     * Vincula el movimiento indicado con la inscripcion
     *
     * @param integer $idInscripcion Identificador de la inscripcion
     * @param integer $idMovimiento  Identificador del movimiento
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public static function inscripcionMovimiento($idInscripcion, $idMovimiento)
    {
        settype($idInscripcion, 'integer');
        settype($idMovimiento, 'integer');
        
        if ($idInscripcion == 0 || $idMovimiento == 0) {
            return 0;
        }
        
        $reg = array (
            'idEventoInscripcion' => $idInscripcion,
            'idMovimiento'        => $idMovimiento
        );
        $id = DB::connection('crm')->table(TBL_EVENTOMOVIMIENTO)
        ->insertGetId($reg);
        Permiso::log('Se vincula evento al movimiento ('.$idMovimiento.')', LOG_EVENTO);
        
        return $id;
    }


    /**
     * [insertaClase description]
     *
     * @param  [type] $idEventoInscripcion [description]
     * @param  [type] $idEmpleado          [description]
     * @param  [type] $idPersona           [description]
     * @param  [type] $fecha               [description]
     * @param  [type] $hora                [description]
     *
     * @return [type]                      [description]
     */
    public static function insertaClase($idEventoInscripcion, $idEmpleado, $idPersona, $fecha, $hora, $demo = 0)
    {
        settype($idEventoInscripcion, 'integer');
        settype($idEmpleado, 'integer');
        settype($idPersona, 'integer');

        $res = 0;

        if ($idEventoInscripcion>0 && $idPersona>0) {
            $sql = "select totalSesiones from ".TBL_EVENTOINSCRIPCION."
            where idEventoInscripcion = '{$idEventoInscripcion}'
            and fechaEliminacion = '0000-00-00 00:00:00' ";
            $query = DB::connection('crm')->select($sql);
            
            $query = DB::connection('crm')
            ->table(TBL_EVENTOINSCRIPCION)
            ->select('totalSesiones')
            ->where('idEventoInscripcion', $idEventoInscripcion)
            ->where('fechaEliminacion', '0000-00-00 00:00:00')
            ->get()->toArray();
            
            $query = array_map(function($x){return (array)$x;},$query);
            $fila = $query[0];
            $totalSesiones = $fila['totalSesiones'];
            $query = DB::connection('crm')
            ->table(TBL_EVENTOFECHA)
            ->select('idEventoFecha')
            ->where('idEventoInscripcion', $idEventoInscripcion)
            ->where('fechaEliminacion', '0000-00-00 00:00:00');
            $totalClase = $query->count();
            
            $estatusClase = ESTATUS_CLASE_ASIGNADO;
            if ($demo==1) {
                $estatusClase = ESTATUS_CLASE_DEMO;
            }
            if ($totalClase-1 < $totalSesiones) {
                $reg = array(
                    'idEventoInscripcion'      => $idEventoInscripcion,
                    'idTipoEstatusEventoFecha' => $estatusClase,
                    'idEmpleado'               => $idEmpleado,
                    'idPersona'                => $idPersona,
                    'idUnInstalacion'          => 0,
                    'fechaEvento'              => $fecha,
                    'horaEvento'               => $hora
                );
                $res = DB::connection('crm')->table(TBL_EVENTOFECHA)->insertGetId($reg);
                
                Permiso::log('Se agenda clase para la inscripcion ('.$idEventoInscripcion.')', LOG_EVENTO);

                $totalClase = $totalClase + 1;
                $set = array('totalSeguimiento' => $totalClase);
                $where = array('idEventoInscripcion' => $idEventoInscripcion);
                DB::connection('crm')->table(TBL_EVENTOINSCRIPCION)->where($where)->update($set);
            }
        }

        return $res;
    }

    /**
     * Inserta relacion eventouncategoria
     *
     * @param integer $idEventoUn Identificador de eventoun
     * @param integer $idTalla    Identificador de talla
     *
     * @author Antonio Sixtos
     *
     * @return int
     */
    public function insertaConfigCategoria($idEventoUn, $idEventoCategoria)
    {
        settype($idEventoUn, 'integer');
        settype($idEventoCategoria, 'integer');

        $set = array(
            'idEventoUn'        => $idEventoUn,
            'idEventoCategoria' => $idEventoCategoria,
            'fechaRegistro'     => date('Y-m-d H:i:s'),
            'activo'            => 0
        );
        if (!$idEventoUn or !$idEventoCategoria) {
            $set['idEventoUnCategoria'] = 0;
            return $set;
        }
        if (DB::connection('crm')->insert(TBL_EVENTOUNCATEGORIA, $set)) {
            $set['idEventoUnCategoria'] = DB::connection('crm')->insert_id();
        }
        return $set;
    }

    /**
     * Inserta relacion eventounentrega
     *
     * @param integer $idEventoUn Identificador de eventoun
     * @param integer $idUn       Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return int
     */
    public function insertaConfigClub($idEventoUn, $idUn)
    {
        settype($idEventoUn, 'integer');
        settype($idUn, 'integer');

        $set = array(
            'idEventoUn' => $idEventoUn,
            'idUn'       => $idUn,
            'activo'     => 0
        );
        if (!$idEventoUn or !$idUn) {
            $set['idEventoUnEntrega'] = 0;
            return $set;
        }
        if (DB::connection('crm')->insert(TBL_EVENTOUNENTREGA, $set)) {
            $set['idEventoUnEntrega'] = DB::connection('crm')->insert_id();
        }
        return $set;
    }

    /**
     * Inserta relacion eventountalla
     *
     * @param integer $idEventoUn Identificador de eventoun
     * @param integer $idTalla    Identificador de talla
     *
     * @author Jonathan Alcantara
     *
     * @return int
     */
    public function insertaConfigTalla($idEventoUn, $idTalla)
    {
        settype($idEventoUn, 'integer');
        settype($idTalla, 'integer');

        $set = array(
            'idEventoUn' => $idEventoUn,
            'idTalla'    => $idTalla,
            'activo'     => 0
        );
        if (!$idEventoUn or !  $idTalla) {
            $set['idEventoUnTalla'] = 0;
            return $set;
        }
        if (DB::connection('crm')->insert(TBL_EVENTOUNTALLA, $set)) {
            $set['idEventoUnTalla'] = DB::connection('crm')->insert_id();
        }
        return $set;
    }

    /**
     * Inserta un nuevo evento
     *
     * @param integer $idProducto   Identificador de producto
     * @param integer $idTipoEvento Identificador de tipoevento
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function insertaEvento ($idProducto, $idTipoEvento)
    {
        settype($idProducto, 'integer');
        settype($idTipoEvento, 'integer');
        $idEvento = 0;

        if (! $idProducto or ! $idTipoEvento) {
            return $idEvento;
        }
        $set = array(
            'idProducto'   => $idProducto,
            'idTipoEvento' => $idTipoEvento
        );

        DB::connection('crm')->insert(TBL_EVENTO, $set);
        $id = DB::connection('crm')->insert_id();
        $this->permisos_model->log('Se inserta nuevo evento', LOG_EVENTO);

        return $id;
    }

    /**
     * Inserta capacidad a evento
     *
     * @param integer $idTipoEventoCapacidad Identificador de tipoeventocapacidad
     * @param integer $idEventoUn            Identificador de eventoun
     * @param integer $activo                Bandera de estatus de eventouncapacidad
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function insertaEventoCapacidadUn($idTipoEventoCapacidad, $idEventoUn, $activo = 0)
    {
        settype($idTipoEventoCapacidad, 'integer');
        settype($idEventoUn, 'integer');
        $idEventoUnCapacidad = 0;

        if (! $idTipoEventoCapacidad or ! $idEventoUn) {
            return $idEventoUnCapacidad;
        }
        $set = array(
            'idTipoEventoCapacidad' => $idTipoEventoCapacidad,
            'idEventoUn'            => $idEventoUn,
            'activo'                => $activo
        );
        DB::connection('crm')->insert(TBL_EVENTOUNCAPACIDAD, $set);
        $id = DB::connection('crm')->insert_id();
        $this->permisos_model->log('Se inserta capacidad del evento', LOG_EVENTO);

        return $id;
    }

    /**
     *
     * @param <type> $evento
     * @param <type> $club
     * @param <type> $empresa
     * @param <type> $totales
     * @param <type> $orden
     * @param <type> <1posicion></1posicion>
     * @param <type> $registros
     * @param <type> $direction
     *
     * @return <type>
     */
    public function lista($evento = '', $club = 0, $empresa = 0, $totales = false, $orden = 'pr.nombre', $posicion = null, $registros = null, $direction = 'ASC')
    {
        settype($club, 'integer');

        $datos = array();

        $idPuesto = $this->session->userdata('idPuesto');

        $ci =& get_instance();
        $ci->load->model('empleados_model');
        $puesto = $ci->empleados_model->obtienePuesto($idPuesto);

        $filtro = '';
        $pos = strrpos($puesto, 'KIDZ');
        if ($pos !== false) {
            $filtro = ' AND p.idCategoria='.CATEGORIA_SUMMERCAMP;
        }

        $sql = "SELECT UPPER(p.nombre) AS producto, eu.inicioEvento, u.nombre AS club, e.idProducto, eu.idEvento, eu.idUn
            FROM ".TBL_EVENTOUN." eu
            INNER JOIN ".TBL_EVENTOINSCRIPCION." ei ON ei.idEventoUn=eu.idEventoUn AND ei.fechaEliminacion='0000-00-00 00:00:00'
                AND ei.idTipoEstatusInscripcion=1 AND ei.informativo=0
            INNER JOIN ".TBL_UN." u ON u.idUn=eu.idUn
            INNER JOIN ".TBL_EVENTO." e ON e.idEvento=eu.idEvento AND e.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN ".TBL_PRODUCTO." p ON p.idProducto=e.idProducto AND p.fechaEliminacion='0000-00-00 00:00:00' $filtro
            WHERE eu.idUn=$club AND eu.fechaEliminacion='0000-00-00 00:00:00' AND eu.activo=1
                AND ( DATE(NOW()) BETWEEN eu.inicioRegistro AND DATE_ADD(eu.finRegistro, INTERVAL 6 MONTH)  )
                AND p.activo=1
            GROUP BY p.idProducto
            ORDER BY p.nombre";
        $query = DB::connection('crm')->query($sql);

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
     *
     * @param type $idUn
     * @return type
     */
    public function listaAtualizacionClases($idUn)
    {
        $data=  array();
        $sql = "SELECT ef.idEventoFecha, CONCAT_WS(' ',p.nombre,p.paterno,p.materno) AS socio,  pr.nombre AS producto,
                CONCAT_WS(' ', ef.fechaEvento, ef.horaEvento) AS fechaEvento, CONCAT_WS(' ',per.nombre,per.paterno,per.materno) AS empleado,CONCAT(f.prefijoFactura, f.folioFactura) as folioFactura, tef.descripcion AS estatus, tef.idTipoEstatusEventoFecha
                FROM ".TBL_PERSONA." p
                INNER JOIN ".TBL_EVENTOINSCRIPCION." ei ON ei.idPersona=p.idPersona
                INNER JOIN ".TBL_EVENTOUN." eu ON eu.idEventoUn=ei.idEventoUn
                INNER JOIN ".TBL_EVENTO." e ON e.idEvento=eu.idEvento
                INNER JOIN ".TBL_PRODUCTO." pr ON pr.idProducto=e.idProducto
                INNER JOIN ".TBL_EVENTOFECHA." ef ON ef.idEventoInscripcion=ei.idEventoInscripcion AND ef.idTipoEstatusEventoFecha=1
                INNER JOIN ".TBL_EMPLEADO." emp ON emp.idEmpleado=ef.idEmpleado
                INNER JOIN ".TBL_PERSONA." per ON per.idPersona=emp.idPersona
                INNER JOIN ".TBL_EVENTOMOVIMIENTO." em ON em.idEventoInscripcion=ei.idEventoInscripcion
                INNER JOIN ".TBL_FACTURAMOVIMIENTO." fm ON fm.idMovimiento=em.idMovimiento
                INNER JOIN ".TBL_FACTURA." f ON f.idFactura=fm.idFactura
                INNER JOIN ".TBL_TIPOESTATUSEVENTOFECHA." tef ON tef.idtipoestatuseventofecha=ef.idTipoEstatusEventoFecha
                WHERE ei.idUn=".$idUn."
                AND DATE(ef.fechaEvento) <= DATE(NOW())";
        $query = DB::connection('crm')->query($sql);

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
     * Regresa el listado de clases disponibles
     *
     * @param integer $idEventoInscripcion   Indica el valor del filtro en db
     *
     * @return array
     */
    public function listaClasesEvento($idEventoInscripcion = "",  $orden = "ef.fechaEvento DESC, ef.horaEvento DESC")
    {
        settype($idEventoInscripcion, 'integer');
        $data = null;

        $sql = "SELECT DISTINCT ef.idEventoFecha, ef.fechaEvento, ef.horaEvento, p.idPersona,
                IF (ef.idPersona=c.idPersona OR c.idPersona IS NULL, TRIM(UPPER(p.nombre)), TRIM(UPPER(p2.nombre))) AS nombre,
                IF (ef.idPersona=c.idPersona OR c.idPersona IS NULL, TRIM(UPPER(p.paterno)), TRIM(UPPER(p2.paterno))) AS paterno,
                IF (ef.idPersona=c.idPersona OR c.idPersona IS NULL, TRIM(UPPER(p.materno)), TRIM(UPPER(p2.materno))) AS materno,
                ef.idEmpleado,
                teef.descripcion, ef.idTipoEstatusEventoFecha, c.idTipoEstatusComision
            FROM eventofecha ef
            INNER JOIN persona p ON ef.idPersona = p.idPersona
            LEFT JOIN eventofechacomision efc ON efc.idEventoFecha = ef.idEventoFecha
            LEFT JOIN comision c ON c.idComision = efc.idComision AND c.fechaEliminacion = '0000-00-00 00:00:00'
                AND c.idPersona<>274580
            LEFT JOIN persona p2 ON p2.idPersona=c.idPersona
            INNER JOIN tipoestatuseventofecha teef ON ef.idTipoEstatusEventoFecha = teef.idTipoEstatusEventoFecha
            WHERE ef.idEventoInscripcion = $idEventoInscripcion AND ef.fechaEliminacion = '0000-00-00 00:00:00'
            ORDER BY ef.fechaEvento DESC, ef.horaEvento DESC";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Regresa una lista de UN por evento
     *
     * @return array
     */
    public function listaEventoUnActivos($idEvento = 0)
    {
        $lista = array();
        $lista['0'] = '';

        DB::connection('crm')->select('u.idUn, u.nombre');
        DB::connection('crm')->join(TBL_UN.' u', 'eu.idUn=u.idUn');
        if ($idEvento > 0) {
            DB::connection('crm')->where('eu.idEvento', $idEvento);
        }
        DB::connection('crm')->where('eu.activo', '1');
        $query = DB::connection('crm')->order_by('u.nombre')->get(TBL_EVENTOUN.' eu');

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idEventoUn] = $fila->nombre;
            }
        }

        return $lista;
    }

    /**
     * Lista inscritos a evento
     *
     * @param type $idEvento           Identificador de evento
     * @param type $idUn               Identificador de unidad de negocio
     * @param type $totales            Bandera para obtener totales o arreglo de resultados
     * @param type $eventosInscripcion ids de eventoinscripcion a filtrar
     * @param type $order              Orden de la consulta
     * @param type $posicion           Posicion de inicio de consulta
     * @param type $registros          Total de registros de consulta
     * @param type $direction          Direccion de consulta
     *
     * @author Jonathan Alcantara
     *
     * @return array/integer
     */
    public function listaInscritosEvento($idEvento = 0, $idUn = 0, $totales = false, $eventosInscripcion = '', $order = 'pr.nombre', $posicion = 0, $registros = REGISTROS_POR_PAGINA, $direction = 'ASC', $validaFechasEvento = false)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        settype($posicion, 'integer');
        settype($registros, 'integer');

        $innerFechasEvento = '';

        if ($totales) {
            $datos = 0;
        } else {
            $datos = array();
        }
        if (! $idEvento or ! $idUn) {
            return $datos;
        }
        if ($validaFechasEvento) {
            $innerFechasEvento = 'AND ei.inicioEvento = eu.inicioEvento AND ei.finEvento = eu.finEvento';
        }

        if ($idEvento==382) {
            $limite = 12;
        } else {
            $limite = 6;
        }

        $sql="SELECT DISTINCT pr.nombre as producto, p.nombre, p.paterno, p.materno, eu.inicioEvento, u.idUn, u.nombre as club,
            e.idProducto, eu.idEvento, ei.idEventoInscripcion, ei.idEventoUn, p.idPersona,
            DATE(ei.fechaRegistro) AS fechaRegistro, ei.monto, ei.pagado, (ei.monto - ei.pagado)AS debe, ei.idTipoEstatusInscripcion,
            DATE_ADD(f.fecha, INTERVAL 6 MONTH) AS finEvento, DATE_ADD(ei.fechaRegistro, INTERVAL 6 MONTH) AS finRegistro,
            CONCAT(f.prefijoFactura, f.folioFactura) AS folio, mov.idUnicoMembresia, mov.iva, c.codigo
            FROM ".TBL_EVENTOINSCRIPCION." ei
            INNER JOIN ".TBL_EVENTOMOVIMIENTO." em ON em.idEventoInscripcion = ei.idEventoInscripcion
            INNER JOIN ".TBL_PERSONA." p ON ei.idPersona = p.idPersona AND p.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN ".TBL_EVENTOUN." eu ON eu.idEventoUn = ei.idEventoUn ".$innerFechasEvento." AND eu.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN ".TBL_UN." u ON u.idUn = eu.idUn AND u.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN ".TBL_EVENTO." e ON e.idEvento = eu.idEvento  AND e.fechaEliminacion = '0000-00-00 00:00:00'
            INNER JOIN ".TBL_PRODUCTO." pr ON pr.idProducto = e.idProducto  AND pr.fechaEliminacion = '0000-00-00 00:00:00'
            LEFT JOIN ".TBL_MOVIMIENTO." mov ON mov.idMovimiento = em.idMovimiento AND mov.fechaEliminacion = '0000-00-00 00:00:00'
            LEFT JOIN ".TBL_CERTIFICADOPERSONA." cp ON cp.idMovimiento=mov.idMovimiento
            LEFT JOIN ".TBL_CERTIFICADO." c ON c.idCertficado=cp.idCertificado
            LEFT JOIN ".TBL_FACTURAMOVIMIENTO." fm ON fm.idMovimiento = mov.idMovimiento
            LEFT JOIN ".TBL_FACTURA." f ON f.idFactura = fm.idFactura
            WHERE ei.fechaEliminacion = '0000-00-00 00:00:00'
            AND (f.idFactura IS NULL OR DATE(f.fecha)>= DATE_SUB(DATE(NOW()),INTERVAL ".$limite." MONTH) )";
            // DATE_SUB(CAST(DATE_FORMAT(NOW(), '%Y-%m-01') AS DATE),INTERVAL 6 MONTH)
        if ($eventosInscripcion) {
            $sql .= ' AND ei.idEventoInscripcion IN ('.$eventosInscripcion.') ';
        } else {
            if ($idEvento>0) {
                $sql .= ' AND eu.idEvento='.$idEvento;
            }
            if ($idUn>0) {
                $sql .= ' AND eu.idUn='.$idUn;
            }
            $sql .= ' AND ei.idTipoEstatusInscripcion = 1 ';
        }
        #if ($idEvento == 125) {
        #    $sql .= ' AND (DATE(DATE_ADD(f.fecha, INTERVAL 2 MONTH)) >= DATE(NOW()) OR (f.fecha IS NULL))';
        #}
        $sql .= " ORDER BY p.nombre ASC, p.paterno ASC, p.materno ASC";
        if ($totales == false) {
            $sql .= " LIMIT ".$posicion.",".$registros;
        }
        $query = DB::connection('crm')->query($sql);

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
     * Regresa el listado de opciones disponibles
     *
     * @param type $idEventoInscripcion
     * @param type $idEvento
     * @param type $idUn
     * @param type $orden
     * @param type $validaFechasEvento
     *
     * @author Jonathna Alcantara
     *
     * @return array
     */
    public function listaParticipantesEvento($idEventoInscripcion = "", $idEvento = "", $idUn = "", $orden = "eu.inicioEvento", $validaFechasEvento = false, $idPersona = 0)
    {
        $data = null;

        $innerFechasEvento = '';

        if ($validaFechasEvento) {
            $innerFechasEvento = 'AND eu.inicioEvento = ei.inicioEvento AND eu.finEvento = ei.finEvento';
        }

        DB::connection('crm')->select(
            "pr.nombre as producto, UPPER(p.nombre) AS nombre, UPPER(p.paterno) AS paterno, UPPER(p.materno) AS materno,
            eu.inicioEvento, u.nombre as club, e.idProducto, eu.idEvento,
            ei.idEventoInscripcion, ei.idEventoUn, ei.idTipoEstatusInscripcion,
            p.idPersona, ei.fechaRegistro, ep.idEventoPartcipante,
            (
                SELECT IFNULL(DATE(f.fecha),'0000-00-00') AS fechaFactura
                FROM ".TBL_EVENTOMOVIMIENTO." em
                INNER JOIN ".TBL_MOVIMIENTO." mov ON mov.idMovimiento = em.idMovimiento
                LEFT JOIN ".TBL_FACTURAMOVIMIENTO." fm ON fm.idMovimiento = mov.idMovimiento
                LEFT JOIN ".TBL_FACTURA." f ON f.idFactura = fm.idFactura
                WHERE em.idEventoInscripcion = ei.idEventoInscripcion
                ORDER BY em.idEventoInscripcion DESC
                LIMIT 1
            )AS fechaFactura, ei.informativo,
            t.descripcion as estatus
            ", false
        );

        DB::connection('crm')->join(TBL_EVENTOPARTICIPANTE.' ep', "ep.idEventoInscripcion = ei.idEventoInscripcion AND ep.fechaEliminacion = '0000-00-00 00:00:00'", 'LEFT');
        DB::connection('crm')->join(TBL_PERSONA.' p', 'ep.idPersona = p.idPersona AND p.fechaEliminacion="0000-00-00 00:00:00"', "LEFT");
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'eu.idEventoUn = ei.idEventoUn '.$innerFechasEvento.' AND eu.fechaEliminacion="0000-00-00 00:00:00"');
        DB::connection('crm')->join(TBL_UN.' u', 'u.idUn = eu.idUn AND u.fechaEliminacion="0000-00-00 00:00:00" AND u.activo=1');
        DB::connection('crm')->join(TBL_EVENTO.' e', 'e.idEvento = eu.idEvento AND e.fechaEliminacion="0000-00-00 00:00:00"');
        DB::connection('crm')->join(TBL_PRODUCTO.' pr', 'pr.idProducto = e.idProducto AND pr.fechaEliminacion="0000-00-00 00:00:00"');
        DB::connection('crm')->join(TBL_TIPOESTATUSINSCRIPCION.' t', 't.idTipoEstatusInscripcion = ei.idTipoEstatusInscripcion AND t.activo = 1');
        if ($idUn!="") {
            DB::connection('crm')->where('eu.idUn', $idUn);
        }
        if ($idEvento!="") {
            DB::connection('crm')->where('eu.idEvento', $idEvento);
        }
        if ($idEventoInscripcion!="") {
            DB::connection('crm')->where('ei.idEventoInscripcion', $idEventoInscripcion);
        }
        if($idPersona > 0){
            DB::connection('crm')->where('ei.idPersona', $idPersona);
        }
        DB::connection('crm')->where('ei.fechaEliminacion', '0000-00-00 00:00:00');
        $query = DB::connection('crm')->distinct()->order_by($orden)->get(TBL_EVENTOINSCRIPCION." ei");

        if ($query->num_rows>0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Crea un arreglo con los registros de la tabla solicitada
     *
     * @param string $campos     Indica los campos a seleccionar de db
     * @param string $whereCampo Indica los campos sobre los que se buscara en db
     * @param string $whereValor Indica el valor de los campos que se buscara
     * @param string $orden      Indica el orden para el select
     *
     * @return array
     */
    public function listadoTabla($tabla, $campos, $whereCampo = "", $whereValor = "", $whereCampo2 = "", $whereValor2 = "", $orden = "")
    {
        $data = null;
        DB::connection('crm')->select($campos);
        if ($whereCampo!= "" and $whereValor!="") {
            DB::connection('crm')->where($whereCampo, $whereValor);
        }
        if ($whereCampo2!= "" and $whereValor2!="") {
            DB::connection('crm')->where($whereCampo2, $whereValor2);
        }
        $query = DB::connection('crm')->order_by($orden)->get($tabla);

        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
        }
        return $data;
    }

    /**
     * Regresa el numero maximo de participantes que se pueden registrar dentro de una inscripcion
     *
     * @param  integer $idEventoInscripcion Identificador de inscripcion
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function maxIntegrantesXInscripcion($idEventoInscripcion)
    {
        settype($idEventoInscripcion, 'integer');

        if ($idEventoInscripcion == 0) {
            return 0;
        }

        DB::connection('crm')->select('participantes');
        DB::connection('crm')->where('idEventoInscripcion', $idEventoInscripcion);
        $query = DB::connection('crm')->get(TBL_EVENTOINSCRIPCION);
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['participantes'];
        }

        return 0;
    }


    /**
     * [modificaMonto description]
     *
     * @param  [type] $idEventoInscripcion [description]
     * @param  [type] $importe             [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]                      [description]
     */
    public static function modificaMonto($idEventoInscripcion, $importe)
    {
        settype($idEventoInscripcion, 'integer');
        settype($importe, 'float');
        
        if ($idEventoInscripcion == 0) {
            return false;
        }
        
        $set   = array('monto' => $importe);
        $where = array(
            'idEventoInscripcion' => $idEventoInscripcion,
            'eliminado'           => 0
        );
        $res   = DB::connection('crm')->table(TBL_EVENTOINSCRIPCION)
        ->where($where)
        ->update($set);
        
        return true;
    }

    /**
     * Obtiene capacidades del evento
     *
     * @param integer $idEventoUn Identificador de eventoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenCapacidad ($idEventoUn, $permisoModificaTodo)
    {
        settype($idEventoUn, 'integer');
        $datos = array('error' => 1, 'mensaje' => 'Error al recibir datos', 'capacidad' => array());

        if (! $idEventoUn) {
            return $datos;
        }
        $tiposCapacidad = $this->obtenTiposEventoCapacidad();
        foreach ($tiposCapacidad as $idRow => $capacidad) {
            $idEventoUnCapacidad = 0;
            $infoCapacidad = $this->validaEventoCapacidadUn($capacidad['idTipoEventoCapacidad'], $idEventoUn);
            if (! $infoCapacidad) {
                $idEventoUnCapacidad = $this->insertaEventoCapacidadUn($capacidad['idTipoEventoCapacidad'], $idEventoUn);
            } else {
                $idEventoUnCapacidad = $infoCapacidad['idEventoUnCapacidad'];
            }
            if (! $idEventoUnCapacidad) {
                $datos['error']   = 2;
                $datos['mensaje'] = 'Error al insertar en eventouncapacidad';
                return $datos;
            }
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $where = array(
            'tec.activo'           => 1,
            'euc.idEventoUn'       => $idEventoUn,
            'euc.fechaEliminacion' => '0000-00-00 00:00:00',
        );
        if (!$permisoModificaTodo) {
            $where['tec.requierePermiso'] = 0;
        }
        DB::connection('crm')->join(TBL_EVENTOUNCAPACIDAD." euc", "tec.idTipoEventoCapacidad = euc.idTipoEventoCapacidad", "inner");
        DB::connection('crm')->select(
            "euc.idEventoUnCapacidad, tec.idTipoEventoCapacidad,
            tec.descripcion AS tipoEventoCapacidad, euc.capacidad, euc.activo, euc.autorizado, idCategoriaEventoCapacidad", false
        );
        DB::connection('crm')->order_by('tec.orden');
        $query = DB::connection('crm')->get_where(TBL_TIPOEVENTOCAPACIDAD." tec", $where);

        if ($query->num_rows) {
            $datos['capacidad'] = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene categorias
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenCategorias()
    {
        $datos = array();

        $query = DB::connection('crm')->select("
            idEventoCategoria, descripcion AS categoria, activo", false
        )->get_where(TBL_EVENTOCATEGORIA);#->get_where(TBL_TALLA, $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene clubs de entrega del evento
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idUn     Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenClubsEntrega ($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $datos = array();

        if (!$idEvento or !$idUn) {
            return $datos;
        }
        $where = array ('e.fechaEliminacion' => '0000-00-00 00:00:00');

        DB::connection('crm')->join(TBL_EVENTOUN.' eu', "eu.idEvento = e.idEvento AND eu.idEvento = ".$idEvento." AND eu.fechaEliminacion = '0000-00-00 00:00:00' AND eu.idUn = ".$idUn, "INNER");
        DB::connection('crm')->join(TBL_EVENTOUNENTREGA." eue", "eue.idEventoUn = eu.idEventoUn AND eue.fechaEliminacion = '0000-00-00 00:00:00' AND eue.activo = 1", "INNER");
        DB::connection('crm')->join('crm.un u', "u.idUn = eue.idUn", "INNER");

        $query = DB::connection('crm')->distinct()->select(
            "eue.idUn, u.nombre AS club", false
        )->get_where(TBL_EVENTO.' e', $where);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idUn] = $fila->club;
            }
        }
        return $datos;
    }

    /**
     * Obtiene la configuracion de las categorias de eventouncategorias
     *
     * @param integer $idEventoUn Identificador de eventoun
     *
     * @return array
     */
    public function obtenConfigCategorias($idEventoUn)
    {
        settype($idEventoUn, 'integer');

        $categoriasConfig = array();

        if (!$idEventoUn) {
            return $datos;
        }
        $categorias = $this->obtenCategorias();

        if ($categorias) {
            foreach ($categorias as $idRow => $datosCategorias) {
                $categoriasConfig[$datosCategorias['idEventoCategoria']] = $this->validaConfigCategoria($idEventoUn, $datosCategorias['idEventoCategoria']);

                if (!$categoriasConfig[$datosCategorias['idEventoCategoria']]['idEventoUnCategoria']) {
                    $categoriasConfig[$datosCategorias['idEventoCategoria']] = $this->insertaConfigCategoria($idEventoUn, $datosCategorias['idEventoCategoria']);
                }
                $categoriasConfig[$datosCategorias['idEventoCategoria']]['categoria']   = $datosCategorias['categoria'];
            }
        }
        return $categoriasConfig;
    }

    /**
     * Obtiene la configuracion de clubes de entrega para paquetes en un evento
     *
     * @param integer $idEventoUn Identificador de evento un
     * @param integer $idEmpresa  Identificador de empresa
     *
     * @return array
     */
    public function obtenConfigClubsEntrega($idEventoUn, $idEmpresa)
    {
        $CI =& get_instance();

        $CI->load->model('un_model');

        settype($idEventoUn, 'integer');
        settype($idEmpresa, 'integer');

        $clubsConfig = array();

        if (!$idEventoUn or !$idEmpresa) {
            return $datos;
        }
        $clubs = $CI->un_model->listaActivosTodos($idEmpresa);

        if ($clubs) {
            foreach ($clubs as $idUn => $club) {
                if (strpos($club, 'Seleccione') !== false or strpos($club, 'Admon') !== false or $club == '') {
                    unset($clubs[$idUn]);
                } else {
                    $clubsConfig[$idUn] = $this->validaConfigClub($idEventoUn, $idUn);

                    if (!$clubsConfig[$idUn]['idEventoUnEntrega']) {
                        $clubsConfig[$idUn] = $this->insertaConfigClub($idEventoUn, $idUn);
                    }
                    $clubsConfig[$idUn]['club'] = $club;
                }
            }
        }
        return $clubsConfig;
    }

    /**
     * * Muestra la vista de configuracion de comisiones por puesto
     *
     * @param integer $idEvento Identificador de evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenConfigPuestoComision($idEvento)
    {
        settype($idEvento, 'integer');

        $datos = array();

        if ( ! $idEvento) {
            return $datos;
        }
        DB::connection('crm')->join(TBL_PUESTO.' p', "p.idPuesto = epc.idPuesto AND p.fechaEliminacion = '0000-00-00 00:00:00'", "INNER");
        DB::connection('crm')->join(TBL_EVENTO.' e', "e.idEvento = epc.idEvento", "INNER");

        $where = array('epc.idEvento' => $idEvento,'epc.fechaEliminacion' => '0000-00-00 00:00:00');
        $query = DB::connection('crm')->select(
            "epc.idEventoPuestoComision, epc.idPuesto, p.codigo, p.descripcion AS puesto, epc.tipoPuesto, epc.activo, epc.orden", false
        )->order_by('epc.tipoPuesto, epc.orden, epc.fechaRegistro')->get_where(TBL_EVENTOPUESTOCOMISION.' epc', $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * * Obtiene configuracion de excepciones de comisiones por puesto
     *
     * @param integer $idEvento Identificador de evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenConfigPuestoComisionExcepcion($idEventoUn)
    {
        settype($idEventoUn, 'integer');

        $datos = array();


        if ( ! $idEventoUn) {
            return $datos;
        }
        DB::connection('crm')->join(TBL_EMPLEADO.' em', "em.idEmpleado = eupe.idEmpleado", "INNER");
        DB::connection('crm')->join(TBL_EMPLEADOPUESTO.' ep', "ep.idEmpleado = em.idEmpleado AND ep.fechaEliminacion = '0000-00-00 00:00:00'", "INNER");
        DB::connection('crm')->join(TBL_UN.' u', "u.idUn = ep.idUn", "LEFT");
        DB::connection('crm')->join(TBL_PUESTO.' pu', "pu.idPuesto = eupe.idPuesto AND pu.fechaEliminacion = '0000-00-00 00:00:00'", "INNER");
        DB::connection('crm')->join(TBL_PUESTO.' pu2', "pu2.idPuesto = ep.idPuesto AND pu.fechaEliminacion = '0000-00-00 00:00:00'", "INNER");
        DB::connection('crm')->join(TBL_PERSONA.' per', "per.idPersona = em.idPersona", "INNER");

        $where = array('eupe.idEventoUn' => $idEventoUn,'eupe.fechaEliminacion' => '0000-00-00 00:00:00');
        $query = DB::connection('crm')->distinct()->select(
            "eupe.idEventoUnPuestoExcepcion, eupe.idEmpleado AS idEmpleadoConfig, em.idEmpleado, eupe.orden, eupe.tipoPuesto, eupe.activo, per.idPersona, ".
            "pu2.descripcion AS puestoEmpleado, pu.descripcion AS puestoConfig, CONCAT(CONCAT_WS(' ', per.nombre, per.paterno, per.materno), ".
            "' (', pu2.descripcion, ')' ,' (', u.clave, ')')AS nombreEmpleado, eupe.idPuesto AS idPuestoConfig, pu.idPuesto AS idPuestoEmpleado", false
        )->order_by('eupe.tipoPuesto, eupe.orden, eupe.fechaRegistro')->get_where(TBL_EVENTOUNPUESTOEXCEPCION.' eupe', $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene la configuracion de las tallas de eventountallas
     *
     * @param integer $idEventoUn Identificador de eventoun
     *
     * @return array
     */
    public function obtenConfigTallas($idEventoUn)
    {
        settype($idEventoUn, 'integer');

        $tallasConfig = array();

        if (!$idEventoUn) {
            return $datos;
        }
        $tallas = $this->obtenTallas();

        if ($tallas) {
            foreach ($tallas as $idRow => $datosTalla) {
                $tallasConfig[$datosTalla['idTalla']] = $this->validaConfigTalla($idEventoUn, $datosTalla['idTalla']);

                if (!$tallasConfig[$datosTalla['idTalla']]['idEventoUnTalla']) {
                    $tallasConfig[$datosTalla['idTalla']] = $this->insertaConfigTalla($idEventoUn, $datosTalla['idTalla']);
                }
                $tallasConfig[$datosTalla['idTalla']]['talla']       = $datosTalla['talla'];
                $tallasConfig[$datosTalla['idTalla']]['abreviacion'] = $datosTalla['abreviacion'];
            }
        }
        return $tallasConfig;
    }

    /**
     * Obtiene la configuracion general de un evento
     *
     * @param integer $idProductoUn Identificador de productoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenConfiguracionGeneral ($idProductoUn)
    {
        settype($idProductoUn, 'integer');
        $datos = array();

        if (! $idProductoUn) {
            return $datos;
        }
        $where = array(
            'pu.idProductoUn'     => $idProductoUn,
            'pu.activo'           => 1,
            'pu.fechaEliminacion' => '0000-00-00 00:00:00',
            'e.fechaEliminacion'  => '0000-00-00 00:00:00',
            'eu.fechaEliminacion' => '0000-00-00 00:00:00'
        );
        DB::connection('crm')->join(TBL_EVENTO.' e', 'pu.idProducto = e.idProducto', 'inner');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'e.idEvento = eu.idEvento AND pu.idUn = eu.idUn', 'inner');
        $query = DB::connection('crm')->select(
            'e.eventoApp, e.idEvento, e.idTipoEvento, eu.idEventoUn, eu.idUn, eu.inicioRegistro,
            eu.finRegistro, eu.inicioEvento, eu.finEvento, eu.activo AS eventoUnActivo,
            eu.reservarInstalacion, eu.anticipo, eu.edadMinima, eu.edadMaxima,
            eu.idComisionConcepto, e.idEventoClasificacion', false
        )->get_where(TBL_PRODUCTOUN.' pu', $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }

        return $datos;
    }

    /**
     * Obtiene coordinador de evento
     *
     * @param integer $idEventoUn   Identificador de eventoun
     * @param integer $inicioEvento Fecha inicio evento
     * @param integer $finEvento    Fecha fin evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenCoordinador ($idEventoUn, $inicioEvento, $finEvento)
    {
        settype($idEventoUn, 'integer');
        settype($inicioEvento, 'string');
        settype($finEvento, 'string');

        $datos = array(
            'error'     => 1,
            'mensaje'   => 'Error faltan datos',
            'idPersona' => 0
        );
        if (!$idEventoUn or !$inicioEvento or !$finEvento) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $where = array(
            'idEventoUn'       => $idEventoUn,
            'inicioEvento'     => $inicioEvento,
            'finEvento'        => $finEvento,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select("idPersona", false)->get_where(TBL_EVENTOUNCOORDINADOR, $where, 1);

        if ($query->num_rows) {
            $datos['idPersona'] = $query->row()->idPersona;
        }
        return $datos;
    }

    /**
     * [obtenDatosEventoUnEntregaPaqueteCategoria description]
     * @param  [type] $idPersona           [description]
     * @param  [type] $idEventoInscripcion [description]
     *
     * @author Antonio Sixtos <antonio.sixtos@sportsworld.com.mx>
     *
     * @return [type]                      [description]
     */
    public function obtenDatosEventoUnEntregaPaqueteCategoria($idPersona, $idEventoInscripcion)
    {
        settype($idPersona, 'integer');
        settype($idEventoInscripcion, 'integer');

        $datos = array();
        $sql = "SELECT IF(euep.idUn IS NULL, 0, euep.idUn) AS idUn, IF(ep.idTalla  IS NULL, 0, ep.idTalla ) AS idTalla,
                    IF(ep.idEventoCategoria  IS NULL, 0, ep.idEventoCategoria ) AS idEventoCategoria,
                    IF(ep.idDistanciaCarrera  IS NULL, 0, ep.idDistanciaCarrera ) AS idDistanciaCarrera,
                    IF(ep.idOleadaCarrera  IS NULL, 0, ep.idOleadaCarrera ) AS idOleadaCarrera,
                    IF(ep.nombreEquipo IS NULL,'',ep.nombreEquipo) AS nombreEquipo
                FROM crm.eventounentregapaquete euep
                INNER JOIN crm.eventoparticipante ep ON ep.idEventoInscripcion=euep.idEventoInscripcion
                    AND ep.idPersona IN (".$idPersona.") AND ep.fechaEliminacion='0000-00-00 00:00:00'
                WHERE euep.idPersona IN (".$idPersona.") AND euep.idEventoInscripcion IN (".$idEventoInscripcion.")
                    AND euep.fechaEliminacion='0000-00-00 00:00:00'";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene horario de un evento programado (clase personalizada)
     *
     * @param integer $idUn        Id de unidad de negocio a filtrar
     * @param integer $idEmpleado  Id de persona (instructor) a filtrar
     * @param array   $arrayIdUns  Arreglo de unidades de negocio a filtrar
     * @param string  $fechaOrigen Fecha del evento a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenEventoActividadProgramada ($idUn = 0, $idInstalacion = 0, $idEmpleado = 0, $arrayIdUns = array(), $fechaOrigen = '')
    {
        $arrayIn = (string)"";
        $datos   = array();

        settype($idEmpleado, 'integer');
        settype($idUn, 'integer');
        settype($idInstalacion, 'integer');

        if (!empty($arrayIdUns)) {
            $arrayIn = implode(',', $arrayIdUns);
        }
        $where = array(
            'pro.activo'                  => 1,
            'pro.fechaEliminacion'        => '0000-00-00 00:00:00',
            'ef.fechaEliminacion'         => '0000-00-00 00:00:00',
            'ef.idTipoEstatusEventoFecha' => ESTATUS_CLASE_ASIGNADO,
            'pro.idTipoProducto'          => 5,
            'eu.activo'                   => 1,
            'ef.fechaEvento'              => $fechaOrigen
        );
        if ($idInstalacion > 0) {
            DB::connection('crm')->where("ui.idInstalacion", $idInstalacion);
        }
        if ($idEmpleado > 0) {
            DB::connection('crm')->where("ef.idEmpleado", $idEmpleado);
        }

        if ($arrayIn != "") {
            DB::connection('crm')->where_in('eu.idUn', $arrayIn);
        } else {
            DB::connection('crm')->where("eu.idUn", $idUn);
        }
        DB::connection('crm')->join(TBL_EVENTOINSCRIPCION." ei", "ef.idEventoInscripcion = ei.idEventoInscripcion", "inner");
        DB::connection('crm')->join(TBL_EVENTOUN." eu", "eu.idEventoUn = ei.idEventoUn", "inner");
        DB::connection('crm')->join(TBL_EVENTO." ev", "eu.idEvento = ev.idEvento", "inner");
        DB::connection('crm')->join(TBL_PRODUCTO." pro", "ev.idProducto = pro.idProducto", "left");
        DB::connection('crm')->join(TBL_EMPLEADO." emp", "emp.idEmpleado = ef.idEmpleado", "inner");
        DB::connection('crm')->join(TBL_PERSONA." per", "emp.idPersona = per.idPersona", "inner");
        DB::connection('crm')->join(TBL_UN." u", "eu.idUn = u.idUn", "inner");
        DB::connection('crm')->join(TBL_PRODUCTOACTIVIDADDEPORTIVA." pad", "pad.idProducto = pro.idProducto", "inner");
        DB::connection('crm')->join(TBL_UNINSTALACION." ui", "ef.idUnInstalacion = ui.idUnInstalacion", "inner");
        DB::connection('crm')->join(TBL_INSTALACION." i", "ui.idInstalacion = i.idInstalacion", "inner");
        DB::connection('crm')->group_by(
            "idInstActvProg, horaInicio, horaFin, imgActividad, pro.nombre,
            emp.idPersona, per.nombre, per.paterno, per.materno, u.nombre, idInstalacion,
            instalacion, idTipoDia, ef.fechaEvento, ef.fechaEvento, idDiaIni, idDiaFin,
            semanaIni, semanaFin, semanaOrigen, ef.fechaEvento, ef.idEventoFecha,
            idActividad, actividad"
        );
        $query = DB::connection('crm')->select(
            "0 AS idInstActvProg, HOUR(ef.horaEvento) AS horaInicio,
            HOUR(ef.horaEvento) AS horaFin, pro.rutaImagen AS imgActividad,
            pro.nombre, emp.idPersona, per.nombre, per.paterno, per.materno, u.nombre AS club,
            i.idInstalacion AS idInstalacion, i.descripcion AS instalacion, DAYOFWEEK(ef.fechaEvento) - 1 AS idTipoDia,
            ef.fechaEvento AS inicioVigencia, ef.fechaEvento AS finVigencia,
            DAYOFWEEK(ef.fechaEvento)-1 AS idDiaIni, DAYOFWEEK(ef.fechaEvento)-1 AS idDiaFin,
            WEEK(ef.fechaEvento) AS semanaIni, WEEK(ef.fechaEvento) AS semanaFin,
            WEEK(ef.fechaEvento) AS semanaOrigen, ef.fechaEvento AS fechaOrigen, 0 AS idActividad,
            'Clase Personalizada' AS actividad, ef.idEventoFecha", false
        )->get_where(TBL_EVENTOFECHA." ef", $where);

        if ($query->num_rows) {
            foreach ($query->result_array() as $fila) {
                $datos[] = $fila;
            }
        }
        return $datos;
    }

    /**
     * Obtiene inicio de folio del evento
     *
     * @param integer $idEvento Identificador de evento
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function obtenFinFolio($idEvento)
    {
        settype($idEvento, 'integer');

        $finFolio = '';

        if (!$idEvento) {
            return $inicioFolio;
        }
        $where = array('idEvento' => $idEvento);
        $query = DB::connection('crm')->select(
            "finFolio", false
        )->get_where(TBL_EVENTO, $where, 1);

        if ($query->num_rows) {
            $finFolio = $query->row()->finFolio;
        }
        return $finFolio;
    }

    /**
     * [obtenFoliosDisponibles description]
     * @param  [type] $evento   [description]
     * @param  [type] $club     [description]
     * @param  [type] $cantidad [description]
     * @return [type]           [description]
     */
    public function obtenFoliosDisponibles($evento, $cantidad)
    {
        settype($evento, 'integer');
        settype($cantidad, 'integer');

        $sql1 = "CALL crm.spObtenCantFoliosDispCarreras(".$evento.",".$cantidad.", @respuesta)";
        $query1 = DB::connection('crm')->query($sql1);

        $sql2 = "SELECT @respuesta AS resp";
        $query2 = DB::connection('crm')->query($sql2);
        $row = $query2->row();
        return $row->resp;
    }

    /**
     * Obtiene la categoria del evento
     *
     * @param integer $idEvento Identificador del evento
     *
     * @return array
     */
    public function obtenIdCategoria($idEvento)
    {
        settype($idEvento, 'integer');
        
        $sql = "
SELECT pr.idCategoria
FROM  evento e
INNER JOIN producto pr ON pr.idProducto=e.idProducto
WHERE e.idEvento IN (".$idEvento.")
        ";
        $query = DB::connection('crm')->select($sql);
        $row = $query[0]
        return $row->idCategoria;
    }

    /**
     * Obtiene idEventoUn
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idUn     Identificador de club
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function obtenIdEventoUn ($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');
        $idEventoUn = 0;

        if (! $idEvento or ! $idUn) {
            return $idEventoUn;
        }
        $where = array(
            'idEvento'         => $idEvento,
            'idUn'             => $idUn,
            'activo'           => 1,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select(
            "idEventoUn", false
        )->get_where(TBL_EVENTOUN, $where);

        if ($query->num_rows) {
            $idEventoUn = $query->row()->idEventoUn;
        }
        return $idEventoUn;
    }

    /**
     * Obtiene identificador de url de evento
     *
     * @param integer $idEvento Identificador de evento
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function obtenIdentificadorURL($idEvento)
    {
        settype($idEvento, 'integer');

        $idURL = '';

        if (!$idEvento) {
            return $idURL;
        }
        $where = array('idEvento' => $idEvento);
        $query = DB::connection('crm')->select(
            "idURL", false
        )->get_where(TBL_EVENTO, $where, 1);

        if ($query->num_rows) {
            $idURL = $query->row()->idURL;
        }
        return $idURL;
    }

    /**
     * Obtiene inicio de folio del evento
     *
     * @param integer $idEvento Identificador de evento
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function obtenInicioFolio($idEvento)
    {
        settype($idEvento, 'integer');

        $inicioFolio = '';

        if (!$idEvento) {
            return $inicioFolio;
        }
        $where = array('idEvento' => $idEvento);
        $query = DB::connection('crm')->select(
            "inicioFolio", false
        )->get_where(TBL_EVENTO, $where, 1);

        if ($query->num_rows) {
            $inicioFolio = $query->row()->inicioFolio;
        }
        return $inicioFolio;
    }

    /**
     * Regresa idEventoInscripcion buscado por factura
     *
     * @param string $folioFactura Folio de factura a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenInscripcionFactura ($folioFactura)
    {
        settype($folioFactura, 'string');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );
        if (!$folioFactura) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        DB::connection('crm')->where('ei.fechaEliminacion', '0000-00-00 00:00:00');
        DB::connection('crm')->where('(f.idFactura IS NULL OR DATE(f.fecha)>= DATE_SUB(DATE(NOW()),INTERVAL 6 MONTH) )');

        DB::connection('crm')->join(TBL_EVENTOUN.' eu', "eu.idEventoUn = ei.idEventoUn", "INNER");
        DB::connection('crm')->join(TBL_UN.' u', "u.idUn = eu.idUn", "INNER");
        DB::connection('crm')->join(TBL_EVENTOMOVIMIENTO.' em', "em.idEventoInscripcion = ei.idEventoInscripcion", "INNER");
        DB::connection('crm')->join(TBL_MOVIMIENTO.' m', "em.idMovimiento = m.idMovimiento AND m.fechaEliminacion = '0000-00-00 00:00:00'", "INNER");
        DB::connection('crm')->join(TBL_FACTURAMOVIMIENTO.' fm', "m.idMovimiento = fm.idMovimiento", "INNER");
        DB::connection('crm')->join(TBL_FACTURA.' f', "fm.idFactura = f.idFactura AND f.fechaCancelacion = '0000-00-00' AND f.idTipoEstatusFactura = 109", "INNER");
        DB::connection('crm')->join(TBL_PRODUCTO.' p', "p.idProducto = m.idProducto", "INNER");

        $query = DB::connection('crm')->distinct()->select("
            ei.idEventoInscripcion, CONCAT(f.prefijoFactura, f.folioFactura) AS folioFactura, eu.idEventoUn, eu.idEvento, eu.idUn, u.nombre AS club,
            DATE(f.fecha) AS fechaFactura, p.activo AS estatusProducto, eu.fechaEliminacion AS eventoUnFechaEliminacion, p.fechaEliminacion AS productoFechaEliminacion, p.nombre AS producto
        ", false
        )->having("folioFactura = '".$folioFactura."'", null, false)->order_by('f.fecha', 'DESC')->get(TBL_EVENTOINSCRIPCION.' ei');

        $datos['query'] = DB::connection('crm')->last_query();

        $datos['registros']          = $query->num_rows;
        $datos['res']                = array();
        $datos['eventosInscripcion'] = array();

        if ($datos['registros']) {
            $datos['res'] = $query->result_array();

            if ($datos['registros'] > 0) {
                for ($i=0; $i<$datos['registros']; $i++) {
                    $datos['eventosInscripcion'][] = $datos['res'][$i]['idEventoInscripcion'];
                }
            } else {
                $datos['eventosInscripcion'][] = $datos['res'][0]['idEventoInscripcion'];
            }
        }
        return $datos;
    }

    /**
     * Regresa arreglo de inscritos a la carrera
     *
     * @param boolean $totales   Bandera para indicar si se obtienen totales o registros
     * @param string  $idPersona Identificador de persona
     * @param string  $order     Orden de resultados
     * @param integer $offset    Posicion de inicio de resultados
     * @param integer $limit     Limite de resultados
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenInscripcionesCarrera ($totales = false, $idPersona = 0, $order = 'ei.fechaRegistro', $offset = 0, $limit = REGISTROS_POR_PAGINA)
    {
        settype($idPersona, 'integer');

        $limitePosicion = "";

        if ($totales) {
            $datos['res'] = 0;
        } else {
            $datos = array('res' => array());

            if (!$idPersona) {
                $limitePosicion = " LIMIT ".$limit." OFFSET ".$offset;
            }
        }
        $sql = "
            SELECT @rownum:=@rownum+1 AS numero, r.idPersona, r.nombre, r.carrera, r.sexo, r.edad ".
            " FROM (SELECT @rownum:= ".$offset.") n, ( ".
                " SELECT DISTINCT ep.idPersona, CONCAT_WS(' ', per.nombre, per.paterno, per.materno) AS nombre, p.nombre AS carrera,  ".
                " ts.descripcion AS sexo, (YEAR(CURDATE())-YEAR(per.fechaNacimiento)) - (RIGHT(CURDATE(),5) < RIGHT(per.fechaNacimiento,5)) AS edad ".
                " FROM ".TBL_EVENTO." e ".
                " INNER JOIN ".TBL_PRODUCTO." p ON p.idProducto=e.idProducto ".
                " INNER JOIN ".TBL_EVENTOUN." eu ON eu.idEvento=e.idEvento ".
                " INNER JOIN ".TBL_EVENTOINSCRIPCION." ei ON ei.idEventoUn=eu.idEventoUn ".
                " INNER JOIN ".TBL_EVENTOPARTICIPANTE." ep ON ep.idEventoInscripcion=ei.idEventoInscripcion ".
                " INNER JOIN ".TBL_PERSONA." per ON per.idPersona = ep.idPersona ".
                " INNER JOIN ".TBL_TIPOSEXO." ts ON ts.idTipoSexo = per.idTipoSexo ".
                " INNER JOIN ".TBL_EVENTOMOVIMIENTO." em ON em.idEventoInscripcion=ei.idEventoInscripcion ".
                " INNER JOIN ".TBL_FACTURAMOVIMIENTO." fm ON fm.idMovimiento=em.idMovimiento ".
                " WHERE e.idProducto IN (932, 931, 930, 929, 928) ".
                " ORDER BY ".$order.
                " ".$limitePosicion."
            ) r;
        ";
        $query = DB::connection('crm')->query($sql);

        $datos['query'] = '';

        if ($query->num_rows) {
            if ($totales) {
                $datos['res'] = $query->num_rows;
            } else {
                $datos['res'] = $query->result_array();
            }
            $datos['query'] = str_replace("LIMIT ".$limit." OFFSET ".$offset, '', DB::connection('crm')->last_query());
        }
        return $datos;
    }

    /**
     * Regresa un array con la lista de inscritos a la carrera
     *
     * @param string  $nombre          Nombre de el empleado a filtrar
     * @param integer $numeroRegistros Numero de registros a regresar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenInscritosCarrera($nombre = "", $numeroRegistros = REGISTROS_POR_PAGINA)
    {
        settype($nombre, "string");
        settype($numeroRegistros, "integer");

        $nombre = trim($nombre);
        $nombre = DB::connection('crm')->escape_like_str($nombre);
        $nombre = str_replace(" ", "%", $nombre);

        $sql = "SELECT DISTINCT per.idPersona, per.nombre, per.paterno, per.materno ".
            " FROM ".TBL_EVENTO." e ".
            " INNER JOIN ".TBL_PRODUCTO." p ON p.idProducto=e.idProducto ".
            " INNER JOIN ".TBL_EVENTOUN." eu ON eu.idEvento=e.idEvento ".
            " INNER JOIN ".TBL_EVENTOINSCRIPCION." ei ON ei.idEventoUn=eu.idEventoUn ".
            " INNER JOIN ".TBL_EVENTOPARTICIPANTE." ep ON ep.idEventoInscripcion=ei.idEventoInscripcion ".
            " INNER JOIN ".TBL_PERSONA." per ON per.idPersona = ep.idPersona ".
            " INNER JOIN ".TBL_TIPOSEXO." ts ON ts.idTipoSexo = per.idTipoSexo ".
            " INNER JOIN ".TBL_EVENTOMOVIMIENTO." em ON em.idEventoInscripcion=ei.idEventoInscripcion ".
            " INNER JOIN ".TBL_FACTURAMOVIMIENTO." fm ON fm.idMovimiento=em.idMovimiento ".
            " WHERE e.idProducto IN (932, 931, 930, 929, 928) ";
        $sql .= " AND CONCAT(per.nombre,' ', per.paterno,' ', per.materno) LIKE '%".$nombre."%' ";
        $sql .= " ORDER BY per.nombre, per.paterno, per.materno LIMIT ".$numeroRegistros." ";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Obtiene lista de categorias config para evento
     *
     * @param integer $idEvento Identificador del evento
     *
     * @return array
     */
    public function obtenListaCategorias($idEvento, $idUn)
    {
        $datos = array();
        $sql = "SELECT  ec.idEventoCategoria, ec.descripcion
                FROM evento e
                INNER JOIN eventoun eu ON eu.idEvento=e.idEvento AND eu.idUn IN (".$idUn.")
                INNER JOIN eventouncategoria euc ON euc.idEventoUn=eu.idEventoUn
                    AND euc.activo IN (1) AND euc.fechaEliminacion = '0000-00-00 00:00:00'
                INNER JOIN eventocategoria ec ON ec.idEventoCategoria=euc.idEventoCategoria
                WHERE e.idEvento IN (".$idEvento.");";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idEventoCategoria] = $fila->descripcion;
            }
        }
        return $datos;
    }

    /**
     * Obtiene lista de categorias config para evento
     *
     * @param integer $idEvento Identificador del evento
     *
     * @return array
     */
    public function obtenListaDistanciaCarrera($idEvento, $idUn)
    {
        $datos = array();
        $sql = "SELECT  dc.idDistanciaCarrera, dc.distancia
                FROM evento e
                INNER JOIN eventoun eu ON eu.idEvento=e.idEvento AND eu.idUn IN (".$idUn.")
                INNER JOIN eventoundistanciacarrera eudc ON eudc.idEventoUn=eu.idEventoUn
                    AND eudc.activo IN (1) AND eudc.fechaEliminacion = '0000-00-00 00:00:00'
                INNER JOIN distanciacarrera dc ON dc.idDistanciaCarrera=eudc.idDistanciaCarrera
                WHERE e.idEvento IN (".$idEvento.");";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idDistanciaCarrera] = $fila->distancia;
            }
        }
        return $datos;
    }

    /**
     * Obtiene lista de categorias config para evento
     *
     * @param integer $idEvento Identificador del evento
     *
     * @return array
     */
    public function obtenListaOleadaCarrera($idEvento, $idUn)
    {
        $datos = array();
        $sql = "SELECT  oc.idOleadaCarrera, oc.descripcion
                FROM evento e
                INNER JOIN eventoun eu ON eu.idEvento=e.idEvento AND eu.idUn IN (".$idUn.")
                INNER JOIN eventounoleadacarrera euoc ON euoc.idEventoUn=eu.idEventoUn
                    AND euoc.activo IN (1) AND euoc.fechaEliminacion = '0000-00-00 00:00:00'
                INNER JOIN oleadacarrera oc ON oc.idOleadaCarrera=euoc.idOleadaCarrera
                WHERE e.idEvento IN (".$idEvento.");";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idOleadaCarrera] = $fila->descripcion;
            }
        }
        return $datos;
    }

    /**
     * [obtenNumeroCorredor description]
     * @param  [type] $idEventoPartcipante [description]
     * @return [type]                      [description]
     */
    public function obtenNumeroCorredor($idEventoPartcipante)
    {
        settype($idEventoPartcipante, 'integer');
        $datos = 0;

        $sql = "SELECT  ep.numFolio
                FROM eventoparticipante ep
                WHERE ep.idEventoPartcipante IN (".$idEventoPartcipante.");";
        $query = DB::connection('crm')->query($sql);

        if ($query->num_rows) {
            $row    = $query->row();
            $datos  = $row->numFolio;
        }

        return $datos;
    }

    /**
     * Obtiene tallas
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTallas()
    {
        $datos = array();
        $where = array('idTalla >' => 0);

        $query = DB::connection('crm')->select("
            idTalla, nombre AS talla , abreviacion", false
        )->get_where(TBL_TALLA, $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene tallas del evento
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idUn     Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTallasCarreras ($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $datos = array();

        if (!$idEvento or !$idUn) {
            return $datos;
        }
        $where = array ('e.fechaEliminacion' => '0000-00-00 00:00:00');

        DB::connection('crm')->join(TBL_EVENTOUN.' eu', "eu.idEvento = e.idEvento AND eu.idEvento = ".$idEvento." AND eu.fechaEliminacion = '0000-00-00 00:00:00' AND eu.idUn = ".$idUn, "INNER");
        DB::connection('crm')->join(TBL_EVENTOUNTALLA." eut", "eut.idEventoUn = eu.idEventoUn AND eut.fechaEliminacion = '0000-00-00 00:00:00' AND eut.activo = 1", "INNER");
        DB::connection('crm')->join(TBL_TALLA.' t', "t.idTalla = eut.idTalla AND t.activo = 1", "INNER");

        $query = DB::connection('crm')->distinct()->select(
            "t.idTalla, t.nombre AS talla, t.abreviacion", false
        )->get_where(TBL_EVENTO.' e', $where);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idTalla] = $fila->talla;
            }
        }
        return $datos;
    }

    /**
     * Regresa arreglo de tipo de estatus de una clase
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function obtenTipoEstatusEventoFecha ()
    {
        $datos = array();
        $where = array('activo' => 1);
        $query = DB::connection('crm')->select(
            "idTipoEstatusEventoFecha, descripcion"
        )->get_where(TBL_TIPOESTATUSEVENTOFECHA, $where);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idTipoEstatusEventoFecha] = $fila->descripcion;
            }
        }
        return $datos;
    }

    /**
     * Regresa arreglo de tipo de estatus de inscripcion a un evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTipoEstatusInscripcion ()
    {
        $datos = array();
        $where = array('activo' => 1);
        $query = DB::connection('crm')->select(
            "idTipoEstatusInscripcion, descripcion as tipoEstatusInscripcion"
        )->get_where(TBL_TIPOESTATUSINSCRIPCION, $where);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idTipoEstatusInscripcion] = $fila->tipoEstatusInscripcion;
            }
        }
        return $datos;
    }

    /**
     * Obtiene tipos de evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTiposEvento ()
    {
        $datos = array();
        $where = array('activo' => 1);
        $query = DB::connection('crm')->select(
            "idTipoEvento, descripcion AS tipoEvento"
        )->get_where(TBL_TIPOEVENTO, $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene tipo de capacidades del evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTiposEventoCapacidad ()
    {
        $datos = array();
        $where = array('activo' => 1);
        $query = DB::connection('crm')->select(
            "idTipoEventoCapacidad, descripcion AS tipoEventoCapacidad"
        )->get_where(TBL_TIPOEVENTOCAPACIDAD, $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Regresa total de clases impartidas en un evento
     *
     * @param integer $idEventoInscripcion Identificador de eventoinscripcion
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function obtenTotalClasesImpartidas ($idEventoInscripcion)
    {
        settype($idEventoInscripcion, 'integer');

        if (! $idEventoInscripcion) {
            return 0;
        }
        $where = array(
            'idEventoInscripcion'      => $idEventoInscripcion,
            'idTipoEstatusEventoFecha' => ESTATUS_CLASE_IMPARTIDO,
            'fechaEliminacion'         => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->get_where(TBL_EVENTOFECHA, $where);

        return $query->num_rows ? $query->num_rows : 0;
    }

    /**
     * Obtiene el total de participantes
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idUn     Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTotalParticipantes($idEvento, $idUn, $validaFechasEvento = false)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $datos = array(
            'error'              => 1,
            'mensaje'            => 'Error faltan datos',
            'totalParticipantes' => 0
        );
        $innerFechasEvento = '';

        if ($validaFechasEvento) {
            $innerFechasEvento = 'AND ei.inicioEvento = eu.inicioEvento AND ei.finEvento = eu.finEvento';
        }

        if (!$idEvento or ! $idUn) {
            return $datos;
        }
        DB::connection('crm')->join(TBL_EVENTOINSCRIPCION.' ei', 'eu.idEventoUn = ei.idEventoUn '.$innerFechasEvento.' AND ei.fechaEliminacion="0000-00-00 00:00:00"', 'INNER');
        DB::connection('crm')->join(TBL_EVENTOPARTICIPANTE.' ep', 'ep.idEventoInscripcion = ei.idEventoInscripcion AND ep.fechaEliminacion="0000-00-00 00:00:00"', 'INNER');
        DB::connection('crm')->where('eu.idEvento', $idEvento);
        DB::connection('crm')->where('eu.idUn', $idUn);
        DB::connection('crm')->where('eu.fechaEliminacion', '0000-00-00 00:00:00');

        $query = DB::connection('crm')->select(
            "COUNT(DISTINCT ep.idEventoPartcipante)AS totalParticipantes", false
        )->get(TBL_EVENTOUN.' eu');

        if ($query->num_rows) {
            $datos['error']   = 0;
            $datos['mensaje'] = '';
            $datos['totalParticipantes'] = $query->row()->totalParticipantes;
        }
        return $datos;
    }

    /**
     * Regresa total de sesiones de un evento
     *
     * @param integer $idEventoInscripcion Identificador de eventoinscripcion
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenSesiones($idEventoInscripcion)
    {
        settype($idEventoInscripcion, 'integer');

        $datos = array();
        $datos['error']         = 1;
        $datos['mensaje']       = 'Error, faltan datos';
        $datos['totalSesiones'] = 0;

        if (!$idEventoInscripcion) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';
        $where = array('idEventoInscripcion' => $idEventoInscripcion);
        $query = DB::connection('crm')->select('totalSesiones')->get_where(TBL_EVENTOINSCRIPCION, $where);

        if ($query->num_rows) {
            $datos['totalSesiones'] = $query->row()->totalSesiones;
        }
        return $datos;
    }

    /**
     *  Regresa la capacidad maxima de inscripcion por evento
     *
     * @param integer $tipo
     * @param integer $idEvento
     * @param integer $idUn
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function obtenerCapacidadUn($tipo, $idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        if ($idEvento==0  || $idUn==0 || $tipo==0) {
            return (-1);
        }

        DB::connection('crm')->select('euc.capacidad');
        DB::connection('crm')->from(TBL_EVENTO.' e');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'eu.idEvento=e.idEvento AND eu.fechaEliminacion=\'0000-00-00 00:00:00\'', 'INNER');
        DB::connection('crm')->join(TBL_EVENTOUNCAPACIDAD.' euc', 'euc.idEventoUn=eu.idEventoUn AND euc.activo=1 AND euc.fechaEliminacion=\'0000-00-00 00:00:00\'', 'INNER');
        DB::connection('crm')->where('e.idEvento', $idEvento);
        DB::connection('crm')->where('e.fechaEliminacion', '0000-00-00 00:00:00');
        DB::connection('crm')->where('eu.idUn', $idUn);
        DB::connection('crm')->where('euc.idTipoEventoCapacidad', $tipo);
        $query = DB::connection('crm')->get();
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila["capacidad"];
        } else {
            return (-1);
        }
    }

    /**
     * Obtiene las inscripciones onfromativas pro usuario
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function obtieneInscripcionesInformativas($idPersona, $idEventoUn = 0)
    {
        settype($idPersona, 'integer');
        settype($idEventoUn, 'integer');

        DB::connection('crm')->select('ei.informativo, ei.idEventoInscripcion, ei.idPersona, ei.idEmpleado, pu.idProductoUn');
        DB::connection('crm')->from(TBL_EVENTOINSCRIPCION.' ei');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu',' eu.idEventoUn = ei.idEventoUn and eu.activo = 1 and eu.fechaEliminacion = \'0000-00-00 00:00:00\'','inner');
        DB::connection('crm')->join(TBL_EVENTO.' e',' e.idEvento = eu.idEvento and e.fechaEliminacion = \'0000-00-00 00:00:00\'','inner');
        DB::connection('crm')->join(TBL_PRODUCTO.' p',' p.idProducto = e.idProducto and p.activo = 1 and p.fechaEliminacion = \'0000-00-00 00:00:00\' and (p.permanente = 1 or date(now()) between p.inicioVigencia and p.finVigencia) and p.idTipoProducto=5','inner');
        DB::connection('crm')->join(TBL_PRODUCTOUN.' pu',' pu.idProducto = p.idProducto and pu.idUn = eu.idUn and pu.activo = 1 and pu.fechaEliminacion =  \'0000-00-00 00:00:00\' ','inner');
        if($idPersona > 0){
            DB::connection('crm')->where('ei.idPersona', $idPersona);
        }
        if($idEventoUn > 0){
            DB::connection('crm')->where('ei.idEventoUn', $idEventoUn);
        }
        DB::connection('crm')->where('ei.idTipoEstatusInscripcion <>', '2');
        DB::connection('crm')->where('ei.idTipoEstatusInscripcion <>', '3');
        DB::connection('crm')->where('e.fechaEliminacion', '0000-00-00 00:00:00');
        DB::connection('crm')->where('ei.informativo', 1);
        $query = DB::connection('crm')->get();

        if ($query->num_rows > 0) {
            return $query->result_array();
        }
        return 0;
    }

    /**
     * Obtiene el valor de campo solicitado dentro del catalogo referido
     *
     * @param integer $id    ID del catalogo a procesar
     * @param integer $campo Nombre del campo a devolver
     *
     * @return string
     */
    public function opcionesEventoFecha($id, $campo)
    {
        if (DB::connection('crm')->field_exists($campo, TBL_EVENTOFECHA) == false) {
            return null;
        }

        DB::connection('crm')->select($campo);
        $query = DB::connection('crm')->where("idEventoFecha", $id)->get(TBL_EVENTOFECHA);
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila[$campo];
        }
        return null;
    }

    /**
     * Obtiene el valor de campo solicitado dentro del catalogo referido
     *
     * @param integer $id    ID del catalogo a procesar
     * @param integer $campo Nombre del campo a devolver
     *
     * @return string
     */
    public function opcionesProductoEvento($id, $campo)
    {
        if (DB::connection('crm')->field_exists($campo, TBL_EVENTO) == false) {
            return null;
        }

        DB::connection('crm')->select($campo);
        $query = DB::connection('crm')->where("idProducto", $id)->get(TBL_EVENTO);
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila[$campo];
        }
        return null;
    }


    /**
     * [precioPrimerSemana description]
     *
     * @param  [type] $idProducto [description]
     * @param  [type] $idUn       [description]
     *
     * @return [type]             [description]
     */
    public function precioPrimerSemana($idProducto, $idUn)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        $res = 0;
        
        if ($idProducto<=0 || $idUn<=1) {
            return $res;
        }
        
        $sql = "SELECT euc.capacidad
            FROM producto p
            INNER JOIN evento e ON e.idProducto=p.idProducto
                AND e.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventoun eu ON eu.idEvento=e.idEvento
                AND eu.idUn=$idUn AND eu.activo=1 AND eu.fechaEliminacion='0000-00-00 00:00:00'
            INNER JOIN eventouncapacidad euc ON euc.idEventoUn=eu.idEventoUn
                AND euc.idTipoEventoCapacidad=28 AND euc.activo=1
                AND euc.autorizado=1 AND euc.capacidad>0
            WHERE p.idProducto=$idProducto AND p.activo=1 AND p.fechaEliminacion='0000-00-00 00:00:00'
            ORDER BY euc.idEventoUnCapacidad DESC
            LIMIT 1";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            $res = $query[0]->capacidad;
        }
        
        return $res;
    }

    /**
     * [preventa description]
     *
     * @param  integer $idEvento [description]
     * @param  integer $idUn     [description]
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function preventa($idEvento, $idUn)
    {
        $res = false;

        return $res;
    }

    /**
     * [rangoEdades description]
     *
     * @param  [type] $idEvento [description]
     * @param  [type] $idUn     [description]
     *
     * @return [type]           [description]
     */
    public function rangoEdad($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $datos['edadMinima'] = 0;
        $datos['edadMaxima'] = 0;

        if ($idEvento<=0 || $idUn<=0) {
            return $datos;
        }

        DB::connection('crm')->select('edadMinima, edadMaxima');
        DB::connection('crm')->from(TBL_EVENTOUN);
        DB::connection('crm')->where('idUn', $idUn);
        DB::connection('crm')->where('idEvento', $idEvento);
        DB::connection('crm')->where('activo', 1);
        DB::connection('crm')->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = DB::connection('crm')->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $datos['edadMinima'] = $fila['edadMinima'];
            $datos['edadMaxima'] = $fila['edadMaxima'];
        }

        return $datos;
    }

    /**
     * Replica configuracion general de un club a todos los demas
     *
     * @param integer $idEventoUn Identificador de eventoun
     * @param integer $idEvento   Identificador de evento
     * @param integer $idUn       Identificador de un
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function replicaConfigGeneral($idEventoUn, $idEvento, $idUn)
    {
        settype($idEventoUn, 'integer');
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => '',
            'res'     => false
        );

        if ( ! $idEventoUn or ! $idEvento or ! $idUn) {
            return $datos;
        }
        $datos['error']    = 0;
        $datos['mensaje']  = '';

        $where = array('idEventoUn' => $idEventoUn);
        $query = DB::connection('crm')->select(
            "activo, inicioRegistro, finRegistro, inicioEvento, finEvento, reservarInstalacion, anticipo, edadMinima, edadMaxima", false
        )->get_where(TBL_EVENTOUN, $where);

        if ($query->num_rows) {
            $datosOrigen = $query->row_array();

            if ($datosOrigen) {
                extract($datosOrigen);

                $set = array(
                    'activo'              => $activo,
                    'inicioRegistro'      => $inicioRegistro,
                    'finRegistro'         => $finRegistro,
                    'inicioEvento'        => $inicioEvento,
                    'finEvento'           => $finEvento,
                    'reservarInstalacion' => $reservarInstalacion,
                    'anticipo'            => $anticipo,
                    'edadMinima'          => $edadMinima,
                    'edadMaxima'          => $edadMaxima
                );
                $where = array(
                    'idEvento'         => $idEvento,
                    'fechaEliminacion' => '0000-00-00 00:00:00',
                    'idEventoUn <>'    => $idEventoUn
                );
                $datos['res'] = DB::connection('crm')->update(TBL_EVENTOUN, $set, $where);

                $this->permisos_model->log('Replica configuracion general de idUn='.$idUn.' a todos los demas clubes', LOG_EVENTO);

                $datos['mensaje']  = 'Se replico exitosamente la configuracion en todos los clubes.';

            }
        }
        return $datos;
    }

    /**
     * Replica configuracion de capacidad de un club a todos los demas
     *
     * @param integer $idEventoUn Identificador de eventoun
     * @param integer $idEvento   Identificador de evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function replicaCapacidad($idEventoUn, $idEvento)
    {
        settype($idEventoUn, 'integer');
        settype($idEvento, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => ''
        );
        $datosEventoUnDestino = array();
        $datosOrigen          = array();

        if ( ! $idEventoUn or ! $idEvento) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = 'Se replico exitosamente la configuracion en todos los clubes.';

        $where = array('idEvento' => $idEvento, 'fechaEliminacion' => '0000-00-00 00:00:00', 'idEventoUn <>' => $idEventoUn);
        $query = DB::connection('crm')->select('idEventoUn', false)->get_where(TBL_EVENTOUN, $where);

        $datos['query0'] = DB::connection('crm')->last_query();

        if ($query->num_rows) {
            $datosEventoUnDestino = $query->result_array();

            $datos['datosEventoUnDestino'] = $datosEventoUnDestino;
        }
        $where = array(
            'idEventoUn'       => $idEventoUn,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select(
            "idTipoEventoCapacidad, capacidad, activo, idCategoriaEventoCapacidad ", false
        )->get_where(TBL_EVENTOUNCAPACIDAD, $where);

        $datos['query1'] = DB::connection('crm')->last_query();

        if ($query->num_rows) {

            $datosOrigen = $query->result_array();

            $datos['datosOrigen'] = $datosOrigen;
        }
        foreach ($datosOrigen as $idRow => $datosCapacidad) {

            extract($datosCapacidad);

            foreach ($datosEventoUnDestino as $idRow => $eventoDestino) {
                $where = array(
                    'idEventoUn'            => $eventoDestino['idEventoUn'],
                    'idTipoEventoCapacidad' => $idTipoEventoCapacidad,
                    'fechaEliminacion'      => '0000-00-00 00:00:00'
                );
                $query = DB::connection('crm')->select('idEventoUnCapacidad', false)->get_where(TBL_EVENTOUNCAPACIDAD, $where);

                if ($query->num_rows) {
                    $set   = array('capacidad' => $capacidad, 'activo' => $activo, 'idCategoriaEventoCapacidad' => $idCategoriaEventoCapacidad, 'autorizado' => 0);
                    $where = array('idEventoUnCapacidad' => $query->row()->idEventoUnCapacidad);

                    $res = DB::connection('crm')->update(TBL_EVENTOUNCAPACIDAD, $set, $where);
                } else {
                    $set = array(
                        'idTipoEventoCapacidad'      => $idTipoEventoCapacidad,
                        'idEventoUn'                 => $eventoDestino['idEventoUn'],
                        'capacidad'                  => $capacidad,
                        'activo'                     => $activo,
                        'autorizado'                 => 0,
                        'idCategoriaEventoCapacidad' => $idCategoriaEventoCapacidad
                    );
                    $res = DB::connection('crm')->insert(TBL_EVENTOUNCAPACIDAD, $set);
                }
                if ($datos['error'] == 0 and ! $res) {
                    $datos['error']   = 2;
                    $datos['mensaje'] = 'Error al replicar configuracion de capacidad';
                }
                $datos['query2'][] = DB::connection('crm')->last_query();

                $this->permisos_model->log('Replica configuracion de idTipoEventoCapacidad='.$idTipoEventoCapacidad.' del idEventoUn='.$idEventoUn.' al idEventoUn='.$eventoDestino['idEventoUn'], LOG_EVENTO);
            }
        }
        return $datos;
    }

    /**
     *
     * @param type $idEventoInscripcion
     * @param type $fechaEvento
     * @return type
     */
    public function totalClases($idEventoInscripcion, $fechaEvento)
    {
        $query = DB::connection('crm')->query("SELECT COUNT(*) AS total FROM ".TBL_EVENTOFECHA." WHERE idEventoInscripcion=".$idEventoInscripcion." AND fechaEvento='".$fechaEvento."' AND fechaEliminacion='0000-00-00 00:00:00'");
        $row = $query->row();
        return $row->total;
    }

    /**
     * Obtiene el total de inscripciones registradas dentro del evento indicado
     *
     * @param integer $idEvento
     * @param integer $idUn
     * @param boolean $validaFechasEvento
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function totalInscritos($idEvento = 0, $idUn = 0, $validaFechasEvento = false)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        $innerFechasEvento = '';

        if ($idEvento == 0) {
            return 0;
        }
        if ($validaFechasEvento) {
            $innerFechasEvento = 'AND ei.inicioEvento = eu.inicioEvento AND ei.finEvento = eu.finEvento';
        }
        DB::connection('crm')->from(TBL_EVENTO.' e');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'eu.idEvento = e.idEvento AND eu.fechaEliminacion="0000-00-00 00:00:00"', 'INNER');
        DB::connection('crm')->join(TBL_EVENTOINSCRIPCION.' ei', 'eu.idEventoUn = ei.idEventoUn '.$innerFechasEvento.' AND ei.fechaEliminacion="0000-00-00 00:00:00"', 'INNER');
        DB::connection('crm')->where('e.idEvento', $idEvento);
        DB::connection('crm')->where('eu.idUn', $idUn);

        return DB::connection('crm')->count_all_results();
    }

    /**
     * Obtiene el total de opciones activas en el catalogo indicado
     *
     * @return integer
     */
    public function totalOpciones($evento, $club)
    {
        DB::connection('crm')->select(
            'pr.nombre as producto, p.nombre, p.paterno, p.materno,
            eu.inicioEvento, u.nombre as club, e.idProducto, eu.idEvento'
        );
        DB::connection('crm')->join(TBL_PERSONA.' p', 'ei.idPersona = p.idPersona');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'eu.idEventoUn = ei.idEventoUn');
        DB::connection('crm')->join(TBL_UN.' u', 'u.idUn = eu.idUn');
        DB::connection('crm')->join(TBL_EVENTO.' e', 'e.idEvento = eu.idEvento');
        DB::connection('crm')->join(TBL_PRODUCTO.' pr', 'pr.idProducto = e.idProducto');

        if ($evento<>'') {
            DB::connection('crm')->like('pr.nombre', $evento);
        }
        if ($club<>'' and $club>0) {
            DB::connection('crm')->where('eu.idUn', $club);
        }

        DB::connection('crm')->where('ei.fechaEliminacion', '0000-00-00 00:00:00');
        DB::connection('crm')->where('pr.activo', '1');
        DB::connection('crm')->where('u.activo', '1');
        DB::connection('crm')->where('eu.activo', '1');
        $query = DB::connection('crm')->distinct()->get("eventoinscripcion ei");

        return $query ->num_rows();
    }

    /**
     * Regresar el totoal de participantes registrados en la inscripcion indicada
     *
     * @param  integer $idEventoInscripcion Identificador de inscripcion
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function totalParticipantesXInscripcion($idEventoInscripcion)
    {
        settype($idEventoInscripcion, 'integer');

        DB::connection('crm')->from(TBL_EVENTOPARTICIPANTE);
        DB::connection('crm')->where('idEventoInscripcion', $idEventoInscripcion);
        DB::connection('crm')->where('fechaEliminacion', '0000-00-00 00:00:00');

        return DB::connection('crm')->count_all_results();
    }


    /**
     * Valida si existe registro de categoria en eventouncategoria
     *
     * @param integer $idEventoUn           Identificador de eventoun
     * @param integer $idEventoCategoria    Identificador de categoria
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaConfigCategoria($idEventoUn, $idEventoCategoria)
    {
        settype($idEventoUn, 'integer');
        settype($idEventoCategoria, 'integer');

        $datos = array(
            'idEventoUnCategoria'   => 0,
            'idEventoUn'            => $idEventoUn,
            'idEventoCategoria'     => $idEventoCategoria,
            'activo'                => 0
        );
        if (!$idEventoUn or !$idEventoCategoria) {
            return $datos;
        }
        $where = array(
            'idEventoUn'         => $idEventoUn,
            'idEventoCategoria'  => $idEventoCategoria,
            'fechaEliminacion'   => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select("idEventoUnCategoria, idEventoUn, idEventoCategoria, activo", false
        )->get_where(TBL_EVENTOUNCATEGORIA, $where, 1);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Valida si existe registro de eventounentrega
     *
     * @param integer $idEventoUn Identificador de eventoun
     * @param integer $idUn       Identificador de unidad de negocio
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaConfigClub($idEventoUn, $idUn)
    {
        settype($idEventoUn, 'integer');
        settype($idUn, 'integer');

        $datos = array(
            'idEventoUnEntrega' => 0,
            'idEventoUn'        => $idEventoUn,
            'idUn'              => $idUn,
            'activo'            => 0
        );
        if (!$idEventoUn or !$idUn) {
            return $datos;
        }
        $where = array(
            'idEventoUn'       => $idEventoUn,
            'idUn'             => $idUn,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select("
            idEventoUnEntrega, idEventoUn, idUn, activo", false
        )->get_where(TBL_EVENTOUNENTREGA, $where, 1);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Valida si existe registro de talla en eventoun
     *
     * @param integer $idEventoUn Identificador de eventoun
     * @param integer $idTalla    Identificador de talla
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaConfigTalla($idEventoUn, $idTalla)
    {
        settype($idEventoUn, 'integer');
        settype($idTalla, 'integer');

        $datos = array(
            'idEventoUnTalla' => 0,
            'idEventoUn'      => $idEventoUn,
            'idTalla'         => $idTalla,
            'activo'          => 0
        );
        if (!$idEventoUn or ! $idTalla) {
            return $datos;
        }
        $where = array(
            'idEventoUn'       => $idEventoUn,
            'idTalla'          => $idTalla,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select("
            idEventoUnTalla, idEventoUn, idTalla, activo", false
        )->get_where(TBL_EVENTOUNTALLA, $where, 1);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Valida si la fecha de la clase esta libre
     *
     * @param integer $idEventoInscripcion Identificador de eventoinscripcion
     * @param integer $idEventoFecha Identificador de eventofecha
     * @param string  $fechaEvento    Fecha a validar
     * @param string  $horaEvento     Hora a validar
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function validaDisponibilidadClase($idEventoInscripcion, $idEventoFecha, $fechaEvento, $horaEvento)
    {
        settype($idEventoFecha, 'integer');

        if ($idEventoFecha) {
            DB::connection('crm')->where("idEventoFecha <>", $idEventoFecha);
        }
        DB::connection('crm')->where('idEventoInscripcion', $idEventoInscripcion);
        DB::connection('crm')->where("fechaEvento", $fechaEvento);
        DB::connection('crm')->where("horaEvento", $horaEvento);
        DB::connection('crm')->where("fechaEliminacion", '0000-00-00 00:00:00');
        DB::connection('crm')->where("idTipoEstatusEventoFecha", ESTATUS_CLASE_ASIGNADO);
        $query = DB::connection('crm')->select(
            "idEventoFecha"
        )->get(TBL_EVENTOFECHA);

        return $query->num_rows ? true : false;
    }

    /**
     * Valida la disponibilidad de un instructor para impartir clases personalizadas
     *
     * @param array $datos Arreglo con los datos para filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaDisponibilidadInstructor ($datos = array())
    {
        $ocupado            = array('si' => true, 'horaOcupada' => 0);
        $arrayHorasOcupadas = array();

        if (empty($datos)) {
            return $ocupado;
        }
        extract($datos);
        $ocupado = array('si' => false, 'horaOcupada' => 0);

        DB::connection('crm')->select("idEventoFecha, fechaEvento, horaEvento");
        DB::connection('crm')->where("fechaEliminacion", '0000-00-00 00:00:00');
        DB::connection('crm')->where("idTipoEstatusEventoFecha", ESTATUS_CLASE_ASIGNADO);
        DB::connection('crm')->where("idEmpleado", $idEmpleado);
        DB::connection('crm')->where("fechaEvento", $inicioVigencia);

        if ($id > 0) {
            DB::connection('crm')->where("idEventoFecha <>", $id);
        }
        DB::connection('crm')->having('DAYOFWEEK(fechaEvento)-1', $idTipoDia, false);
        $query = DB::connection('crm')->get(TBL_EVENTOFECHA);

        if ($query->num_rows > 0) {
            foreach ($query->result_object() as $fila) {
                $horaInicioBD    = explode(':', $fila->horaEvento);
                $horaInicioBD[0] = round($horaInicioBD[0]);

                if (! in_array($horaInicioBD[0], $arrayHorasOcupadas)) {
                    $arrayHorasOcupadas[] = $horaInicioBD[0];
                }
            }
            $horaIniPost    = explode(':', $horaInicio);
            $horaIniPost[0] = round($horaIniPost[0]);

            if ((isset($horaFin)) and ($horaFin)) {
                $horaFinPost    = explode(':', $horaFin);
                $horaFinPost[0] = round($horaFinPost[0]);

                for ($i = $horaIniPost[0]; $i <= $horaFinPost[0]; $i++) {
                    if (in_array($i, $arrayHorasOcupadas)) {
                        $ocupado['si']          = true;
                        $ocupado['horaOcupada'] = $i.":00 hrs.";
                        break;
                    }
                }
            } else {
                if (in_array($horaIniPost[0], $arrayHorasOcupadas)) {
                    $ocupado['si']          = true;
                    $ocupado['horaOcupada'] = $horaIniPost[0].":00 hrs.";
                }
            }
        }
        return $ocupado;
    }

    /**
     * Valida si la edad de un participante es la adecuada para el evento
     *
     * @param integer $idPersona  Identificador de persona
     * @param integer $edadMinima Edad minima a validar
     * @param integer $edadMaxima Edad maxima a validar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaEdadParticipante ($idPersona, $edadMinima, $edadMaxima)
    {
        settype($idPersona, 'integer');
        settype($edadMinima, 'integer');
        settype($edadMaxima, 'integer');

        $datos            = array();
        $datos['mensaje'] = 'Imposible validar participante, falta información';
        $datos['error']   = 1;

        if (! $idPersona) {
            return $datos;
        }
        $datos['mensaje'] = 'El participante no cumple con la edad requerida.';
        $datos['error']   = 2;

        $CI =& get_instance();
        $CI->load->model('persona_model');

        if ($edadMinima and $edadMaxima) {
            $edadMaxima++;

            $datosPersona = $CI->persona_model->datosGenerales($idPersona);
            $fechaNac     = $datosPersona['fecha'];
            $fechaActual  = date('Y-m-d');
            $fechaMinima  = date('Y-m-d', strtotime($fechaActual.' -'.$edadMaxima.' year'));
            $fechaMinima  = date('Y-m-d', strtotime($fechaMinima.' +1 day'));
            $fechaMaxima  = date('Y-m-d', strtotime($fechaActual.' -'.$edadMinima.' year'));

            $fechaNac    = strtotime($fechaNac);
            $fechaMinima = strtotime($fechaMinima);
            $fechaMaxima = strtotime($fechaMaxima);

            if (($fechaNac >= $fechaMinima) and ($fechaNac <= $fechaMaxima)) {
                $datos['mensaje'] = 'Participante valido.';
                $datos['error']   = 0;
            }
        } elseif (($edadMinima) and (! $edadMaxima)) {
            $datosPersona = $CI->persona_model->datosGenerales($idPersona);
            $fechaNac     = $datosPersona['fecha'];
            $fechaActual  = date('Y-m-d');
            $fechaMaxima  = date('Y-m-d', strtotime($fechaActual.' -'.$edadMinima.' year'));

            $fechaNac    = strtotime($fechaNac);
            $fechaMaxima = strtotime($fechaMaxima);

            if (($fechaNac <= $fechaMaxima)) {
                $datos['mensaje'] = 'Participante valido.';
                $datos['error']   = 0;
            }
        } elseif ((! $edadMinima) and ($edadMaxima)) {
            $edadMaxima++;

            $datosPersona = $CI->persona_model->datosGenerales($idPersona);
            $fechaNac = $datosPersona['fecha'];
            $fechaActual = date('Y-m-d');
            $fechaMinima = date('Y-m-d', strtotime($fechaActual.' -'.$edadMaxima.' year'));
            $fechaMinima = date('Y-m-d', strtotime($fechaMinima.' +1 day'));

            $fechaNac    = strtotime($fechaNac);
            $fechaMinima = strtotime($fechaMinima);

            if ($fechaNac >= $fechaMinima) {
                $datos['mensaje'] = 'Participante valido.';
                $datos['error']   = 0;
            }
        } else {
            $datos['mensaje'] = 'Participante valido.';
            $datos['error']   = 0;
        }
        return $datos;
    }

    /**
     * Valida si existe registro de capacidad en evento
     *
     * @param integer $idTipoEventoCapacidad Identificador de tipoeventocapacidad
     * @param integer $idEventoUn            Identificador de eventoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaEventoCapacidadUn($idTipoEventoCapacidad, $idEventoUn)
    {
        settype($idTipoEventoCapacidad, 'integer');
        settype($idEventoUn, 'integer');
        $datos = array();

        if (! $idTipoEventoCapacidad or ! $idEventoUn) {
            return $datos;
        }
        $where = array(
            'idTipoEventoCapacidad' => $idTipoEventoCapacidad,
            'idEventoUn'            => $idEventoUn,
            'fechaEliminacion'      => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select(
            "idEventoUnCapacidad, activo", false
        )->get_where(TBL_EVENTOUNCAPACIDAD, $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Valida si existe relacion de producto con evento
     *
     * @param integer $idProducto Identificador de producto
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function validaEventoProducto ($idProducto)
    {
        settype($idProducto, 'integer');
        $idEvento = 0;

        if (! $idProducto) {
            return $idEvento;
        }
        $where = array(
            'idProducto'       => $idProducto,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->select(
            "idEvento", false
        )->get_where(TBL_EVENTO, $where);

        if ($query->num_rows) {
            $idEvento = $query->row()->idEvento;
        }
        return $idEvento;
    }

    /**
     * Valida identificador repetido de url de evento
     *
     * @param integer $idEvento Identificador de evento
     * @param integer $idURL    Identificador de URL de evento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaIdURL($idURL)
    {
        settype($idURL, 'string');

        $datos = array(
            'error'     => 1,
            'mensaje'   => 'Error faltan datos',
            'registros' => 0
        );
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $where = array(
            'idURL'            => $idURL,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = DB::connection('crm')->get_where(TBL_EVENTO, $where);
        $datos['query'] = DB::connection('crm')->last_query();
        if ($idURL == '') {
            $datos['registros'] = 0;
        } else {
            $datos['registros'] = $query->num_rows;
        }
        return $datos;
    }

    /**
     * [validaNoInscripcionCarrera description]
     * @param  [type] $idEvento   [description]
     * @param  [type] $idPersona  [description]
     * @param  [type] $idProducto [description]
     *
     * @author Antonio Sixtos
     *
     * @return [type]             [description]
     */
    public function validaNoInscripcionCarrera($idEvento, $idPersona, $idProducto)
    {
        settype($idEvento, 'integer');
        settype($idPersona, 'integer');
        settype($idProducto, 'integer');

        $sql="SELECT COUNT(ep.idEventoPartcipante) AS resultado
            FROM crm.eventoparticipante ep
            INNER JOIN crm.eventoinscripcion ei ON ei.idEventoInscripcion=ep.idEventoInscripcion
            INNER JOIN crm.eventomovimiento em ON em.idEventoInscripcion=ei.idEventoInscripcion
            INNER JOIN crm.movimiento mov ON mov.idMovimiento=em.idMovimiento AND mov.idTipoEstatusMovimiento IN (65,66) AND mov.idProducto IN (".$idProducto.")
            #INNER JOIN crm.facturamovimiento fm ON fm.idMovimiento=mov.idMovimiento
            WHERE ep.idPersona IN (".$idPersona.") AND ep.fechaEliminacion='0000-00-00 00:00:00'";
        $query = DB::connection('crm')->query($sql);

        $row = $query->row();
        return $row->resultado;
    }

    /**
     * [validaNombreEquipo description]
     * @param  [type]  $nombreEquipo        [description]
     * @param  [type]  $idEvento            [description]
     * @param  integer $idEventoInscripcion [description]
     *
     * @author Antonio Sixtos
     *
     * @return [type]                       [description]
     */
    public function validaNombreEquipo($nombreEquipo, $idEvento, $idEventoInscripcion=0)
    {
        $data = array();

        $sql="SELECT COUNT(ep.idEventoPartcipante) AS resultado
                FROM crm.eventoparticipante ep
                INNER JOIN crm.eventoinscripcion ei ON ei.idEventoInscripcion=ep.idEventoInscripcion AND ei.idPersona=ep.idPersona";

        if ($idEventoInscripcion>0) {
            $sql.=" AND ei.idEventoInscripcion NOT IN (".$idEventoInscripcion.")";
        }
        $sql.=" INNER JOIN crm.eventoun eu ON eu.idEventoUn=ei.idEventoUn
                INNER JOIN crm.evento e ON e.idEvento=eu.idEvento AND e.idEvento IN (".$idEvento.")
                WHERE ep.nombreEquipo = '".$nombreEquipo."'";
        $query = DB::connection('crm')->query($sql);

        $row = $query->row();
        return $row->resultado;
    }

    /**
     * Valida si una personas ya ha participado en enventos
     *
     * @param integer $idPersona Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return boolen
     */
    public function validaReinisidencia ($idPersona)
    {
        settype($idPersona, 'integer');
        $res = false;

        if (!$idPersona) {
            return $res;
        }
        $where = array(
            'ei.fechaEliminacion' => '0000-00-00 00:00:00',
            'ep.fechaEliminacion' => '0000-00-00 00:00:00',
            'ei.idTipoEstatusInscripcion' => EVENTO_INSCRIPCION_IMPARTIDA
        );
        DB::connection('crm')->join(TBL_EVENTOPARTICIPANTE.' ep', 'ep.idEventoInscripcion = ei.idEventoInscripcion', 'inner');
        DB::connection('crm')->where('(ei.idPersona = '.$idPersona.' OR ep.idPersona = '.$idPersona.')');
        $query = DB::connection('crm')->get_where(TBL_EVENTOINSCRIPCION.' ei', $where);

        if ($query->num_rows) {
            $res = true;
        }
        return $res;
    }

    /**
     * Obtiene los datos de un inscrito al evento
     *
     * @param integer $idUn       Identificador de unidad de negocio
     * @param integer $totales    Bandera para regresar total de registros
     * @param integer $elementos  Total de elementos a regresar
     * @param integer $posicion   Posicion desde donde empiezan los registros a regresar
     * @param integer $orden      Orden de los registros
     * @param integer $direction  Direccion de los registros
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function vencidos ($idUn = 0, $totales = false, $registros = null, $posicion = null, $orden = 'eu.finEvento', $direction = 'ASC')
    {
        settype($idUn, 'integer');

        if ($totales) {
            $datos = 0;
        } else {
            $datos = array();
        }
        $estatusInscripcion = array(EVENTO_INSCRIPCION_IMPARTIDA, EVENTO_INSCRIPCION_CANCELADA);
        DB::connection('crm')->select(
            "p.idPersona, CONCAT_WS(' ',p.nombre, p.paterno, p.materno)AS nombre, pr.nombre as producto, ei.totalSesiones,
            (
                SELECT euc.capacidad
                FROM ".TBL_EVENTOUNCAPACIDAD." euc
                WHERE euc.idTipoEventoCapacidad = ".EVENTO_CAPACIDAD_NUMEROCLASES."
                AND euc.idEventoUn = eu.idEventoUn AND euc.activo = 1 AND euc.fechaEliminacion = '0000-00-00 00:00:00'
            ) AS maximoClases,
            DATE_ADD(f.fecha, INTERVAL 6 MONTH) AS finEvento, tei.descripcion AS estatus,
            f.fecha as fechaPago, tem.descripcion AS estatusMovimiento", false
        );
        DB::connection('crm')->join(TBL_PERSONA.' p', 'ei.idPersona=p.idPersona');
        DB::connection('crm')->join(TBL_EVENTOUN.' eu', 'ei.idEventoUn=eu.idEventoUn');
        DB::connection('crm')->join(TBL_EVENTO.' ev', 'ev.idEvento=eu.idEvento');
        DB::connection('crm')->join(TBL_UN.' un', 'un.idUn=eu.idUn');
        DB::connection('crm')->join(TBL_PRODUCTO.' pr', 'pr.idProducto=ev.idProducto');
        DB::connection('crm')->join(TBL_TIPOEVENTO.' te', 'ev.idTipoEvento = te.idTipoEvento');
        DB::connection('crm')->join(TBL_TIPOESTATUSINSCRIPCION.' tei', 'ei.idTipoEstatusInscripcion= tei.idTipoEstatusInscripcion');
        DB::connection('crm')->join(TBL_EVENTOMOVIMIENTO." em", 'em.idEventoInscripcion = ei.idEventoInscripcion');
        DB::connection('crm')->join(TBL_MOVIMIENTO." m", 'm.idMovimiento = em.idMovimiento');
        DB::connection('crm')->join(TBL_TIPOESTATUSMOVIMIENTO." tem", 'tem.idTipoEstatusMovimiento = m.idTipoEstatusMovimiento');
        DB::connection('crm')->join(TBL_FACTURAMOVIMIENTO." fm", 'fm.idMovimiento = m.idMovimiento');
        DB::connection('crm')->join(TBL_FACTURA." f", 'f.idFactura = fm.idFactura');
        DB::connection('crm')->where("eu.finEvento <> '0000-00-00 00:00:00'");
        DB::connection('crm')->where('te.idTipoEvento', EVENTO_CLASESPERSONALIZADAS);
        DB::connection('crm')->where('m.idTipoEstatusMovimiento', MOVIMIENTO_PAGADO);
        DB::connection('crm')->where('f.idTipoEstatusFactura', ESTATUS_FACTURA_PAGADA);
        DB::connection('crm')->where_not_in('tei.idTipoEstatusInscripcion', $estatusInscripcion);
        DB::connection('crm')->having('YEAR(finEvento) = YEAR(NOW())');
        DB::connection('crm')->having('MONTH(finEvento) = MONTH(NOW())');

        if ($idUn) {
            DB::connection('crm')->where('eu.idUn', $idUn);
        }
        if ($orden) {
            DB::connection('crm')->order_by($orden, $direction);
        }
        $query = DB::connection('crm')->get(TBL_EVENTOINSCRIPCION.' ei', $registros, $posicion);

        if ($query->num_rows) {
            if ($totales) {
                $datos = $query->num_rows;
            } else {
                $datos = $query->result_array();
            }
        }
        return $datos;
    }

    public function esEventoSafeSplash($idEvento){
        $query = DB::connection('crm')->from('evento e')
                ->join('crm.eventoun eu','e.idEvento=eu.idEvento')
                ->join('crm.eventouncapacidad euc','euc.idEventoUn=eu.idEventoUn and euc.idTipoEventoCapacidad = 25')
                ->where('e.idEvento',$idEvento)
                ->get();
        if($query->num_rows() > 0){
            return true;
        }else{
            return false;
        }
    }

     /**
     * Verifica Estado de Autorizacion
     *
     * @param integer $idEventoUn Identificador de eventoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function VerificaautoriazaConfigCapacidad($idEventoUnCapacidad)
    {
        settype($idEventoUnCapacidad, 'integer');


        $where = array(
            'idEventoUnCapacidad'       => $idEventoUnCapacidad,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );

        $query = DB::connection('crm')->select(
            "autorizado",0
        )->get_where(TBL_EVENTOUNCAPACIDAD, $where);

        if ($query->num_rows) {
            $autorizado = $query->row()->autorizado;
        }
        return $autorizado;
/*




        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al autorizar configuracion de capacidad';


        $set = array('autorizado' => 1);
        $where = array(
            'idEventoUn'       => $idEventoUn,
            'autorizado'       => 0,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );


        if ($datos['res'] = DB::connection('crm')->update(TBL_EVENTOUNCAPACIDAD, $set, $where)) {
            $datos['error']   = 0;
            $datos['mensaje'] = 'Se autorizo exitosamente la configuracion';

            $this->permisos_model->log('Autoriza configuracion de idEventoUn='.$idEventoUn, LOG_EVENTO);
        }
        return $datos;
*/
    }

}
