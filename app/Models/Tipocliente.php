<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Tipocliente extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.tipocliente';
    protected $primaryKey = 'idTipoCliente';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * Regresa un array con los tipo de cliente
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function arrayTipoCliente()
    {
        $lista = array();

        $this->db->cache_on();
        $this->db->select('idTipoCliente, descripcion');
        $this->db->where('activo', '1');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->order_by('orden')->get(self::TIPOCLIENTE);
        $this->db->cache_off();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $lista[$fila->idTipoCliente] = $fila->descripcion;
            }
        }
        return $lista;
    }

    /**
     * Regresa un array con los roles de cliente establecidos para el tipo de cliente solicitado
     *
     * @author Jorge Cruz
     *
     * @param integer $tipoCliente Identificador del tipo de cliente
     *
     * @return array
     */
    public function arrayTipoRolCliente($tipoCliente)
    {
        $lista = array();

        $this->db->cache_on();
        $this->db->select('idTipoRolCliente, descripcion, base');
        $this->db->where('idTipoCliente', $tipoCliente);
        $this->db->where('activo', '1');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->order_by('orden')->get(self::TIPOROLCLIENTE);
        $this->db->cache_off();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                if ($fila->base == 1) {
                    $lista[$fila->idTipoRolCliente] = $this->nombreTipoCliente($tipoCliente);
                } else {
                    $lista[$fila->idTipoRolCliente] = $fila->descripcion;
                }
            }
        }
        return $lista;
    }

    /**
     * Obtiene el nombre para el tipo de cliente solicitado
     *
     * @param integer $cliente Identificador del tipo de cliente
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function nombreTipoCliente($cliente)
    {
        settype($cliente, 'integer');

        $this->db->select('descripcion');
        $this->db->where('idTipoCliente', $cliente);
        $query = $this->db->get(self::TIPOCLIENTE);

        if ($query->num_rows == 0) {
            return null;
        }

        $fila = $query->row_array();
        return $fila["descripcion"];
    }

    /**
     * Obtiener el nombre del rol de cliente solicitado
     *
     * @param integer $rol Identificador del tipo de rol del cliente
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function nombreRolCliente($rol)
    {
        settype($rol, 'integer');

        $this->db->select('idTipoCliente, descripcion, base');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idTipoRolCliente', $rol);
        $query = $this->db->get(self::TIPOROLCLIENTE);

        if ($query->num_rows == 0) {
            return null;
        }

        $fila = $query->row_array();
        if ($fila["base"] == 1) {
            $id = $fila["idTipoCliente"];

            $this->db->select('descripcion');
            $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
            $this->db->where('idTipoCliente', $id);
            $query = $this->db->get(TBL_TIPOCLIENTE);

            if ($query->num_rows == 0) {
                return null;
            }

            $fila = $query->row_array();
            return $fila["descripcion"];
        } else {
            return $fila["descripcion"];
        }
        return null;
    }

    /**
     * Obtiene el identificador del tipo de persona para el rol indicado
     *
     * @param integer $rol Identificador del tipo de rol del cliente
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function origenRol($rol)
    {
        settype($rol, 'integer');

        $this->db->select('idTipoCliente');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idTipoRolCliente', $rol);
        $query = $this->db->get(self::TIPOROLCLIENTE);
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['idTipoCliente'];
        }
        return null;
    }

    /**
     * Obtiene la descripcion del idTipoRolCliente
     *
     * @param integer $rol
     *
     * @author Antonio Sixtos
     *
     * @return integer
     */
    public function tipoRolCliente($rol)
    {
        settype($rol, 'integer');

        $this->db->select('descripcion');
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idTipoRolCliente', $rol);
        $query = $this->db->get(self::TIPOROLCLIENTE);
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['descripcion'];
        }
        return null;
    }

    /**
     * Obtiene el rol base de un tipo de cliente
     *
     * @param integer $idTipoCliente Identificador de tipocliente
     *
     * @author Jonathan Alcantara
     *
     * @return string/integer
     */
    public static function obtenRolBase($idTipoCliente)
    {
        settype($idTipoCliente, 'integer');
        $resultado = 'error';

        if (!$idTipoCliente) {
            return $resultado;
        }
        $where = array(
            'idTipoCliente'    => $idTipoCliente,
            'base'             => 1,
            'fechaEliminacion' => '0000-00-00 00:00:00',
            'activo'           => 1,
        );

        $query = DB::connection('crm')->table(TBL_TIPOROLCLIENTE)->select('idTipoRolCliente');

        if ($query->count() > 0) {
            $query = $query->get();
            return $query[0]->idTipoRolCliente;
        }

        return $resultado;
    }
}
