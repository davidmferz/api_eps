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

class Categoria extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.categoria';
    protected $primaryKey = 'idCategoria';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * Cambia el estatus a activo/inactivo en la categoria indicada
     *
     * @param integer $id      Identficador de la categoria
     * @param integer $estatus Estatus de la categoria 0 - Inactivo, 1 - Activo
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function activar($id, $estatus)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return false;
        }

        settype($estatus, 'integer');
        if ($estatus > 1) {
            return false;
        }

        $this->db->where('idAntecesor', $id);
        $this->db->from(TBL_CATEGORIA);
        $total = $this->db->count_all_results();

        if ($total > 0) {
            $this->db->where('idAntecesor', $id);
            $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
            $query = $this->db->get(TBL_CATEGORIA);

            foreach ($query->result() as $fila) {
                $this->activar($fila->idCategoria, $estatus);
            }
        }

        $datos = array (
            'activo' => $estatus
        );

        $this->db->where('idCategoria', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_CATEGORIA, $datos);

        $this->permisos_model->log('Se cambio el estatus de activo para la categoria ('.$id.')', LOG_CATEGORIA);

        return true;
    }

    /**
     * Agrega al revisor de la categoria
     *
     * @param integer $id        Id de la categoria seleccionada
     * @param integer $idRevisor IdPersona del revisor
     * @param integer $tipo      Identificador de tipo de revision
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function agregarRevisor($id, $idRevisor, $tipo = 'Precio')
    {
        settype($id, 'integer');
        if ($id == 0) {
            return false;
        }
        settype($idRevisor, 'integer');
        if ($idRevisor == 0) {
            return false;
        }
        $categorias = array($id);
        $categorias = array_merge($categorias, $this->obtenSubCategorias($id));

        if ($categorias) {
            $categoriasHijo = array();

            foreach($categorias as $idRow => $categoria) {
                $categoriasDatos = explode('-', $categoria);
                if ( ! in_array($categoriasDatos[0], $categoriasHijo)) {
                    $categoriasHijo[] = $categoriasDatos[0];
                }
            }
            foreach ($categoriasHijo as $idRow => $idCategoriaHijo) {
                $this->db->from(TBL_CATEGORIAREVISOR);
                $this->db->where('idCategoria', $idCategoriaHijo);
                $this->db->where('idPersona', $idRevisor);
                $this->db->where('tipo', $tipo);
                $total = $this->db->count_all_results();
                if ($total == 0) {
                    $datos = array (
                        'idCategoria' => $idCategoriaHijo,
                        'idPersona'   => $idRevisor,
                        'tipo'        => $tipo
                    );
                    $this->db->insert(TBL_CATEGORIAREVISOR, $datos);
                }
                $this->permisos_model->log('Se agrego al revisor ('.$idRevisor.') para la categoria ('.$id.') tipo '.$tipo , LOG_CATEGORIA);
            }
        }
        return true;
    }

    /**
     * Obtiene el valor del campo solicitado para la categoria indicada por medio de id
     *
     * @param integer $id    Id de la categoria a procesar
     * @param string  $campo Nombre del campo solicitado
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public static function campo($id, $campo)
    {
        settype($id, 'integer');
        
        
        $query = DB::connection('crm')->table(TBL_CATEGORIA)
        ->select(DB::connection('crm')->raw("$campo as campo"))
        ->where('fechaEliminacion', '0000-00-00 00:00:00')
        ->where('idCategoria', $id)->get()->toArray();
        if (count($query) > 0) {
            $fila = $query[0];
            return $fila->campo;
        }
        return null;
    }

    /**
     * Revisa si el nombre de una categoria esta duplicado en la BD
     *
     * @param integer $nombre Nombre a buscar en la BD
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function duplicado($nombre)
    {
        $this->db->where('nombre', $nombre);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->from(TBL_CATEGORIA);
        $total = $this->db->count_all_results();

        if ($total == 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Elimina reponsable de categoria y subcategorias de un producto fisicamente de la BD
     *
     * @param integer $idCategoriaPadre Identificador de categoria seleccionada
     * @param integer $idPersona        Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function eliminaResponsable ($idCategoriaPadre, $idPersona)
    {
        settype($idCategoriaPadre, 'integer');
        settype($idPersona, 'integer');

        if (! $idCategoriaPadre or ! $idPersona) {
            return false;
        }
        $categorias = array($idCategoriaPadre);
        $categorias = array_merge($categorias, $this->obtenSubCategorias($idCategoriaPadre));

        foreach ($categorias as $info) {
            $infoCat     = explode('-', $info);
            $idCategoria = $infoCat[0];
            $where       = array(
                'idCategoria' => $idCategoria,
                'idPersona'   => $idPersona
            );
            $query = $this->db->select(
                "idCategoriaResponsable"
            )->get_where(TBL_CATEGORIARESPONSABLE, $where);

            if ($query->num_rows) {
                $where = array('idCategoriaResponsable' => $query->row()->idCategoriaResponsable);
                $res   = $this->db->delete(TBL_CATEGORIARESPONSABLE, $where);
                if ($res) {
                    $this->permisos_model->log('Se elimino al responsable ('.$idPersona.') de la categoria ('.$idCategoria.')', LOG_CATEGORIA);
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Elimin la categoria indicada de manera logica en el sistema
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

        $this->db->where('idAntecesor', $id);
        $this->db->from(TBL_CATEGORIA);
        $total = $this->db->count_all_results();

        if ($total > 0) {
            $this->db->where('idAntecesor', $id);
            $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
            $query = $this->db->get(TBL_CATEGORIA);

            foreach ($query->result() as $fila) {
                $this->eliminar($fila->idCategoria);
            }

        }

        $datos = array (
            'fechaEliminacion' => date("Y-m-d H:i:s"),
            'activo'           => 0
        );

        $this->db->where('idCategoria', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_CATEGORIA, $datos);

        $this->permisos_model->log('Se elimino la categoria con ID '.$id, LOG_CATEGORIA);

        return true;
    }

    /**
     * Elimina al revisor de la categoria
     *
     * @param integer $id        Id de la categoria seleccionada
     * @param integer $idRevisor IdPersona del revisor
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function eliminarRevisor($id, $idRevisor)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return false;
        }
        settype($idRevisor, 'integer');
        if ($idRevisor == 0) {
            return false;
        }
        $categorias = array($id);
        $categorias = array_merge($categorias, $this->obtenSubCategorias($id));

        if ($categorias) {
            $categoriasHijo = array();

            foreach($categorias as $idRow => $categoria) {
                $categoriasDatos = explode('-', $categoria);
                if ( ! in_array($categoriasDatos[0], $categoriasHijo)) {
                    $categoriasHijo[] = $categoriasDatos[0];
                }
            }
            foreach ($categoriasHijo as $idRow => $idCategoriaHijo) {
                $this->db->from(TBL_CATEGORIAREVISOR);
                $this->db->where('idCategoria', $idCategoriaHijo);
                $this->db->where('idPersona', $idRevisor);
                $this->db->where('tipo', $tipo);
                $total = $this->db->count_all_results();
                if ($total == 0) {
                    $datos = array (
                        'idCategoria' => $idCategoriaHijo,
                        'idPersona'   => $idRevisor
                    );
                    $this->db->delete(TBL_CATEGORIAREVISOR, $datos);

                    $this->permisos_model->log('Se elimino al revisor ('.$idRevisor.') de la categoria ('.$idCategoriaHijo.')', LOG_CATEGORIA);
                }
            }
        }
        return true;
    }

    /**
     * Guarda reponsable de categoria y subcategorias de un producto
     *
     * @param integer $idCategoriaPadre Identificador de categoria seleccionada
     * @param integer $idPersona        Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function guardaResponsable ($idCategoriaPadre, $idPersona)
    {
        settype($idCategoriaPadre, 'integer');
        settype($idPersona, 'integer');

        if (! $idCategoriaPadre or ! $idPersona) {
            return false;
        }
        $categorias = array($idCategoriaPadre);
        $categorias = array_merge($categorias, $this->obtenSubCategorias($idCategoriaPadre));

        foreach ($categorias as $info) {
            $infoCat     = explode('-', $info);
            $idCategoria = $infoCat[0];
            $where       = array(
                'idCategoria' => $idCategoria,
                'idPersona'   => $idPersona
            );
            $query = $this->db->select(
                "idCategoriaResponsable"
            )->get_where(TBL_CATEGORIARESPONSABLE, $where);

            if (! $query->num_rows) {
                $set =& $where;
                $res = $this->db->insert(TBL_CATEGORIARESPONSABLE, $set);
                if ($res) {
                    $this->permisos_model->log('Se agrego al responsable ('.$idPersona.') para la categoria ('.$idCategoria.')', LOG_CATEGORIA);
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Actualiza o inserta relacion de categoria con tipo de producto
     *
     * @param integer $idCategoriaPadre Identificador de categoria
     * @param integer $idTipoProducto   Identificador de tipoproducto
     * @param integer $activo           Estatus a actualizar o insertar
     * @param integer $id               Identificador de categoriatipoproducto
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function guardaTipoProducto ($idCategoriaPadre, $idTipoProducto, $activo, $id)
    {
        settype($idCategoriaPadre, 'integer');
        settype($idTipoProducto, 'integer');
        settype($activo, 'integer');
        settype($id, 'integer');
        $datos = array('error' => 0, 'mensaje' => 'Se actualizó exitosamente la información', 'idCategoriaTipoProducto' => $id);

        if (! $idCategoriaPadre or ! $idTipoProducto) {
            $datos['error'] = 1;
            $datos['mensaje'] = 'Error faltan tados';
            return $datos;
        }
        $categorias = array($idCategoriaPadre.'-');
        $categorias = array_merge($categorias, $this->obtenSubCategorias($idCategoriaPadre));

        if (count($categorias) > 1) {
            foreach ($categorias as $key => $info) {
                $infoCat                 = explode('-', $info);
                $idCategoria             = $infoCat[0];
                $idCategoriaTipoProducto = $this->validaIipoProducto($idCategoria, $idTipoProducto);

                if ($idCategoriaTipoProducto) {
                    $set   = array('activo' => $activo);
                    $where = array('idCategoriaTipoProducto' => $idCategoriaTipoProducto);
                    $res   = $this->db->update(TBL_CATEGORIATIPOPRODUCTO, $set, $where);

                    if (! $res) {
                        $datos['error']   = 1;
                        $datos['mensaje'] = 'Error al actualizar subcategoria';
                        return $datos;
                    }
                    if ($key == 0) {
                        $datos['idCategoriaTipoProducto'] = $res ? $idCategoriaTipoProducto : 0;
                    }
                } else {
                    $set = array(
                        'idCategoria'    => $idCategoria,
                        'idTipoProducto' => $idTipoProducto,
                        'activo'         => $activo
                    );
                    $res = $this->db->insert(TBL_CATEGORIATIPOPRODUCTO, $set);
                    if (! $res) {
                        $datos['error'] = 1;
                        $datos['mensaje'] = 'Error al insertar subcategoria';
                        return $datos;
                    }
                    if ($key == 0) {
                        $datos['idCategoriaTipoProducto'] = $res ? $this->db->insert_id() : 0;
                    }
                }
            }
        } else {
            if ($id) {
                $set   = array('activo' => $activo);
                $where = array('idCategoriaTipoProducto' => $id);
                $res   = $this->db->update(TBL_CATEGORIATIPOPRODUCTO, $set, $where);

                $datos['idCategoriaTipoProducto'] = $res ? $id : 0;
            } else {
                $set = array(
                    'idCategoria'    => $idCategoriaPadre,
                    'idTipoProducto' => $idTipoProducto,
                    'activo'         => $activo
                );
                $res = $this->db->insert(TBL_CATEGORIATIPOPRODUCTO, $set);
                $datos['idCategoriaTipoProducto'] = $res ? $this->db->insert_id() : 0;
            }
        }
        return $datos;
    }

    /**
     * Actualiza o inserta los datos para definir una categoria
     *
     * @param integer $id          Identificador de la categoría a procesar
     * @param string  $nombre      Nombre de la categoría
     * @param string  $descripcion Descripción de la categoría
     * @param string  $imagen      Ruta url de la imagen correspondiente a la categoria
     * @param string  $activo      Estatus de la categoria
     * @param string  $orden       Orden de visualizacion
     * @param integer $antecesor   Identificador de la categoria antecesora
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function guardar($id, $nombre, $descripcion, $imagen, $activo, $orden, $antecesor)
    {
        settype($id, 'integer');
        settype($orden, 'integer');

        if ($descripcion == "" || $descripcion == null) {
            return false;
        }

        if ($nombre == "" || $nombre == null) {
            return false;
        }

        $datos = array (
            'nombre'      => $nombre,
            'descripcion' => $descripcion,
            'activo'      => $activo,
            'orden'       => $orden,
            'rutaImagen'  => $imagen,
            'idAntecesor' => $antecesor
        );

        if ($id>0) {
            $this->db->where('idCategoria', $id);
            $this->db->update(TBL_CATEGORIA, $datos);

            $this->permisos_model->log('Se modifico la categoria con ID '.$id, LOG_CATEGORIA);
        } else {
            $this->db->insert(TBL_CATEGORIA, $datos);

            $this->permisos_model->log('Se agrego categoria '.$nombre, LOG_CATEGORIA);
        }

        return true;
    }

    /**
     * Crea un array con catalogos activos regresando el Id y la descripcion del catalogo
     *
     * @param integer $antecesor Identificador de la categoria sobre que se evalua si pertenece a la categoria
     * @param integer $base      Identificador del atencesor sobre el cual se genera la lista
     * @param integer $activo    Estatus de categoria a procesar "" - Todos, "1" - Activos, "0" - Inactivos
     * @param string  $orden     Paramentros de ordenamiento de la lista
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function lista($antecesor=0, $base=0, $activo=1, $orden="")
    {
        settype($antecesor, 'integer');
        settype($base, 'integer');

        if ($orden=='') {
            $orden = 'nombre';
        } else {
            if ($this->db->field_exists($orden, TBL_CATEGORIA) == false) {
                return null;
            }
        }

        $data = null;

        $this->db->select('idCategoria, nombre, orden, activo, idAntecesor');
        if ($activo != "") {
            settype($activo, 'integer');
            $this->db->where('activo', $activo);
        }
        $this->db->where('idAntecesor', $base);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');

        $query = $this->db->order_by($orden)->get(TBL_CATEGORIA);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $f['idCategoria'] = $fila->idCategoria;

                $this->db->where('idAntecesor', $f['idCategoria']);
                $this->db->from(TBL_CATEGORIA);
                $f['total'] = $this->db->count_all_results();

                $f['nombre'] = $fila->nombre;
                $f['orden'] = $fila->orden;
                $f['activo'] = $fila->activo;

                $k = $this->pertenceCategoria($f['idCategoria'], $antecesor);
                $data[] = $f;

                if ($k==true) {
                    $r = $this->lista($antecesor, $f['idCategoria'], $activo, $orden);
                    if ($r != null) {
                        foreach ($r as $k) {
                            $k['nombre'] = '<pre style="display:inline">&#09;</pre>'.$k['nombre'];
                            $data[] = $k;
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Crea un array con los antecesoeres regresando el Id de la categoria y Nombre de la categoria
     *
     * @param integer $idAntecesor Identificador de la categoria antecesor
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaAntecesores($idAntecesor, $validaResponsable = 1)
    {
        settype($idAntecesor, 'integer');
        $data  = null;

        $where = array(
            'c.idAntecesor'      => $idAntecesor,
            'c.activo'           => 1,
            'c.fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = $this->db->select(
            "c.idCategoria, c.nombre"
        )->order_by("c.nombre")->get_where(TBL_CATEGORIA." c", $where);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $f['id'] = $fila->idCategoria;
                $f['nombre'] = utf8_encode($fila->nombre);
                $data[] = $f;
            }
        }

        /*
        if ($this->permisos_model->validaAdmin($this->session->userdata('idUsuario'))) {
            $where = array(
                'c.idAntecesor'      => $idAntecesor,
                'c.activo'           => 1,
                'c.fechaEliminacion' => '0000-00-00 00:00:00'
            );
            $query = $this->db->select(
                "c.idCategoria, c.nombre"
            )->order_by("c.nombre")->get_where(TBL_CATEGORIA." c", $where);

            if ($query->num_rows) {
                foreach ($query->result() as $fila) {
                    $f['id'] = $fila->idCategoria;
                    $f['nombre'] = utf8_encode($fila->nombre);
                    $data[] = $f;
                }
            }
        } else {
            if ($validaResponsable == 0) {
              $where = array(
                    'c.idAntecesor'      => $idAntecesor,
                    'c.activo'           => 1,
                    'c.fechaEliminacion' => '0000-00-00 00:00:00'
                );
            } else {
                $where = array(
                    'c.idAntecesor'      => $idAntecesor,
                    'cr.idPersona'       => $this->session->userdata('idPersona'),
                    'c.activo'           => 1,
                    'c.fechaEliminacion' => '0000-00-00 00:00:00'
                );
                $this->db->join(TBL_CATEGORIARESPONSABLE." cr", "c.idCategoria = cr.idCategoria", "inner");
            }

            $query = $this->db->select(
                "c.idCategoria, c.nombre"
            )->order_by("c.nombre")->get_where(TBL_CATEGORIA." c", $where);

            if ($query->num_rows) {
                foreach ($query->result() as $fila) {
                    $f['id'] = $fila->idCategoria;
                    $f['nombre'] = utf8_encode($fila->nombre);
                    $data[] = $f;
                }
            }
        } */

        return $data;
    }

    /**
     * Crea un array con la lista de responsables de la categoria indicada
     *
     * @param integer $id Indica el numero de Id de la categoria
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaResponsables($id)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return null;
        }

        $data = null;
        $this->db->select(
            TBL_CATEGORIARESPONSABLE.'.idPersona, '.
            TBL_PERSONA.'.nombre, '.
            TBL_PERSONA.'.paterno, '.
            TBL_PERSONA.'.materno'
        );
        $this->db->from(TBL_CATEGORIARESPONSABLE);
        $this->db->join(TBL_PERSONA, TBL_PERSONA.'.idPersona = '.TBL_CATEGORIARESPONSABLE.'.idPersona', 'inner');
        $this->db->where('idCategoria', $id);
        $query = $this->db->order_by('nombre, paterno, materno')->get();
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $f['idPersona'] = $fila->idPersona;
                $f['nombre'] = $fila->nombre.' '.$fila->paterno.' '.$fila->materno;
                $data[] = $f;
            }
        }
        return $data;
    }

    /**
     * Crea un array con la lista de revisores de la categoria indicada
     *
     * @param integer $id Id de la categoria
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaRevisores($id)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return null;
        }

        $data = null;
        $this->db->where('cr.idCategoria', $id);
        $this->db->join(TBL_PERSONA.' p', 'p.idPersona = cr.idPersona', 'inner');
        $query = $this->db->select(
            "p.idPersona, CONCAT_WS(' ', p.nombre, p.materno, p.paterno)AS nombre, cr.tipo",
            false
        )->order_by('nombre')->get(TBL_CATEGORIAREVISOR.' cr');
        if ($query->num_rows > 0) {
            $data = $query->result_array();
        }
        return $data;
    }

    /**
     * Crea un array con las categorias de un antecesor o categoria
     *
     * @author Sergio Albarran
     *
     * @return array
     */
    public function listaSimpleAntecesor($idCategoria, $idAntecesor)
    {
        $data = null;
        $this->db->select('idCategoria, nombre, idAntecesor');
        $this->db->where('activo', '1');
        if ($idAntecesor>0)
            $this->db->where('idAntecesor', $idAntecesor);
        if ($idCategoria>0)
            $this->db->where('idCategoria', $idCategoria);
        $query = $this->db->order_by('nombre')->get(TBL_CATEGORIA);
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $f['idCategoria'] = $fila->idCategoria;
                $f['nombre'] = $fila->nombre;
                $f['idAntecesor'] = $fila->idAntecesor;
                $data[] = $f;

            }
        }
        return $data;
    }

    /**
     * Crea un array con las categorias activas regresando los datos solicitados
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaSimpleCategorias()
    {
        $data = null;
        $this->db->select('idCategoria, nombre, idAntecesor');
        $this->db->where('activo', '1');
        $query = $this->db->order_by('nombre')->get(TBL_CATEGORIA);
        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
        }
        return $data;
    }

    /**
     * Regresa lista de tipos de producto con su relacion a categoria
     *
     * @param integer $idCategoria Identificador de categoria
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function listaTipoProducto ($idCategoria)
    {
        settype($idCategoria, 'integer');
        $datos = array();

        if (! $idCategoria) {
            return $datos;
        }
        $where = array("tp.activo" => 1);
        $query = $this->db->select(
            "tp.idTipoProducto, tp.descripcion AS tipoProducto,
            IFNULL(
                    (
                        SELECT ctp.idCategoriaTipoProducto
                        FROM categoriatipoproducto ctp
                        WHERE ctp.idTipoProducto = tp.idTipoProducto
                        AND ctp.idCategoria = '".$idCategoria."'
                    ),0
            )AS idCategoriaTipoProducto,
            IFNULL(
                    (
                        SELECT ctp.activo
                        FROM categoriatipoproducto ctp
                        WHERE ctp.idTipoProducto = tp.idTipoProducto
                        AND ctp.idCategoria = '".$idCategoria."'
                    ),0
            )AS activo",
            false
        )->get_where(TBL_TIPOPRODUCTO." tp");

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Obtiene todas las subcategorias de una categoria
     *
     * @param integer $idCategoriaPadre Identificador de categoria
     * @param integer $idPersona        Identificador de responsable
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenSubCategorias ($idCategoriaPadre, $idPersona = 0)
    {
        settype($idCategoriaPadre, 'integer');
        settype($idPersona, 'integer');
        $datos = array();

        if (! $idCategoriaPadre) {
            return $datos;
        }
        if ($idPersona) {
            $this->db->join(TBL_CATEGORIARESPONSABLE." cr", 'c.idCategoria = cr.idCategoria', 'inner');
            $this->db->where('cr.idPersona', $idPersona);
        }
        $where = array(
            'c.activo'           => 1,
            'c.fechaEliminacion' => '0000-00-00 00:00:00',
            'c.idAntecesor'      => $idCategoriaPadre
        );
        $query = $this->db->select(
            "c.idCategoria, c.nombre AS categoria",
            false
        )->get_where(TBL_CATEGORIA." c", $where);

        if ($query->num_rows) {
            foreach ($query->result_array() as $fila) {
                $datos[] = $fila['idCategoria'].'-'.$fila['categoria'];
                $hijos   = $this->obtenSubCategorias($fila['idCategoria'], $idPersona);
                if ($hijos) {
                    $datos = array_merge($datos, $hijos);
                }
            }
        }
        return $datos;
    }

    /**
     * Verifica si la categoria base es precendente de la categoria antecesor
     *
     * @param integer $base      Identificador de categoria base
     * @param integer $antecesor Identificador de categoria antecesor
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function pertenceCategoria($base, $antecesor)
    {
        settype($base, 'integer');
        settype($antecesor, 'integer');

        if ($antecesor == 0) {
            return false;

        }
        if ($base == $antecesor) {
            return true;
        }

        $r = false;
        $id = $this->campo($antecesor, "idAntecesor");
        if ($id == $base) {
            $r = true;
        } else {
            $r = $this->pertenceCategoria($base, $id);
        }

        return $r;
    }

    /**
     * Regresa el numero siguiente para el dato orden que se puede utilizar al definir una categoria
     *
     * @param integer $id Identificador de categoria base
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function siguienteOrden($id=0)
    {
        settype($id, 'integer');

        $this->db->select_max('orden');
        $this->db->from(TBL_CATEGORIA);
        $this->db->where('idAntecesor', $id);
        $query = $this->db->get();

        $fila = $query->row_array();
        return $fila['orden']+10;
    }

    /**
     * Arma la ruta de antecesores del Id de categoria indicado
     *
     * @param integer $id Id de la categoria a buscar
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function rutaAntecesor($id)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return "";
        }

        $nombre = $this->campo($id, "nombre");
        $idAntecesor = $this->campo($id, "idAntecesor");
        $anterior = $this->rutaAntecesor($idAntecesor);

        return $anterior ." >> ".$nombre;
    }

    /**
     * Valida si existe el registro de categoriaproducto
     *
     * @param integer $idCategoria    Identificador de categoria
     * @param integer $idTipoProducto Identificador de tipo producto
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public function validaIipoProducto ($idCategoria, $idTipoProducto)
    {
        settype($idCategoria, 'integer');
        settype($idTipoProducto, 'integer');

        if (! $idCategoria or ! $idTipoProducto) {
            return 0;
        }
        $where = array(
            'idCategoria'    => $idCategoria,
            'idTipoProducto' => $idTipoProducto
        );
        $query = $this->db->select(
                "idCategoriaTipoProducto"
        )->get_where(TBL_CATEGORIATIPOPRODUCTO, $where);

        return $query->num_rows ? $query->row()->idCategoriaTipoProducto : 0;
    }
}
