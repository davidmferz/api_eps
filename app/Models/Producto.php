<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use API_EPS\Models\Un;
use API_EPS\Models\CatRutinas;
use API_EPS\Models\Tipocliente;
use API_EPS\Models\MenuActividad;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table = 'crm.producto';
    protected $primaryKey = 'idProducto';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * Cambia el estatus de activo del registro indicado
     *
     * @param integer $id     Id del registro a procesar
     * @param integer $opcion Id del campo a cambiar
     *
     * @return boolean
     */
    public function activaOpcion($id, $opcion)
    {
        if ($id == 0 || $id == null) {
            return false;
        }

        $activo = $this->opcionesCampo($id, $opcion);
        if ($activo == null) {
            return false;
        }

        if ($activo == 1) {
            $activo = 0;
            $this->db->where("idProducto", $id);
            $this->db->update(TBL_PRODUCTOUN, array ('activo' => $activo, 'fechaEliminacion' => date("Y-m-d H:i:s")));
        } else {
            $activo = 1;
            $this->db->where("idProducto", $id);
            $this->db->update(TBL_PRODUCTOUN, array ('activo' => $activo));
        }
        $datos = array (
            'activo' => $activo
        );

        $identificador = 'idProducto';
        $this->db->where($identificador, $id);
        $this->db->update(TBL_PRODUCTO, $datos);

        return true;
    }

    /**
     * Activa un producto asociado a un club
     *
     * @param integer $producto Producto a ser utlizado
     * @param integer $un       Unidad de negocio asociada
     *
     * @return boolean
     */
    public function activoProductoClub($producto, $un)
    {
        settype($producto, 'integer');
        settype($un, 'integer');
        if ($producto == 0) {
            return false;
        }
        if ($un == 0) {
            return false;
        }

        $this->db->select('activo');
        $this->db->from(TBL_PRODUCTOUN.' pu');
        $this->db->where('pu.idProducto', $producto);
        $this->db->where('pu.idUn', $un);
        $this->db->where('pu.eliminado', 0);
        $query = $this->db->get();
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
     * Actualiza el descuento indicado del producto
     *
     * @param integer $id               Identificador del precion del producto a ser afectado
     * @param float   $minimo           Monto minimo del descuento a establecer
     * @param float   $maximo           Monto maximo del descuento a establecer
     * @param integer $idEsquemApago    Identificador de esquemapago
     * @param integer $idTipoMembresia  Identificador de tipomembresia
     * @param integer $idTipoRolCliente Identificador de tiporolcliente
     * @param integer $unidades         Cantidad de unidades a las que aplica el descuento
     * @param integer $activo           Estatus de descuento
     * @param integer $idProductoGrupo  Identificador de grupo del descuento
     * @param integer $idTipoDescuento  Identificador de tipodescuento
     *
     * @return boolean
     */
    public function actualizaDescuento($id, $minimo, $maximo, $idEsquemaPago = 0, $idTipoMembresia = 0, $idTipoRolCliente = 0, $unidades = 1, $activo = 1, $idProductoGrupo = 0, $idTipoDescuento = 0, $fidelidad = 0)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($id, 'integer');
        settype($minimo, 'float');
        settype($maximo, 'float');
        settype($idEsquemaPago, 'integer');
        settype($idTipoMembresia, 'integer');
        settype($idTipoRolCliente, 'integer');
        settype($unidades, 'integer');
        settype($activo, 'integer');
        settype($idProductoGrupo, 'string');
        settype($idTipoDescuento, 'integer');
        settype($fidelidad, 'integer');
        settype($fechaVigencia, 'string');

        if ($id == 0) {
            return 0;
        }
        $datosIdProducto = $this->obtenIdProducto(0, 0, $id);
        $idProducto      = $datosIdProducto['idProducto'];
        $datosIdUn       = $this->obtenIdUn(0, 0, $id);
        $idUn            = $datosIdUn['idUn'];
        $productoNombre  = $this->nombre($idProducto);
        $clubNombre      = $CI->un_model->nombre($idUn);
        $datosProductoUn = $this->obtenerProductoUn($idProducto, $idUn);
        $idProductoUn    = $datosProductoUn['idProductoUn'];
        $fechaFin        = date('Y').'-12-31';

        if ($fechaVigencia) {
            $datosFechaVigencia = explode('-', $fechaVigencia);
            $fechaInicio = $fechaVigencia;
            $fechaFin    = $datosFechaVigencia[0].'-12-31';
        }

        $set = array (
            'minimo' => $minimo,
            'maximo' => $maximo
        );
        if ($idTipoMembresia) {
            $set['idTipoMembresia'] = $idTipoMembresia;
        }
        if ($idEsquemaPago) {
            $set['idEsquemaPago'] = $idEsquemaPago;
        }
        if ($idTipoRolCliente) {
            $set['idTipoRolCliente'] = $idTipoRolCliente;
        }
        if ($unidades) {
            $set['unidades'] = $unidades;
        }
        if ($unidades) {
            $set['unidades'] = $unidades;
        }
        $set['activo']          = $activo;
        $set['idProductoGrupo'] = $idProductoGrupo;
        $set['idTipoDescuento'] = $idTipoDescuento;
        $set['idTipoFidelidad'] = $fidelidad;

        if ($activo == 1) {
            $set2  = array('activo' => 0);
            $where = $set;
            unset($where['minimo']);
            unset($where['maximo']);
            unset($where['activo']);

            $where['idProductoUn']     = $idProductoUn;
            $where['finVigencia']      = $fechaFin;
            $where['fechaEliminacion'] = '0000-00-00 00:00:00';
            $where['activo']           = 1;

            //$this->db->update(TBL_PRODUCTODESCUENTO, $set2, $where);
        }
        $this->db->where('idProductoDescuento', $id);
        $result = $this->db->update(TBL_PRODUCTODESCUENTO, $set);

        $accionLog = $activo ? 'Autoriza' : 'Actualiza';
        $this->permisos_model->log($accionLog.' descuento (Min:"'.$minimo.'" Max:"'.$maximo.'") en producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);

        return $result ? $id : 0;
    }

    /**
     * Actualiza puntos del producto
     *
     * @param integer $idProductoPuntos Identificador del precion del producto a ser afectado
     * @param integer $puntos           Monto de puntos a establecer
     * @param float   $porcentaje       Monto porcentaje a establecer
     * @param integer $idEsquemApago    Identificador de esquemapago
     * @param integer $idTipoRolCliente Identificador de tiporolcliente
     * @param integer $unidades         Cantidad de unidades a las que aplican los puntos
     * @param integer $activo           Estatus
     * @param integer $idProductoGrupo  Identificador de grupo de los puntos
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaPuntos($idProductoPuntos, $puntos, $porcentaje, $idEsquemaPago = 0, $idTipoRolCliente = 0, $unidades = 1, $activo = 1, $idProductoGrupo = 0)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProductoPuntos, 'integer');
        settype($puntos, 'float');
        settype($porcentaje, 'float');
        settype($idEsquemaPago, 'integer');
        settype($idTipoRolCliente, 'integer');
        settype($unidades, 'integer');
        settype($activo, 'integer');
        settype($idProductoGrupo, 'string');

        if ($idProductoPuntos == 0) {
            return 0;
        }
        $datosIdProducto = $this->obtenIdProducto(0, 0, 0, $idProductoPuntos);
        $idProducto      = $datosIdProducto['idProducto'];
        $datosIdUn       = $this->obtenIdUn(0, 0, 0, $idProductoPuntos);
        $idUn            = $datosIdUn['idUn'];
        $productoNombre  = $this->nombre($idProducto);
        $clubNombre      = $CI->un_model->nombre($idUn);

        $set = array (
            'puntos'     => $puntos,
            'porcentaje' => $porcentaje
        );
        if ($idEsquemaPago) {
            $set['idEsquemaPago'] = $idEsquemaPago;
        }
        if ($idTipoRolCliente) {
            $set['idTipoRolCliente'] = $idTipoRolCliente;
        }
        if ($unidades) {
            $set['unidades'] = $unidades;
        }
        $set['activo']          = $activo;
        $set['idProductoGrupo'] = $idProductoGrupo;

        $where = array('idProductoPuntos' => $idProductoPuntos);
        $result = $this->db->update(TBL_PRODUCTOPUNTOS, $set, $where);

        $accionLog = $activo ? 'Autoriza' : 'Actualiza';
        $this->permisos_model->log($accionLog.' Puntos:"'.$puntos.'" Porcentaje:"'.$porcentaje.'" en producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);

        return $result ? $idProductoPuntos : 0;
    }

    /**
     * Genera un array con los esquemas de pago disponible para el tipo de producto especificado
     *
     * @param integer $tipoProducto Identificador del tipo de producto
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function arrayProductoEsquemaPago($tipoProducto)
    {
        $lista = array();

        $this->db->select('pe.idEsquemaPago, ep.descripcion');
        $this->db->join(TBL_ESQUEMAPAGO.' ep', 'ep.idEsquemaPago = pe.idEsquemaPago');
        $this->db->where('pe.idTipoProducto', $tipoProducto);
        $this->db->order_by('ep.orden');
        $query = $this->db->get(TBL_PRODUCTOESQUEMAPAGO." pe");

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idEsquemaPago] = $fila->descripcion;
            }
        }
        return $lista;
    }

    /**
     * Genera un array con los tipo de rol de cliente definidos para el tipo de producto solicitado
     *
     * @param integer $tipoProducto Indentificador del tipo de producto
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function arrayProductoRolCliente($tipoProducto, $validaActivo = true)
    {
        $lista = array();

        $CI =& get_instance();
        $CI->load->model('tipocliente_model');

        $this->db->select('pc.idTipoRolCliente');
        $this->db->join(TBL_TIPOROLCLIENTE.' trc', 'trc.idTipoRolCliente = pc.idTipoRolCliente');
        $this->db->where('pc.idTipoProducto', $tipoProducto);

        if ($validaActivo) {
            $this->db->where('trc.activo', 1);
        }
        $this->db->order_by('trc.idTipoCliente, trc.orden');
        $query = $this->db->get(TBL_PRODUCTOCLIENTE." pc");

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $nombre = $CI->tipocliente_model->nombreRolCliente($fila->idTipoRolCliente);
                $lista[$fila->idTipoRolCliente] = $nombre;
            }
        }
        return $lista;
    }

    /**
     *
     * @param integer $un
     * @param integer $tipo
     * @param integer $validaPrecio
     *
     * @return array
     */
    public function arrayProductoTipo($un, $tipo, $validaPrecio)
    {
        settype($un, 'integer');
        settype($tipo, 'integer');
        settype($validaPrecio, 'integer');

        $lista = array();
        $ci =& get_instance();
        $ci->load->model('un_model');

        $idEmpresa = $ci->un_model->obtenerEmpresa($un);
        $admin = $ci->un_model->obtenUnAdiministracion($idEmpresa);

        $this->db->select('p.nombre, p.idProducto');
        $this->db->from(TBL_PRODUCTOUN.' pu');
        $this->db->join(TBL_PRODUCTO.' p', 'p.idProducto = pu.idProducto');
        if ($validaPrecio == 1) {
            $this->db->join(TBL_PRODUCTOPRECIO.' pp', 'pp.idProductoUn = pu.idProductoUn');
        }

        $where = '(pu.idUn='.$un.' or pu.idUn='.$admin.')';
        $this->db->where($where);
        $this->db->where('p.activo', 1);
        $this->db->where('pu.activo', 1);
        $this->db->where('p.idTipoProducto', $tipo);
        $this->db->where('p.eliminado', 0);
        $this->db->where('pu.eliminado', 0);
        if ($validaPrecio == 1) {
            $this->db->where('pp.activo', 1);
            $this->db->where('pp.eliminado', 0);
            $this->db->where('pp.inicioVigencia <=', date('Y-m-d'));
            $this->db->where('pp.finVigencia >=', date('Y-m-d'));
        }
        $query = $this->db->order_by('p.nombre')->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idProducto] = $fila->nombre;
            }
        }

        return $lista;
    }

    /**
     * Devuelve una array con la lista de Clubs donde el producto indicado se encuentra disponible
     *
     * @param integer $producto Identificador del producto
     * @param inteher $empresa  Identificador de la empresa a filtrar
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function arrayProductoUn($producto, $empresa = 0, $idEmpresaGrupo = 0)
    {
        settype($producto, 'integer');
        settype($empresa, 'integer');

        $lista = array();
        $lista[0] = '';

        $this->db->select('pu.idUn, u.nombre, u.idTipoUn');
        $this->db->from(TBL_PRODUCTOUN.' pu');
        $this->db->join(TBL_UN.' u', 'u.idUn = pu.idUn', 'inner');

        if ($idEmpresaGrupo >0){
            $this->db->join(TBL_EMPRESA.' emp', 'emp.idEmpresa = u.idEmpresa');
            $this->db->where('emp.idEmpresaGrupo', $idEmpresaGrupo);
        }

        $this->db->where('pu.idProducto', $producto);
        $this->db->where('pu.activo', 1);
        $this->db->where('pu.eliminado', 0);
        $this->db->where('u.fechaEliminacion', '0000-00-00 00:00:00');
        if ($empresa > 0) {
            $this->db->where('u.idEmpresa', $empresa);
        }

        $query = $this->db->order_by('nombre')->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                if ($fila->idTipoUn == 1) {
                    $lista [$fila->idUn] = 'Todos';
                } else {
                    $lista[$fila->idUn] = $fila->nombre;
                }
            }
        }

        return $lista;
    }

    /**
     * Obtiene tipo de productos asignado a una categoria
     *
     * @param integer $idCategoria Identificador de categoria
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function arrayTipoProductoCategoria($idCategoria)
    {
        settype($idCategoria, 'integer');
        $datos = array();

        $this->db->select('tp.idTipoProducto, tp.descripcion');
        $this->db->from(TBL_TIPOPRODUCTO.' tp');
        $this->db->join(TBL_CATEGORIATIPOPRODUCTO.' ctp', 'ctp.idTipoProducto = tp.idTipoProducto', 'inner');
        $this->db->where('ctp.idCategoria', $idCategoria);
        $this->db->where('ctp.activo', 1);
        $this->db->where('tp.activo', 1);
        $this->db->group_by('tp.idTipoProducto, tp.descripcion');
        $query = $this->db->get();

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idTipoProducto] = utf8_encode($fila->descripcion);
            }
        }
        return $datos;
    }

    /**
     * Pasa a estatus de autorizado el identificador enviado
     *
     * @param  integer $idProductoPrecio Identificador de precio
     *
     * @author Jorge Cruz
     *
     * @return void
     */
    public function autorizaPrecio($idProductoPrecio)
    {
        settype($idProductoPrecio, 'integer');

        $datos = array (
            'activo' => 1
        );

        $this->db->where('idProductoPrecio', $idProductoPrecio);
        $this->db->where('eliminado', 0);
        $this->db->where('activo', 0);
        $this->db->update(TBL_PRODUCTOPRECIO, $datos);

        $total = $this->db->affected_rows();

        if ($total>0) {
            $this->permisos_model->log('Autoriza precio producto', LOG_PRODUCTO, 0, 0, false);

            $sql = "SELECT pp.idProductoUn, pp.idEsquemaPago, pp.idTipoRolCliente,
                    pp.idTipoMembresia, pp.unidades, pp.idTipoFidelidad,
                    pp.finVigencia
                INTO @pu, @ep, @trc, @tm, @u, @tf, @fv
                FROM ".TBL_PRODUCTOPRECIO." pp
                WHERE pp.idProductoPrecio=".$idProductoPrecio;
            $this->db->query($sql);

            $sql = "UPDATE ".TBL_PRODUCTOPRECIO." pp
                    SET pp.activo=0, pp.fechaEliminacion=NOW()
                WHERE pp.idProductoUn=@pu AND pp.idEsquemaPago=@ep AND pp.idTipoRolCliente=@trc
                    AND pp.idTipoMembresia=@tm AND pp.unidades=@u AND pp.idTipoFidelidad=@tf
                    AND pp.activo=1 AND pp.finVigencia=@fv
                    AND pp.eliminado=0
                AND pp.idProductoPrecio NOT IN (".$idProductoPrecio.")";
            $this->db->query($sql);
        }
    }

    /**
     * Obtiene el valor de campo solicitado dentro del catalogo referido
     *
     * @param integer $id    ID del catalogo a procesar
     * @param integer $campo Nombre del campo a devolver
     *
     * @return string
     */
    public function camposTabla($campo, $whereCampo="idPersona", $whereValor="")
    {
        $this->db->select($campo);
        $this->db->where('eliminado', 0);
        $query = $this->db->where($whereCampo, $whereValor)->get(TBL_PRODUCTO);
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila[$campo];
        }
        return null;
    }

    /**
     * Obtiene el valor del campo solicitado para el catalogo indicado por medio de id
     *
     * @param integer $id    Id del catalogo a procesar
     * @param string  $campo Nombre del campo solicitado
     *
     * @return string
     */
    public function catalogoCampo($id, $campo)
    {
        if ($id == 0) {
            return null;
        }
        if ($campo == '') {
            return null;
        }
        $this->db->select($campo);
        $query = $this->db->where('idProducto', $id)->get(TBL_PRODUCTO);
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila[$campo];
        }
        return null;
    }

    /**
     * Regresa el string con la clave de producto
     *
     * @param  integer $idProducto Identificador de producto
     * @param  integer $idUn       Identificador de club
     *
     * @return string
     */
    public static function ctaProducto($idProducto, $idUn)
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');

        $res = '';

        if ($idProducto==0 || $idUn==0) {
            return $res;
        }

        $sql =  "
            SELECT cp.cuentaProducto
            FROM producto p
            INNER JOIN productoun pu ON pu.idProducto=p.idProducto
                AND pu.activo=1 AND pu.eliminado=0 AND pu.idUn={$idUn}
            INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
                AND pp.activo=1 AND pp.eliminado=0
                AND pp.idCuentaProducto>0
                AND DATE(NOW()) BETWEEN pp.inicioVigencia AND pp.finVigencia
            INNER JOIN cuentaproducto cp ON cp.idCuentaProducto=pp.idCuentaProducto
            WHERE p.idProducto={$idProducto} AND p.activo=1 AND p.eliminado=0
            ORDER BY pp.idProductoPrecio DESC
            LIMIT 1
        ";
        $query = DB::connection('crm')->select($sql);

        $res = [];
        if (count($query) > 0) {
            $fila = $query[0];
            $res = $fila->cuentaProducto;
        }
        return $res;
    }

    /**
     * [cveProducto description]
     *
     * @param  integer $idProducto Identificador de producto
     *
     * @return string
     */
    public static function cveProducto($idProducto)
    {
        settype($idProducto, 'integer');

        $cve = '';
        $query = DB::connection('crm')->table(TBL_PRODUCTO)
        ->select('cveProductoServicio')
        ->where('idProducto', $idProducto)->get()->toArray();

        if (count($query) > 0) {
            $fila = $query[0];
            $cve = $fila->cveProductoServicio;
        }
        return $cve;
    }

    /**
     * [cveUnidad description]
     *
     * @param  integer $idProducto Identificador de producto
     *
     * @return string
     */
    public static function cveUnidad($idProducto)
    {
        settype($idProducto, 'integer');

        $cve = '';
        $query = DB::connection('crm')->table(TBL_PRODUCTO)
        ->select('cveUnidad')
        ->where('idProducto', $idProducto)->get()->toArray();
        if (count($query) > 0) {
            $fila = $query[0];
            $cve = $fila->cveUnidad;
        }
        return $cve;
    }

    /**
     * Regresa los datos generales del producto solicitado
     *
     * @param integer $producto Identificador del producto
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function datos($producto)
    {
        $datos = array();
        settype($producto, 'integer');

        if ($producto==0) {
            return $datos;
        }

        $this->db->select('idCategoria, nombre, descripcion, activo, inicioVigencia, finVigencia, idTipoProducto, rutaImagen, permanente');
        $this->db->from(TBL_PRODUCTO);
        $this->db->where('idProducto', $producto);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            return $query->row_array();
        }

        return $datos;
    }

    /**
     * [datosActividad description]
     *
     * @param  [type] $idProducto [description]
     *
     * @return [type]             [description]
     */
    public function datosActividad($idProducto)
    {
        $this->db->select('idActividadDeportiva');
        $this->db->from(TBL_PRODUCTOACTIVIDADDEPORTIVA);
        $where = array('idProducto'=>$idProducto,'activo'=> 1 );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $dato[]= $fila->idActividadDeportiva;
            }
            return $dato;
        } else {
            return null;
        }
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

        $datos = array (
            'fechaEliminacion' => date("Y-m-d H:i:s")
        );
        $productoNombre = $this->nombre($id);

        $this->db->select('idProductoUn');
        $this->db->from(TBL_PRODUCTOUN);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idProducto', $id);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                if ($fila->idProductoUn) {
                    $this->eliminaPrecio($fila->idProductoUn);
                }
                $this->db->select('idProductoDescuento');
                $this->db->from(TBL_PRODUCTODESCUENTO);
                $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
                $this->db->where('idProductoUn', $fila->idProductoUn);
                $queryDescuento = $this->db->get();

                if ($queryDescuento->num_rows > 0) {
                    foreach ($queryDescuento->result() as $filaDescuento) {
                        $this->eliminaDescuento($filaDescuento->idProductoDescuento);
                    }
                }
                $this->db->where('idProductoUn', $fila->idProductoUn);
                $this->db->update(TBL_PRODUCTOUN, $datos);
            }
        }
        $this->db->where('idProducto', $id);
        $this->db->update(TBL_PRODUCTO, $datos);

        $this->permisos_model->log('Elimina producto "'.$productoNombre.'"', LOG_PRODUCTO, 0, 0, false, $id);
        return true;
    }

    /**
     * Regresa el descuento de un producto
     *
     * @param integer $idProducto          Identificador de producto
     * @param integer $idUn                Identificador de unidad de negocio
     * @param integer $idTipoRolCliente    Valor general de idTipoRolCliente que arroja el buscador de personas
     * @param integer $idEsquemaPago       Identificador de esquemapago
     * @param string  $fechaVigencia       Filtro para vigencia
     * @param integer $idTipoMembresia     Identificador de tipomembresia
     * @param integer $unidades            Valor de total de unidades para descuento
     * @param integer $usarGeneral         Bandera para usar precio general si no se encuentra el que se busca
     * @param integer $idTipoCliente       Identificador de tipocliente
     * @param integer $idTipoRolClienteBD  Valor real de la BD de idTipoRolCliente
     * @param boolean $validaActivo        Bandera para validar descuento activo
     * @param string  $idProductoGrupo     Identificador de productogrupo
     * @param string  $idTipoDescuento     Identificador de tipodescuento
     * @param string  $validaTipoDescuento Bandera de validacion de tipo descuento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function descuento ($idProducto, $idUn, $idTipoRolCliente = ROL_CLIENTE_NINGUNO, $idEsquemaPago = ESQUEMA_PAGO_CONTADO, $fechaVigencia = '', $idTipoMembresia = 0, $unidades = 1, $usarGeneral = 0, $idTipoCliente = 0, $idTipoRolClienteBD = 0, $periodo = 0, $validaActivo = true, $idProductoGrupo = '', $idTipoDescuento = 0, $validaTipoDescuento = true, $fidelidad='')
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        settype($idTipoRolCliente, 'integer');
        settype($unidades, 'integer');
        settype($idEsquemaPago, 'integer');
        settype($idTipoMembresia, 'integer');
        settype($idProductoGrupo, 'string');
        settype($idTipoDescuento, 'integer');

        $datos           = array();
        $datos['minimo'] = '0.00';
        $datos['maximo'] = '0.00';
        $datos['id']     = 0;
        $datos['error']  = 0;
        $datos['activo'] = 0;
        $fechaVigencia   = ($fechaVigencia == '') ? date('Y-m-d') : $fechaVigencia;
        $datos['query']  = '';

        if (! $idProducto or ! $idUn) {
            $datos['error'] = 1;
            $datos['mensaje'] = 'Faltan datos para consulta';
            return $datos;
        }
        $where = array(
            'pd.fechaEliminacion' => '0000-00-00 00:00:00',
            'pu.fechaEliminacion' => '0000-00-00 00:00:00',
            'pu.activo'           => 1,
            'pu.idProducto'       => $idProducto,
            'pu.idUn'             => $idUn,
            'pd.idTipoRolCliente' => $idTipoRolCliente,
            'pd.unidades'         => $unidades,
            'pd.idTipoMembresia'  => $idTipoMembresia,
            'pd.idEsquemaPago'    => $idEsquemaPago
        );

        $datosTipoProducto = $this->obtenTipoProducto($idProducto);
        $idTipoProducto = $datosTipoProducto['idTipoProducto'];

        if ($idTipoProducto == TIPO_PRODUCTO_MEMBRESIA and $validaTipoDescuento) {
            $fechaActual         = date('Y-m-d');
            $fechaInicioMes      = date('Y-m-').'01';
            $fechaQuincenaMes    = date('Y-m-').'15';
            $fechaInicioProxMes  = date('Y-m-d', strtotime($fechaInicioMes.' +1 month'));
            $diasPrimerQuincena  = array($fechaQuincenaMes);
            $diasSegundaQuincena = array();

            if ( $idUn==2 || $idUn==3 || $idUn==8 || $idUn==10 || $idUn==15 || $idUn==31
                || $idUn==32 || $idUn==33 || $idUn==36 || $idUn==37 || $idUn==38 || $idUn==53
                || $idUn==54 || $idUn==55 || $idUn==56 || $idUn==57 || $idUn==58
            ) {
                for ($i=1;$i<=3;$i++) {
                    $diasPrimerQuincena[] = date('Y-m-d', strtotime($fechaQuincenaMes.' -'.$i.' day'));
                }
                for ($i=1;$i<=3;$i++) {
                    $diasSegundaQuincena[] = date('Y-m-d', strtotime($fechaInicioProxMes.' -'.$i.' day'));
                }
                if (in_array($fechaActual, $diasPrimerQuincena)) {
                    $idTipoDescuento = TIPO_DESCUENTO_PRIMERA_QUINCENA;
                } else if (in_array($fechaActual, $diasSegundaQuincena)) {
                    $idTipoDescuento = TIPO_DESCUENTO_SEGUNDA_QUINCENA;
                }
            }
        }
        $where['pd.idTipoDescuento'] = $idTipoDescuento;

        if ($periodo > 0) {
            $where['pd.inicioVigencia >='] = $periodo.'-01-01';
            $where['pd.finVigencia <=']    = $periodo.'-12-31';
        } else {
            $this->db->where("((DATE('".$fechaVigencia."') BETWEEN inicioVigencia AND finVigencia) OR (pd.finVigencia='0000-00-00'))");
        }
        if ($validaActivo) {
            $where['pd.activo'] = 1;
        }
        if ($idProductoGrupo != '') {
            $where['pd.idProductoGrupo'] = $idProductoGrupo;
        }
        if ($fidelidad != '') {
            $where['pd.idTipoFidelidad'] = $fidelidad;
        }
        $this->db->join(TBL_PRODUCTOUN.' pu', 'pu.idProductoUn = pd.idProductoUn AND pu.idProductoGrupo = pd.idProductoGrupo', 'inner');
        $this->db->join(TBL_TIPODESCUENTO.' td', 'td.idTipoDescuento = pd.idTipoDescuento', 'inner');
        $query = $this->db->select(
            "pd.minimo, pd.maximo, pd.idProductoDescuento AS id, pd.activo",
            false
        )->order_by('pd.idProductoDescuento', 'DESC')->get_where(TBL_PRODUCTODESCUENTO.' pd', $where);

        if ($query->num_rows) {
            $fila            = $query->row_array();
            $datos['minimo'] = number_format($fila["minimo"], 2);
            $datos['maximo'] = number_format($fila["maximo"], 2);
            $datos['id']     = $fila["id"];
            $datos['activo'] = $fila['activo'];
        } else {
            if ($usarGeneral) {
                $datos['id'] = 0;

                if ( ! $datos['id']) {
                    $CI =& get_instance();
                    $CI->load->model('un_model');

                    $idEmpresa = $CI->un_model->obtenerEmpresa($idUn);
                    $idUnAdmin = $CI->un_model->obtenUnAdiministracion($idEmpresa);

                    if ($idUn != $idUnAdmin) {
                        $datos = $this->descuento($idProducto, $idUnAdmin, $idTipoRolCliente, $idEsquemaPago, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                    }
                    if (! $datos['id'] and $idTipoCliente) {
                        $CI->load->model('tipocliente_model');
                        $rolBase   = $CI->tipocliente_model->obtenRolBase($idTipoCliente);

                        if (! $datos['id'] and ($rolBase != $idTipoRolCliente)) {
                            $datos = $this->descuento($idProducto, $idUn, $rolBase, $idEsquemaPago, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                            if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                                $datos = $this->descuento($idProducto, $idUnAdmin, $rolBase, $idEsquemaPago, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                            }
                        }
                        if (! $datos['id'] and $idTipoRolClienteBD and ($idTipoRolClienteBD != $idTipoRolCliente) and ($idTipoRolClienteBD != $rolBase)) {
                            $datos = $this->descuento($idProducto, $idUn, $idTipoRolClienteBD, $idEsquemaPago, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                            if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                                $datos = $this->descuento($idProducto, $idUnAdmin, $idTipoRolClienteBD, $idEsquemaPago, $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                            }
                        }
                    }
                    if (! $datos['id']) {
                        $datos = $this->descuento($idProducto, $idUn, ROL_CLIENTE_NINGUNO, $idEsquemaPago, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                        if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                            $datos = $this->descuento($idProducto, $idUnAdmin, ROL_CLIENTE_NINGUNO, $idEsquemaPago, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                        }
                    }
                    if (! $datos['id']) {
                        $datos = $this->descuento($idProducto, $idUn, $idTipoRolCliente, 0, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                        if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                            $datos = $this->descuento($idProducto, $idUnAdmin, $idTipoRolCliente, 0, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                        }
                    }
                    if (! $datos['id']) {
                        $datos = $this->descuento($idProducto, $idUn, ROL_CLIENTE_NINGUNO, 0, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                        if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                            $datos = $this->descuento($idProducto, $idUnAdmin, ROL_CLIENTE_NINGUNO, 0, $fechaVigencia = '', $idTipoMembresia, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                        }
                    }
                    if (! $datos['id']) {
                        if ($idTipoProducto == TIPO_PRODUCTO_MEMBRESIA and $idTipoDescuento > 0 and $validaTipoDescuento) {
                            $idTipoDescuento = 0;
                            $validaTipoDescuento = false;
                            $datos = $this->descuento($idProducto, $idUn, $idTipoRolCliente, $idEsquemaPago, $fechaVigencia = '', $idTipoMembresia, $unidades, 1, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo, $idTipoDescuento, $validaTipoDescuento, $fidelidad);
                        }
                    }
                }
            }
        }
        return $datos;
    }

    /**
     *
     * @param integer $precio
     * @param string  $campo
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function descuentoCampo($descuento, $campo)
    {
        settype($descuento, 'integer');
        if ($descuento == 0) {
            return null;
        }
        if ($campo == "") {
            return null;
        }
        $this->db->select($campo);
        $query = $this->db->where('idProductoDescuento', $descuento)->get(TBL_PRODUCTODESCUENTO);
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila[$campo];
        }
        return null;
    }

    /**
     * Marca como eliminado el descuento especifico de un producto
     *
     * @param integer $idProductoDescuento Identificador de productodescuento
     *
     * @return boolean
     */
    public function eliminaDescuento($idProductoDescuento)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProductoDescuento, 'integer');
        if ($idProductoDescuento == 0) {
            return false;
        }
        $datosIdProducto = $this->obtenIdProducto(0, 0, $idProductoDescuento);
        $idProducto      = $datosIdProducto['idProducto'];
        $datosIdUn       = $this->obtenIdUn(0, 0, $idProductoDescuento);
        $idUn            = $datosIdUn['idUn'];
        $productoNombre  = $this->nombre($idProducto);
        $clubNombre      = $CI->un_model->nombre($idUn);

        $datos = array (
            'fechaEliminacion' => date("Y-m-d H:i:s"),
            'activo'           => 0
        );

        $this->db->where('idProductoDescuento', $idProductoDescuento);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_PRODUCTODESCUENTO, $datos);

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        }
        $this->permisos_model->log('Elimina descuento en producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);

        return true;
    }

    /**
     * Elimina descuento de producto
     *
     * @param integer $idProductoPrecio Identificador de productoprecio
     *
     * @return boolean
     */
    public function eliminaDescuentoProducto ($idProductoDescuento)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProductoDescuento, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );

        if ( ! $idProductoDescuento) {
            return $datos;
        }
        $datosIdProducto = $this->obtenIdProducto(0, 0, $idProductoDescuento);
        $idProducto      = $datosIdProducto['idProducto'];
        $datosIdUn       = $this->obtenIdUn(0, 0, $idProductoDescuento);
        $idUn            = $datosIdUn['idUn'];
        $productoNombre  = $this->nombre($idProducto);
        $clubNombre      = $CI->un_model->nombre($idUn);

        $set   = array('fechaEliminacion' => date('Y-m-d H:i:s'));
        $where = array('idProductoDescuento' => $idProductoDescuento);

        if ($this->db->update(TBL_PRODUCTODESCUENTO, $set, $where)) {
            $datos['mensaje'] = '';
            $datos['error']   = 0;

            $this->permisos_model->log('Elimina descuento del producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);
        } else {
            $datos['mensaje'] = 'Error al eliminar descuento';
            $datos['error']   = 2;
        }
        return $datos;
    }

    /**
     * Marca como eliminado el precio especifico de un producto
     *
     * @param integer $idProductoUn Identificador de productoun
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function eliminaPrecio($idProductoUn)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProductoUn, 'integer');
        if ($idProductoUn == 0) {
            return false;
        }
        $datosIdProducto = $this->obtenIdProducto($idProductoUn);
        $idProducto      = $datosIdProducto['idProducto'];
        $datosIdUn       = $this->obtenIdUn($idProductoUn);
        $idUn            = $datosIdUn['idUn'];
        $productoNombre  = $this->nombre($idProducto);
        $clubNombre      = $CI->un_model->nombre($idUn);

        $datos = array (
            'fechaEliminacion' => date("Y-m-d H:i:s"),
        );

        $this->db->where('idProductoUn', $idProductoUn);
        $this->db->where("DATE(NOW()) BETWEEN inicioVigencia AND finVigencia");
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_PRODUCTOPRECIO, $datos);

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        }
        $this->permisos_model->log('Elimina precio del producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);

        return true;
    }

    /**
     * Elimina precio de producto
     *
     * @param integer $idProductoPrecio Identificador de productoprecio
     *
     * @return boolean
     */
    public function eliminaPrecioProducto ($idProductoPrecio)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProductoPrecio, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );

        if ( ! $idProductoPrecio) {
            return $datos;
        }
        $datosIdProducto = $this->obtenIdProducto(0, $idProductoPrecio, 0);
        $idProducto      = $datosIdProducto['idProducto'];
        $datosIdUn       = $this->obtenIdUn(0, $idProductoPrecio, 0);
        $idUn            = $datosIdUn['idUn'];
        $productoNombre  = $this->nombre($idProducto);
        $clubNombre      = $CI->un_model->nombre($idUn);
        $set             = array('fechaEliminacion' => date('Y-m-d H:i:s'));
        $where           = array('idProductoPrecio' => $idProductoPrecio);

        if ($this->db->update(TBL_PRODUCTOPRECIO, $set, $where)) {
            $datos['mensaje'] = '';
            $datos['error']   = 0;

            $this->permisos_model->log('Elimina precio del producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);
        } else {
            $datos['mensaje'] = 'Error al eliminar precio';
            $datos['error']   = 2;
        }
        return $datos;
    }

    /**
     * Elimina puntos de producto
     *
     * @param integer $idProductoPuntos Identificador de productopuntos
     *
     * @return boolean
     */
    public function eliminarPuntos($idProductoPuntos)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProductoPuntos, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos'
        );

        if ( ! $idProductoPuntos) {
            return $datos;
        }
        $datosIdProducto = $this->obtenIdProducto(0, 0, 0, $idProductoPuntos);
        $idProducto      = $datosIdProducto['idProducto'];
        $datosIdUn       = $this->obtenIdUn(0, 0, 0, $idProductoPuntos);
        $idUn            = $datosIdUn['idUn'];
        $productoNombre  = $this->nombre($idProducto);
        $clubNombre      = $CI->un_model->nombre($idUn);

        $set   = array('fechaEliminacion' => date('Y-m-d H:i:s'));
        $where = array('idProductoPuntos' => $idProductoPuntos);

        if ($this->db->update(TBL_PRODUCTOPUNTOS, $set, $where)) {
            $datos['mensaje'] = '';
            $datos['error']   = 0;

            $this->permisos_model->log('Elimina puntos del producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);
        } else {
            $datos['mensaje'] = 'Error al eliminar puntos';
            $datos['error']   = 2;
        }
        return $datos;
    }

    /**
     * Guarda el alias de el grupo de un producto
     *
     * @param integer $idProducto           Identificador de grupo
     * @param integer $idProductoGrupo      Identificador de productogrupo
     * @param string  $alias                Alias de grupo a guardar
     * @param integer $idProductoGrupoAlias Identificador de productogrupoalias
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function guardaAliasGrupo ($idProducto, $idProductoGrupo, $alias, $idProductoGrupoAlias = 0)
    {
        settype($idProducto, 'integer');
        settype($idProductoGrupo, 'integer');
        settype($alias, 'string');

        $datos = array(
            'error'                => 1,
            'mensaje'              => 'Error faltan datos',
            'idProductoGrupoAlias' => 0
        );
        if ( ! $idProducto or ! $idProductoGrupo or ! $alias) {
            return $datos;
        }
        $productoNombre = $this->nombre($idProducto);
        $datosGrupo     = $this->obtieneGrupoNombreAlias($idProducto, $idProductoGrupo);
        $grupo          = $datosGrupo['grupo'];

        $datos['error']   = 2;
        $datos['mensaje'] = 'Error al guardar alias del producto';
        $set              = array('alias' => utf8_decode($alias));

        if ($idProductoGrupoAlias) {
            $where                         = array('idProductoGrupoAlias' => $idProductoGrupoAlias);
            $datos['idProductoGrupoAlias'] = $this->db->update(TBL_PRODUCTOGRUPOALIAS, $set, $where) ? $idProductoGrupoAlias : $datos['idProductoAlias'];
        } else {
            $set['idProducto']      = $idProducto;
            $set['idProductoGrupo'] = $idProductoGrupo;

            $datos['idProductoGrupoAlias'] = $this->db->insert(TBL_PRODUCTOGRUPOALIAS, $set) ? $this->db->insert_id() : $datos['idProductoGrupoAlias'];
        }
        if ($datos['idProductoGrupoAlias']) {
            $datos['error']   = 0;
            $datos['mensaje'] = '';
            $this->permisos_model->log('Cambia nombre de grupo "'.$grupo.'" a "'.utf8_decode($alias).'" en producto "'.$productoNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);
        }
        return $datos;
    }

    /**
     * Funcion que guarda la dispinibilidad de los premios para lealtad
     *
     * @param type $idProducto
     * @param type $disponibilidad
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function guardaDisponibilidadLealtad($idProducto,$disponibilidad)
    {
        settype($idProducto, 'integer');
        settype($disponibilidad, 'integer');

        if($idProducto == 0){
            return false;
        }

        $this->db->select('idProductoLealtadDisponibilidad, disponibilidad');
        $this->db->from(TBL_PRODUCTOLEALTADDISPONIBILIDAD);
        $this->db->where('fechaEliminacion','0000-00-00 00:00:00');
        $this->db->where('idProducto',$idProducto);
        $query = $this->db->get();

        $dispo = 0;
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idProductoLealtadDisponibilidad', $fila->idProductoLealtadDisponibilidad);
                $id = $fila->idProductoLealtadDisponibilidad;
                $dispo= $fila->disponibilidad;
                $datos = array('disponibilidad'=>$disponibilidad);
                $this->db->update(TBL_PRODUCTOLEALTADDISPONIBILIDAD, $datos);
            }
        }  else {
            $datos = array('idProducto'=>$idProducto,'disponibilidad'=>$disponibilidad);
            $this->db->insert(TBL_PRODUCTOLEALTADDISPONIBILIDAD, $datos);
            $id = $this->db->insert_id();
        }

        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        } else {
            $this->permisos_model->log(utf8_decode("Se modifico la disponibilidad de (".$dispo." a ".$disponibilidad." ) #ID (".$id.") "), LOG_PRODUCTO);
            return $id;
        }
    }

    /**
     * Guarda productoun
     *
     * @param integer $idProducto      Identificador de producto
     * @param integer $idUn            Identificador de unidad de negocio
     * @param integer $activo          Bandera de registro activo
     * @param integer $idProductoGrupo Identificador de productogrupo
     * @param integer $idProductoUn    Identificador de productoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function guardaProductoUn ($idProducto, $idUn, $activo, $idProductoGrupo, $idProductoUn = 0)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        settype($activo, 'integer');
        settype($idProductoGrupo, 'integer');
        settype($idProductoUn, 'integer');
        settype($idProductoUnCopiar, 'integer');

        $datos = array(
            'error'        => 1,
            'mensaje'      => 'Error faltan datos',
            'idProductoUn' => 0
        );
        if ( ! $idProducto or ! $idUn) {
            return $datos;
        }
        $datos['mensaje'] = 'Error al guardar productoun';
        $datos['error']   = 2;
        $set              = array ('activo' => $activo, 'idProductoGrupo' => $idProductoGrupo);
        $productoNombre   = $this->nombre($idProducto);
        $datosGrupo       = $this->obtieneGrupoNombreAlias($idProducto, $idProductoGrupo);
        $grupo            = $datosGrupo['grupo'];
        $clubNombre       = $CI->un_model->nombre($idUn);
        $estatusLog       = $activo ? 'activo' : 'inactivo';

        if ($idProductoUn) {
            $where = array('idProductoUn' => $idProductoUn);

            if ($this->db->update(TBL_PRODUCTOUN, $set, $where)) {
                $datos['mensaje']      = '';
                $datos['error']        = 0;
                $datos['idProductoUn'] = $idProductoUn;

                $this->permisos_model->log('Actualiza producto "'.$productoNombre.'" en "'.$clubNombre.'" a grupo "'.$grupo.'" con  estatus "'.$estatusLog.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);
            }
        } else {
            $set['idProducto'] = $idProducto;
            $set['idUn']       = $idUn;

            if ($this->db->insert(TBL_PRODUCTOUN, $set)) {
                $datos['mensaje']      = '';
                $datos['error']        = 0;
                $datos['idProductoUn'] = $this->db->insert_id();

                $this->permisos_model->log('Inserta producto '.$productoNombre.' en '.$clubNombre.' con grupo "'.$grupo.'" con  estatus "'.$estatusLog.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);
            }
        }
        return $datos;
    }

    /**
     * Actualiza o inserta la opcion de algun catalogo
     *
     * @param integer $id             Identificador del producto
     * @param string  $idCat          Identificador de la categoria
     * @param string  $nombre         Nombre del producto
     * @param string  $descripcion    Descripcion del producto
     * @param integer $activo         Estatus del producto
     * @param string  $imagen         Imagen del producto
     * @param integer $inicioVigencia Inicio de vigencia del producto
     * @param integer $finVigencia    Fin de vigencia del producto
     * @param integer $idTipoProducto Tipo de producto
     *
     * @return boolean
     */
    public function guardarOpcion(
        $id,
        $idCat,
        $nombre,
        $descripcion,
        $activo,
        $imagen,
        $inicioVigencia,
        $finVigencia,
        $permanente,
        $idTipoProducto,
        $publicarPrecio,
        $appMovil,
        $cveProductoServicio = '',
        $cveUnidad = ''
    )
    {
        settype($idCat, 'integer');
        settype($publicarPrecio, 'integer');
        settype($appMovil, 'integer');

        if ($permanente == 1) {
            $finVigencia = '0000-00-00 00:00:00';
        }
        $datos = array (
            'idProducto'          => $id,
            'idCategoria'         => $idCat,
            'nombre'              => $nombre,
            'descripcion'         => utf8_decode($descripcion),
            'activo'              => $activo,
            'inicioVigencia'      => $inicioVigencia,
            'finVigencia'         => $finVigencia,
            'rutaImagen'          => $imagen,
            'permanente'          => $permanente,
            'idTipoProducto'      => $idTipoProducto,
            'publicarPrecio'      => $publicarPrecio,
            'appMovil'            => $appMovil,
            'cveProductoServicio' => $cveProductoServicio,
            'cveUnidad'           => $cveUnidad
        );
        $resp = $this->opcionesCampo($id, 'idProducto');

        $ci =& get_instance();
        if ($resp != 0 ) {
            $where = array('idProducto'=> $id);
            $this->db->where($where);
            $this->db->update(TBL_PRODUCTO, $datos);
            $this->permisos_model->log('Guarda configuracion de producto '.$nombre, LOG_PRODUCTO, 0, 0, false, $id);

            if ($idTipoProducto == 5) {
                $ci->load->model('evento_model');
                $ci->evento_model->guardaEvento($id);
            }

            if ($idTipoProducto == 2) {
                $ci->load->model('mantenimientos_model');
                $ci->mantenimientos_model->guardaMantenimiento($id);
            }
        } else {
            unset($datos['idProducto']);
            $datos['idEmpresaGrupo'] = $this->session->userdata('idEmpresaGrupo');
            $this->db->insert(TBL_PRODUCTO, $datos);
            $idProducto = $this->db->insert_id();
            $this->permisos_model->log('Guarda configuracion de producto '.$nombre, LOG_PRODUCTO, 0, 0, false, $idProducto);

            $this->guardaProductoUn($idProducto, $this->session->userdata('idUn'), 1, 0);

            if ($idTipoProducto == 6) {
                $this->insertaTipoLocker($idProducto);
            }

            if ($idTipoProducto == 5) {
                $ci->load->model('evento_model');
                $ci->evento_model->guardaEvento($idProducto);
            }

            if ($idTipoProducto == 2) {
                $ci->load->model('mantenimientos_model');
                $ci->mantenimientos_model->guardaMantenimiento($idProducto);
            }

            return $idProducto;
        }

        return true;
    }

    /**
     *
     * @param <type> $precio
     * @param <type> $importe
     * @param <type> $producto
     * @param <type> $club
     * @param <type> $esquema
     * @param <type> $cuenta
     * @param <type> $cliente
     * @param <type> $inicio
     * @param <type> $fin
     * @param <type> $estatus
     * @param <type> $medida
     *
     * @author Jorge Cruz
     *
     * @return <type>
     */
    public function guardarPrecio(
        $idProductoPrecio,
        $importe,
        $producto=0,
        $club=0,
        $esquema=0,
        $cuenta=0,
        $cuentaProducto=0,
        $cliente=0,
        $inicio=0,
        $fin=0,
        $estatus=2,
        $medida=0,
        $idTipoMembresia=0,
        $unidades=0,
        $idProductoGrupo=0,
        $fidelidad=0)
    {
        $datos = array ();
        $res   = array ();

        settype($idProductoPrecio, 'integer');
        settype($producto, 'integer');
        settype($club, 'integer');
        settype($esquema, 'integer');
        settype($cuenta, 'integer');
        settype($cuentaProducto, 'integer');
        settype($cliente, 'integer');
        settype($importe, 'float');
        settype($estatus, 'integer');
        settype($medida, 'integer');
        settype($idTipoMembresia, 'integer');
        settype($unidades, 'integer');
        settype($idProductoGrupo, 'integer');
        settype($fidelidad, 'integer');

        $datos['importe'] = $importe;

        $CI =& get_instance();
        $CI->load->model('tipocliente_model');
        $CI->load->model('un_model');

        $datosProductoUn = $this->obtenerProductoUn($producto, $club);
        $idProductoUn = $datosProductoUn['idProductoUn'];
        $datosIdProducto = $this->obtenIdProducto($idProductoUn);
        $idProducto = $datosIdProducto['idProducto'];
        $productoNombre = $this->nombre($idProducto);
        $clubNombre = $CI->un_model->nombre($club);

        if ($idProductoPrecio > 0 and $estatus == 1) {
            if ($cliente > 0) {
                $datos['idTipoRolCliente'] = $cliente;
                $tipoCliente = $CI->tipocliente_model->origenRol($cliente);
                $datos['idTipoCliente'] = $tipoCliente;
            }
            if ($inicio != '0') {
                $datos['inicioVigencia'] = $inicio;
            }
            if ($fin != '0') {
                $datos['finVigencia'] = $fin;
            }
            if ($estatus < 2) {
                $datos['activo'] = $estatus;
            }
            if ($cuenta > 0) {
                $datos['idCuentaContable'] = $cuenta;
            }
            if ($cuentaProducto > 0) {
                $datos['idCuentaProducto'] = $cuentaProducto;
            }
            if ($esquema > 0) {
                $datos['idEsquemaPago'] = $esquema;
            }
            if ($medida > 0) {
                $datos['idTipoMedida'] = $medida;
            }
            if ($idTipoMembresia > 0) {
                $datos['idTipoMembresia'] = $idTipoMembresia;
            }
            if ($unidades > 0) {
                $datos['unidades'] = $unidades;
            }
            if ($idProductoGrupo > 0) {
                $datos['idProductoGrupo'] = $idProductoGrupo;
            }
            if ($fidelidad) {
                $datos['idTipoFidelidad']  = $fidelidad;
            }
            if ($estatus == 1) {
                $set   = array('activo' => 0);
                $where = $datos;
                unset($where['activo']);
                unset($where['inicioVigencia']);
                unset($where['importe']);

                $where['idProductoUn']     = $idProductoUn;
                $where['fechaEliminacion'] = '0000-00-00 00:00:00';
                $where['activo']           = 1;

                $this->db->update(TBL_PRODUCTOPRECIO, $set, $where);
            }
            $this->db->where('idProductoPrecio', $idProductoPrecio);
            $result = $this->db->update(TBL_PRODUCTOPRECIO, $datos);

            if (! $result) {
                $idProductoPrecio = 0;
            } else {
                $accionLog = $estatus ? 'Autoriza' : 'Actualiza';
                $this->permisos_model->log($accionLog.' precio "$'.$datos['importe'].'" en producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);
            }
        } else {
            if ( ! $idProductoUn) {
                return false;
            }
            $tipoCliente               = $CI->tipocliente_model->origenRol($cliente);
            $datos['idTipoRolCliente'] = $cliente;
            $datos['idTipoCliente']    = $tipoCliente;
            $datos['idProductoUn']     = $idProductoUn;
            $datos['inicioVigencia']   = $inicio;
            $datos['finVigencia']      = $fin;
            $datos['activo']           = ($estatus == 1) ? 0 : $estatus;
            $datos['idCuentaContable'] = $cuenta;
            $datos['idCuentaProducto'] = $cuentaProducto;
            $datos['idEsquemaPago']    = $esquema;
            $datos['idTipoMedida']     = $medida;
            $datos['idTipoMembresia']  = $idTipoMembresia;
            $datos['unidades']         = $unidades;
            $datos['idProductoGrupo']  = $idProductoGrupo;
            $datos['idTipoFidelidad']  = $fidelidad;

            $result       = $this->db->insert(TBL_PRODUCTOPRECIO, $datos);
            $res['query'] = $this->db->last_query();

            if (! $result) {
                $idProductoPrecio = 0;
            } else {
                $idProductoPrecio = $this->db->insert_id();
                $accionLog = $estatus ? 'Autoriza' : 'Inserta';
                $this->permisos_model->log($accionLog.' precio "$'.$datos['importe'].'" en producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);
            }
        }
        $res['idProductoPrecio'] = $idProductoPrecio;
        return $res;
    }

    /**
      * Inserta los valores que relacionan al producto con la actividad deportiva de cada categoría
      *
      * @param integer $idProducto           identifica al producto
      * @param integer $idActividadDeportiva identifica a la actividad por producto
      * @param boolean $activo               identifica el estado de la actividad
      *
      * @author Santa Garcia
      *
      * @return void
      */
    public function insertaActividad($idProducto,$idActividadDeportiva,$activo)
    {
        $datos = array('idProducto'=>$idProducto,'idActividadDeportiva'=>$idActividadDeportiva,'activo'=>$activo);
        $this->db->select('idProductoActividad');
        $this->db->from(TBL_PRODUCTOACTIVIDADDEPORTIVA);
        $where = array('idProducto'=>$idProducto,'idActividadDeportiva'=> $idActividadDeportiva );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $this->db->where('idProductoActividad', $fila['idProductoActividad']);
            $this->db->update(TBL_PRODUCTOACTIVIDADDEPORTIVA, $datos);
        } else {
            $this->db->insert(TBL_PRODUCTOACTIVIDADDEPORTIVA, $datos);
        }
        $cambio = $this->db->affected_rows();
        if ($cambio == 0) {
            return false;
        }

        return true;
    }

    /**
     * Ingresa un nuevo descuento para el producto indicado
     *
     * @param integer $producto        Idenficador del producto
     * @param integer $un              Identificador del club donde estara disponible
     * @param integer $cliente         Identificador del tipo de cliente
     * @param date    $inicio          Fecha de inicio de vigencia del precio
     * @param date    $fin             Fecha final de vigencia del precio
     * @param float   $minimo          Monto del descuento minimo del producto
     * @param float   $maximo          Monto del descuento maximo del producto
     * @param integer $idEsquemaPago   Identificador de esquemapago
     * @param integer $idTipoMembresia Identificador de tipomembresia
     * @param integer $unidades        Cantidad de unidades a las que aplica el descuento
     * @param integer $activo          Estatus de descuento
     * @param integer $idProductoGrupo Indentificador de grupo del descuento
     * @param integer $idTipoDescuento Indentificador de tipodescuento
     *
     * @return boolean
     */
    public function insertaDescuento ($producto, $un, $cliente, $inicio, $fin, $minimo, $maximo, $idEsquemaPago = 0, $idTipoMembresia = 0, $unidades = 1, $activo = 1, $idProductoGrupo = 0, $idTipoDescuento = 0, $fidelidad = 0)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($producto, 'integer');
        settype($un, 'integer');
        settype($cliente, 'integer');
        settype($minimo, 'float');
        settype($maximo, 'float');
        settype($idEsquemaPago, 'integer');
        settype($idTipoMembresia, 'integer');
        settype($unidades, 'integer');
        settype($activo, 'integer');
        settype($idProductoGrupo, 'string');
        settype($idTipoDescuento, 'integer');
        settype($fidelidad, 'integer');

        if (! $producto or ! $un) {
            return 0;
        }
        $productoNombre = $this->nombre($producto);
        $clubNombre = $CI->un_model->nombre($un);

        $this->db->select('idProductoUn');
        $this->db->from(TBL_PRODUCTOUN);
        $this->db->where('idProducto', $producto);
        $this->db->where('idun', $un);
        $this->db->where('activo', 1);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $productoUn = $fila['idProductoUn'];
        } else {
            return 0;
        }
        $set = array (
            'minimo'           => $minimo,
            'maximo'           => $maximo,
            'inicioVigencia'   => $inicio,
            'finVigencia'      => $fin,
            'activo'           => $activo,
            'idProductoUn'     => $productoUn,
            'idTipoRolCliente' => $cliente,
            'unidades'         => $unidades,
            'idProductoGrupo'  => $idProductoGrupo,
            'idTipoDescuento'  => $idTipoDescuento,
            'idTipoFidelidad'  => $fidelidad
        );
        if ($idTipoMembresia) {
            $set['idTipoMembresia'] = $idTipoMembresia;
        }
        if ($idEsquemaPago) {
            $set['idEsquemaPago'] = $idEsquemaPago;
        }
        $res = $this->db->insert(TBL_PRODUCTODESCUENTO, $set);

        if ($res) {
            $idInsertado = $this->db->insert_id();

            $accionLog = $activo ? 'Autoriza' : 'Inserta';
            $this->permisos_model->log($accionLog.' descuento (Min:"'.$minimo.'" Max:"'.$maximo.'") en producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $producto);

            return $idInsertado;
        }
        return 0;
    }

    /**
     * Inserta descuentos actuales de un grupo al nuevo productoun
     *
     * @param integer $idProductoUn           Identificador de productoun a insertar descuentos
     * @param integer $idProductoUnCopiar     Identificador del grupoun donde se toman los descuentos
     * @param integer $idProductoGrupoDestino Identificador de grupo nuevo
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function insertaDescuentosGrupo ($idProductoUn, $idProductoUnCopiar, $idProductoGrupoDestino, $idProducto, $idProductoGrupoOrigen, $idUn)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProductoUn, 'integer');
        settype($idProductoUnCopiar, 'integer');
        settype($idProductoGrupoDestino, 'integer');
        settype($idProducto, 'integer');
        settype($idProductoGrupoOrigen, 'integer');
        settype($idUn, 'integer');

        $datosGrupo     = $this->obtieneGrupoNombreAlias($idProducto, $idProductoGrupoOrigen);
        $grupoOrigen    = $datosGrupo['grupo'];
        $datosGrupo     = $this->obtieneGrupoNombreAlias($idProducto, $idProductoGrupoDestino);
        $grupoDestino   = $datosGrupo['grupo'];
        $productoNombre = $this->nombre($idProducto);
        $clubNombre     = $CI->un_model->nombre($idUn);

        $datos = array(
            'mensaje' => 'Error faltan datos',
            'error'   => 3
        );
        if ( ! $idProductoUn or ! $idProductoUnCopiar or ! $idProductoGrupoDestino) {
            return $datos;
        }
        $query = $this->db->query("
            INSERT INTO ".TBL_PRODUCTODESCUENTO."(
                    minimo, maximo, inicioVigencia, finVigencia, activo, idProductoUn, idTipoRolCliente, afectacuinUnidades, idEsquemaPago,
                    idTipoMembresia, unidades, idProductoGrupo, idTipoDescuento
            )
            SELECT pd.minimo, pd.maximo, pd.inicioVigencia, pd.finVigencia, 0 AS activo,
            ".$idProductoUn." AS idProductoUn, pd.idTipoRolCliente, pd.afectacuinUnidades,
            pd.idEsquemaPago, pd.idTipoMembresia,  pd.unidades,
            ".$idProductoGrupoDestino." AS idProductoGrupo, 0 AS idTipoDescuento
            FROM ".TBL_PRODUCTODESCUENTO." pd
            WHERE pd.activo = 1 AND pd.fechaEliminacion = '0000-00-00 00:00:00' AND
              pd.idProductoGrupo = ".$idProductoGrupoDestino." AND pd.idProductoUn = ".$idProductoUnCopiar);
        $datos['mensaje'] = '';
        $datos['error']   = 0;

        $this->permisos_model->log('Cambia a "'.$clubNombre.'" de grupo "'.$grupoOrigen.'" a grupo "'.$grupoDestino.'", en producto "'.$productoNombre.'", se insertan descuentos de grupo "'.$grupoDestino.'" pendientes de autorizacion', LOG_PRODUCTO, 0, 0, false, $idProducto);

        return $datos;
    }

    /**
     * Inserta precios actuales de un grupo al nuevo productoun
     *
     * @param integer $idProductoUn           Identificador de productoun a insertar precios
     * @param integer $idProductoUnCopiar     Identificador del grupoun donde se toman los precios
     * @param integer $idProductoGrupoDestino Identificador de grupo nuevo
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function insertaPreciosGrupo ($idProductoUn, $idProductoUnCopiar, $idProductoGrupoDestino, $idProducto, $idProductoGrupoOrigen, $idUn)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProductoUn, 'integer');
        settype($idProductoUnCopiar, 'integer');
        settype($idProductoGrupoDestino, 'integer');
        settype($idProducto, 'integer');
        settype($idProductoGrupoOrigen, 'integer');
        settype($idUn, 'integer');

        $datosGrupo     = $this->obtieneGrupoNombreAlias($idProducto, $idProductoGrupoOrigen);
        $grupoOrigen    = $datosGrupo['grupo'];
        $datosGrupo     = $this->obtieneGrupoNombreAlias($idProducto, $idProductoGrupoDestino);
        $grupoDestino   = $datosGrupo['grupo'];
        $productoNombre = $this->nombre($idProducto);
        $clubNombre     = $CI->un_model->nombre($idUn);


        $datos = array(
            'mensaje' => 'Error faltan datos',
            'error'   => 3
        );
        if ( ! $idProductoUn or ! $idProductoUnCopiar or ! $idProductoGrupoDestino) {
            return $datos;
        }
        $query = $this->db->query("
            INSERT INTO ".TBL_PRODUCTOPRECIO."
                (importe, inicioVigencia, finVigencia, activo, idProductoUn,
                idTipoCliente, idEsquemaPago, idCuentaContable, idTipoRolCliente, idTipoMedida, idTipoMembresia, unidades,
                idProductoGrupo, idCuentaProducto
            )
            SELECT pp.importe, pp.inicioVigencia, pp.finVigencia, 0 AS activo, ".$idProductoUn." AS idProductoUn,
            pp.idTipoCliente, pp.idEsquemaPago, pp.idCuentaContable, pp.idTipoRolCliente, pp.idTipoMedida, pp.idTipoMembresia,  pp.unidades,
            ".$idProductoGrupoDestino." AS idProductoGrupo, pp.idCuentaProducto
            FROM ".TBL_PRODUCTOPRECIO." pp
            WHERE pp.activo = 1 AND pp.fechaEliminacion = '0000-00-00 00:00:00' AND
            pp.idProductoGrupo = ".$idProductoGrupoDestino." AND pp.idProductoUn = ".$idProductoUnCopiar);
        $datos['mensaje'] = '';
        $datos['error']   = 0;
        $this->permisos_model->log('Cambia a "'.$clubNombre.'" de grupo "'.$grupoOrigen.'" a grupo "'.$grupoDestino.'", en producto "'.$productoNombre.'", se insertan precios de grupo "'.$grupoDestino.'" pendientes de autorizacion', LOG_PRODUCTO, 0, 0, false, $idProducto);

        return $datos;
    }

    /**
     * Ingresa puntos para el producto indicado
     *
     * @param integer $idProducto       Idenficador del producto
     * @param integer $idUn             Identificador del club donde estara disponible
     * @param integer $idTipoRolCliente Identificador del tipo de cliente
     * @param date    $inicioVigencia   Fecha de inicio de vigencia del precio
     * @param date    $finVigencia      Fecha final de vigencia del precio
     * @param float   $puntos           Monto de puntos del producto
     * @param float   $porcentaje       Monto de porcentaje
     * @param integer $idEsquemApago    Identificador de esquemapago
     * @param integer $unidades         Cantidad de unidades a las que aplican los puntos
     * @param integer $activo           Estatus
     * @param integer $idProductoGrupo  Indentificador de grupo de los puntos
     *
     * @autor Jonathan Alcantara
     *
     * @return boolean
     */
    public function insertaPuntos($idProducto, $idUn, $idTipoRolCliente, $inicioVigencia, $finVigencia, $puntos, $porcentaje, $idEsquemaPago = 0, $unidades = 1, $activo = 1, $idProductoGrupo = 0)
    {
        $CI =& get_instance();
        $CI->load->model('un_model');

        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        settype($idTipoRolCliente, 'integer');
        settype($puntos, 'float');
        settype($porcentaje, 'float');
        settype($idEsquemaPago, 'integer');
        settype($unidades, 'integer');
        settype($activo, 'integer');
        settype($idProductoGrupo, 'string');

        if (! $idProducto or ! $idUn) {
            return 0;
        }
        $productoNombre = $this->nombre($idProducto);
        $clubNombre = $CI->un_model->nombre($idUn);

        $this->db->select('idProductoUn');
        $this->db->from(TBL_PRODUCTOUN);
        $this->db->where('idProducto', $idProducto);
        $this->db->where('idun', $idUn);
        $this->db->where('activo', 1);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $idProductoUn = $fila['idProductoUn'];
        } else {
            return 0;
        }
        $set = array (
            'puntos'           => $puntos,
            'porcentaje'       => $porcentaje,
            'inicioVigencia'   => $inicioVigencia,
            'finVigencia'      => $finVigencia,
            'activo'           => $activo,
            'idProductoUn'     => $idProductoUn,
            'idTipoRolCliente' => $idTipoRolCliente,
            'unidades'         => $unidades,
            'idProductoGrupo'  => $idProductoGrupo
        );
        if ($idEsquemaPago) {
            $set['idEsquemaPago'] = $idEsquemaPago;
        }
        $res = $this->db->insert(TBL_PRODUCTOPUNTOS, $set);

        if ($res) {
            $idInsertado = $this->db->insert_id();

            $accionLog = $activo ? 'Autoriza' : 'Inserta';
            $this->permisos_model->log($accionLog.' Puntos:"'.$puntos.'" Porcentaje:"'.$porcentaje.'" en producto "'.$productoNombre.'" en "'.$clubNombre.'"', LOG_PRODUCTO, 0, 0, false, $idProducto);

            return $idInsertado;
        }
        return 0;
    }

    /**
     * Inserta el valor de idProducto en la tabla Tipo Locker
     *
     * @param integer $idProducto Tipo de producto
     *
     * @return string
     */
    public function insertaTipoLocker($idProducto){
        if($idProducto != 0){
            $datos = array (
                'idProducto'   => $idProducto
            );

            $this->db->insert(TBL_TIPOLOCKER, $datos);
        }
    }

    /**
     * Regresa el listado de opciones disponibles
     *
     * @param integer $filtro1   Indica el valor del primer filtro en db
     * @param integer $filtro2   Indica el valor del segundo filtro en db
     * @param integer $filtro3   Indica el valor del tercer filtro en db
     * @param integer $filtro4   Indica el valor del cuarto filtro en db
     * @param integer $filtro5   Indica el valor del quinto filtro en db
     * @param integer $filtro6   Indica el valor del sexto filtro en db
     * @param integer $orden     Indica el orden para el select
     * @param integer $posicion  Numero de elemenmtos para hacer LIMIT
     * @param integer $elementos Numero de elementos a seleccionar
     *
     * @return array
     */
    public function lista($filtro1 = '', $filtro2 = '', $filtro3 = '', $filtro4 = '', $filtro5 = '', $idUn = '', $orden = '', $posicion = 0, $elementos = 100, $filtro7 = '',$filtro8=0)
    {
        $data = null;
        $tabla = "producto a";

        if ($orden == null) {
            $orden = 'a.idProducto desc';
        }

        $this->db->select('a.idProducto, a.nombre, c.descripcion, b.nombre as Categoria, a.activo');
        $this->db->join('categoria b', 'a.idCategoria= b.idCategoria');
        $this->db->join('tipoproducto c', 'a.idTipoProducto = c.idTipoProducto');


        if ($idUn > 0) {
            $this->db->join('productoun d', 'a.idProducto = d.idProducto AND d.activo=1');
            $this->db->join('un e', 'e.idUn = d.idUn');
            $this->db->where('d.idUn', $idUn);
            $this->db->where('d.fechaEliminacion', '0000-00-00 00:00:00');
        } else{
            //  RBN: INICIA -> Lista de Articulos que pertenecen al idProductoGrup del user actual
            $this->db->join('productoun d', 'a.idProducto = d.idProducto');
            $this->db->join('un e', 'e.idUn = d.idUn');
            $this->db->join('empresa emp', 'emp.idempresa=e.idEmpresa');
            $this->db->where('emp.idEmpresaGrupo', $this->session->userdata('idEmpresaGrupo'));
            $this->db->where('d.fechaEliminacion', '0000-00-00 00:00:00');
            //  RBN: FIN -> Lista de Articulos que pertenecen al idProductoGrup del user actual
        }
        if ($filtro1 <> '') {
            $this->db->like('a.nombre', $filtro1);
        }
        if ($filtro2 <> '' && $filtro2<>0) {
            $this->db->where('a.idCategoria', $filtro2);
        }
        if ($filtro3 <> '') {
            $this->db->where('a.activo', $filtro3);
        }
        if ($filtro4 <> '') {
            $this->db->where('a.inicioVigencia >=', $filtro4);
        }
        if ($filtro5 <> '') {
            $this->db->where('a.inicioVigencia <=', $filtro5);
        }

        if ($filtro7 <> '') {
            $this->db->where($filtro7);
        }

        if ($filtro8 <> 0) {
            $this->db->where('c.idTipoProducto', $filtro8);
        }

        $this->db->where('a.fechaEliminacion =', date("0000-00-00 00:00:00"));
        $query = $this->db->distinct()->order_by($orden)->get($tabla, $elementos, $posicion);

        if ($query->num_rows>0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     *
     * @param integer $producto
     * @param integer $club
     * @param integer $cliente
     *
     */
    public function listaDescuentos($producto, $club, $cliente)
    {
        $descuentos = array();

        settype($producto, 'integer');
        settype($club, 'integer');
        settype($cliente, 'integer');

        $this->db->select('pd.idProductoDescuento, pd.minimo, pd.maximo');
        $this->db->from(TBL_PRODUCTODESCUENTO.' pd');
        $this->db->join(TBL_PRODUCTOUN.' pu', 'pd.idProductoUn = pu.idProductoUn');
        $this->db->join(TBL_PRODUCTO.' p', 'p.idProducto = pu.idProducto');
        $this->db->where('p.activo', 1);
        $this->db->where('pu.activo', 1);
        $this->db->where('pd.activo', 1);
        $this->db->where('p.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pu.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pd.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('p.idProducto', $producto);
        $this->db->where('pu.idUn', $club);
        $this->db->where('pd.idTipoRolCliente', $cliente);
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $rango['minimo'] = $fila->minimo;
                $rango['maximo'] = $fila->maximo;
                $descuentos[$fila->idProductoDescuento] = $rango;
            }
        }

        return $descuentos;
    }

    /**
     * Regresa lista de grupos activos
     *
     * @param integer $idProducto Identificador de producto
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function listaGruposActivos ($idProducto)
    {
        $datos = array();
        $where = array('pg.activo' => 1);
        $this->db->join(TBL_PRODUCTOGRUPOALIAS.' pga', "pg.idProductoGrupo = pga.idProductoGrupo AND pga.idProducto = "
            .$idProducto, "LEFT");
        $query = $this->db->select(
            'pg.idProductoGrupo, IFNULL(pga.idProductoGrupoAlias, 0)AS idProductoGrupoAlias, IFNULL(pga.alias, pg.descripcion)AS grupo', false
        )->get_where(TBL_PRODUCTOGRUPO." pg", $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Genera un array que contiene el identificador del precio y el importe
     *
     * @param integer $producto Identificador del producto
     * @param integer $club     Identificador del club
     * @param integer $cliente  Identificador del rol de cliente
     * @param integer $esquema  Identificador del esquema de pago
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaPrecios($producto, $club, $cliente, $esquema, $fechaVigencia='')
    {
        $precios = array();

        settype($producto, 'integer');
        settype($club, 'integer');
        settype($cliente, 'integer');
        settype($esquema, 'integer');

        if ($fechaVigencia == "") {
            $fechaVigencia = date('Y-m-d');
        }

        $this->db->select('pp.idProductoPrecio, pp.importe');
        $this->db->from(TBL_PRODUCTOPRECIO.' pp');
        $this->db->join(TBL_PRODUCTOUN.' pu', 'pp.idProductoUn = pu.idProductoUn');
        $this->db->join(TBL_PRODUCTO.' p', 'p.idProducto = pu.idProducto');
        $this->db->where('p.activo', 1);
        $this->db->where('pu.activo', 1);
        $this->db->where('pp.activo', 1);
        $this->db->where('p.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pu.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pp.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pp.finVigencia >=', date('Y-m-d'));
        $this->db->where('p.idProducto', $producto);
        $this->db->where('pu.idUn', $club);
        $this->db->where('pp.idTipoRolCliente', $cliente);
        $this->db->where('pp.idEsquemaPago', $esquema);
        $query = $this->db->get();

        if ($query->num_rows == 0) {
            $this->db->select('pp.idProductoPrecio, pp.importe');
            $this->db->from(TBL_PRODUCTOPRECIO.' pp');
            $this->db->join(TBL_PRODUCTOUN.' pu', 'pp.idProductoUn = pu.idProductoUn');
            $this->db->join(TBL_PRODUCTO.' p', 'p.idProducto = pu.idProducto');
            $this->db->where('p.activo', 1);
            $this->db->where('pu.activo', 1);
            $this->db->where('pp.activo', 1);
            $this->db->where('p.fechaEliminacion', '0000-00-00 00:00:00');
            $this->db->where('pu.fechaEliminacion', '0000-00-00 00:00:00');
            $this->db->where('pp.fechaEliminacion', '0000-00-00 00:00:00');
            $this->db->where('pp.finVigencia >=', date('Y-m-d'));
            $this->db->where('p.idProducto', $producto);
            $this->db->where('pu.idUn', $club);
            $this->db->where('pp.idTipoRolCliente', PRECIO_PUBLICOGENERAL);
            $this->db->where('pp.idEsquemaPago', $esquema);
            $query = $this->db->get();
        }
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $precios[$fila->idProductoPrecio] = $fila->importe;
            }
        }

        return $precios;
    }

    /**
     * [listaPreciosPublicar description]
     *
     * @param  integer $idUn [description]
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaPreciosPublicar($idUn,$periodo)
    {
        settype($idUn, 'integer');
        $lista = array();

        if ($idUn==0) {
            return $lista;
        }
        $finVigencia = $periodo.'-12-31';
        $sql =  "SELECT * FROM (
                SELECT p.idProducto, p.nombre, pp.importe
                FROM producto p
                INNER JOIN productoun pu ON pu.idProducto=p.idProducto AND pu.activo=1
                    AND pu.fechaEliminacion='0000-00-00 00:00:00'
                    AND pu.idUn=$idUn
                INNER JOIN productoprecio pp ON pp.idProductoUn=pu.idProductoUn
                    AND pp.activo=1 AND pp.fechaEliminacion='0000-00-00 00:00:00'
                    AND '$finVigencia' BETWEEN pp.inicioVigencia AND pp.finVigencia
                    AND pp.idEsquemaPago=1 AND pp.idTipoRolCliente=9
                WHERE p.publicarPrecio=1 AND p.activo=1 AND p.fechaEliminacion='0000-00-00 00:00:00'
                ORDER BY pp.idProductoPrecio DESC
            ) a GROUP BY a.idProducto ORDER BY a.nombre";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $e['nombre'] = $fila->nombre;
                $e['importe'] = $fila->importe;
                $lista[] = $e;
            }
        }

        return $lista;
    }

    /**
     * Busca los productos con estatus de activos y regresa un array con el id y el nombre de los productos
     *
     * @param integer $idUn           Id del club a filtrar
     * @param boolean $todos          Indica si se debe agrega administracion como opción de "Todos"
     * @param integer $idTipoProducto Identificador de tipo de producto a filtrar
     *
     * @author Jorge Cruz
     *
     * @return array - Regresa un array con la lista de clubs activos
     */
    public function listaProducto($idUn = 0, $todos = false, $idTipoProducto = TIPO_PRODUCTO_MEMBRESIA)
    {
        settype($idUn, 'integer');
        settype($idTipoProducto, 'integer');

        $lista = array();
        $lista['0'] = '';

        if ($idUn > 0) {
            $sql="
                SELECT pu.idProductoUn, p.nombre
                FROM productoun pu
                LEFT JOIN producto p ON p.idProducto = pu.idProducto
                WHERE pu.idUn = '".$idUn."'
                AND p.idTipoProducto = '".$idTipoProducto."'
                AND p.fechaEliminacion = '0000-00-00 00:00:00'
                AND pu.fechaEliminacion = '0000-00-00 00:00:00'
                AND p.activo = 1
                AND pu.activo = 1
                ORDER BY p.nombre;";
            $query = $this->db->query($sql);

            if ($query->num_rows > 0) {
                foreach ($query->result() as $fila) {
                    $lista[$fila->idProductoUn] = $fila->nombre;
                }
            }
        }
        return $lista;
    }

    /**
     * Regresa el listado de opciones disponibles para la tienda
     *
     * @param integer $filtro1   Indica el valor del primer filtro en db
     * @param integer $filtro2   Indica el valor del segundo filtro en db
     * @param integer $filtro3   Indica el valor del tercer filtro en db
     * @param integer $filtro4   Indica el valor del cuarto filtro en db
     * @param integer $filtro5   Indica el valor del quinto filtro en db
     * @param integer $filtro6   Indica el valor del sexto filtro en db
     * @param integer $orden     Indica el orden para el select
     * @param integer $posicion  Numero de elemenmtos para hacer LIMIT
     * @param integer $elementos Numero de elementos a seleccionar
     *
     * @return array
     */
    public function listaTienda($filtro1 = '', $filtro2 = '', $filtro3 = '', $filtro4 = '', $filtro5 = '', $filtro6 = '', $orden = '', $posicion = 0, $elementos = 200, $filtro7 = '',$filtro8=0, $filtro9=0)
    {
        $data = null;
        $tabla = "producto a";

        if ($orden == null) {
            $orden = 'a.nombre ';
        }

        $this->db->select('a.idProducto, a.nombre, a.descripcion, a.activo, a.rutaImagen, a.idCategoria');
        $this->db->join('categoria b', 'a.idCategoria= b.idCategoria');
        $this->db->join('tipoproducto c', 'a.idTipoProducto = c.idTipoProducto');

        if ($filtro6 > 0) {
            $this->db->join('productoun d', 'a.idProducto = d.idProducto AND d.activo = 1');
            $this->db->join('un e', 'e.idUn = d.idUn');
            $this->db->where('d.idUn', $filtro6);
        }
        if ($filtro1 <> '') {
            $this->db->like('a.nombre', $filtro1);
        }
        if ($filtro2 <> '' && $filtro2<>0) {
            $this->db->where('a.idCategoria', $filtro2);
        }
        if ($filtro3 <> '') {
            $this->db->where('a.activo', $filtro3);
        }
        if ($filtro4 and $filtro5) {
            $this->db->where("('".$filtro4."' BETWEEN a.inicioVigencia AND a.finVigencia OR a.permanente=1)" );
        }
        if ($filtro7 <> '') {
            $this->db->where($filtro7);
        }

        if ($filtro8 <> 0) {
            $this->db->where('c.idTipoProducto', $filtro8);
        }
        if ($filtro9 <> 0) {
            $this->db->where('a.idProducto', $filtro9);
        }
        $this->db->where('a.fechaEliminacion', "0000-00-00 00:00:00");
        $this->db->where('b.fechaEliminacion', "0000-00-00 00:00:00");
        $this->db->where('c.activo', 1);
        $this->db->where('b.activo', 1);
		#omitir cargo de tienda
        $this->db->where('a.idProducto <>','390');
        $query = $this->db->distinct()->order_by($orden)->get($tabla, $elementos, $posicion);

        if ($query->num_rows) {
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
    public function listadoTabla($campos, $whereCampo, $whereValor, $orden)
    {
        $data = null;
        $this->db->select($campos);
        if ($whereCampo!= "" and $whereValor!="") {
            $this->db->where($whereCampo, $whereValor);
        }
        $query = $this->db->order_by($orden)->get(TBL_PRODUCTO);
        if ($query->num_rows>0) {
            foreach ($query->result() as $fila) {
                $data[] = $fila;
            }
        }
        return $data;
    }

    /**
     * Registra el valor de un combo box en la base de datos
     *
     * @param integer $msin    Id del paquete msi a procesar
     * @param integer $banco   Valor del inst. seleccionado
     * @param integer $estatus Status para el registro
     * @param integer $idUn    Unidad de negocio asociada
     *
     * @return boolean
     */
    public function modelGuardaProductoMsi($producto, $banco, $estatus, $idUn)
    {
        $relacion = 0;

        $this->db->select('idProductoMSI');
        $this->db->from('productomsi');
        $this->db->where('idPeriodoMsi', $banco);
        $this->db->where('idUn', $idUn);
        $this->db->where('idProducto', $producto);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $relacion = $fila["idProductoMSI"];
        }

        if ($relacion == 0) {
            $datos = array (
                'idPeriodoMsi'=> $banco,
                'idUn'        => $idUn,
                'idProducto'  => $producto,
                'activo'      => $estatus
            );
            $this->db->insert('productomsi', $datos);
        } else {
            $datos = array (
                'activo' => $estatus
            );
            $this->db->where('idProductoMSI', $relacion);
            $this->db->update('productomsi', $datos);
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        }
        return true;
    }

    /**
     * Obtiene el nombre de un producto
     *
     * @param integer $idProducto Id del producto
     *
     * @return string
     */
    public function nombre($idProducto)
    {
        settype($idProducto, 'integer');

        if ($idProducto == 0) {
            return 0;
        }

        $this->db->select('nombre');
        $this->db->from(TBL_PRODUCTO);
        $where = array('idProducto' => $idProducto);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows()>0) {
            foreach ($query->result() as $fila) {
                $nombre = $fila->nombre;
            }
        }
        if (isset($nombre)) {
            return $nombre;
        } else {
            return 0;
        }
    }

    /**
     * Regresa identificador de producto
     *
     * @param integer $idProductoUn Identificador de productoun
     * @param integer $idProductoPrecio    Identificador de productoprecio
     * @param integer $idProductoDescuento Identificador de productodescuento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenIdProducto ($idProductoUn = 0, $idProductoPrecio = 0, $idProductoDescuento = 0, $idProductoPuntos = 0)
    {
        settype($idProductoUn, 'integer');
        settype($idProductoPrecio, 'integer');
        settype($idProductoDescuento, 'integer');
        settype($idProductoPuntos, 'integer');

        $datos = array(
            'error'      => 1,
            'mensaje'    => 'Error faltan datos',
            'idProducto' => 0
        );
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error no se encontro registro de producto';
        $where            = array();

        if ($idProductoUn) {
            $where['idProductoUn'] = $idProductoUn;
        } elseif ($idProductoPrecio) {
            $where['pu.activo']           = 1;
            $where['pu.fechaEliminacion'] = '0000-00-00 00:00:00';

            $this->db->join(TBL_PRODUCTOPRECIO.' pp', 'pp.idProductoUn = pu.idProductoUn AND pp.idProductoPrecio = '.$idProductoPrecio, 'INNER');
        } elseif ($idProductoDescuento) {
            $where['pu.activo']           = 1;
            $where['pu.fechaEliminacion'] = '0000-00-00 00:00:00';

            $this->db->join(TBL_PRODUCTODESCUENTO.' pd', 'pd.idProductoUn = pu.idProductoUn AND pd.idProductoDescuento = '.$idProductoDescuento, 'INNER');
        } elseif ($idProductoPuntos) {
            $where['pu.activo']           = 1;
            $where['pu.fechaEliminacion'] = '0000-00-00 00:00:00';

            $this->db->join(TBL_PRODUCTOPUNTOS.' ppu', 'ppu.idProductoUn = pu.idProductoUn AND ppu.idProductoPuntos = '.$idProductoPuntos, 'INNER');
        }
        $query = $this->db->select('pu.idProducto')->get_where(TBL_PRODUCTOUN.' pu', $where);

        if ($query->num_rows) {
            $datos['error']      = 0;
            $datos['mensaje']    = '';
            $datos['idProducto'] = $query->row()->idProducto;
        }
        return $datos;
    }

    /**
     * Regresa identificador de producto
     *
     * @param integer $idProductoUn        Identificador de productoun
     * @param integer $idProductoPrecio    Identificador de productoprecio
     * @param integer $idProductoDescuento Identificador de productodescuento
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenIdUn ($idProductoUn = 0, $idProductoPrecio = 0, $idProductoDescuento = 0, $idProductoPuntos = 0)
    {
        settype($idProductoUn, 'integer');
        settype($idProductoPrecio, 'integer');
        settype($idProductoDescuento, 'integer');
        settype($idProductoPuntos, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos',
            'idUn'    => 0
        );
        $datos['error']   = 2;
        $datos['mensaje'] = 'Error no se encontro registro de producto';
        $where = array();

        if ($idProductoUn) {
            $where['idProductoUn'] = $idProductoUn;
        } elseif ($idProductoPrecio) {
            $this->db->join(TBL_PRODUCTOPRECIO.' pp', "pp.idProductoUn = pu.idProductoUn AND pu.activo = 1 AND pu.fechaEliminacion = '0000-00-00 00:00:00' AND pp.idProductoPrecio = ".$idProductoPrecio, 'INNER');
        } elseif ($idProductoDescuento) {
            $this->db->join(TBL_PRODUCTODESCUENTO.' pd', "pd.idProductoUn = pu.idProductoUn AND pu.activo = 1 AND pu.fechaEliminacion = '0000-00-00 00:00:00' AND pd.idProductoDescuento = ".$idProductoDescuento, 'INNER');
        } elseif ($idProductoPuntos) {
            $this->db->join(TBL_PRODUCTOPUNTOS.' ppu', "ppu.idProductoUn = pu.idProductoUn AND pu.activo = 1 AND pu.fechaEliminacion = '0000-00-00 00:00:00' AND ppu.idProductoPuntos = ".$idProductoPuntos, 'INNER');
        }
        $query = $this->db->select('pu.idUn')->get_where(TBL_PRODUCTOUN.' pu', $where);

        if ($query->num_rows) {
            $datos['error']   = 0;
            $datos['mensaje'] = '';
            $datos['idUn']    = $query->row()->idUn;
        }
        return $datos;
    }

    /**
     * Obtiene lista de tipo de descuentos
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTipoDescuentos ()
    {
        $datos = array();
        $where = array('activo' => 1);

        $query = $this->db->select(
            "idTipoDescuento, descripcion AS tipoDescuento", false
        )->get_where(TBL_TIPODESCUENTO, $where);

        if ($query->num_rows) {
            $datos = $query->result_array();
        }
        return $datos;
    }

    /**
     * Regresa tipo de producto
     *
     * @param integer $idProducto Identificador de producto
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTipoProducto ($idProducto)
    {
        settype($idProducto, 'integer');
        $datos = array();
        $datos['idTipoProducto'] = 0;
        $datos['tipoProducto']   = 'Seleccione';

        if (! $idProducto) {
            return $datos;
        }
        $where = array(
            'tp.activo'          => 1,
            'p.idProducto'       => $idProducto,
            'p.fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $this->db->join(TBL_TIPOPRODUCTO." tp", "p.idTipoProducto = tp.idTipoProducto", "inner");
        $query = $this->db->select(
            "p.idTipoProducto, tp.descripcion AS tipoProducto"
        )->get_where(TBL_PRODUCTO." p", $where);

        if ($query->num_rows) {
            $row                     = $query->row();
            $datos['idTipoProducto'] = $row->idTipoProducto;
            $datos['tipoProducto']   = $row->tipoProducto;
        }
        return $datos;
    }

    /**
     * Regresa tipo de producto
     *
     * @param integer $idProducto Identificador de producto
     *
     * @author Ruben Alcocer
     *
     * @return integer
     */
    public function TipoProducto ($idProducto=0)
    {
        settype($idProducto, 'integer');
        if ($idProducto == 0)
            return 0;
        $this->db->select('idTipoProducto');
        $this->db->from(TBL_PRODUCTO.' pu');
        $this->db->where('pu.idProducto', $idProducto);
        $query = $this->db->get();

        if ($query->num_rows) {
            $row = $query->row();
            return $row->idTipoProducto;
        }
        return 0;
    }

     /**
     * Regresa cuantos productos iguales existen
     *
     * @param integer $idProducto Identificador de producto
     *
     * @author Ruben Alcocer
     *
     * @return integer
     */
    public function comparteProducto ($idProducto, $idTipoProducto)
    {
        settype($idProducto, 'integer');
        settype($idTipoProducto, 'integer');
        $idEmpresaGrupo = $this->session->userdata('idEmpresaGrupo');

        $this->db->select('idTipoProducto');
        $this->db->from(TBL_PRODUCTO.' p');
        $this->db->join(TBL_PRODUCTOUN." pu", "pu.idProducto=p.idproducto", "inner");
        $this->db->join(TBL_UN." u", "u.idUn=pu.idUn", "inner");
        $this->db->join(TBL_EMPRESA." e", "e.idEmpresa=u.idEmpresa", "inner");
        $this->db->where('p.idTipoProducto', $idTipoProducto);
        $this->db->where('p.idProducto', $idProducto);
        $this->db->group_by('e.idEmpresaGrupo');

        $query = $this->db->get();

        return $query->num_rows();
    }

    /**
     * Obtiene unidades de negocio que tiene relacion con el evento
     *
     * @param integer $idEmpresa Id de empresa a filtrar
     * @param integer $idEvento  Id de evento a filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return void
     */
    public function obtenUnEvento ($idEmpresa, $idEvento)
    {
        $lista = array();

        $query = $this->db->query(
            "SELECT ".TBL_UN.".idUn,
            CASE WHEN ".TBL_UN.".idTipoUn = 1
                THEN 'Todos'
                ELSE ".TBL_UN.".nombre
            END AS 'nombre'
            FROM ".TBL_UN."
            INNER JOIN ".TBL_EVENTOUN."
            ON ".TBL_EVENTOUN.".idUn = ".TBL_UN.".idUn
            WHERE ".TBL_EVENTOUN.".activo = '1'
            AND ".TBL_UN.".activo = '1'
            AND ".TBL_EVENTOUN.".idEvento = '".$idEvento."'
            AND ".TBL_UN.".idEmpresa = '".$idEmpresa."'
            ORDER BY ".TBL_UN.".idTipoUn, ".TBL_UN.".nombre"
        );

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idUn] = $fila->nombre;
            }
        }
        return $lista;
    }

    /**
     * Obtener producto un
     *
     * @param integer $producto     Producto a ser utlizado
     * @param integer $un           Unidad de negocio asociada
     * @param boolean $validaActivo Bandera para saber si se requiere o no un registro activo
     *
     * @return array
     */
    public function obtenerProductoUn($producto, $un, $validaActivo = true)
    {
        settype($producto, 'integer');
        settype($un, 'integer');
        if ($producto == 0) {
            return false;
        }
        if ($un == 0) {
            return false;
        }

        $this->db->select('idProductoUn, activo');
        $this->db->from(TBL_PRODUCTOUN.' pu');
        $this->db->where('pu.idProducto', $producto);
        $this->db->where('pu.idUn', $un);
        $this->db->where('pu.fechaEliminacion', '0000-00-00 00:00:00');
        if ($validaActivo) {
            $this->db->where('pu.activo', 1);
        }
        $query = $this->db->get();
        if ($query->num_rows>0) {
            return $query->row_array();
        } else {
            return false;
        }
    }

    /**
     * Obtiene el club al cual esta asignado el descuento
     *
     * @param integer $precio Identificador del descuento
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function obtenerUnDescuento($descuento)
    {
        settype($descuento, 'integer');

        if ($descuento == 0) {
            return null;
        }

        $this->db->select('pu.idUn');
        $this->db->from(TBL_PRODUCTOUN.' pu');
        $this->db->join(TBL_PRODUCTODESCUENTO.' pd', 'pd.idProductoUn = pu.idProductoUn');
        $this->db->where('pu.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pd.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pd.idProductoDescuento', $descuento);
        $query =  $this->db->get();
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila['idUn'];
        } else {
            return null;
        }
    }


    /**
     * Obtiene el club al cual esta asignado el precio
     *
     * @param integer $precio Identificador del precio
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function obtenerUnPrecio($precio)
    {
        settype($precio, 'integer');

        if ($precio == 0) {
            return null;
        }

        $this->db->select('pu.idUn');
        $this->db->from(TBL_PRODUCTOUN.' pu');
        $this->db->join(TBL_PRODUCTOPRECIO.' pp', 'pp.idProductoUn = pu.idProductoUn');
        $this->db->where('pu.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pp.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('pp.idProductoPrecio', $precio);
        $query =  $this->db->get();
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila['idUn'];
        } else {
            return null;
        }
    }

    /**
     * Obtienen nombte o en su caso alias de un grupo
     *
     * @param integer $idProducto Identificador de producto
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtieneGrupoNombreAlias ($idProducto, $idProductoGrupo)
    {
        settype($idProducto, 'integer');
        settype($idProductoGrupo, 'integer');

        $datos = array();

        if ( ! $idProducto) {
            return $datos;
        }
        $where = array(
            'pg.activo'          => 1,
            'pg.idProductoGrupo' => $idProductoGrupo
        );
        $this->db->join(TBL_PRODUCTOGRUPOALIAS.' pga', "pg.idProductoGrupo = pga.idProductoGrupo AND pga.idProducto = ".$idProducto, "LEFT");
        $query = $this->db->select(
            'pg.idProductoGrupo, IFNULL(pga.alias, pg.descripcion)AS grupo', false
        )->get_where(TBL_PRODUCTOGRUPO." pg", $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Obtiene grupo de productoun
     *
     * @param integer $idProductoUn Identificador de productoun
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtieneGrupoProductoUn ($idProductoUn, $validaActivo = true)
    {
        settype($idProductoUn, 'integer');

        $datos = array(
            'idProductoGrupo' => 0,
            'grupo'           => '',
            'error'           => 1,
            'mensaje'         => 'Error faltan datos'
        );

        if ( ! $idProductoUn) {
            return $datos;
        }
        $where = array(
            'pu.idProductoUn'     => $idProductoUn,
            'pu.fechaEliminacion' => '0000-00-00 00:00:00'
        );
        if ($validaActivo) {
            $datos['pu.activo'] = 1;
        }
        $this->db->join(TBL_PRODUCTOGRUPO.' pg', "pg.idProductoGrupo = pu.idProductoGrupo", 'INNER');
        $this->db->join(TBL_PRODUCTOGRUPOALIAS.' pga', "pga.idProductoGrupo = pg.idProductoGrupo AND pu.idProducto = pga.idProducto", 'LEFT');

        $query = $this->db->select(
            'pu.idProductoGrupo, IFNULL(pga.idProductoGrupoAlias, 0)AS idProductoGrupoAlias, IFNULL(pga.alias, pg.descripcion)AS grupo', false
        )->get_where(TBL_PRODUCTOUN.' pu', $where);

        if ($query->num_rows) {
            $datos = $query->row_array();
        } else {
            $datos['mensaje'] = 'Error no se encontro grupo';
            $datos['error']   = 2;
        }
        return $datos;
    }

    /**
     * Obtiene log del producto
     *
     * @param integer $totales    Bandera de totales
     * @param integer $idProducto Indentificador de producto
     * @param integer $registros  Variable de limite de registros
     * @param integer $posicion   Variable de posicion
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtieneLogProducto ($totales, $idProducto, $registros = REGISTROS_POR_PAGINA, $posicion = 0, $orden = 'l.fecha', $direccion = 'DESC')
    {
        $where = array(
            'l.idLogCategoria' => LOG_PRODUCTO,
            'l.idProducto'     => $idProducto
        );
        if ($totales) {
            $registros = null;
            $posicion  = null;
            $datos     = 0;
        } else {
            $datos = array();
        }
        $this->db->join(TBL_USUARIOS.' u', 'u.IdUsuario = l.idUsuario', 'INNER');
        $this->db->join(TBL_PERSONA.' p', 'p.idPersona = l.idPersona', 'INNER');
        $this->db->join(TBL_UN, 'un.idUn = l.idUn', 'INNER');

        $query = $this->db->select(
            "l.idLog, l.fecha, un.nombre AS club, Concat('(', u.NombreUsuario, ')',  p.nombre, ' ', p.paterno, ' ', p.materno) AS nombre, l.descripcion",
            false
        )->order_by($orden, $direccion)->group_by('l.idLog')->get_where(TBL_LOG.' l', $where, $registros, $posicion);

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
     * Obtiene el valor de campo solicitado dentro del catalogo referido
     *
     * @param integer $id    ID del catalogo a procesar
     * @param integer $campo Nombre del campo a devolver
     *
     * @return string
     */
    public function opcionesCampo($id, $campo)
    {
        if ($this->db->field_exists($campo, TBL_PRODUCTO) == false) {
            return null;
        }

        $this->db->select($campo);
        $query = $this->db->where("idProducto", $id)->get(TBL_PRODUCTO);

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila[$campo];
        }
        return null;
    }


    /**
     *
     * @param integer $precio
     * @param string $campo
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function precioCampo($precio, $campo)
    {
        settype($precio, 'integer');
        if ($precio == 0) {
            return null;
        }
        if ($campo == "") {
            return null;
        }
        $this->db->select($campo);
        $query = $this->db->where('idProductoPrecio', $precio)->get(TBL_PRODUCTOPRECIO);
        if ($query->num_rows>0) {
            $fila = $query->row_array();
            return $fila[$campo];
        }
        return null;
    }


    /**
     * Regresa el precio de un producto
     *
     * @param integer $idProducto         Identificador de producto
     * @param integer $idUn               Identificador de unidad de negocio
     * @param integer $idTipoRolCliente   Valor general de idTipoRolCliente que arroja el buscador de personas
     * @param integer $idEsquemaPago      Identificador de esquemapago
     * @param string  $fechaVigencia      Filtro para vigencia
     * @param integer $idTipoMembresia    Identificador de tipomembresia
     * @param integer $unidades           Numero de unidades para el precio
     * @param integer $idCuentaContable   Identificador de cuenta contable
     * @param integer $usarGeneral        Bandera para usar precio general si no se encuentra el que se busca
     * @param integer $idTipoCliente      Identificador de tipocliente
     * @param integer $idTipoRolClienteBD Valor real de la BD de idTipoRolCliente
     * @param boolean $validaActivo       Bandera para validar precio activo
     * @param boolean $idProductoGrupo    Identificador de grupo del productoprecio
     * @param boolean $idCuentaProducto   Identificador de cuentaproducto
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public static function precio ($idProducto, $idUn, $idTipoRolCliente = ROL_CLIENTE_NINGUNO, $idEsquemaPago = ESQUEMA_PAGO_CONTADO
    /*,
        $fechaVigencia = '', $idTipoMembresia = 0, $unidades = 1, $idCuentaContable = 0, $usarGeneral = 1, $idTipoCliente = 0,
        $idTipoRolClienteBD = 0, $periodo = 0, $validaActivo = true, $idProductoGrupo = '', $idCuentaProducto = 0, $fidelidad=''*/
        )
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');

        $datos = array();
        $datos['monto']           = '0.00';
        $datos['id']              = 0;
        $datos['idCta']           = 0;
        $datos['idCtaProd']       = 0;
        $datos['cuenta']          = '';
        $datos['cuentaProducto']  = '';
        $datos['error']           = 0;
        $datos['mensaje']         = '';
        $datos['numCuenta']       = 0;
        $datos['numCuentaProducto']  = 0;
        $datos['activo']          = 0;
        $datos['query']           = array();
        //$fechaVigencia            = ($fechaVigencia == '') ? date('Y-m-d') : $fechaVigencia;

        if (! $idProducto or ! $idUn) {
            $datos['error']   = 1;
            $datos['mensaje'] = 'Faltan datos para consulta';

            return $datos;
        }

        // $query = DB::connection('crm')->table(TBL_PRODUCTOPRECIO. ' AS  pp');
      /*  if ($idCuentaContable) {
            $query = $query->where('pp.idCuentaContable', $idCuentaContable);
        }
        if ($idCuentaProducto) {
            $query = $query->where('pc.idCuentaProducto', $idCuentaProducto);
        }


        if ($periodo > 0) {
            $where[] = ['pp.finVigencia','<=',$periodo.'-12-31'];
        } else {
            $query = $query->where(  DB::connection('crm')->raw("DATE('".$fechaVigencia."') BETWEEN inicioVigencia AND finVigencia"));
        }
        if ($validaActivo) {
            $where[] = ['pp.activo','=',1];
        }
        if ($idProductoGrupo != '') {
            $where[] = ['pp.idProductoGrupo','=',$idProductoGrupo];
        }
        if ($fidelidad!='') {
            $query = $query->where('pp.idTipoFidelidad', $fidelidad);
        }*/

        $sql= "SELECT pp.importe, pp.idProductoPrecio AS id, pp.idCuentaContable, CONCAT('(', cc.numCuenta, ') ', cc.descripcion) AS cuenta,cc.numCuenta, cp.idCuentaProducto, CONCAT('(', cp.cuentaProducto, ') ', cp.descripcion) AS cuentaProducto, IFNULL(cp.cuentaProducto, '') AS numCuentaProducto, pp.activo
            from productoprecio as pp
            JOIN productoUn as  pu ON pu.idProductoUn = pp.idProductoUn
                AND pu.idProductoGrupo = pp.idProductoGrupo
                AND pu.eliminado = 0
                AND pu.activo = 1
                AND pu.idProducto       = {$idProducto}
                AND pu.idUn             = {$idUn}
            JOIN un as u ON u.idUn = pu.idUn
                -- AND u.fechaEliminacion = '0000-00-00 00:00:00'
                AND u.eliminado = 0
                AND u.activo = 1
            JOIN cuentacontable AS cc ON pp.idCuentaContable = cc.idCuentaContable
            JOIN cuentaproducto AS cp ON pp.idCuentaProducto = cp.idCuentaProducto
            WHERE 1 = 1
            AND pp.eliminado        = 0
            AND pp.activo=1
            AND pp.idTipoRolCliente = {$idTipoRolCliente}
            AND pp.idEsquemaPago    = {$idEsquemaPago}
            AND pp.idTipoMembresia  = 0
            AND pp.unidades         = 1
            order by pp.idProductoPrecio DESC ";
        $query= DB::connection('crm')->select($sql);

        if (count($query)>0) {
            $fila                       = $query[0];
            $datos['monto']             = number_format($fila->importe, 2, '.', '');
            $datos['id']                = $fila->id;
            $datos['idCta']             = $fila->idCuentaContable;
            $datos['idCtaProd']         = $fila->idCuentaProducto;
            $datos['cuenta']            = $fila->cuenta;
            $datos['cuentaProducto']    = $fila->cuentaProducto;
            $datos['numCuenta']         = $fila->numCuenta;
            $datos['numCuentaProducto'] = $fila->numCuentaProducto;
            $datos['activo']            = $fila->activo;
        }

        return $datos;
    }


    /**
     * [productoActividadDeportiva description]
     *
     * @param  [type] $idcategoria [description]
     *
     * @return [type]              [description]
     */
    public function productoActividadDeportiva($idcategoria)
    {
        $this->db->select('idActividadDeportiva,descripcion');
        $this->db->from(TBL_ACTIVIDADDEPORTIVA);
        $where = array('idCategoriaDeportiva'=>$idcategoria,'activo'=> 1 );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $dato=array();
            foreach ($query->result() as $fila) {
                $dato[$fila->idActividadDeportiva]= $fila->descripcion;
            }
            return $dato;
        } else {
            return null;
        }
    }

    /**
     * Obtiene la disponibilidad de productos premio
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function productoDisponibilidad($idProducto)
    {
        settype($idProducto, 'integer');

        if($idProducto == 0){
            return false;
        }

        $this->db->select('p.idProducto,pld.disponibilidad');
        $this->db->from(TBL_PRODUCTO.' p');
        $this->db->join(TBL_PRODUCTOLEALTADDISPONIBILIDAD.' pld','pld.idProducto = p.idProducto and pld.fechaEliminacion = \'0000-00-00 00:00:00\'');
        $this->db->where('p.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('p.idCategoria', CATEGORIA_PREMIO);
        $this->db->where('p.idTipoProducto', TIPO_PRODUCTO_PREMIO);
        $this->db->where('p.idProducto', $idProducto);
        $this->db->where('p.inicioVigencia <= ', date('Y-m-d'));
        $this->db->where('(p.finVigencia >=date(now()) or p.finVigencia= \'0000-00-00 00:00:00\')');

        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                return $fila->disponibilidad;
            }
        }
        return false;
    }

    /**
     * Regresa los puntos de un producto
     *
     * @param integer $idProducto          Identificador de producto
     * @param integer $idUn                Identificador de unidad de negocio
     * @param integer $idTipoRolCliente    Valor general de idTipoRolCliente que arroja el buscador de personas
     * @param integer $idEsquemaPago       Identificador de esquemapago
     * @param string  $fechaVigencia       Filtro para vigencia
     * @param integer $unidades            Valor de total de unidades
     * @param integer $usarGeneral         Bandera para usar precio general si no se encuentra el que se busca
     * @param integer $idTipoCliente       Identificador de tipocliente
     * @param integer $idTipoRolClienteBD  Valor real de la BD de idTipoRolCliente
     * @param boolean $validaActivo        Bandera para validar puntos activos
     * @param string  $idProductoGrupo     Identificador de productogrupo
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function puntos ($idProducto, $idUn, $idTipoRolCliente = ROL_CLIENTE_NINGUNO, $idEsquemaPago = ESQUEMA_PAGO_CONTADO, $fechaVigencia = '', $unidades = 1, $usarGeneral = 0, $idTipoCliente = 0, $idTipoRolClienteBD = 0, $periodo = 0, $validaActivo = true, $idProductoGrupo = '')
    {
        settype($idProducto, 'integer');
        settype($idUn, 'integer');
        settype($idTipoRolCliente, 'integer');
        settype($unidades, 'integer');
        settype($idEsquemaPago, 'integer');
        settype($idProductoGrupo, 'string');

        $datos               = array();
        $datos['puntos']     = '0';
        $datos['porcentaje'] = '0.00';
        $datos['id']         = 0;
        $datos['error']      = 0;
        $datos['activo']     = 0;
        $fechaVigencia       = ($fechaVigencia == '') ? date('Y-m-d') : $fechaVigencia;
        $datos['query']      = '';

        if (! $idProducto or ! $idUn) {
            $datos['error'] = 1;
            $datos['mensaje'] = 'Faltan datos para consulta';
            return $datos;
        }
        $where = array(
            'pp.fechaEliminacion' => '0000-00-00 00:00:00',
            'pu.fechaEliminacion' => '0000-00-00 00:00:00',
            'pu.activo'           => 1,
            'pu.idProducto'       => $idProducto,
            'pu.idUn'             => $idUn,
            'pp.idTipoRolCliente' => $idTipoRolCliente,
            'pp.unidades'         => $unidades,
            'pp.idEsquemaPago'    => $idEsquemaPago
        );

        $datosTipoProducto = $this->obtenTipoProducto($idProducto);
        $idTipoProducto = $datosTipoProducto['idTipoProducto'];

        if ($periodo > 0) {
            $where['pp.inicioVigencia >='] = $periodo.'-01-01';
            $where['pp.finVigencia <=']    = $periodo.'-12-31';
        } else {
            $this->db->where("((DATE('".$fechaVigencia."') BETWEEN inicioVigencia AND finVigencia) OR (pp.finVigencia='0000-00-00'))");
        }
        if ($validaActivo) {
            $where['pp.activo'] = 1;
        }
        if ($idProductoGrupo != '') {
            $where['pp.idProductoGrupo'] = $idProductoGrupo;
        }

        $this->db->join(TBL_PRODUCTOUN.' pu', 'pu.idProductoUn = pp.idProductoUn AND pu.idProductoGrupo = pp.idProductoGrupo', 'inner');
        $query = $this->db->select(
            "pp.puntos, pp.porcentaje, pp.idProductoPuntos AS id, pp.activo",
            false
        )->order_by('pp.idProductoPuntos', 'DESC')->get_where(TBL_PRODUCTOPUNTOS.' pp', $where);


        if ($query->num_rows) {
            $fila                = $query->row_array();
            $datos['puntos']     = $fila["puntos"];
            $datos['porcentaje'] = number_format($fila["porcentaje"], 2);
            $datos['id']         = $fila["id"];
            $datos['activo']     = $fila['activo'];
        } else {
            if ($usarGeneral) {
                $datos['id'] = 0;

                if ( ! $datos['id']) {
                    $CI =& get_instance();
                    $CI->load->model('un_model');

                    $idEmpresa = $CI->un_model->obtenerEmpresa($idUn);
                    $idUnAdmin = $CI->un_model->obtenUnAdiministracion($idEmpresa);

                    if ($idUn != $idUnAdmin) {
                        $datos = $this->puntos($idProducto, $idUnAdmin, $idTipoRolCliente, $idEsquemaPago, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                    }
                    if (! $datos['id'] and $idTipoCliente) {
                        $CI->load->model('tipocliente_model');
                        $rolBase   = $CI->tipocliente_model->obtenRolBase($idTipoCliente);

                        if (! $datos['id'] and ($rolBase != $idTipoRolCliente)) {
                            $datos = $this->puntos($idProducto, $idUn, $rolBase, $idEsquemaPago, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                            if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                                $datos = $this->puntos($idProducto, $idUnAdmin, $rolBase, $idEsquemaPago, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                            }
                        }
                        if (! $datos['id'] and $idTipoRolClienteBD and ($idTipoRolClienteBD != $idTipoRolCliente) and ($idTipoRolClienteBD != $rolBase)) {
                            $datos = $this->puntos($idProducto, $idUn, $idTipoRolClienteBD, $idEsquemaPago, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                            if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                                $datos = $this->puntos($idProducto, $idUnAdmin, $idTipoRolClienteBD, $idEsquemaPago, $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                            }
                        }
                    }
                    if (! $datos['id']) {
                        $datos = $this->puntos($idProducto, $idUn, ROL_CLIENTE_NINGUNO, $idEsquemaPago, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                        if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                            $datos = $this->puntos($idProducto, $idUnAdmin, ROL_CLIENTE_NINGUNO, $idEsquemaPago, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                        }
                    }
                    if (! $datos['id']) {
                        $datos = $this->puntos($idProducto, $idUn, $idTipoRolCliente, 0, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                        if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                            $datos = $this->puntos($idProducto, $idUnAdmin, $idTipoRolCliente, 0, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                        }
                    }
                    if (! $datos['id']) {
                        $datos = $this->puntos($idProducto, $idUn, ROL_CLIENTE_NINGUNO, 0, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                        if (! $datos['id'] and ($idUn != $idUnAdmin)) {
                            $datos = $this->puntos($idProducto, $idUnAdmin, ROL_CLIENTE_NINGUNO, 0, $fechaVigencia = '', $unidades, 0, $idTipoCliente, $idTipoRolClienteBD, $periodo, $validaActivo, $idProductoGrupo);
                        }
                    }
                }
            }
        }
        return $datos;
    }

    /**
     * Obtiene el total de opciones activas en el catalogo indicado
     *
     * @return integer
     */
    public function totalOpciones($nombre, $categoria, $estatus, $fechaInicio, $fechaFin, $club, $vigente,$tipo=0)
    {
        $this->db->select('a.idProducto, a.nombre, c.descripcion, b.nombre as Categoria, a.activo');
        $this->db->join('categoria b', 'a.idCategoria= b.idCategoria');
        $this->db->join('tipoproducto c', 'a.idTipoProducto = c.idTipoProducto');


        if ($club > 0) {
            $this->db->join('productoun d', 'a.idProducto = d.idProducto');
            $this->db->join('un e', 'e.idUn = d.idUn AND d.activo=1');
            $this->db->where('d.idUn', $club);
            $this->db->where('d.fechaEliminacion', '0000-00-00 00:00:00');
        }else{
            //  RBN: INICIA -> Lista() de Articulos que pertenecen al idProductoGrup del user actual
            $this->db->join('productoun d', 'a.idProducto = d.idProducto');
            $this->db->join('un e', 'e.idUn = d.idUn');
            $this->db->join('empresa emp', 'emp.idempresa=e.idEmpresa');
            $this->db->where('emp.idEmpresaGrupo', $this->session->userdata('idEmpresaGrupo'));
            $this->db->where('d.fechaEliminacion', '0000-00-00 00:00:00');
            //  RBN: FIN -> Lista de Articulos que pertenecen al idProductoGrup del user actual
        }
        if ($nombre<>'') {
            $this->db->like('a.nombre', $nombre);
        }
        if ($categoria<>'' && $categoria<>0) {
            $this->db->where('a.idCategoria', $categoria);
        }
        if ($estatus<>'') {
            $this->db->where('a.activo', $estatus);
        }
        if ($fechaInicio<>'') {
            $this->db->where('a.inicioVigencia >=', $fechaInicio);
        }
        if ($fechaFin<>'') {
            $this->db->where('a.inicioVigencia <=', $fechaFin);
        }
        if ($vigente <> '') {
            $this->db->where($vigente);
        }
        if ($tipo <> 0) {
            $this->db->where('c.idTipoProducto', $tipo);
        }

        $this->db->where('a.fechaEliminacion', '0000-00-00 00:00:00');
        $query =  $this->db->distinct()->get("producto a");

        return $query ->num_rows();
    }

    /**
     * Verifica si un evento esta activo en un club
     *
     * @param integer $idEvento Producto a ser utlizado
     * @param integer $idUn     Unidad de negocio asociada
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function validaEventoUn ($idEvento, $idUn)
    {
        settype($idEvento, 'integer');
        settype($idUn, 'integer');

        if (! $idEvento or ! $idUn) {
            return false;
        }
        $where = array(
            'idEvento' => $idEvento,
            'idUn' => $idUn,
            'activo' => 1,
            'fechaEliminacion' => '0000-00-00 00:00:00'
        );
        $query = $this->db->select("activo")->get_where(TBL_EVENTOUN, $where);

        return $query->num_rows ? true : false;
    }

    /**
     * Obtiene el rango de fechas de la vigencia del descuento indicado
     *
     * @param integer $precio Identificador del descuento
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function vigenciaDescuento($descuento)
    {
        settype($descuento, 'integer');
        if ($descuento == 0) {
            return null;
        }

        $this->db->select('inicioVigencia, finVigencia');
        $this->db->from(TBL_PRODUCTODESCUENTO);
        $this->db->where('idProductoDescuento', $descuento);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $resultado['inicio'] = $fila['inicioVigencia'];
            $resultado['fin']    = $fila['finVigencia'];

            return $resultado;
        } else {
            return null;
        }
    }

    /**
     * Obtiene el rango de fechas de la vigencia del precio indicado
     *
     * @param integer $precio
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function vigenciaPrecio($precio)
    {
        settype($precio, 'integer');
        if ($precio == 0) {
            return null;
        }

        $this->db->select('inicioVigencia, finVigencia');
        $this->db->from(TBL_PRODUCTOPRECIO);
        $this->db->where('idProductoPrecio', $precio);
        $this->db->where('eliminado', 0);
        $query = $this->db->get();

        if ($query->num_rows>0) {
            $fila = $query->row_array();
            $resultado['inicio'] = $fila['inicioVigencia'];
            $resultado['fin']    = $fila['finVigencia'];

            return $resultado;
        } else {
            return null;
        }
    }

    /**
     *
     * @param <type> $producto
     *
     * @author Jorge Cruz
     *
     * @return <type>
     */
    public function vigenciaProducto($idProducto)
    {
        settype($idProducto, 'integer');

        $datos = array();

        if ($idProducto == 0) {
            return datos;
        }
        $this->db->select('inicioVigencia, finVigencia, permanente');
        $this->db->where('idProducto', $idProducto);
        $this->db->where('eliminado', 0);
        $query = $this->db->get(TBL_PRODUCTO, 1);

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * Cambia el estatus de activo del registro indicado
     *
     * @param integer $nombreCheck Nombre del registro a revisar por repetido
     * @param integer $idCatCheck  Categoria del producto a revisar
     * @param integer $idCheck     Id del registro a revisar
     *
     * @return boolean
     */
    public function yaInsertado($nombreCheck, $idCatCheck, $idCheck)
    {
        $this->db->where('eliminado', 0);
        $this->db->where('nombre', $nombreCheck);
        $this->db->where('idCategoria', $idCatCheck);
        $this->db->where('idProducto !=', $idCheck);
        $queryR = $this->db->get(TBL_PRODUCTO);

        if ($queryR->num_rows >0) {
            return true;
        } else {
            return false;
        }
    }
}
