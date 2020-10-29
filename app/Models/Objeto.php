<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Objeto extends Model
{
    // use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.objecto';
    protected $primaryKey = 'idObjeto';

    // const CREATED_AT = 'fechaRegistro';
    // const UPDATED_AT = 'fechaActualizacion';
    // const DELETED_AT = 'fechaEliminacion';

    /**
     * Busca el objeto indicado la tabla objetos
     *
     * @param integer $idObjeto indica el Id del elemento padre dentro del arbol de objetos
     *
     * @return array Regresa un array con todos los objetos
     */
    public static function obtenerObjeto($idObjeto = 0)
    {
        if ($idObjeto > 0) {
            $query = DB::connection('crm')->table(TBL_OBJETO)->where(array('idObjeto' => $idObjeto));

            if ($query->count() > 0) {
                $query = $query->get()->toArray();
                $query = array_map(function ($x) {return (array) $x;}, $query);
                return $query[0];
            }
        }
    }

    /**
     * Busca todos los objetos contenidos en la tabla objetos
     *
     * @param integer $idObjeto Indica el Id del elemento padre dentro del arbol de objetos
     *
     * @return array Regresa un array con todos los objetos
     */
    public function todosObjetos($idObjeto = 0)
    {
        $this->db->select('idObjeto, idObjetoPadre, nombreObjeto, vinculo, orden, tipoObjeto');
        $this->db->where('idObjetoPadre', $idObjeto);
        if ($this->session->userdata('idOperador') != 1) {
            $this->db->where('restringido', 0);
        }
        $query = $this->db->order_by('orden')->get(TBL_OBJETO);
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $fila->submenu = $this->todosObjetos($fila->idObjeto);
                $data[]        = $fila;
            }
            return $data;
        }
    }

    /**
     * Busca en la tabla objetos todos los menus para crear el Menu de opciones del sistema
     *
     * @param integer $idObjeto Indica el Id del elemento padre dentro del arbol de menus
     *
     * @return array Regresa un array con las opciones de menu
     */
    public function obtenerMenus($idObjeto = 0, $idUsuario = 0)
    {
        $data = array();
        $this->db->select('idObjeto, nombreObjeto, vinculo');
        $this->db->where('tipoObjeto', 1);
        $this->db->where('idObjetoPadre', $idObjeto);
        $this->db->where('estatus', 1);
        $query = $this->db->order_by('orden')->get(TBL_OBJETO);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $permiso = $this->permisos_model->validaTodosPermisos($fila->idObjeto);

                if ($permiso == true or $fila->vinculo == 'user/logout') {
                    if ($fila->vinculo == 'vacio' or $fila->vinculo == '') {
                        $fila->vinculo = '#';
                        $fila->submenu = $this->obtenerMenus($fila->idObjeto);
                    }
                    $data[] = $fila;
                }
            }
            #return $data;
        } else {
            if ($this->session->userdata("accesoAD") == 1) {
                #$data[] = array("idObjeto"=>7, "nombreObjeto"=>"Salir", "vinculo"=>"user/logout", "submenu"=>array("idObjeto"=>31, "nombreObjeto"=>"Cambiar contrasena", "vinculo"=>"user/cambiaPassword"));
                $data[] = array("idObjeto" => 7, "nombreObjeto" => "Salir", "vinculo" => "user/logout");
                #$menuSU = $this->db->query("SELECT 31 AS idObjeto, 'Cambiar contraseña' AS nombreObjeto, 'user/cambiaPassword' AS vinculo UNION ALL SELECT 7 AS idObjeto, 'Salir' AS nombreObjeto, 'user/logout' AS vinculo");
                #foreach ($menuSU->result() as $fila){
                #    $data[] = $fila;
                #}
                #return $data;
            }
        }

        #print_r($data);
        return $data;
    }

    /**
     * Realiza la actualización o inserción de registro en la tabla de Objetos
     *
     * @return void
     */
    public function guardar()
    {
        $datos = array(
            'idObjetoPadre' => $this->input->post('idObjetoPadre'),
            'nombreObjeto'  => $this->input->post('nombreObjeto'),
            'descripcion'   => $this->input->post('descripcion'),
            'vinculo'       => $this->input->post('vinculo'),
            'orden'         => $this->input->post('orden'),
            'estatus'       => $this->input->post('estatus'),
            'tipoObjeto'    => $this->input->post('tipoObjeto'),
        );

        if ($this->input->post('idObjeto') > 0) {
            $this->db->where('idObjeto', $this->input->post('idObjeto'));
            $this->db->update(TBL_OBJETO, $datos);
        } else {
            $this->db->insert(TBL_OBJETO, $datos);
        }
    }

    /**
     * Elimina el objeto indicado de la tabla
     *
     * @param integer $idObjeto Id del ob jeto a eliminar
     *
     * @return void
     */
    public function eliminar($idObjeto)
    {
        $this->db->select('idObjeto');
        $query = $this->db->where('idObjetoPadre', $idObjeto)->get(TBL_OBJETO);
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $this->eliminar($fila['idObjeto']);
        }
        $this->db->delete(TBL_OBJETO, array('idObjeto' => $idObjeto));
    }

    /**
     * Regresa el nombre del objeto indicado
     *
     * @param integer $idObjeto Id de objeto a buscar
     *
     * @return string
     */
    public function regresaNombre($idObjeto)
    {
        if ($idObjeto == 0) {
            return null;
        }
        $this->db->select('nombreObjeto');
        $query = $this->db->where('idObjeto', $idObjeto)->get(TBL_OBJETO);
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['nombreObjeto'];
        }
    }

    /**
     * Regresa el valor consecutivo para asignar ordenamiento dentro del cataologo de objetos
     *
     * @param integer $idObjeto Id de objeto a buscar
     *
     * @return void
     */
    public function siguienteOrden($idObjeto)
    {
        if ($idObjeto == 0) {
            return 'ninguno';
        }
        $this->db->select_max('orden');
        $query = $this->db->where('idObjetoPadre', $idObjeto)->get(TBL_OBJETO);
        $fila  = $query->row_array();
        return $fila['orden'] + 10;
    }

    /**
     * Obtiene los menus(objetos) principales de la BD
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenMenus()
    {
        $query = $this->db->select('idObjeto, nombreObjeto');
        $query = $this->db->where('tipoObjeto', '1');
        $query = $this->db->where('idObjetoPadre', '0');
        $query = $this->db->where('nombreObjeto <>', 'Salir');
        $query = $this->db->order_by('nombreObjeto', 'ASC');
        $query = $this->db->get(TBL_OBJETO);
        $data  = array();

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $row) {
                $data[$row['idObjeto']] = $row['nombreObjeto'];
            }
            return $data;
        }
    }

    /**
     * Consulta nombres de submenus en la BD dependiendo del id de menu(Objeto Padre) que se le mande.
     *
     * @param int $idMenu id del objeto padre
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenSubMenus($idMenu)
    {
        $data = array();
        $this->db->select('idObjeto, nombreObjeto');
        $this->db->where('tipoObjeto', '1');
        $this->db->where('idObjetoPadre', $idMenu);
        $this->db->where('nombreObjeto <>', 'Salir');
        $this->db->order_by('nombreObjeto', 'ASC');
        $query = $this->db->get(TBL_OBJETO);

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $data[$fila['idObjeto']] = $fila['nombreObjeto'];
            }
        }

        return $data;
    }

    /**
     * Busca en la BD si los submenus pertenecen a un objeto padre
     * esto es por si el usuario selecciono un menu, validar tambien que seleccione
     * un submenu de lo contrario no lo deja pasar
     *
     * @param array $menus arreglo de los menus para recorrer uno por uno
     * @param array $submenus arreglo que contiene todos los menus que selecciono el usuario
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function buscaSubMenus($menus, $submenus)
    {
        foreach ($menus as $idMenu) {
            $query = $this->db->select('idObjeto');
            $query = $this->db->where('idObjetoPadre', $idMenu);
            $query = $this->db->where_in('idObjeto', $submenus);
            $query = $this->db->get(TBL_OBJETO);

            if ($query->num_rows <= 0) {
                return $idMenu;
            }
        }
        return 0;
    }

    /**
     * Valida si un objeto es Objeto Hijo
     *
     * @param integer $idObjeto Id del Objeto Padre
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function validaPermisosInternos($idObjeto = 0)
    {
        $value = 0;

        if ($idObjeto == 0) {
            return $value;
        }

        $this->db->select('idObjeto')->where('idObjetoPadre', $idObjeto);
        $this->db->where('nombreObjeto <>', 'Salir');
        $this->db->order_by('orden', 'ASC');
        $query = $this->db->get(TBL_OBJETO);

        if ($query->num_rows > 0) {
            $value = 1;
        }
        return $value;
    }

    /**
     * [cargaHijosCheckBox description]
     *
     * @param  [type] $idObjeto [description]
     *
     * @return [type]           [description]
     */
    public function cargaHijosCheckBox($idObjeto)
    {
        $query = $this->db->select('idObjeto');
        $query = $this->db->where('idObjetoPadre', $idObjeto);
        $query = $this->db->where('nombreObjeto <>', 'Salir');
        $query = $this->db->order_by('orden', 'ASC');
        $query = $this->db->get(TBL_OBJETO);

        foreach ($query->result_array() as $fila) {
            $this->hijos .= $fila['idObjeto'] . " ";
            $tieneHijos = $this->validaPermisosInternos($fila['idObjeto']);
            if ($tieneHijos == 1) {
                $this->cargaHijosCheckBox($fila['idObjeto']);
            }
        }
        return $this->hijos;
    }

    /**
     * [obtenTodosHijos description]
     *
     * @param  [type] $hijos [description]
     *
     * @return [type]        [description]
     */
    public function obtenTodosHijos($hijos)
    {
        $hijosArreglo = explode(",", $hijos);
        $query        = $this->db->select('idObjeto, nombreObjeto');
        $query        = $this->db->where_in('idObjeto', $hijosArreglo);
        $query        = $this->db->get(TBL_OBJETO);

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $data[$fila['idObjeto']] = $fila['nombreObjeto'];
            }
            return $data;
        }
    }

    /**
     * Verifica si un menu tiene submenus autorizados para el usuario
     *
     * @param integer $idPuesto Id del puesto a buscar
     * @param integer $idUsuario Id del usuario a buscar
     * @param integer $idObjeto Id del objeto a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function verificaHijosConPermiso($idPuesto, $idUsuario, $idObjeto)
    {
        $permisos   = false;
        $queryHijos = $this->db->select('idObjeto');
        $queryHijos = $this->db->where('idObjetoPadre', $idObjeto);
        $queryHijos = $this->db->get(TBL_OBJETO);

        if ($queryHijos->num_rows > 0) {
            foreach ($queryHijos->result_array() as $fila) {
                $permisos = $this->permisos_model->validaTodosPermisos($fila['idObjeto']);
                if ($permisos === true) {
                    return $permisos;
                    exit;
                }
            }
        }
        return $permisos;
    }

    /**
     * Verifica si un objeto es menu principal
     *
     * @param integer $idObjeto Id del objeto a buscar
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function verificaEsMenu($idObjeto)
    {
        $esMenu    = false;
        $queryMenu = $this->db->select('idObjeto');
        $queryMenu = $this->db->where('idObjeto', $idObjeto);
        $queryMenu = $this->db->where('idObjetoPadre', 0);
        $queryMenu = $this->db->where('tipoObjeto', 1);
        $queryMenu = $this->db->get(TBL_OBJETO);

        if ($queryMenu->num_rows > 0) {
            $esMenu = true;
        }

        return $esMenu;
    }

    /**
     * Obtiene todo el arbol de un determinado objeto
     *
     * @param integer $idObjeto Identificador de objeto
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenArbolObjeto($idObjeto)
    {
        settype($idObjeto, 'integer');
        $objetosHijos  = array();
        $objetosPadres = array();
        $arbol         = array();

        if ($idObjeto == 0) {
            return $arbol;
        }
        $where = array('idObjeto' => $idObjeto);
        $query = $this->db->select(
            "idObjetoPadre, nombreObjeto"
        )->get_where(TBL_OBJETO, $where);

        if ($query->num_rows > 0) {
            $objetosHijos[] = array(
                'idObjeto'     => $idObjeto,
                'nombreObjeto' => $query->row()->nombreObjeto,
            );
            if ($query->row()->idObjetoPadre > 0) {
                $objetosPadres = $this->obtenArbolObjeto($query->row()->idObjetoPadre);
            }
        }
        $arbol = array_merge($objetosPadres, $objetosHijos);
        return $arbol;
    }

    /**
     * Obtiene todos los hijos(submenus y botones) de un objeto padre.
     *
     * @param int $idObjetoPadre id del objeto padre
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenHijos($idObjetoPadre)
    {
        $data = array();
        $this->db->select('idObjeto, nombreObjeto');
        $this->db->where('idObjetoPadre', $idObjetoPadre);
        $this->db->where('nombreObjeto <>', 'Salir');
        $this->db->order_by('nombreObjeto', 'ASC');
        $query = $this->db->get(TBL_OBJETO);

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $data[$fila['idObjeto']] = $fila['nombreObjeto'];
            }
        }
        return $data;
    }
}
