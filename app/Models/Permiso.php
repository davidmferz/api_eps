<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Permiso extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.permisopuesto';
    protected $primaryKey = 'IdPermisoPuesto ';

    /**
     * Constructor de la clase Permisos_model
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     *
     *
     * @param integer $persona Identificador de perosna
     * @param string  $nombre  Nombre de la persona
     * @param string  $usuario Nombre de usurio
     * @param string  $puesto  Nombre del puesto
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function busquedaUsuarios($persona = 0, $nombre = '', $usuario = '', $puesto = '')
    {
        settype($persona, 'integer');

        $nombre  = trim($nombre);
        $puesto  = trim($puesto);
        $usuario = trim($usuario);

        $w_operador = '';
        if ($this->session->userdata('idOperador') != 1) {
            $w_operador = " AND u1.idOperador=" . $this->session->userdata('idOperador');
        }

        $w_persona = '';
        if ($persona > 0) {
            $w_persona = " AND u.idPersona=" . $persona . " ";
        }
        $w_nombre = '';
        if ($nombre != '') {
            $nombre = $this->db->escape_like_str($nombre);
            $nombre = htmlspecialchars($nombre);

            if ($nombre != '') {
                $w_nombre = " AND CONCAT_WS(' ', p.nombre, p.paterno,p.materno) LIKE '%" . $nombre . "%' ";
            }
        }
        $w_usuario = '';
        if ($usuario != '') {
            $usuario = $this->db->escape_like_str($usuario);
            $usuario = htmlspecialchars($usuario);

            if ($usuario != '') {
                $w_usuario = " AND u.NombreUsuario LIKE '%" . $usuario . "%' ";
            }
        }
        $w_puesto = '';
        if ($puesto != '') {
            $puesto = $this->db->escape_like_str($puesto);
            $puesto = htmlspecialchars($puesto);

            if ($puesto != '') {
                $w_puesto = " AND pu.descripcion LIKE '%" . $puesto . "%' ";
            }
        }

        $data = array();
        $sql  = "SELECT u.idUsuario, p.nombre, p.paterno, p.materno, u.NombreUsuario AS usuario, pu.descripcion AS puesto
            FROM usuarios u
            INNER JOIN persona p on p.idPersona=u.idPersona AND p.fechaEliminacion = '0000-00-00 00:00:00'
            LEFT JOIN un u1 ON u1.idUn=u.idUn
            LEFT JOIN empleado e on e.idEmpleado=u.idEmpleado AND e.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN empleadopuesto ep on ep.idEmpleado=e.idEmpleado AND ep.fechaEliminacion = '0000-00-00 00:00:00'
            LEFT JOIN puesto pu on pu.idPuesto = ep.idPuesto AND pu.fechaEliminacion = '0000-00-00 00:00:00'
            WHERE u.estatus=1 AND u.fechaEliminacion = '0000-00-00 00:00:00' $w_persona $w_nombre $w_usuario $w_puesto $w_operador";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            return $query->result_array();
        }

        return $data;
    }

    /**
     * Busca en la BD si ya existe registro de permiso para el usuario y objeto
     *
     * @param int $idUsuario id del usuario a buscar
     * @param int $idObjeto  id del objeto a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function validaPermisoUsuario($idUsuario = 0, $idObjeto = 0)
    {
        settype($idObjeto, 'integer');
        settype($idUsuario, 'integer');
        $permiso = false;

        $this->db->where('idUsuario', $idUsuario);
        $this->db->where('idObjeto', $idObjeto);
        $this->db->where('estatus', '1');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $queryBusqueda = $this->db->get(self::TABLAUSUARIOSPERMISOS);

        if ($queryBusqueda->num_rows > 0) {
            $permiso = true;
        }

        return $permiso;
    }

    /**
     * Lista las validaciones de usuario por club
     *
     * @param int $idUsuario id del usuario a buscar
     * @param int $idUn  id del Un a buscar
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function validaPermisoUsuarioClub($idUsuario, $idUn)
    {
        settype($idUsuario, 'integer');
        settype($idUn, 'integer');

        $this->db->select('activo');
        $this->db->from(TBL_PERMISO_UN);
        $this->db->where('idUsuario', $idUsuario);
        $this->db->where('idUn', $idUn);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        //echo '<pre>'.$this->db->last_query().'</pre>';
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            return $fila['activo'];
        }
        return 0;
    }

    /**
     * Busca en la BD si ya existe registro de permiso para el puesto y objeto
     *
     * @param int $idPuesto id de el puesto a buscar
     * @param int $idObjeto id de el objeto a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function validaPermisoPuesto($idPuesto = 0, $idObjeto = 0)
    {
        $checked = '';

        if (($idObjeto == 0) or ($idPuesto == 0)) {
            return $checked;
        }

        $this->db->where('idPuesto', $idPuesto);
        $this->db->where('idObjeto', $idObjeto);
        $this->db->where('estatus', '1');
        $queryBusqueda = $this->db->get(self::TABLAPUESTOSPERMISOS);

        if ($queryBusqueda->num_rows > 0) {
            $checked = 'checked';
        }
        return $checked;
    }

    /**
     * Actualiza el estatus del permiso por puesto dentro de la BD
     *
     * @param integer $puesto  Identificador del puesto
     * @param integer $objeto  Identificador del objeto
     * @param string  $estatus Estatus del permiso 1 - Activo, 0 - Inactivo
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function guardarPorPuesto($puesto = 0, $objeto = 0, $estatus = 0)
    {
        settype($puesto, 'integer');
        settype($objeto, 'integer');
        settype($estatus, 'integer');

        $resultado = false;

        if (($puesto == 0) or ($objeto == 0)) {
            return $resultado;
        }

        $this->db->select('idPermisoPuesto');
        $this->db->where('idPuesto', $puesto);
        $this->db->where('idObjeto', $objeto);
        $queryBusqueda = $this->db->get(self::TABLAPUESTOSPERMISOS);

        $datos = array(
            'idPuesto' => $puesto,
            'idObjeto' => $objeto,
            'estatus'  => $estatus,
        );

        if ($queryBusqueda->num_rows == 0) {
            $resultado = $this->db->insert(self::TABLAPUESTOSPERMISOS, $datos);
            $this->permisos_model->log('Inserta nuevo permiso por puesto', self::LOG_PERMISOS);
        } else {
            $id = $queryBusqueda->row()->idPermisoPuesto;

            $this->db->where('idPermisoPuesto', $id);
            $this->db->update(self::TABLAPUESTOSPERMISOS, $datos);
            $this->permisos_model->log('Actualizo permiso por puesto', self::LOG_PERMISOS);
        }

        return $resultado;
    }

    /**
     * Actualiza el estatus del permiso por usuario dentro de la BD
     *
     * @param integer $usuario Identificador del usuario
     * @param integer $objeto  Identificador del objeto
     * @param string  $estatus Estatus del permiso 1 - Activo, 0 - Inactivo
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function guardarPorUsuario($usuario = 0, $objeto = 0, $estatus = 0)
    {
        settype($usuario, 'integer');
        settype($objeto, 'integer');
        settype($estatus, 'integer');

        $resultado = false;

        if ($usuario == 0 or $objeto == 0) {
            return $resultado;
        }

        $this->db->select('idPermisoUsuario');
        $this->db->where('idUsuario', $usuario);
        $this->db->where('idObjeto', $objeto);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $queryBusqueda = $this->db->get(self::TABLAUSUARIOSPERMISOS);

        $datos = array(
            'idUsuario' => $usuario,
            'idObjeto'  => $objeto,
            'estatus'   => $estatus,
        );

        if ($queryBusqueda->num_rows == 0) {
            $resultado = $this->db->insert(self::TABLAUSUARIOSPERMISOS, $datos);
            $this->permisos_model->log('Inserta nuevo permiso usuario', self::LOG_PERMISOS);
        } else {
            $id = $queryBusqueda->row()->idPermisoUsuario;
            $this->db->where('idPermisoUsuario', $id);
            $this->db->update(self::TABLAUSUARIOSPERMISOS, $datos);
            $this->permisos_model->log('Actualizo permiso de usuario', self::LOG_PERMISOS);
        }

        return $resultado;
    }

    /**
     * Valida permisos, si el usuario es admin se otorga el permiso,
     * si no se valida si se trata de un menu y si tiene submenus autorizados,
     * si es asi se da el permiso y si no es menu se busca en submenus si
     * esta autorizado o no
     *
     * @param integer $idObjeto Id del objeto a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function validaTodosPermisos($idObjeto = 0)
    {
        settype($idObjeto, 'integer');
        $pase = false;

        if (!$idObjeto) {
            return $pase;
        }
        $idPuesto  = $this->session->userdata('idPuesto');
        $idUsuario = $this->session->userdata('idUsuario');

        $esMenu = $this->objeto_model->verificaEsMenu($idObjeto);

        if ($esMenu) {
            $pase = $this->objeto_model->verificaHijosConPermiso($idPuesto, $idUsuario, $idObjeto);
        } else {
            if (in_array($idObjeto, $this->session->userdata('permisos')) ||
                in_array($this->_superUsuario, $this->session->userdata('permisos'))) {
                $pase = true;
            } else {
                $pase = false;
            }
        }

        return $pase;
    }

    /**
     * Agrega un registro al log de acciones
     *
     * @param string  $accion       Descripcion de la accion realizada
     * @param integer $categoriaLog Identificador de la categoria de log
     * @param integer $membresia    Identificador de membresia afectada
     * @param integer $club         Identificador del club al cual pertenece la membresia
     * @param integer $regresaId    Bandera que sirve para indicar si se requiere que se regrese el id modificado con anterioridad
     * @param integer $idProducto   Identificador del producto que es afectado por cambio de datos
     *
     * @author Jorge Cruz
     *
     * @return void
     */
    public static function log($accion, $categoriaLog, $membresia = 0, $persona = 0, $regresaId = false, $idProducto = 0, $idUsuario = 0)
    {
        // $str = $this->db->last_query();
        $str = '';

        //Vaalidamos que el query sea menor a 2000 caracterecteres
        if (strlen($str) > 2000) {
            $cadena_aux = substr($cadena, 1, 2000);
            //se limita el query a 2000 caracteres
            $str = htmlentities($cadena);
        }

        if ($idUsuario == 0) {
            $idUsuario = session('idUsuario');
        }
        $idPersona = session('idPersona');
        $idUn      = session('idUn');

        $query = DB::connection('crm')
            ->table(TBL_LOGCATEGORIA)
            ->where('idLogCategoria', $categoriaLog)
            ->get();
        $total = count($query);
        if ($total == 0) {
            return false;
        }

        settype($categoriaLog, 'integer');
        settype($idUsuario, 'integer');
        settype($idPersona, 'integer');
        settype($persona, 'integer');
        settype($membresia, 'integer');
        settype($idUn, 'integer');
        settype($idProducto, 'integer');

        $datos = array(
            'idLogCategoria'   => $categoriaLog,
            'idUsuario'        => $idUsuario,
            'idPersona'        => $idPersona,
            'idPersonaAplica'  => $persona,
            'descripcion'      => $accion,
            'idUnicoMembresia' => $membresia,
            'idUn'             => $idUn,
            'query'            => $str,
            'idProducto'       => $idProducto,
        );

        $id = DB::connection('crm')->table(TBL_LOG)
            ->insertGetId($datos);

        if ($regresaId == true) {
            return $id;
        } else {
            return true;
        }
    }

    /**
     * Valida si el usuario es admin
     *
     * @param integer $idUsuario Id del usuario a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function validaAdmin($idUsuario = 0)
    {
        $admin = false;

        if ($idUsuario == 0) {
            return $admin;
        }

        $this->db->select('idUsuario');
        $this->db->where('idUsuario', $idUsuario);
        $this->db->where('NombreUsuario', 'admin');
        $queryAdmin = $this->db->get(self::TABLAUSUARIOS);

        if ($queryAdmin->num_rows > 0) {
            $admin = true;
        }
        return $admin;
    }

    /**
     * Obtiene puesto de usuario con el id de Usuario
     *
     * @param integer $idUsuario Id del usuario a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenPuestoUsuario($idUsuario = 0)
    {
        $data = array();

        if ($idUsuario == 0) {
            return $data;
        }

        $idEmpleado = $this->obtenNumeroEmpleado($idUsuario);

        $this->db->select('p.idPuesto, p.descripcion');
        $this->db->from(TBL_EMPLEADO . ' e');
        $this->db->join(TBL_EMPLEADOPUESTO . ' ep', 'e.idEmpleado=ep.idEmpleado', 'INNER');
        $this->db->join(TBL_PUESTO . ' p', 'ep.idPuesto=p.idPuesto', 'INNER');
        $this->db->where('e.idEmpleado', $idEmpleado);
        $this->db->where('e.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('e.idTipoEstatusEmpleado', 196);
        $this->db->where('ep.fechaEliminacion', '0000-00-00 00:00:00');
        $queryPuesto = $this->db->get();

        if ($queryPuesto->num_rows > 0) {
            $data['idPuesto']     = $queryPuesto->row()->idPuesto;
            $data['nombrePuesto'] = $queryPuesto->row()->descripcion;
        } else {
            $data['idPuesto']     = 0;
            $data['nombrePuesto'] = 'NA';
        }
        return $data;
    }

    /**
     * Obtiene numero de empleado con el id de Usuario
     *
     * @param integer $idUsuario Id del usuario a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function obtenNumeroEmpleado($idUsuario = 0)
    {
        $idEmpleado = 0;

        if ($idUsuario == 0) {
            return $idEmpleado;
        }

        $this->db->select('idEmpleado');
        $this->db->where('idUsuario', $idUsuario);
        $queryEmpleado = $this->db->get(self::TABLAUSUARIOS);

        if ($queryEmpleado->num_rows > 0) {
            $idEmpleado = $queryEmpleado->row()->idEmpleado;
        }
        return $idEmpleado;
    }

    /**
     * Obtiene usarios activos en el siac
     *
     * @param
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    public function usuariosAuto($nombre = "", $numeroRegistros = 25, $idPersona = 0)
    {
        settype($numeroRegistros, 'integer');
        settype($$idPersona, 'integer');

        $w_operador = '';
        if ($this->session->userdata('idOperador') != 1) {
            $w_operador = ' AND u1.idOperador=' . $this->session->userdata('idOperador');
        }
        $nombre = $this->db->escape($nombre);
        $nombre = substr($nombre, 0, -1);
        $nombre = substr($nombre, 1);

        $sql = "SELECT u.idUsuario, p.nombre, p.materno, p.paterno, p.idPersona
            FROM usuarios u
            LEFT JOIN un u1 ON u1.idUn=u.idUn
            LEFT JOIN persona p ON u.idPersona=p.idPersona
            WHERE u.estatus=1 AND u.fechaEliminacion='0000-00-00 00:00:00' AND
                CONCAT(p.nombre,' ', p.paterno,' ', p.materno) LIKE '%" . $nombre . "%' $w_operador
            ORDER BY p.nombre, p.paterno, p.materno";
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    public function puestoAuto($nombre = "", $numeroRegistros = 25, $idPersona = 0)
    {
        $this->db->select('idPuesto, descripcion, codigo');
        $this->db->from(TBL_PUESTO);
        $this->db->like('descripcion', $nombre);
        $this->db->order_by('descripcion');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Actualiza el estatus del permiso por usuario por club
     *
     * @param integer $usuario Identificador del usuario
     * @param integer $objeto  Identificador del objeto
     * @param string  $estatus Estatus del permiso 1 - Activo, 0 - Inactivo
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function guardarPorUsuarioClub($usuario = 0, $objeto = 0, $estatus = 0)
    {
        settype($usuario, 'integer');
        settype($objeto, 'integer');
        settype($estatus, 'integer');

        $resultado = false;

        if (($usuario == 0) or ($objeto == 0)) {
            return $resultado;
        }

        $this->db->select('idPermisoUn');
        $this->db->where('idUsuario', $usuario);
        $this->db->where('idUn', $objeto);
        $queryBusqueda = $this->db->get(TBL_PERMISO_UN);
        echo $this->db->last_query() . "<br />";
        $datos = array(
            'idUsuario' => $usuario,
            'idUn'      => $objeto,
            'activo'    => $estatus,
        );

        if ($queryBusqueda->num_rows == 0) {
            $resultado = $this->db->insert(TBL_PERMISO_UN, $datos);
            echo $this->db->last_query() . "<br />";
            $this->permisos_model->log('Inserta nuevo permiso usuario club', self::LOG_PERMISOS);
        } else {
            $id = $queryBusqueda->row()->idPermisoUn;

            $this->db->where('idPermisoUn', $id);
            $this->db->update(TBL_PERMISO_UN, $datos);
            echo $this->db->last_query() . "<br />";
            $this->permisos_model->log('Actualizo permiso de usuario club', self::LOG_PERMISOS);
        }

        return $resultado;
    }

    /**
     * lista los clubes por permiso
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function listaActivosXpermiso($idEmpresa = 0, $todos = false, $allocation = 0, $admonCentral = 0)
    {
        $lista      = array();
        $lista['0'] = '';

        $ci = &get_instance();
        $ci->load->model('un_model');

        if ($todos == true && $idEmpresa > 0) {
            $this->db->cache_on();
            $sql = "
                (
                select p.idUn, u.nombre as club, u.orden
                from " . TBL_PERMISO_UN . " p
                inner join " . TBL_UN . " u on p.idUn = u.idUn
                where u.activo = 1 and p.activo = 1 and p.idUsuario=" . $this->session->userdata('idUsuario') . "
                and  u.idEmpresa = " . $idEmpresa . " and p.fechaEliminacion = '0000-00-00 00:00:00'
                )
                UNION DISTINCT
                (
                select ug.idUn, u.nombre as club, u.orden
                from " . TBL_UNGERENTE . " ug
                inner join " . TBL_UN . " u on ug.idUn = u.idUn
                where u.activo = 1 and ug.fechaEliminacion = '0000-00-00 00:00:00' and ug.idPersona=" . $this->session->userdata('idPersona') . "
                )
                UNION DISTINCT
                (
                SELECT us.idUn, u.nombre AS club, u.orden
                FROM " . TBL_USUARIOS . " us
                INNER JOIN " . TBL_UN . " u ON us.idUn = u.idUn
                WHERE us.IdUsuario = " . $this->session->userdata('idUsuario') . "
                AND us.fechaEliminacion = '0000-00-00 00:00:00'
                )
                order by orden;";
            $query = $this->db->query($sql);
            $this->db->cache_off();

            if ($query->num_rows > 0) {
                foreach ($query->result() as $fila) {
                    $lista[$fila->idUn] = 'Todos';
                }
            }
        }
        $empresa  = $ci->un_model->obtenerEmpresa($this->session->userdata('idUn'));
        $idTipoUn = $ci->un_model->obtenDatosUn($this->session->userdata('idUn'));
        if ($idTipoUn['idTipoUn'] != 1) {
            $this->db->cache_on();
            $a = '';
            if ($idEmpresa > 0) {
                $a = " and u.idEmpresa =" . $idEmpresa;
            }

            $sql = "
                (
                select p.idUn, u.nombre as club, u.orden from " . TBL_PERMISO_UN . " p
                inner join " . TBL_UN . " u on p.idUn = u.idUn
                where u.activo = 1 and p.activo = 1 and p.idUsuario=" . $this->session->userdata('idUsuario') . "
                $a  and u.idTipoUn <> 1 and p.fechaEliminacion = '0000-00-00 00:00:00'
                )
                UNION DISTINCT
                (
                select ug.idUn, u.nombre as club, u.orden from " . TBL_UNGERENTE . " ug
                inner join " . TBL_UN . " u on ug.idUn = u.idUn
                where u.activo = 1 and ug.fechaEliminacion = '0000-00-00 00:00:00' and ug.idPersona=" . $this->session->userdata('idPersona') . "
                )
                UNION DISTINCT
                (
                SELECT us.idUn, u.nombre AS club, u.orden
                FROM " . TBL_USUARIOS . " us
                INNER JOIN " . TBL_UN . " u ON us.idUn = u.idUn
                WHERE us.IdUsuario = " . $this->session->userdata('idUsuario') . "
                AND us.fechaEliminacion = '0000-00-00 00:00:00'
                )
                order by orden;";
            $query = $this->db->query($sql);

            $this->db->cache_off();
            if ($query->num_rows > 0) {
                foreach ($query->result() as $fila) {
                    $lista[$fila->idUn] = $fila->club;
                }
                if (isset($lista[$this->session->userdata('idUn')])) {
                } else {
                    if ($empresa == $idEmpresa) {
                        $lista[$this->session->userdata('idUn')] = $ci->un_model->nombre($this->session->userdata('idUn'));
                    }
                }
            } else {
                if ($empresa == $idEmpresa) {
                    $lista[$this->session->userdata('idUn')] = $ci->un_model->nombre($this->session->userdata('idUn'));
                }
            }
        } else {
            $this->db->cache_on();
            $this->db->select('idUn, nombre');
            if ($idEmpresa > 0) {
                $this->db->where('idEmpresa', $idEmpresa);
            }
            if ($allocation == 1) {
                $this->db->where('nombre', 'Allocations');
                $this->db->or_where('activo', '1');
            } else {
                $this->db->where('activo', '1');
            }
            if ($admonCentral == 1) {
                $this->db->where('idOperador', 1);
            } else {
                $this->db->where('idTipoUn <>', '1');
            }
            $query = $this->db->order_by('orden')->get(TBL_UN);

            $this->db->cache_off();

            if ($query->num_rows > 0) {
                foreach ($query->result() as $fila) {
                    $lista[$fila->idUn] = $fila->nombre;
                }
            } else {
                if ($empresa == $idEmpresa) {
                    $lista[$this->session->userdata('idUn')] = $ci->un_model->nombre($this->session->userdata('idUn'));
                }
            }
        }
        return $lista;
    }

    /**
     * Valida si un usuario tiene permiso para categoria
     *
     * @param $idCategoria Indentificador de categoria
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function validaPermisoCategoria($idCategoria)
    {
        settype($idCategoria, 'integer');
        $idCategoriaResponsable = 0;

        if (!$idCategoria) {
            return false;
        }
        $idPersona = $this->session->userdata('idPersona');
        $where     = array(
            'cr.idPersona'   => $idPersona,
            'cr.idCategoria' => $idCategoria,
        );
        $query = $this->db->select(
            "cr.idCategoriaResponsable"
        )->get_where(TBL_CATEGORIARESPONSABLE . " cr", $where);

        if ($query->num_rows) {
            $idCategoriaResponsable = $query->row()->idCategoriaResponsable;
        }
        if ($idCategoriaResponsable) {
            return true;
        } else {
            return $this->validaTodosPermisos($this->_superUsuario);
        }
    }

    /**
     * Lista de permisos asiganado a puestos
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function listaPermisosPuestos()
    {
        $this->db->select('idObjeto, descripcion');
        $this->db->from(TBL_OBJETO);
        $this->db->like('nombreObjeto', 'Liberar_Usuario');
        $this->db->where('estatus', '1');
        $this->db->order_by('descripcion');
        $query = $this->db->get();
        //echo '<pre>'.$this->db->last_query().'</pre>';
        $lista = array();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idObjeto] = $fila->descripcion;
            }
            return $lista;
        } else {
            return null;
        }
    }
    /**
     * Lista de permisos asiganado a puestos
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function listaPermisoPuestos($opciones, $totales = 0, $posicion = 0, $registros = 25, $orden = '')
    {
        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }
        $m = '';
        $p = '';
        if ($totales == 0) {
            if ($posicion == '') {
                $posicion = 0;
            }
            $m = " limit $posicion,$registros ";
        }
        if ($orden == '') {
            $orden = 'u.NombreUsuario';
        }
        $a = '';
        /*  if ($opciones["usuario"] != '') {
        $a =' and u.NombreUsuario like "%'.$opciones["usuario"].'%"';
        }*/
        $b = '';
        if ($opciones["puesto"] != 0) {
            $b = ' and p.idPuesto=' . $opciones["puesto"];
        }
        $c = '';
        if ($opciones["permiso"] != 0) {
            $c = ' and pap.idObjeto=' . $opciones["permiso"];
        }

        $sql = "
            select pap.idPermisoAplicaPuestos,o.descripcion, pap.idPuesto,p.descripcion  as puesto from permisoaplicapuestos pap
                #left join permisousuario pu on pap.idObjeto =pu.idObjeto
                inner join puesto p on p.idPuesto = pap.idPuesto
                #left  join usuarios u on u.IdUsuario = pu.idUsuario
                inner join objeto o on o.idObjeto = pap.idObjeto
            where pap.fechaEliminacion = '0000-00-00 00:00:00' $a $b $c
           ";

        $query = $this->db->query($sql);
        //echo '<pre>'.$this->db->last_query().'</pre>';
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
     * Guarda permisos asiganado a puestos
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function guardaPermisoPuestos($puesto, $objeto)
    {
        settype($puesto, 'integer');
        settype($objeto, 'integer');

        $this->db->select('idPermisoAplicaPuestos');
        $this->db->where('idPuesto', $puesto);
        $this->db->where('idObjeto', $objeto);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $queryBusqueda = $this->db->get(TBL_PERMISOAPLICAPUESTOS);

        $datos = array(
            'idPuesto' => $puesto,
            'idObjeto' => $objeto,
        );
        //echo $this->db->last_query();
        if ($queryBusqueda->num_rows == 0) {
            $resultado = $this->db->insert(TBL_PERMISOAPLICAPUESTOS, $datos);
            $this->permisos_model->log('Asigna permiso por puesto', self::LOG_PERMISOS);
            return true;
        } else {
            return false;
        }
    }
    /**
     * Editar permisos asiganado a puestos
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function datosPermisosPuestos($idPermisoAplicaPuestos)
    {
        settype($idPermisoAplicaPuestos, 'integer');

        $this->db->select('idPermisoAplicaPuestos, idObjeto, idPuesto');
        $this->db->where('idPermisoAplicaPuestos', $idPermisoAplicaPuestos);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_PERMISOAPLICAPUESTOS);
        //echo $this->db->last_query();
        if ($query->num_rows() > 0) {
            return $query->result_array();
        } else {
            return false;
        }
    }
    /**
     * Guardar Edicion permisos asiganado a puestos
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function guardaEdicionPermisoPuestos($idPermisoAplicaPuestos, $puesto, $objeto)
    {
        settype($puesto, 'integer');
        settype($objeto, 'integer');
        settype($idPermisoAplicaPuestos, 'integer');

        $this->db->select('idPermisoAplicaPuestos');
        $this->db->where('idPuesto', $puesto);
        $this->db->where('idObjeto', $objeto);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_PERMISOAPLICAPUESTOS);
        //echo $this->db->last_query();
        $datos = array(
            'idPuesto' => $puesto,
            'idObjeto' => $objeto,
        );

        if ($query->num_rows == 0) {
            $this->db->where('idPermisoAplicaPuestos', $idPermisoAplicaPuestos);
            $this->db->update(TBL_PERMISOAPLICAPUESTOS, $datos);
            $this->permisos_model->log('Actualizo permiso por puesto', self::LOG_PERMISOS);
            return true;
        } else {
            return false;
        }
    }
    /**
     * Eliminar permisos asiganado a puestos
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function eliminarPermisoPuestos($idPermisoAplicaPuestos)
    {
        settype($idPermisoAplicaPuestos, 'integer');

        $this->db->select('idPermisoAplicaPuestos');
        $this->db->where('idPermisoAplicaPuestos', $idPermisoAplicaPuestos);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get(TBL_PERMISOAPLICAPUESTOS);
        //echo $this->db->last_query();
        $datos = array("fechaEliminacion" => date("Y-m-d H:i:s"));

        if ($query->num_rows > 0) {
            $this->db->where('idPermisoAplicaPuestos', $idPermisoAplicaPuestos);
            $this->db->update(TBL_PERMISOAPLICAPUESTOS, $datos);
            $this->permisos_model->log('Elimino permiso por puesto', self::LOG_PERMISOS);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Valida si un usuario tiene permiso de revisor de categoria
     *
     * @param $idCategoria Indentificador de categoria
     * @param $tipo        Indentificador de tipo de revision
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function validaRevisorCategoria($idCategoria, $tipo = '')
    {
        settype($idCategoria, 'integer');
        settype($tipo, 'string');
        $idCategoriaRevisor = 0;

        if (!$idCategoria) {
            return false;
        }
        $idPersona = $this->session->userdata('idPersona');
        $where     = array(
            'cr.idPersona'   => $idPersona,
            'cr.idCategoria' => $idCategoria,
        );
        if ($tipo != '') {
            $where['cr.tipo'] = $tipo;
        }
        $query = $this->db->select(
            "cr.idCategoriaRevisor"
        )->get_where(TBL_CATEGORIAREVISOR . " cr", $where);

        if ($query->num_rows) {
            $idCategoriaRevisor = $query->row()->idCategoriaRevisor;
        }
        if ($idCategoriaRevisor) {
            return true;
        } else {
            return $this->validaTodosPermisos($this->_superUsuario);
        }
    }

    /**
     * [bloqueoReportes description]
     *
     * @return [type] [description]
     */
    public function bloqueoReportes()
    {
        $res = false;

        $sql   = "SELECT * FROM generales WHERE bloqueoReportes=1";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $res = true;
        }

        return $res;
    }

}
