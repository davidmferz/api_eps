<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Empleado extends Model
{

    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.empleado';
    protected $primaryKey = 'idEmpleado';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    public function scopeGetEmail($query, $idPersona)
    {
        return $query->select('mail.mail')
            ->where('empleado.idPersona', $idPersona)
            ->join('crm.mail', 'empleado.idPersona', '=', 'mail.idPersona')
            ->where('mail.idTipoMail', 37)
            ->where('mail.eliminado', 0)
            ->first();

    }

    public function scopeObtenDatosEmpleado($query, $idPersona)
    {
        return $query->selectRaw("idEmpleado,CONCAT(p.nombre,' ',p.paterno,' ',p.materno) as  nombre, idArea, empleado.rfc, imss, idTipoEstatusEmpleado, idOperador, fechaContratacion")
            ->join('crm.persona as p', 'p.idPersona', 'empleado.idPersona')
            ->where('p.idPersona', $idPersona)
            ->get()
            ->toArray();

    }

    /**
     * Actualiza las actividades deportivas de un empleado
     *
     * @param integer $idActividadDeportiva Identificador de actividad deporitva
     * @param integer $idEmpleado           Identificador de empleado
     * @param integer $idEmpleadoActividad  Identificador de empleadoactiviad
     * @param integer $activo               Bandera de activad activa o inactiva
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function actualizaActividadDeportiva($idActividadDeportiva, $idEmpleado, $idEmpleadoActividad, $activo)
    {
        settype($idActividadDeportiva, 'integer');
        settype($idEmpleadoActividad, 'integer');
        settype($activo, 'integer');

        $datos                        = array();
        $datos['idEmpleadoActividad'] = $idEmpleadoActividad;
        $datos['error']               = 1;
        $datos['mensaje']             = 'Faltan datos';

        if (!$idActividadDeportiva) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = 'La información se actualizó correctamente';

        if ($idEmpleadoActividad) {
            $where = array('idEmpleadoActividad' => $idEmpleadoActividad);
            $set   = array('activo' => $activo);

            $res = $this->db->update(TBL_EMPLEADOACTIVIDAD, $set, $where);

            if (!$res) {
                $datos['error']   = 2;
                $datos['mensaje'] = 'Error al actualizar registro';
            }
        } else {
            if ($activo) {
                $set = array(
                    'idActividadDeportiva' => $idActividadDeportiva,
                    'idEmpleado'           => $idEmpleado,
                    'activo'               => $activo,
                );
                $res = $this->db->insert(TBL_EMPLEADOACTIVIDAD, $set);

                if ($res) {
                    $datos['idEmpleadoActividad'] = $this->db->insert_id();
                } else {
                    $datos['error']   = 3;
                    $datos['mensaje'] = 'Error al insertar registro';
                }
            }
        }
        return $datos;
    }

    /**
     * Actualiza el club de un empleado
     *
     * @param integer $idEmpleadoPuesto Identificador de empleadopuesto
     * @param integer $idUn             Identificador de unidad de negocio
     * @param string  $club             Nombre del nuevo club
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaClub($idEmpleadoPuesto, $idUn, $club = '')
    {
        settype($idEmpleadoPuesto, 'integer');
        settype($idUn, 'integer');

        if (!$idEmpleadoPuesto or !$idUn) {
            return false;
        }
        $where = array('idEmpleadoPuesto' => $idEmpleadoPuesto);
        $set   = array('idUn' => $idUn);
        $res   = $this->db->update(TBL_EMPLEADOPUESTO, $set, $where);

        if ($res) {
            $this->permisos_model->log('Se actualiza club de empleado a ' . $club, LOG_EMPLEADOS);
        }
        return $res;
    }

    /**
     * Actualiza el estatus de un empleado
     *
     * @param integer $idEmpleado            Identificador de empleado
     * @param integer $idTipoEstatusEmpleado Nuevo estatus de empleado
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaEstatus($idEmpleado, $idTipoEstatusEmpleado)
    {
        settype($idEmpleado, 'integer');
        settype($idTipoEstatusEmpleado, 'integer');

        if (!$idEmpleado or !$idTipoEstatusEmpleado) {
            return false;
        }
        $where = array('idEmpleado' => $idEmpleado);
        $set   = array('idTipoEstatusEmpleado' => $idTipoEstatusEmpleado);
        $res   = $this->db->update(TBL_EMPLEADO, $set, $where);

        if ($res) {
            $this->permisos_model->log('Actualiza estatus de empleado', LOG_EMPLEADOS);
        }
        return $res;
    }

    /**
     * Actualiza el operador de un empleado
     *
     * @param  integer $idEmpleado Identificador de empleado
     * @param  integer $idOperador Identificador de operador
     *
     * @return boolean
     */
    public function actualizaOperador($idEmpleado, $idOperador)
    {
        settype($idEmpleado, 'integer');
        settype($idOperador, 'integer');

        if ($idEmpleado == 0 or $idOperador == 0) {
            return false;
        }

        $where = array('idEmpleado' => $idEmpleado);
        $set   = array('idOperador' => $idOperador);
        $res   = $this->db->update(TBL_EMPLEADO, $set, $where);

        if ($res) {
            $this->permisos_model->log('Actualiza operador de empleado', LOG_EMPLEADOS);
        }
        return $res;
    }

    /**
     * Actualiza el puesto de un empleado
     *
     * @param integer $idEmpleadoPuesto Identificador de empleadopuesto
     * @param integer $idPuesto         Identificador de puesto
     * @param string  $puesto           Nombre del puesto nuevo
     *
     * @author Jonathan Alcantara
     *
     * @return boolean
     */
    public function actualizaPuesto($idEmpleadoPuesto, $idPuesto, $puesto = '')
    {
        settype($idEmpleadoPuesto, 'integer');
        settype($idPuesto, 'integer');

        if (!$idEmpleadoPuesto or !$idPuesto) {
            return false;
        }
        $where = array('idEmpleadoPuesto' => $idEmpleadoPuesto);
        $set   = array('idPuesto' => $idPuesto);
        $res   = $this->db->update(TBL_EMPLEADOPUESTO, $set, $where);

        if ($res) {
            $this->permisos_model->log('Se actualiza puesto de empleado a ' . $puesto, LOG_EMPLEADOS);
        }
        return $res;
    }

    /**
     * Regresa arreglo de puestos
     *
     * @param string $puesto Descripcion del puesto
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function arrayPuestos($puesto)
    {
        $datos = array();

        $puesto = trim($puesto);
        if ($puesto == "") {
            return $datos;
        }

        $this->db->select('idPuesto, descripcion');
        $this->db->from(TBL_PUESTO);
        $this->db->like('descripcion', $puesto, 'both');
        $this->db->order_by('descripcion');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idPuesto] = $fila->descripcion;
            }
        }
        return $datos;
    }

    /**
     * Obtiene el detalle de evaluaciones de un empleado
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function detalleEvaluacion($idPersona, $estatus = '')
    {
        settype($idPersona, 'integer');

        if ($idPersona == 0) {
            return 0;
        }

        if ($estatus == 'Enviado') {
            $sql = "SELECT s.contratoIndeterminado,concat_ws(' ',p.nombre,p.paterno,p.materno) as empleado, s.promedio, s.fechaActualizacion,sp.descripcion as pregunta,
                    snc.descripcion as respuesta ,snc.valor, se.nombre as evaluacion,concat_ws(' ',p2.nombre,p2.paterno,p2.materno) as responsable,
                    pu.descripcion as puesto,if(s.comentarios is null,'Ninguno',s.comentarios) as comentarios, e.fechaContratacion, s.fechaEnvioEvaluacion,
                    s.fechaActualizacion, s.estatus
                from " . TBL_SATISFACCIONEMPLEADOEVALUACION . " s
                left join " . TBL_SATISFACCIONATENCIONEVALUACION . " sae on sae.idSatisfaccionEmpleadoEvaluacion = s.idSatisfaccionEmpleadoEvaluacion and sae.fechaEliminacion = '0000-00-00 00:00:00'
                left join " . TBL_SATISFACCIONPREGUNTAEVALUACION . " spe on spe.idsatisfaccionpreguntaevaluacion = sae.idSatisfaccionPreguntaEvaluacion and spe.activo = 1 and spe.fechaEliminacion = '0000-00-00 00:00;00'
                left join " . TBL_SATISFACCIONPREGUNTA . " sp on sp.idSatisfaccionPregunta = spe.idSatisfaccionPregunta and sp.activo = 1 and sp.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_PERSONA . " p on p.idPersona = s.idPersona
                left join " . TBL_SATISFACCIONNIVELCALIFICACION . " snc on snc.idsatisfaccionnivelcalificacion = sae.idSatisfaccionNivelCalificacion and snc.activo = 1
                left join " . TBL_SATISFACCIONEVALUACION . " se on se.idSatisfaccionEvaluacion = s.idSatisfaccionEvaluacion and se.activo = 1 and se.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_PERSONA . " p2 on p2.idPersona = s.idResponsable
                inner join " . TBL_EMPLEADO . " e on e.idPersona = s.idPersona and e.idTipoEstatusEmpleado = 196 and e.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_EMPLEADOPUESTO . " ep on ep.idEmpleado = e.idEmpleado and ep.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_PUESTO . " pu on pu.idPuesto = ep.idPuesto and pu.fechaEliminacion = '0000-00-00 00:00:00'
                where #s.estatus = '" . $estatus . "' and
                    s.fechaEliminacion = '0000-00-00 00:00:00' and s.idPersona =" . $idPersona . " ";
        } else {
            $sql = "select s.contratoIndeterminado,concat_ws(' ',p.nombre,p.paterno,p.materno) as empleado, s.promedio, s.fechaActualizacion,sp.descripcion as pregunta,
                    snc.descripcion as respuesta ,snc.valor, se.nombre as evaluacion,concat_ws(' ',p2.nombre,p2.paterno,p2.materno) as responsable,
                    pu.descripcion as puesto,if(s.comentarios is null,'Ninguno',s.comentarios) as comentarios, e.fechaContratacion, s.fechaEnvioEvaluacion,
                    s.fechaActualizacion, s.estatus
                from " . TBL_SATISFACCIONEMPLEADOEVALUACION . " s
                inner join " . TBL_SATISFACCIONATENCIONEVALUACION . " sae on sae.idSatisfaccionEmpleadoEvaluacion = s.idSatisfaccionEmpleadoEvaluacion and sae.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_SATISFACCIONPREGUNTAEVALUACION . " spe on spe.idsatisfaccionpreguntaevaluacion = sae.idSatisfaccionPreguntaEvaluacion and spe.activo = 1 and spe.fechaEliminacion = '0000-00-00 00:00;00'
                inner join " . TBL_SATISFACCIONPREGUNTA . " sp on sp.idSatisfaccionPregunta = spe.idSatisfaccionPregunta and sp.activo = 1 and sp.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_PERSONA . " p on p.idPersona = s.idPersona
                inner join " . TBL_SATISFACCIONNIVELCALIFICACION . " snc on snc.idsatisfaccionnivelcalificacion = sae.idSatisfaccionNivelCalificacion and snc.activo = 1
                inner join " . TBL_SATISFACCIONEVALUACION . " se on se.idSatisfaccionEvaluacion = s.idSatisfaccionEvaluacion and se.activo = 1 and se.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_PERSONA . " p2 on p2.idPersona = s.idResponsable
                inner join " . TBL_EMPLEADO . " e on e.idPersona = s.idPersona and e.idTipoEstatusEmpleado = 196 and e.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_EMPLEADOPUESTO . " ep on ep.idEmpleado = e.idEmpleado and ep.fechaEliminacion = '0000-00-00 00:00:00'
                inner join " . TBL_PUESTO . " pu on pu.idPuesto = ep.idPuesto and pu.fechaEliminacion = '0000-00-00 00:00:00'
                where s.estatus = '" . $estatus . "' and
                    s.fechaEliminacion = '0000-00-00 00:00:00' and s.idPersona =" . $idPersona . " ";
        }
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Regresa un array con la lista de empleados activos, regresando los siguientes
     * datos: idPersona, Nombre, Apellido Paterno, Apellido Materno, Puesto
     *
     * @param string   $nombre             Nombre de el empleado a filtrar
     * @param integer  $restricciones      Aplicar filtro de restricciones por permisos
     * @param integer  $idUn               Id de unidad de negocio a filtrar
     * @param integer  $numeroRegistros    Numero de registros a regresar
     * @param integer  $idPersona          Identificador de persona
     * @param string   $tipoPuestoComision Identificador de tipopuestocomision
     * @param idEvento $idEvento           Identificador de evento
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function empleadoActivos($nombre = "", $restricciones = 0, $idUn = 0, $numeroRegistros = REGISTROS_POR_PAGINA, $idPersona = 0, $tipoPuestoComision = '', $idEvento = 0)
    {
        settype($restricciones, "integer");
        settype($idUn, "integer");
        settype($numeroRegistros, "integer");
        settype($idPersona, "integer");
        settype($tipoPuestoComision, "string");
        settype($idEvento, "integer");

        $nombre              = trim($nombre);
        $nombre              = $this->db->escape_like_str($nombre);
        $nombre              = str_replace(" ", "%", $nombre);
        $selectDefault       = '';
        $fromDefault         = '';
        $innerDefault        = '';
        $innerComisionConfig = '';
        $whereDefault        = '';
        $sqlOrderDefault     = '';
        $sql                 = '';
        $sql1                = '';
        $sql2                = '';
        $sql3                = '';

        if ($this->session->userdata('idOperador') == 1) {
            $selectDefault = "SELECT DISTINCT p.idPersona, p.nombre, p.paterno, p.materno, pu.descripcion AS puesto ";
            $fromDefault   = "FROM " . TBL_PERSONA . " p ";
            $innerDefault  = "
                INNER JOIN " . TBL_EMPLEADO . " e ON p.idPersona = e.idPersona AND e.fechaEliminacion='0000-00-00 00:00:00'
                INNER JOIN " . TBL_EMPLEADOPUESTO . " ep ON e.idEmpleado = ep.idEmpleado AND ep.fechaEliminacion='0000-00-00 00:00:00'
                INNER JOIN " . TBL_PUESTO . " pu ON ep.idPuesto = pu.idPuesto ";
            if ($tipoPuestoComision and $idEvento) {
                $innerComisionConfig .= " INNER JOIN " . TBL_EVENTOPUESTOCOMISION . " epc ON epc.idPuesto = pu.idPuesto AND epc.tipoPuesto = '" . $tipoPuestoComision . "' AND epc.idEvento = " . $idEvento . " AND epc.activo = 1 AND epc.fechaEliminacion = '0000-00-00 00:00:00' ";
            }
            $whereDefault = " WHERE CONCAT(p.nombre,' ', p.paterno,' ', p.materno) LIKE '%" . $nombre . "%' ";

            if ($idUn > 0) {
                $whereDefault .= " AND ep.idUn = " . $idUn . " ";
            }
            if ($restricciones > 0) {
                if ($this->permisos_model->validaTodosPermisos(PER_COMISION_TODOSCLUBS) == false) {
                    $whereDefault .= " AND (ep.idUn = " . $this->session->userdata('idUn') .
                    " OR ep.idUn IN (SELECT idUn FROM ungerente WHERE fechaEliminacion='0000-00-00 00:00:00' AND idPersona=" .
                    $this->session->userdata('idPersona') . ")) ";
                }
                if ($this->permisos_model->validaTodosPermisos(PER_COMISION_BUSCAREMPLEADO) == false) {
                    $whereDefault .= " AND e.idPersona = " . $this->session->userdata('idPersona') . " ";
                }
                if ($this->permisos_model->validaTodosPermisos(PER_COMISION_SOLOJECUTIVOS) == true) {
                    $p = strtoupper($this->obtienePuesto($this->session->userdata('idPuesto')));
                    if (strpos($p, 'VENTAS') !== false) {
                        $whereDefault .= " AND pu.descripcion LIKE '%VENTAS%' ";
                    }
                }
                if ($this->permisos_model->validaTodosPermisos(PER_COMISION_BUSCAREMPLEADOSINACTIVOS) == false) {
                    $whereDefault .= " AND e.idTipoEstatusEmpleado = " . ESTATUS_EMPLEADO_ACTIVO . " ";
                }
            } else {
                $whereDefault .= " AND e.idTipoEstatusEmpleado = " . ESTATUS_EMPLEADO_ACTIVO . " ";
            }
            $sql1 = $selectDefault . $fromDefault . $innerDefault . $innerComisionConfig . $whereDefault;

            if ($tipoPuestoComision and $idEvento) {
                $innerComisionConfig = " INNER JOIN " . TBL_EVENTOUNPUESTOEXCEPCION . " eupe ON eupe.idPuesto = pu.idPuesto AND eupe.tipoPuesto = '" . $tipoPuestoComision . "' AND eupe.activo = 1 AND eupe.fechaEliminacion = '0000-00-00 00:00:00'";
                $innerEventoUn       = " INNER JOIN " . TBL_EVENTOUN . " eu ON eu.idEventoUn = eupe.idEventoUn AND eu.idEvento = " . $idEvento . " AND eu.activo = 1 AND eu.fechaEliminacion = '0000-00-00 00:00:00'";
                $sql2                = $selectDefault . $fromDefault . $innerDefault . $innerComisionConfig . $innerEventoUn . $whereDefault;
                $innerComisionConfig = " INNER JOIN " . TBL_EVENTOUNPUESTOEXCEPCION . " eupe ON eupe.idEmpleado = e.idEmpleado AND eupe.tipoPuesto = '" . $tipoPuestoComision . "' AND eupe.activo = 1 AND eupe.fechaEliminacion = '0000-00-00 00:00:00'";
                $sql3                = $selectDefault . $fromDefault . $innerDefault . $innerComisionConfig . $innerEventoUn . $whereDefault;
                $selectUnion         = "SELECT a.idPersona, a.nombre, a.paterno, a.materno, a.puesto ";
                $fromUnion           = "FROM ( ";

                if ($sql2 and $sql3) {
                    $fromUnion .= $sql1 . " UNION " . $sql2 . " UNION " . $sql3;
                } else {
                    $fromUnion .= $sql1;
                }
                $fromUnion .= ") a ORDER BY a.nombre, a.paterno, a.materno LIMIT " . $numeroRegistros . " ";

                $sql = $selectUnion . $fromUnion;
            } else {
                $sql1 .= " ORDER BY p.nombre, p.paterno, p.materno LIMIT " . $numeroRegistros . " ";

                $sql = $sql1;
            }
            $query = $this->db->query($sql);
        } else {
            $o = $this->session->userdata('idOperador');

            $sql = "SELECT DISTINCT a.idPersona, a.nombre, a.paterno, a.materno, a.descripcion AS puesto
                FROM (
                    SELECT DISTINCT p.idPersona, p.nombre, p.paterno, p.materno, pu.descripcion
                    FROM persona p
                    INNER JOIN usuarios u ON u.IdPersona=p.idPersona AND u.Estatus=1 AND u.fechaEliminacion='0000-00-00 00:00:00'
                    INNER JOIN un u1 ON u1.idUn=u.idUn
                    INNER JOIN operador o ON o.idOperador=u1.idOperador AND o.idOperador=$o
                    LEFT JOIN empleado e ON e.idPersona=p.idPersona AND e.fechaActualizacion='2014-09-12 05:57:29'
                    LEFT JOIN empleadopuesto ep ON ep.idEmpleado=e.idEmpleado AND ep.fechaEliminacion='0000-00-00 00:00:00'
                    LEFT JOIN puesto pu ON pu.idPuesto=ep.idPuesto
                    UNION ALL
                    SELECT DISTINCT p.idPersona, p.nombre, p.paterno, p.materno, pu.descripcion
                    FROM empleado e
                    INNER JOIN empleadopuesto ep ON ep.idEmpleado=e.idEmpleado AND ep.fechaEliminacion='0000-00-00 00:00:00'
                    INNER JOIN un u1 ON u1.idUn=ep.idUn
                    INNER JOIN operador o ON o.idOperador=u1.idOperador AND o.idOperador=$o
                    INNER JOIN persona p ON p.idPersona=e.idPersona
                    INNER JOIN puesto pu ON pu.idPuesto=ep.idPuesto
                    WHERE e.idTipoEstatusEmpleado=197 AND e.fechaActualizacion='2014-09-12 05:57:29'
                ) a
                ORDER BY a.nombre, a.paterno, a.materno";
            $query = $this->db->query($sql);
        }

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Actuliza la pagina de inicio por puesto
     *
     * @param integer $puesto  Identificador del puesto
     * @param string  $vinculo Vinculo para la pagina de inicio
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function guardarPaginaInicioPuesto($puesto, $vinculo)
    {
        settype($puesto, 'integer');

        if ($puesto == 0) {
            return false;
        }

        if (!file_exists(RUTA_LOCAL . '\system\application\views\home\/' . $vinculo . '.php')) {
            return false;
        }

        $datos = array('paginaInicio' => $vinculo);
        $where = array('idPuesto' => $puesto);
        $res   = $this->db->update(TBL_PUESTO, $datos, $where);
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return false;
        }

        return true;
    }

    /**
     * Lista empleados mediante filtros
     *
     * @param integer $opciones
     * @param integer $totales
     * @param integer $totales
     * @param integer $posicion
     * @param integer $registros
     * @param integer $orden
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function listaEmpleados($opciones, $totales = 0, $posicion = 0, $registros = 25, $orden = '')
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
            $orden = 'e.idEmpleado';
        }
        $a = '';
        if ($opciones["idUn"] != 0) {
            $a = ' and ep.idUn=' . $opciones["idUn"];
        }
        $b = '';
        if ($opciones["puesto"] != '') {
            $b = ' and pu.descripcion LIKE \'%' . $opciones["puesto"] . '%\'';
        }
        $d = '';
        if ($opciones["idEmpleado"] != 0) {
            $d = ' and e.idEmpleado=' . $opciones["idEmpleado"];
        }
        $e = '';
        if ($opciones["estatus"] != 0) {
            $e = ' and e.idTipoEstatusEmpleado=' . $opciones["estatus"];
        }
        $f = '';
        if ($opciones["idEmpresa"] != 0) {
            $f = ' and em.idEmpresa = ' . $opciones["idEmpresa"];
        }
        $g = '';
        if ($opciones["sinEmail"] == 1) {
            $g = " HAVING email = '' ";
        }
        $j = '';
        if ($opciones["mail"] != '') {
            $j = " HAVING email LIKE '%" . $opciones['mail'] . "%' ";
        }
        $h = '';
        $i = '';
        if ($opciones["idPersonaEmpleado"] > 0) {
            $h = ' AND e.idPersona = ' . $opciones['idPersonaEmpleado'] . " ";
        } elseif ($opciones['nombre'] != '') {
            $arregloTmp = explode('(', $opciones['nombre']);
            $i          = " AND CONCAT_WS(' ', p.nombre, p.paterno, p.materno) LIKE '%" . str_replace(' ', '%', $arregloTmp[0]) . "%' ";
        }
        $k = '';
        if ($opciones["operador"] > 0) {
            $k = ' AND e.idOperador = ' . $opciones["operador"];
        }
        $l = '';
        if ($opciones["estatusContrato"] > -1) {
            if ($opciones["estatusContrato"] > 2) {
                if ($opciones["estatusContrato"] == 3) {
                    $l = ' AND see.contratoIndeterminado = 1';
                } else {
                    $l = ' AND see.contratoIndeterminado = 0';
                }
            }
            if ($opciones["estatusContrato"] <= 2) {
                if ($opciones["estatusContrato"] == 1) {
                    $l = ' AND see.estatus = \'Enviado\' ';
                }
                if ($opciones["estatusContrato"] == 2) {
                    $l = ' AND see.estatus = \'Vencido\' ';
                }
                if ($opciones["estatusContrato"] == 0) {
                    $l = ' AND ADDDATE(ADDDATE(e.fechaContratacion, INTERVAL 2 MONTH),INTERVAL 20 DAY)>date(now()) and e.idOperador =' . OPERADOR_HUMMAN_ACCESS;
                }
            }
        }

        $sql = "SELECT e.idPersona, e.idEmpleado, u.idUn, u.nombre AS club,
              p.nombre, p.paterno, p.materno, ep.idPuesto, pu.descripcion AS puesto,
              te.descripcion AS estatus, em.activo, te.activo, ep.idEmpleadoPuesto, u.idEmpresa,
              us.IdUsuario, e.idTipoEstatusEmpleado, pap.idPermisoAplicaPuestos,
              o.clubes AS operador, o.idOperador,see.contratoIndeterminado as contratoIndeterminado,
              e.fechaContratacion, IF(e.idTipoEstatusEmpleado IN (197), DATE(e.fechaActualizacion), '0000-00-00') AS fechaBaja,
              if(see.promedio is null,0,see.promedio) as porcentaje,see.estatus as estatusEncuesta, see.idSatisfaccionEmpleadoEvaluacion,
             (
              SELECT IFNULL(GROUP_CONCAT(DISTINCT m.mail), '')
              FROM " . TBL_MAIL . " m
              WHERE m.idPersona = e.idPersona AND m.fechaEliminacion = '0000-00-00 00:00:00'
                AND m.idTipoMail = " . TIPO_MAIL_EMPLEADO . "
            )AS email
            FROM " . TBL_EMPLEADO . " e
            INNER JOIN " . TBL_EMPLEADOPUESTO . " ep ON ep.idEmpleado = e.idEmpleado
            INNER JOIN " . TBL_PUESTO . " pu ON pu.idPuesto = ep.idPuesto
            INNER JOIN " . TBL_PERSONA . " p ON p.idPersona = e.idPersona
            INNER JOIN " . TBL_TIPOESTATUSEMPLEADO . " te ON te.idTipoEstatusEmpleado = e.idTipoEstatusEmpleado
            INNER JOIN " . TBL_UN . " u ON u.idUn = ep.idUn
            INNER JOIN " . TBL_EMPRESA . " em ON em.idEmpresa = u.idEmpresa
            LEFT JOIN " . TBL_OPERADOR . " o ON e.idOperador=o.idOperador
            LEFT JOIN " . TBL_USUARIOS . " us ON us.IdEmpleado = e.idEmpleado AND us.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN " . TBL_PERMISOAPLICAPUESTOS . " pap ON pap.idPuesto = pu.idPuesto AND pap.fechaEliminacion = '0000-00-00 00:00:00'
            LEFT JOIN " . TBL_SATISFACCIONEMPLEADOEVALUACION . " see on see.idPersona = e.idPersona and see.fechaEliminacion = '0000-00-00 00:00:00'
            WHERE ep.fechaEliminacion = '0000-00-00 00:00:00' AND p.fechaEliminacion = '0000-00-00 00:00:00'
                AND u.fechaEliminacion = '0000-00-00 00:00:00' AND em.fechaEliminacion = '0000-00-00 00:00:00'
                AND u.activo = IF(u.nombre = 'Allocations',0,1) AND em.activo = 1 AND te.activo = 1
            $f $a $b $d $e $h $i $k $l
            GROUP BY e.idEmpleado
            $g $j
            ORDER BY $orden $m";
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
     * Lista los estatus para empleado
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function listaEstatus()
    {
        $data      = array();
        $data['0'] = '';

        $query = $this->db->query('SELECT distinct idTipoEstatusEmpleado, descripcion FROM ' . TBL_TIPOESTATUSEMPLEADO . ' where activo=1');

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $data[$fila->idTipoEstatusEmpleado] = $fila->descripcion;
            }
            return $data;
        }
    }

    /**
     * Obtiene los datos de la tabla empleadopuesto
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function scopeDatosEmpleadoPuesto($query, $idEmpleado)
    {

        $where = array('empleadoPuesto.idEmpleado' => $idEmpleado, 'empleadoPuesto.fechaEliminacion' => '0000-00-00 00:00:00');
        return $query->select('empleadoPuesto.idEmpleadoPuesto', 'empleadoPuesto.idUn', 'empleadoPuesto.idPuesto', 'empleadoPuesto.idPuestoSuperior')
            ->join('empleadoPuesto', 'empleadoPuesto.idEmpleado', '=', 'empleado.idEmpleado')
            ->where($where)
            ->first()
            ->toArray();
    }

    /**
     * Regresa el idEmpleado buscando con el idPersona
     *
     * @param integer $idPersona Id Persona a Filtrar
     *
     * @author Jonathan Alcantara
     *
     * @return integer
     */
    public static function obtenIdEmpleado($idPersona = 0, $activo = 0)
    {
        settype($idPersona, 'integer');
        settype($activo, 'integer');

        $idEmpleado = 0;

        if ($idPersona == 0) {
            return $idEmpleado;
        }

        $a = '';
        if ($activo == 1) {
            $a = ' AND idTipoEstatusEmpleado=196';
        }

        $sql        = "SELECT idEmpleado FROM " . TBL_EMPLEADO . " WHERE idPersona = $idPersona {$a};";
        $query      = DB::connection('crm')->select($sql);
        $idEmpleado = (count($query) > 0) ? $query[0]->idEmpleado : 0;

        return $idEmpleado;
    }

}
