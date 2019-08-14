<?php

namespace API_EPS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Persona extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.persona';
    protected $primaryKey = 'idPersona';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    public function scopeDatosPersona($query, $idPersona, $idSocio)
    {
        $where = [
            'persona.idPersona'                => $idPersona,
            'socio.idSocio'                    => $idSocio,
            'socio.idTipoEstatusSocio'         => 81,
            'socio.eliminado'                  => 0,
            'membresia.idTipoEstatusMembresia' => 27,
            'membresia.eliminado'              => 0,
        ];
        return $query->select('un.nombre as club', 'un.idUn', 'persona.nombre', 'persona.paterno', 'persona.materno', 'membresia.idMembresia')
            ->join('crm.socio', 'socio.idPersona', '=', 'persona.idPersona')
            ->join('crm.membresia', 'socio.idUnicoMembresia', '=', 'membresia.idUnicoMembresia')
            ->join('crm.Un', 'un.idUn', '=', 'membresia.idUn')
            ->where($where)->get();
        /*
    ->first()
    ->toArray();*/
    }

    public static function getMail($idPersona){
        $sql="SELECT  mail FROM crm.persona as p
        JOIN crm.mail as m ON p.idPersona=m.idPersona
        where p.idPersona={$idPersona}
        order by m.idTipoMail";
        $query = DB::connection('crm')->select($sql);
        if(count($query )>0){
            return $query[0]->mail;
        }else{
            return false;
        }


    }

    /**
     * Bloquea telefonos de las campañas del  call center
     *
     * @author Antonio Sixtos
     *
     * @return array
     */
    public function actualizaBloqueoCallCenter($idPersona, $bloqueCallCenter)
    {
        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $data = array('bloqueoCallCenter' => $bloqueCallCenter);
        $this->db->where('idPersona', $idPersona);
        $this->db->update(TBL_PERSONA, $data);

        $total = $this->db->affected_rows();

        $this->permisos_model->log('Se actualizo bloqueo de Call Center', LOG_PERSONA, $idUnicoMembresia);

        return $total;
    }

    /**
     * actualiza el numero de credenciales impresas
     *
     * @param integer $idPersona     Identificador de la persona
     * @param integer $numCredencial numero de impresiones
     * @param integer $tipo          Identificador del tipo de persona
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function actualizaCredencial($idPersona, $numCredencial, $tipo, $unico = 0)
    {
        settype($idPersona, 'integer');
        settype($tipo, 'integer');
        settype($tipo, 'integer');

        if ($tipo == TIPO_EMPLEADO) {
            $tabla = TBL_EMPLEADO;
        } else {
            $tabla = TBL_SOCIO;
        }
        $this->db->select('idPersona, credencial');
        $this->db->from($tabla);
        $where = array('idPersona' => $idPersona);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $this->db->where('idPersona', $fila['idPersona']);
            $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
            $datos = array('credencial' => ++$numCredencial);
            $this->db->update($tabla, $datos);
            $this->permisos_model->log('Se actualizo el numero de impresion de credencial', LOG_PERSONA, $unico);
            return true;
        } else {
            return null;
        }
    }

    public function casoKidz($idPersona)
    {
        settype($idPersona, 'integer');
        $res = false;

        if ($idPersona > 0) {
            $this->db->select('idPersona');
            $this->db->from('personacasokidz');
            $this->db->where('idPersona', $idPersona);
            $query = $this->db->get();

            if ($query->num_rows > 0) {
                $res = true;
            }
        }

        return $res;
    }

    /**
     * Obtiene el numero de credenciales impresas a la fecha
     *
     * @param integer $idPersona Identificador de la persona
     * @param integer $tipo      Identificador del tipo de persona
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function credencial($idPersona, $tipo = 0)
    {
        settype($idPersona, 'integer');
        settype($tipo, 'integer');

        if ($tipo == TIPO_EMPLEADO) {
            $tabla = TBL_EMPLEADO;
        } else {
            $tabla = TBL_SOCIO;
        }
        $this->db->select('credencial');
        $this->db->from($tabla);
        $where = array('idPersona' => $idPersona);
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila       = $query->row_array();
            $credencial = $fila['credencial'];
            return $credencial;
        } else {
            return null;
        }
    }

    /**
     * Lista Bitacora de foto
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function bitacoraFoto($opciones, $totales = 0, $posicion = 0, $registros = 25)
    {
        if (isset($opciones['idPersona'])) {
            settype($opciones['idPersona'], 'integer');
        } else {
            return 0;
        }

        if ($registros == 0) {
            $registros = REGISTROS_POR_PAGINA;
        }

        $this->db->select('l.idLog, l.descripcion,p.nombre, p.paterno, p.materno, l.fecha');
        $this->db->from(TBL_LOG . ' l');
        $this->db->join(TBL_PERSONA . ' p', 'p.idPersona = l.idPersona');
        $where = array('l.idLogCategoria' => LOG_FOTO);
        $this->db->where($where);
        $this->db->like('l.descripcion', 'foto', 'both');
        $this->db->like('l.descripcion', $opciones['idPersona'], 'both');
        if ($totales == 0) {
            $this->db->limit($registros, $posicion);
        }
        $this->db->order_by('l.fecha', 'DESC');
        $query = $this->db->get();

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
     * [buscarBancario description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function buscarBancario($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT idDomicilio
            FROM domicilio d
            LEFT JOIN socio s on s.idPersona=d.idPersona
            WHERE s.idUnicoMembresia=" . $idUnicoMembresia . " and s.idTipoRolCliente=1
            AND s.fechaEliminacion='0000-00-00 00:00:00' and d.fechaeliminacion='0000-00-00 00:00:00'
            AND d.bancario=1";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            $data = 1;
        } else {
            $data = 0;
        }

        return $data;
    }

    /**
     * Identifica si se debe bloquear la edicion de una persona
     *
     * @param  integer $idPersona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function bloquearEdicion($idPersona)
    {
        settype($idPersona, 'integer');

        if ($idPersona <= 0) {
            return false;
        }

        $sql = "SELECT SUM(a.total) AS total FROM (
                SELECT COUNT(*) AS total FROM socio WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM membresiainvolucrado WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM empleado WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM tagkidz WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM movimiento WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM invitadoespecial WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM eventoinscripcion WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM eventoparticipante WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM usuarios WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM lockerpersona WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM huella WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM foto WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM invitado WHERE idPersona=$idPersona
                UNION ALL
                SELECT COUNT(*) AS total FROM responsablemenor WHERE idPersonaResponsable=$idPersona
            ) a";
        $query = $this->db->query($sql);
        $row   = $query->row();

        if ($row->total > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     *
     * @param array   $opciones        Array con opciones de busqueda en el sistema
     * @param integer $totales         Bandera para indicar si regresa unicamente el numero total de registros encontrados
     * @param integer $numeroRegistros Numero de registros a mostrar por pagina
     * @param integer $posicion        Posicion de inicio en pagina
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function busquedaMultiple($opciones, $totales = 0, $numeroRegistros = 25, $posicion = 0)
    {
        settype($totales, 'integer');
        settype($numeroRegistros, 'integer');
        settype($pagina, 'integer');

        $ci = &get_instance();
        $ci->load->model('socio_model');

        $this->permisos_model->log('Busqueda de personas en sistema ' . json_encode_sw($opciones), LOG_PERSONA);
        $idEmpresaGrupo = $ci->un_model->obtenerEmpresaGrupo($this->session->userdata('idUn'));
        $idUsuario      = $this->session->userdata('idUsuario');

        $w_nombre    = '';
        $w_nombre_ft = 'persona';

        if (isset($opciones['nombre'])) {
            $opciones['nombre'] = trim($opciones['nombre']);
            $opciones['nombre'] = $this->db->escape_like_str($opciones['nombre']);
            $opciones['nombre'] = htmlspecialchars($opciones['nombre']);

            $opciones['nombre'] = str_replace('*', '%', $opciones['nombre']);
            if ($opciones['nombre'] != '') {
                $w_nombre_ft = '(
                    SELECT p1.*
                    FROM personalevenshtein l
                    INNER JOIN persona p1 ON p1.idPersona=l.idPersona AND p1.bloqueo=0
                        AND p1.fechaEliminacion=\'0000-00-00 00:00:00\' AND p1.idEmpresaGrupo=' . $idEmpresaGrupo . '
                    WHERE MATCH(nombreCompleto) AGAINST (\'' . str_replace(' ', '%', $opciones['nombre']) . '\' IN BOOLEAN MODE)
                )';

                $w_nombre = " AND CONCAT_WS(' ', p.nombre, p.paterno,p.materno) LIKE '%" . $opciones['nombre'] . "%' ";
            }

            //if ($totales==0) {
            $sql = "INSERT INTO zzzduplicados (idUsuario, texto, fechaRegistro)
                    VALUES ($idUsuario, CONCAT('Busqueda (nombre): ', '" . $opciones['nombre'] . "'), NOW())";
            $this->db->query($sql);
            //}
        }
        $w_persona = '';
        if (isset($opciones['persona'])) {
            settype($opciones['persona'], 'integer');
            if ($opciones['persona'] > 0) {
                $w_persona = ' AND p.idPersona=' . $opciones['persona'] . ' ';

                //if ($totales==0 && $opciones["persona"]=='') {
                $sql = "INSERT INTO zzzduplicados (idUsuario, texto, fechaRegistro)
                        VALUES ($idUsuario, CONCAT('Busqueda (idPersona): ', '" . $opciones['persona'] . "'), NOW())";
                $this->db->query($sql);
                //}
            }
        }

        $soloSocio     = 0;
        $w_membresia   = '';
        $num_membresia = 0;
        if (isset($opciones['membresia'])) {
            settype($opciones['membresia'], 'integer');
            $num_membresia = $opciones['membresia'];
            if ($opciones['membresia'] > 0) {
                $w_membresia = ' AND a.membresia=' . $opciones['membresia'] . ' ';
                $soloSocio   = 1;
            }

            /*if ($totales==0) {
        $sql = "INSERT INTO zzzduplicados (idUsuario, texto, fechaRegistro)
        VALUES ($idUsuario, CONCAT('Busqueda (Membresia): ', '".$opciones["membresia"]."'), NOW())";
        $this->db->query($sql);
        }*/
        }

        if (isset($opciones['soloSocio'])) {
            settype($opciones['soloSocio'], 'integer');
            if ($opciones['soloSocio'] > 0) {
                $w_membresia = ' AND a.membresia<=0 ';
                $soloSocio   = 1;
            }
        }

        $w_sexo = '';
        if (isset($opciones['sexo'])) {
            settype($opciones['sexo'], 'integer');
            if ($opciones['sexo'] > 0) {
                $w_sexo = ' AND p.idTipoSexo=' . $opciones['sexo'] . ' ';
            }
        }

        $noSocio = 0;
        if (isset($opciones['noSocio'])) {
            settype($opciones['noSocio'], 'integer');
            if ($opciones['noSocio'] == 1) {
                $w_membresia = ' AND (a.membresia IS NULL OR a.membresia=0) ';
                $noSocio     = 1;
            }
        }

        if ($this->session->userdata('idUn') == 1) {
            $w_concesionario = '';
        } else {
            if (isset($opciones['w_concesionario'])) {
                $w_concesionario = ' AND p.concesionario IN (1,3) ';
            } else {
                $w_concesionario = ' AND p.concesionario=0 ';
            }
        }

        $limit = '';
        if ($totales == 0) {
            if ($numeroRegistros > 0) {
                $limit = " LIMIT $posicion, $numeroRegistros";
            }
        }

        $data = array();

        if ($w_membresia == '' && $w_nombre == '' && $w_persona == '' && $w_sexo == '' && $w_concesionario == '') {
            if ($totales == 1) {
                return 0;
            } else {
                return $data;
            }
        }

        if ($w_membresia == '' && $w_nombre == '' && $w_persona == '' && $w_sexo == '' && $w_concesionario == ' AND p.concesionario=0 ') {
            if ($totales == 1) {
                return 0;
            } else {
                return $data;
            }
        }

        $w_bloqueoMail = 'AND p.bloqueo=0';
        if (isset($opciones['w_concesionario'])) {
            $w_bloqueoMail = "";
        }

        $sql = "SELECT persona, membresia, club, unico, nombre, paterno, materno, tipo, idTipo, edad, fechaNacimiento, idTipoPersona FROM (";
        if ($soloSocio == 0) {
            $sql .= " (SELECT p.idPersona AS persona, '' AS membresia, '' AS club, '' AS unico, p.nombre, p.paterno, p.materno,
                'Persona' AS tipo, '9' AS idTipo,
                p.edad AS edad,
                p.fechaNacimiento, p.idTipoPersona
                FROM $w_nombre_ft p
                LEFT JOIN socio s ON p.idPersona=s.idPersona AND s.eliminado = 0
                LEFT JOIN empleado i ON p.idPersona=i.idPersona AND i.idTipoEstatusEmpleado=" . ESTATUS_EMPLEADO_ACTIVO . "
                WHERE p.fechaEliminacion='0000-00-00 00-00-00' AND (s.idSocio = 0 OR s.idSocio IS NULL)
                    AND (i.idEmpleado = 0 OR i.idEmpleado IS NULL) AND p.idEmpresaGrupo=$idEmpresaGrupo
                $w_bloqueoMail $w_nombre $w_persona $w_sexo $w_concesionario)";
        }
        if ($noSocio == 0) {
            if (strstr($sql, '(SELECT')) {
                $sql .= " UNION";
            }
            $sql .= " (SELECT p.idPersona AS persona, m.idMembresia AS membresia, u.nombre AS club, m.idUnicoMembresia AS unico, p.nombre, p.paterno, p.materno,
                'Socio' AS tipo, '0' AS idTipo, p.edad AS edad, p.fechaNacimiento, p.idTipoPersona
                FROM socio s
                INNER JOIN $w_nombre_ft p ON p.idPersona=s.idPersona AND p.fechaEliminacion='0000-00-00 00:00:00'
                    AND p.idEmpresaGrupo=$idEmpresaGrupo $w_bloqueoMail
                INNER JOIN membresia m ON m.idUnicoMembresia=s.idUnicoMembresia ";
            if ($num_membresia > 0) {
                $sql .= " AND m.idMembresia=" . $num_membresia;
            }
            $sql .= " INNER JOIN un u ON u.idUn=m.idUn
                WHERE s.eliminado = 0 AND m.eliminado = 0
                $w_nombre $w_persona $w_sexo $w_concesionario)  ";
            if ($num_membresia > 0) {
                $sql .= " UNION (SELECT p.idPersona AS persona, m.idMembresia AS membresia, u.nombre AS club,
                        m.idUnicoMembresia AS unico, p.nombre, p.paterno, p.materno,
                        'Socio' AS tipo, '0' AS idTipo, p.edad AS edad, p.fechaNacimiento, p.idTipoPersona
                    FROM $w_nombre_ft p
                    INNER JOIN membresiainvolucrado mi ON mi.idPersona=p.idPersona AND mi.idTipoInvolucrado=1
                        AND mi.fechaEliminacion='0000-00-00-00'
                    INNER JOIN membresia m ON m.idUnicoMembresia=mi.idUnicoMembresia AND m.idMembresia=$num_membresia
                    INNER JOIN un u ON u.idUn=m.idUn
                    WHERE p.idEmpresaGrupo=$idEmpresaGrupo AND p.fechaEliminacion='0000-00-00 00:00:00'
                        AND m.eliminado = 0 $w_nombre $w_persona $w_sexo $w_concesionario
                        $w_bloqueoMail
                    GROUP BY p.idPersona) ";
            }
        }

        if ($soloSocio == 0) {
            if (strstr($sql, '(SELECT')) {
                $sql .= ' UNION';
            }
            $sql .= " (SELECT i.idPersona AS persona, '' AS membresia, '' AS club, '' AS unico, p.nombre, p.paterno, p.materno,
                'Empleado' AS tipo, '8' AS idTipo, p.edad AS edad, p.fechaNacimiento, p.idTipoPersona
                FROM empleado i
                INNER JOIN persona p ON p.idPersona=i.idPersona AND p.fechaEliminacion='0000-00-00 00:00:00'
                    AND p.idEmpresaGrupo=$idEmpresaGrupo $w_bloqueoMail
                WHERE i.idTipoEstatusEmpleado= " . ESTATUS_EMPLEADO_ACTIVO . "
                $w_nombre $w_persona $w_sexo $w_concesionario)";
        }
        $sql .= ") a WHERE 1=1 $w_membresia ";

        $w_minEdad = '';
        if (isset($opciones['minEdad'])) {
            settype($opciones['minEdad'], 'integer');
            if ($opciones['minEdad'] >= 0) {
                $w_minEdad = ' AND (a.edad>=' . $opciones['minEdad'] . ' OR a.idTipoPersona=45) ';
            }
        } else {
            $opciones['minEdad'] = 0;
        }

        $w_maxEdad = '';
        if (isset($opciones['maxEdad'])) {
            settype($opciones['maxEdad'], 'integer');
            if ($opciones['minEdad'] == 0) {
                if ($opciones['maxEdad'] == 0) {
                    $w_maxEdad = ' AND (a.edad<=' . $opciones['maxEdad'] . ' OR a.idTipoPersona=45) ';
                } else {
                    $w_maxEdad = ' AND (a.edad<=' . $opciones['maxEdad'] . ' OR a.idTipoPersona=45) ';
                }
            } else {
                if ($opciones['maxEdad'] > 0) {
                    $w_maxEdad = ' AND (a.edad<=' . $opciones['maxEdad'] . ' OR a.idTipoPersona=45) ';
                }
            }
        } else {
            $opciones['maxEdad'] = 0;
        }

        if ($opciones['minEdad'] == 0 && $opciones['maxEdad'] == 0) {
            $w_maxEdad = ' AND (a.edad>=' . $opciones['maxEdad'] . ' OR a.idTipoPersona=45) ';
            $w_minEdad = '';
        }

        $sql .= " HAVING 1=1 $w_minEdad $w_maxEdad";
        if (isset($opciones['maxEdad'])) {
            if ($opciones['maxEdad'] == 0 && $opciones['minEdad'] == 0) {
                $sql .= " AND fechaNacimiento <> '0000-00-00' ";
            }
        }
        $sql .= " ORDER BY nombre, paterno, materno, materno " . $limit;

        $query = $this->db->query($sql);
        //echo $this->db->last_query();
        if ($query->num_rows > 0) {
            if ($totales == 1) {
                return $query->num_rows;
            }
            return $query->result_array();
        }

        if ($totales == 1) {
            return 0;
        }

        return $data;
    }

    /**
     * Valida si los datos de una persona ya se encuentran completos
     *
     * @param integer $persona Identificador de la persona
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function datosCompletos($persona, $contacto = 0, $idConvenioDetalle = 0, $convenios = 0)
    {
        settype($persona, 'integer');

        if ($persona == 0) {
            return 'Persona invalida';
        }

        $okPersona    = false;
        $datosPersona = $this->datosGenerales($persona);
        if ($datosPersona != null) {
            if ($datosPersona['tipo'] == 44) {
                if (is_name_valid(utf8_encode($datosPersona['nombre'])) == true &&
                    is_name_valid(utf8_encode($datosPersona['paterno'])) == true &&
                    is_name_valid(utf8_encode($datosPersona['materno'])) == true) {
                    $okPersona = true;
                } else {
                    $okPersona = false;
                }
            }
            if ($datosPersona['tipo'] == 45) {
                $okPersona = true;
            }
            if ($datosPersona['fecha'] == '0000-00-00') {
                $okPersona = false;
            }
            if ($datosPersona['sexo'] == 0) {
                $okPersona = false;
            }
            if ($datosPersona['civil'] == 0) {
                $okPersona = false;
            }
        }
        if ($okPersona == false) {
            return 'Datos de persona incompletos';
        }

        $total      = 0;
        $okTelefono = false;
        $this->db->select('telefono, lada, idTipoTelefono');
        $this->db->from(TBL_TELEFONO);
        $this->db->where('eliminado', 0);
        $this->db->where('idPersona', $persona);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $total++;
            $telefonos = $query->result_array();
            foreach ($telefonos as $fila) {
                if ($fila['telefono'] != '' && $fila['idTipoTelefono'] > 0) {
                    $okTelefono = true;
                }
            }
        }
        if ($okTelefono == false) {
            return 'Falta registrar numero de telefono';
        }

        if ($contacto != 0) {
            $okMail = false;
            $total  = 0;
            $this->db->select('mail, idTipoMail');
            $this->db->from(TBL_MAIL);
            $this->db->where('eliminado', 0);
            $this->db->where('idPersona', $persona);
            $query = $this->db->get();
            if ($query->num_rows > 0) {
                $total++;
                $mails = $query->result_array();
                foreach ($mails as $fila) {
                    if ($fila["mail"] != '' && $fila["idTipoMail"] > 0) {
                        $okMail = true;
                    }
                }
            }
            if ($okMail == false) {
                return 'Falta registrar una cuenta de E-Mail';
            }

            $okDomicilio = false;
            $total       = 0;
            $this->db->select('idTipoDomicilio, calle, numero, colonia, idEstado,idMunicipio, cp, fiscal, RFC, nombreFiscal');
            $this->db->from(TBL_DOMICILIO);
            $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
            $this->db->where('idPersona', $persona);
            $query = $this->db->get();
            if ($query->num_rows > 0) {
                $total++;
                $domicilios = $query->result_array();
                foreach ($domicilios as $fila) {
                    if ($datosPersona['tipo'] == 44 && $fila['fiscal'] == 0) {
                        if ($fila['calle'] != '' && $fila['colonia'] != '' && $fila['idEstado'] > 0
                            && $fila['idMunicipio'] > 0 && $fila['cp'] != '' && $fila['idTipoDomicilio'] > 0
                        ) {
                            $okDomicilio = true;
                        }
                    }
                    if (($datosPersona['tipo'] == 44 && $fila['fiscal'] == 1) || $datosPersona['tipo'] == 45) {
                        if ($fila['calle'] != '' && $fila['colonia'] != '' && $fila['idEstado'] > 0
                            && $fila['idMunicipio'] > 0 && $fila['cp'] != '' && $fila['idTipoDomicilio'] > 0 && $fila['fiscal'] == 1
                            && $fila['RFC'] != '' && $fila['nombreFiscal'] != '') {
                            $okDomicilio = true;
                        }
                    }
                }
            }
            if ($okDomicilio == false) {
                return 'Falta registrar un domicilio valido';
            }
        }

        return 'Ok';
    }

    /**
     * [datosCompletosContacto description]
     * @param  [type] $persona [description]
     * @return [type]          [description]
     */
    public function datosCompletosContacto($persona)
    {
        $okContacto = false;
        $total      = 0;
        $this->db->select('idTipoContacto, idPersonaContacto');
        $this->db->from(TLB_CONTACTO);
        $this->db->where('idPersona', $persona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $total++;
            $mails = $query->result_array();
            foreach ($mails as $fila) {
                if ($fila['idTipoContacto'] != '' && $fila['idPersonaContacto'] > 0) {
                    $okContacto = true;
                }
            }
            return 'Ok';
        }
        if ($okContacto == false) {
            return 'Falta registrar algun contacto';
        }
    }

    /**
     * Regresa una array con los datos del domicilio solicitado
     *
     * @param integer $persona Identificador de persona
     * @param integer $id      Identificador del domicilio
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function datosDomicilio($persona, $id = 0, $idTipoDomicilio = 0)
    {
        settype($persona, 'integer');
        settype($id, 'integer');

        if ($persona == 0) {
            return null;
        }

        $this->db->select('idTipoDomicilio AS tipoDomicilio, calle, numero, colonia, idEstado AS estado, idMunicipio AS municipio, cp, referencia, fiscal, RFC, nombreFiscal'); //, bancario
        $this->db->from(TBL_DOMICILIO);
        $this->db->where('idPersona', $persona);

        if ($id != 0) {
            $this->db->where('idDomicilio', $id);
        }
        if ($idTipoDomicilio) {
            $this->db->where('idTipoDomicilio', $idTipoDomicilio);
        }
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila;
        } else {
            return null;
        }
    }

    /**
     * Regresa una array con los datos generales de la persona solicitada
     *
     * @param integer $persona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public static function datosGenerales($persona)
    {
        settype($persona, 'integer');
        if ($persona == 0) {
            return null;
        }

        $sql = "
SELECT p.nombre, p.paterno, p.materno, p.idTipoPersona AS tipo,
    p.fechaNacimiento AS fecha, p.idTipoSexo AS sexo, p.idTipoEstadoCivil AS civil,
    IFNULL(p.RFC, '') AS RFC, p.idTipoTituloPersona AS titulo,
    p.fallecido, p.idEstado AS estado, p.bloqueoMail AS bloqueo,
    p.CURP, p.fechaRegistro AS registro, p.tour, p.idTipoProfesion,
    p.concesionario, p.producto, p.concesionarioValido, p.idTipoEscolaridad,
    p.idTipoNivelIngresos, p.hijos, p.bloqueoCallCenter,
    p.idTipoPersona AS tipo,
    IF(e.idEmpleado IS NOT NULL, 'Empleado',  IF(s.idSocio IS NOT NULL, 'Socio', 'Publico General')) AS tipoCliente
FROM persona p
LEFT JOIN empleado e ON e.idPersona=p.idPersona
    AND e.idTipoEstatusEmpleado=196 AND e.fechaEliminacion='0000-00-00 00:00:00'
LEFT JOIN socio s ON s.idPersona=p.idPersona AND s.eliminado=0
    AND s.idTipoEstatusSocio=81
WHERE p.idPersona={$persona} AND p.bloqueo=0
GROUP BY p.idPersona";
        $query = DB::connection('crm')->select($sql);

        if (count($query) > 0) {
            $query = array_map(function ($x) {return (array) $x;}, $query);
            $fila = $query[0];
            return $fila;
        } else {
            return null;
        }
    }

    /**
     * Genera una array con los datos generales de la cuenta de mail solicitada
     *
     * @param integer $persona Identificador de la persona
     * @param integer $id      Indentificador de la cuenta de mail
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function datosMail($persona, $id)
    {
        settype($persona, 'integer');
        settype($id, 'integer');

        if ($persona == 0) {
            return null;
        }

        if ($id == 0) {
            return null;
        }

        $this->db->select('idTipoMail AS tipoMail, mail, bloqueoMail AS bloqueo');
        $this->db->from(TBL_MAIL);
        $this->db->where('idPersona', $persona);
        $this->db->where('idMail', $id);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila;
        } else {
            return null;
        }
    }

    /**
     * Verifica que una persona tenga registrados sus datos minimos
     *
     * @param integer $persona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function datosMinimos($persona)
    {
        settype($persona, 'integer');

        if ($persona == 0) {
            return false;
        }

        $this->db->select('nombre, paterno, materno, idTipoPersona');
        $this->db->from(TBL_PERSONA);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('idPersona', $persona);
        $query = $this->db->get();
        if ($query->num_rows != 1) {
            return false;
        }

        $fila = $query->row_array();
        if ($fila->nombre == '' || $fila->paterno == '') {
            return false;
        }

        $total      = 0;
        $okTelefono = false;
        $this->db->select('telefono');
        $this->db->from(TBL_TELEFONO);
        $this->db->where('eliminado', 0);
        $this->db->where('idPersona', $persona);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $total++;
            $telefonos = $query->result_array();
            foreach ($telefonos as $fila) {
                if ($fila->$telefono != '') {
                    $okTelefono = true;
                }
            }
        }

        $okMail = false;
        $total  = 0;
        $this->db->select('mail');
        $this->db->from(TBL_MAIL);
        $this->db->where('eliminado', 0);
        $this->db->where('idPersona', $persona);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $total++;
            $mails = $query->result_array();
            foreach ($mails as $fila) {
                if ($fila->mail != '') {
                    $okMail = true;
                }
            }
        }

        if ($total == 0) {
            return false;
        }

        if ($okTelefono == false && $okMail == false) {
            return false;
        }

        return true;
    }

    /**
     * Genera una array con los datos generales del telefono solicitado
     *
     * @param integer $persona Identificador de persona
     * @param integer $id      Identificador de telefono
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function datosTelefono($persona, $id)
    {
        settype($persona, 'integer');
        settype($id, 'integer');

        if ($persona == 0) {
            return null;
        }

        if ($id == 0) {
            return null;
        }

        $this->db->select('idTipoTelefono AS tipoTelefono, telefono, extension, lada');
        $this->db->from(TBL_TELEFONO);
        $this->db->where('idPersona', $persona);
        $this->db->where('idTelefono', $id);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila;
        } else {
            return null;
        }
    }

    /**
     * [datosTitularRFC description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function datosTitularRFC($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT p.nombre,p.paterno,p.materno, p.fechanacimiento
            FROM socio s
            INNER JOIN persona p on s.idpersona=p.idpersona
            INNER JOIN membresia m on s.idUnicoMembresia=m.idUnicoMembresia
            WHERE s.idunicomembresia=" . $idUnicoMembresia . " LIMIT 1";
        $query = $this->db->query($sql);

        if ($query->num_rows == 1) {
            $data = $query->row_array();
        }

        return $data;
    }

    /**
     * Aplica derechos ARCO bajo esquema de cancelacion
     *
     * @param  integer $idPersona Idenficador de persona
     *
     * @author Jorge Cruz
     *
     * @return [type]            [description]
     */
    public function derechosArcoCancelacion($idPersona)
    {
        settype($idPersona, 'integer');

        if ($idPersona > 1) {
            exec('rm -rf /respaldos/www/sportsworld/siac_ci/imagenes/personas/' . $idPersona . '.*');

            $sql = "SELECT d.rutaDocumento
                FROM documentopersona dp
                INNER JOIN documento d ON d.idDocumento=dp.idDocumento
                    AND d.idTipoDocumento NOT IN (2, 21, 744, 730)
                    AND d.rutaDocumento LIKE '%.%'
                WHERE dp.idPersona=$idPersona";
            $query = $this->db->query($sql);

            if ($query->num_rows > 0) {
                $documentos = $query->result_array();
                foreach ($documentos as $fila) {
                    if ($fila->rutaDocumento != '') {
                        if (file_exists($fila->rutaDocumento)) {
                            exec('rm -rf ' . $fila->rutaDocumento);
                            echo 'rm -rf ' . $fila->rutaDocumento . '<br>';
                        }
                    }
                }
            }

            $sql = "CREATE TEMPORARY TABLE tmp_mail_arco
                SELECT DISTINCT m.mail FROM mail m
                WHERE m.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE mail m
                INNER JOIN tmp_mail_arco t ON t.mail=m.mail
                SET m.mail='', m.fechaEliminacion=NOW()";
            $this->db->query($sql);

            $sql = "CREATE TEMPORARY TABLE tmp_telefono_arco
                SELECT DISTINCT t.telefono FROM telefono t
                WHERE t.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE telefono f
                INNER JOIN tmp_telefono_arco t ON f.telefono=t.telefono
                SET f.lada='', f.telefono='', f.extension='', f.fechaEliminacion=NOW()";
            $this->db->query($sql);

            $sql = "UPDATE domicilio d
                SET d.calle='', d.numero='', d.colonia='', d.idEstado=0, d.idMunicipio=0,
                    d.cp='', d.referencia='', d.fiscal=0, d.RFC='', d.nombreFiscal='',
                    d.fechaEliminacion=NOW()
                WHERE d.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE persona p
                SET p.nombre='', p.nombreCorto='', p.paterno='', p.materno='', p.idTipoPersona=0,
                    p.fechaNacimiento='0000-00-00', p.idTipoSexo=0, p.idTipoEstadoCivil=0, p.RFC='',
                    p.CURP='', p.idTipoTituloPersona=0, p.idEstado=0, p.idTipoProfesion=0,
                    p.fechaTour='0000-00-00 00:00:00', p.idTipoEscolaridad=0,
                    p.idTipoNivelIngresos=0, hijos=0
                WHERE p.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "DELETE h FROM huella h
                WHERE h.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "DELETE pa FROM personaauditoria pa
                WHERE pa.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "DELETE pa FROM personaenfermedad pa
                WHERE pa.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "DELETE pa FROM personalevenshtein pa
                WHERE pa.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE empleado e
                SET e.imss='', e.rfc='', e.fechaEliminacion=NOW()
                WHERE e.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE sociodatostarjeta sdt
                INNER JOIN socio s ON s.idSocio=sdt.idSocio
                SET sdt.idBanco=0, sdt.numeroTarjetaCta='', sdt.nombreTarjeta='', sdt.tipoPago=0,
                    sdt.tipoTarjeta=0, sdt.mesExpiracion=0, sdt.anioExpiracion=0, sdt.diaCargo=0,
                    sdt.motivoCancelacion='', sdt.fechaEliminacion=NOW()
                WHERE s.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE membresiainvolucrado mi
                SET mi.fechaEliminacion=NOW()
                WHERE mi.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE socio s
                SET s.fechaEliminacion='0000-00-00 00:00:00', s.idTipoEstatusSocio=82
                WHERE s.idPersona=$idPersona AND s.eliminado=0";
            $this->db->query($sql);

            $sql = "DELETE l FROM logmailusuariosinactivos l
                WHERE l.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "DELETE l FROM `log` l
                WHERE l.idPersonaAplica=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE cita c
                INNER JOIN respuesta r ON r.idCita=c.idCita
                SET r.respuesta='', r.fechaEliminacion=NOW()
                WHERE c.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE cita c
                SET c.fechaEliminacion=NOW(), c.fecha='0000-00-00'
                WHERE c.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "CREATE TEMPORARY TABLE tmp_agenda_arco
                SELECT DISTINCT aap.idAgendaActividad
                FROM agendaactividadparticipante aap
                WHERE aap.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "CREATE TEMPORARY TABLE tmp_log_arco
                SELECT DISTINCT s.idUnicoMembresia
                FROM socio s
                WHERE s.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "DELETE l FROM `log` l
                INNER JOIN tmp_log_arco t ON l.idUnicoMembresia=t.idUnicoMembresia";
            $this->db->query($sql);

            $sql = "UPDATE agendaactividad a
                INNER JOIN tmp_agenda_arco t ON a.idAgendaActividad=t.idAgendaActividad
                SET a.titulo='', a.descripcion='', a.fechaEliminacion=NOW()";
            $this->db->query($sql);

            $sql = "DELETE l FROM logagendaactividad l
                INNER JOIN tmp_agenda_arco t";
            $this->db->query($sql);

            $sql = "UPDATE socios.usuarioweb u
                SET u.usuario='', u.password='', u.estatus=0, u.ultimoAcceso='0000-00-00 00:00:00',
                    u.ultimaActividad='0000-00-00 00:00:00', u.fechaEliminacion=NOW()
                WHERE u.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE socios.activacion u
                SET u.fechaEliminacion=NOW()
                WHERE u.idPersona=$idPersona AND u.fechaEliminacion='0000-00-00 00:00:00'";
            $this->db->query($sql);

            $sql = "UPDATE movimiento m
                SET m.arco = 1
                WHERE m.idPersona=$idPersona";
            $this->db->query($sql);

            $sql = "UPDATE documentopersona dp
                INNER JOIN documento d ON d.idDocumento=dp.idDocumento
                SET d.texto='', d.rutaDocumento='', d.firma='', d.fechaEliminacion=NOW()
                WHERE dp.idPersona=$idPersona";
            $this->db->query($sql);

            return true;
        }

        return false;
    }

    /**
     * [detalleMenor description]
     *
     * @param  [type] $idPersona [description]
     *
     * @return [type]            [description]
     */
    public function detalleMenor($idPersona)
    {
        settype($idPersona, 'integer');

        $r['enfermedad']      = 0;
        $r['cualEnfermedad']  = '';
        $r['actividad']       = 0;
        $r['cualActividad']   = '';
        $r['medicamento']     = 0;
        $r['cualMedicamento'] = '';

        if ($idPersona == 0) {
            return $r;
        }

        $this->db->select('enfermedad, cualEnfermedad, actividad, cualActividad, medicamento, cualMedicamento');
        $this->db->from('menordetalle');
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            $fila = $query->row_array();

            $r['enfermedad']      = $fila['enfermedad'];
            $r['cualEnfermedad']  = $fila['cualEnfermedad'];
            $r['actividad']       = $fila['actividad'];
            $r['cualActividad']   = $fila['cualActividad'];
            $r['medicamento']     = $fila['medicamento'];
            $r['cualMedicamento'] = $fila['cualMedicamento'];
        }

        return $r;
    }

    /**
     * Regresa el numero de dias transcurridos desde la ultima actulizacion de la persona
     *
     * @param  integer $idPersona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function diasActualizacion($idPersona)
    {
        settype($idPersona, 'integer');

        if ($idPersona <= 0) {
            return 0;
        }

        $sql = "SELECT DATEDIFF(DATE(NOW()), fechaActualizacion) AS dias
            FROM persona
            WHERE idPersona=" . $idPersona;
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila['dias'];
        }

        return 0;
    }

    /**
     * Valida si la cuenta de correo electronico se encuentra previamente registra en la base de mails
     *
     * @param string $mail Cuenta de mail a buscar
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function duplicadoMail($mail)
    {
        $this->db->where('mail', $mail);
        $this->db->where('eliminado', 0);
        $this->db->from(TBL_MAIL);
        $total = $this->db->count_all_results();

        if ($total > 0) {
            return true;
        }
        return false;
    }

    private function limpiarTexto($String)
    {
        $String = str_replace(array('á', 'à', 'â', 'ã', 'ª', 'ä'), "a", $String);
        $String = str_replace(array('Á', 'À', 'Â', 'Ã', 'Ä'), "A", $String);
        $String = str_replace(array('Í', 'Ì', 'Î', 'Ï'), "I", $String);
        $String = str_replace(array('í', 'ì', 'î', 'ï'), "i", $String);
        $String = str_replace(array('é', 'è', 'ê', 'ë'), "e", $String);
        $String = str_replace(array('É', 'È', 'Ê', 'Ë'), "E", $String);
        $String = str_replace(array('ó', 'ò', 'ô', 'õ', 'ö', 'º'), "o", $String);
        $String = str_replace(array('Ó', 'Ò', 'Ô', 'Õ', 'Ö'), "O", $String);
        $String = str_replace(array('ú', 'ù', 'û', 'ü'), "u", $String);
        $String = str_replace(array('Ú', 'Ù', 'Û', 'Ü'), "U", $String);
        $String = str_replace(array('[', '^', '´', '`', '¨', '~', ']'), "", $String);
        $String = str_replace("ç", "c", $String);
        $String = str_replace("Ç", "C", $String);
        $String = str_replace("ñ", "n", $String);
        $String = str_replace("Ñ", "N", $String);
        $String = str_replace("Ý", "Y", $String);
        $String = str_replace("ý", "y", $String);

        return $String;
    }

    /**
     * Valida si el nombre indicado ya se encuentra previamente registrado en la base de datos
     *
     * @param string $nombre Nombre de la persona a buscar
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function duplicadoNombre($nombre, $paterno, $materno, $omitir = 0, $sexo = 0)
    {
        settype($omitir, 'integer');
        settype($sexo, 'integer');

        $salida = array();

        // -- Para desahabilitar validacion de duplicidad cometar desde esta linea
        if ($nombre == '') {
            return $salida;
        }

        $nombre  = $this->limpiarTexto($nombre);
        $paterno = $this->limpiarTexto($paterno);
        $materno = $this->limpiarTexto($materno);

        $ci = &get_instance();
        $ci->load->model('un_model');
        $idEmpresaGrupo = $ci->un_model->obtenerEmpresaGrupo($this->session->userdata('idUn'));
        $idUsuario      = $this->session->userdata('idUsuario');

        $sql = "SET @nombre_pd_$idUsuario = '$nombre';";
        $this->db->query($sql);
        $sql = "SET @paterno_pd_$idUsuario = '$paterno';";
        $this->db->query($sql);
        $sql = "SET @materno_pd_$idUsuario = '$materno';";
        $this->db->query($sql);

        $sql = "SET @name_pd_$idUsuario = UPPER(CONCAT(@nombre_pd_$idUsuario,' ',@paterno_pd_$idUsuario,' ',@materno_pd_$idUsuario));";
        $this->db->query($sql);

        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, 'Ñ', 'N');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, 'Á', 'A');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, 'É', 'E');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, 'Í', 'I');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, 'Ó', 'O');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, 'Ú', 'U');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, 'Ü', 'U');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '.', '');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, ',', '');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, ';', '');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, ':', '');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '+', '');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '-', '');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '*', '');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '  ', ' ');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '  ', ' ');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '  ', ' ');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '  ', ' ');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '  ', ' ');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '  ', ' ');";
        $this->db->query($sql);
        $sql = "SET @name_pd_$idUsuario = REPLACE(@name_pd_$idUsuario, '  ', ' ');";
        $this->db->query($sql);

        $sql   = "SELECT @name_pd_$idUsuario AS nombre";
        $query = $this->db->query($sql);
        $fila  = $query->row_array();

        $pre_nombre = $fila["nombre"];
        $arrNombre  = explode(' ', $pre_nombre);

        $total = count($arrNombre);

        $expresion = '';
        for ($i = 0; $i < $total; $i++) {
            if (strlen($arrNombre[$i]) > 4) {
                if ($expresion != '') {
                    $expresion .= ' ';
                }
                $expresion .= substr($arrNombre[$i], 0, 4) . '*';
            }
        }

        $sql = "SET @maximo_pd_$idUsuario = LENGTH(@name_pd_$idUsuario)+3;";
        $this->db->query($sql);
        $sql = "SET @minimo_pd_$idUsuario = LENGTH(@name_pd_$idUsuario)-3;";
        $this->db->query($sql);

        $sql_omitir = '';
        if ($omitir > 0) {
            $sql_omitir = ' AND p.idPersona<>' . $omitir;
        }

        $w_sexo = '';
        if ($sexo > 0) {
            $w_sexo = " AND p.idTIpoSexo IN (0, $sexo) ";
        }

        if ($idUsuario != 1514) {
            $sql = "INSERT INTO zzzduplicados (idUsuario, texto, fechaRegistro)
                VALUES ($idUsuario, CONCAT_WS(' ', '$nombre', '$paterno', '$materno'), NOW())";
            $this->db->query($sql);
        }

        $sql = "SELECT p.idPersona, UPPER(CONCAT_WS(' ', TRIM(p.nombre), TRIM(p.paterno), TRIM(p.materno))) AS nombre,
                m.idMembresia AS membresia, u.nombre AS club,
                CONCAT(TRIM(tel.lada), TRIM(tel.telefono)) AS telefono,
                ma.mail, ma2.mail AS mailEmpleado, pv.idPersonaVendedor,
                IF(pv.fechaActualizacion IS NULL, 1000, DATEDIFF(DATE(NOW()), pv.fechaActualizacion)) AS dias,
                0 as dif
            FROM personalevenshtein pl
            INNER JOIN persona p ON p.idPersona=pl.idPersona AND p.bloqueo=0 AND p.fechaEliminacion='0000-00-00 00:00:00'
                AND p.idEmpresaGrupo=$idEmpresaGrupo
            LEFT JOIN socio s ON s.idPersona=pl.idPersona AND s.fechaEliminacion='0000-00-00 00:00:00'
                AND s.idTipoEstatusSocio<>82
            LEFT JOIN membresia m ON m.idUnicoMembresia=s.idUnicoMembresia
            LEFT JOIN un u ON u.idUn=m.idUn
            LEFT JOIN telefono tel on tel.idPersona=pl.idPersona AND tel.eliminado=0
            LEFT JOIN mail ma ON ma.idPersona=pl.idPersona AND ma.eliminado=0
            LEFT JOIN prospectovendedor pv ON pv.idPersona=pl.idPersona AND pv.faltaVender=1
                AND pv.fechaEliminacion='0000-00-00 00:00:00'
            LEFT JOIN empleado e ON e.idPersona=pv.idPersonaVendedor AND e.idTipoEstatusEmpleado=196
            LEFT JOIN mail ma2 ON ma2.idPersona=e.idPersona AND ma2.idTipoMail=37
                AND ma2.eliminado=0
            WHERE pl.nombreCompleto=@name_pd_$idUsuario $w_sexo $sql_omitir
            GROUP BY pl.idPersona";
        $query = $this->db->query($sql);

        /*
        $sql = "CREATE TEMPORARY TABLE personalevenshtein_pre_$idUsuario ENGINE=MEMORY
        SELECT p.idPersona, REPLACE(l.nombreCompleto, ' ', '') AS nombreCompleto,
        LENGTH(REPLACE(l.nombreCompleto, ' ', '')) AS longitud
        FROM personalevenshtein l
        INNER JOIN persona p ON p.idPersona=l.idPersona AND p.bloqueo=0 AND p.idEmpresaGrupo=$idEmpresaGrupo
        AND p.fechaEliminacion='0000-00-00 00:00:00'
        WHERE l.longitud BETWEEN @minimo_pd_$idUsuario AND @maximo_pd_$idUsuario $w_sexo
        AND MATCH(nombreCompleto) AGAINST ('$expresion' IN BOOLEAN MODE) $sql_omitir;";
        $this->db->query($sql);

        $totReg = $this->db->affected_rows();

        $sql = "INSERT INTO zzz VALUES (CONCAT('LEVENSHTEIN : $idUsuario $totReg [$expresion] (',@minimo_pd_$idUsuario,',',@maximo_pd_$idUsuario,') ', @name_pd_$idUsuario))";
        $this->db->query($sql);

        $sql = "CREATE TEMPORARY TABLE tmp_busperdupli_$idUsuario ENGINE=MEMORY
        SELECT pl.idPersona, LEVENSHTEIN(pl.nombreCompleto, @name_pd_$idUsuario) AS dif
        FROM personalevenshtein_pre_$idUsuario pl
        HAVING (LENGTH(@name_pd_$idUsuario)<=12 AND dif <2) OR (LENGTH(@name_pd_$idUsuario)>12 AND dif <=3);";
        $this->db->query($sql);

        $sql = "SELECT t.idPersona, UPPER(CONCAT_WS(' ', TRIM(p.nombre), TRIM(p.paterno), TRIM(p.materno))) AS nombre,
        m.idMembresia AS membresia, u.nombre AS club,
        CONCAT(TRIM(tel.lada), TRIM(tel.telefono)) AS telefono,
        ma.mail, ma2.mail AS mailEmpleado, pv.idPersonaVendedor,
        IF(pv.fechaActualizacion IS NULL, 1000, DATEDIFF(DATE(NOW()), pv.fechaActualizacion)) AS dias,
        t.dif
        FROM tmp_busperdupli_$idUsuario t
        INNER JOIN persona p ON p.idPersona=t.idPersona
        LEFT JOIN socio s ON s.idPersona=t.idPersona AND s.fechaEliminacion='0000-00-00 00:00:00'
        AND s.idTipoEstatusSocio<>82
        LEFT JOIN membresia m ON m.idUnicoMembresia=s.idUnicoMembresia
        LEFT JOIN un u ON u.idUn=m.idUn
        LEFT JOIN telefono tel on tel.idPersona=t.idPersona AND tel.fechaEliminacion='0000-00-00 00:00:00'
        LEFT JOIN mail ma ON ma.idPersona=t.idPersona AND ma.fechaEliminacion='0000-00-00 00:00:00'
        LEFT JOIN prospectovendedor pv ON pv.idPersona=t.idPersona AND pv.faltaVender=1
        AND pv.fechaEliminacion='0000-00-00 00:00:00'
        LEFT JOIN empleado e ON e.idPersona=pv.idPersonaVendedor AND e.idTipoEstatusEmpleado=196
        LEFT JOIN mail ma2 ON ma2.idPersona=e.idPersona AND ma2.idTipoMail=37
        AND ma2.fechaEliminacion='0000-00-00 00:00:00'
        GROUP BY t.idPersona;";
        $query = $this->db->query($sql);
         */

        if ($query->num_rows > 0) {
            return $query->result_array();
        }

        // -- Hasta aqui

        return $salida;
    }

    /**
     * Regresa la edad de la persona indicada
     *
     * @param integer $persona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public static function edad($persona)
    {
        settype($persona, 'integer');
        if ($persona == 0) {
            return false;
        }

        $sql = 'SELECT edad
            FROM persona
            WHERE idPersona=' . $persona . ' AND fechaNacimiento <> "0000-00-00" ';
        $query = DB::connection('crm')->select($sql);

        if (count($query) > 0) {
            $query = array_map(function ($x) {return (array) $x;}, $query);
            $fila = $query[0];
            return $fila['edad'];
        }

        return 0;
    }

    /**
     * Elimina el registro de un contacto
     *
     * @param integer $id Identificador de la cuenta de correo
     *
     * @author Santa Garcia
     *
     * @return boolean
     */
    public function eliminarContacto($id)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return false;
        }

        $idPersona = 0;
        $this->db->select('idPersona');
        $this->db->from(TBL_CONTACTO);
        $this->db->where('idContacto', $id);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $idPersona = $query->row()->idPersona;
        }

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $datos = array(
            'fechaEliminacion' => date("Y-m-d H:i:s"),
        );

        $this->db->where('idContacto', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_CONTACTO, $datos);

        $this->permisos_model->log('Se elimino contacto de persona', LOG_PERSONA, $idUnicoMembresia, $idPersona);

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return true;
    }

    /**
     * Elimina el registro de un domicilio de la base de datos
     *
     * @param integer $id Identificador del domicilio
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function eliminarDomicilio($id)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return false;
        }

        $idPersona = 0;
        $this->db->select('idPersona');
        $this->db->from(TBL_DOMICILIO);
        $this->db->where('idDomicilio', $id);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $idPersona = $query->row()->idPersona;
        }

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $datos = array(
            'fechaEliminacion' => date("Y-m-d H:i:s"),
        );

        $this->db->where('idDomicilio', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_DOMICILIO, $datos);

        $this->permisos_model->log('Elimino domicilio de persona', LOG_PERSONA, $idUnicoMembresia, $idPersona);

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return true;
    }

    /**
     * Elimina el registro de una cuenta de correo electronico
     *
     * @param integer $id Identificador de la cuenta de correo
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function eliminarMail($id)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return false;
        }

        $idPersona = 0;
        $this->db->select('idPersona');
        $this->db->from(TBL_MAIL);
        $this->db->where('idMail', $id);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $idPersona = $query->row()->idPersona;
        }

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $datos = array(
            'fechaEliminacion' => date("Y-m-d H:i:s"),
        );

        $this->db->where('idMail', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_MAIL, $datos);

        $this->permisos_model->log('Elimino mail de persona', LOG_PERSONA, $idUnicoMembresia, $idPersona);

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return true;
    }

    /**
     * Elimina a responsable del menor especificado por su id
     *
     * @param  integer $id Identificador del responsable del menor
     *
     * @author Jorge Cuz
     *
     * @return boolean
     */
    public function eliminarResponsable($id)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return false;
        }

        $datos = array(
            'fechaEliminacion' => date("Y-m-d H:i:s"),
        );

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idPersona            = 0;
        $idPersonaResponsable = 0;
        $this->db->select('idPersonaMenor, idPersonaResponsable');
        $this->db->from('responsablemenor');
        $this->db->where('idResponsableMenor', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila                 = $query->row_array();
            $idPersona            = (int) $fila['idPersonaMenor'];
            $idPersonaResponsable = (int) $fila['idPersonaResponsable'];
        }

        if ($idPersona > 0) {
            $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

            if ($idSocio > 0) {
                $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
            }

            if ($idUnicoMembresia > 0 && $idPersonaResponsable > 0) {
                $this->db->where('idUnicoMembresia', $idUnicoMembresia);
                $this->db->where('idPersonaResponsable', $idPersonaResponsable);
                $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
                $this->db->update('responsablemenor', $datos);
            }
        }
        $nombre = $this->nombre($idPersonaResponsable);

        $this->db->where('idResponsableMenor', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update('responsablemenor', $datos);

        $this->permisos_model->log('Se elimino Responsable del menor (' . $nombre . ')', LOG_PERSONA, $idUnicoMembresia, $idPersona);

        return true;
    }

    /**
     * Elimina el registro de un numero telefonico
     *
     * @param integer $id Identificador del numero telefonico
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function eliminarTelefono($id)
    {
        settype($id, 'integer');
        if ($id == 0) {
            return false;
        }

        $idPersona = 0;
        $this->db->select('idPersona');
        $this->db->from(TBL_TELEFONO);
        $this->db->where('idTelefono', $id);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $idPersona = $query->row()->idPersona;
        }

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $datos = array(
            'fechaEliminacion' => date("Y-m-d H:i:s"),
        );

        $this->db->where('idTelefono', $id);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->update(TBL_TELEFONO, $datos);

        $this->permisos_model->log('Elimino telefono de persona', LOG_PERSONA, $idUnicoMembresia, $idPersona);

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return true;
    }

    /**
     * Regresa el nombre de la persona solicitada
     *
     * @param integer $persona Identificador de persona
     *
     * @author Sergio Albarran
     *
     * @return string
     */
    public function empleadosPuestoUn($un, $puesto)
    {
        $this->db->select('p.nombre, p.paterno, p.materno, e.idPersona AS personaID');
        $this->db->from(TBL_EMPLEADO . ' e');
        $this->db->join(TBL_PERSONA . ' p', 'p.idPersona=e.idPersona', 'INNER');
        $this->db->join(TBL_EMPLEADOPUESTO . ' ep', 'ep.idEmpleado=e.idEmpleado');
        $this->db->where('e.idTipoEstatusEmpleado', 196);
        $this->db->where('e.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('ep.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('ep.idUn', $un);
        $this->db->where('ep.idPuesto', $puesto);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Regresa el un de la persona solicitada
     *
     * @param integer $idPersona Identificador de persona
     *
     * @author Segio Albarran
     *
     * @return string
     */
    public function empleadosUn($idPersona)
    {
        $this->db->select('ep.idUn');
        $this->db->from(TBL_EMPLEADO . ' e');
        $this->db->join(TBL_EMPLEADOPUESTO . ' ep', 'ep.idEmpleado=e.idEmpleado', 'INNER');
        $this->db->where('e.idPersona', $idPersona);
        $this->db->where('e.idTipoEstatusEmpleado', 196);
        $this->db->where('e.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('ep.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return trim($fila["idUn"]);
        } else {
            return null;
        }
    }

    /**
     * Regresa el puesto por identificador de persona
     *
     * @param integer $idPersona Identificador de persona
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function empleadoArea($idPersona)
    {
        $this->db->select('a.descripcion,e.idEmpleado');
        $this->db->from(TBL_EMPLEADO . ' e');
        $this->db->join(TBL_AREA . ' a', 'a.idArea = e.idArea');
        $this->db->where('e.idPersona', $idPersona);
        $this->db->where('e.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result_array() as $fila) {
                $fila = $query->row_array();
            }
        }
        if (isset($fila)) {
            return $fila;
        } else {
            return null;
        }
    }

    /**
     * Obtiene la estatura de la persona si esta registrado
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function estatura($idPersona)
    {
        settype($idPersona, 'integer');

        if ($idPersona == 0) {
            return 0;
        }
        $sql = "SELECT c.respuesta as estatura
            FROM  " . TBL_RESPUESTA . " c
            WHERE c.idPersona = $idPersona and c.fechaEliminacion = '0000-00-00 00:00:00'
                AND c.idPregunta = " . ESTATURA . "
            ORDER BY 1 DESC LIMIT 1";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila["estatura"];
        }
        return 0;
    }

    /**
     * Inserta/Actuliza los datos en un contacto en la BD
     *
     * @param integer $id       Identificador del contacto a modificar
     * @param integer $persona  Identificador de persona a la cual se el asigan el contacto
     * @param integer $contacto Identificador de persona declarada como contacto
     * @param integer $tipo     Identificador del tipo de contacto
     *
     * @author Jorge Cruz
     *
     * @return <type>
     */
    public function guardarContacto($id, $persona, $contacto, $tipo)
    {
        settype($id, 'integer');
        settype($persona, 'integer');
        settype($contacto, 'integer');
        settype($tipo, 'integer');

        if ($persona == 0 || $contacto == 0 || $tipo == 0) {
            return 0;
        }

        if ($persona == $contacto) {
            return 0;
        }

        $datos = array(
            'idPersona'         => $persona,
            'idTipoContacto'    => $tipo,
            'idPersonaContacto' => $contacto,
        );

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($persona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        if ($id > 0) {
            $this->db->where('idContacto', $id);
            $this->db->update(TBL_CONTACTO, $datos);
        } else {
            $this->db->insert(TBL_CONTACTO, $datos);
            $id = $this->db->insert_id();
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return (-2);
        }

        if ($id > 0) {
            $this->permisos_model->log('Inserto un nuevo contacto', LOG_PERSONA, $idUnicoMembresia, $persona);
        } else {
            $this->permisos_model->log('Modifico contacto de persona', LOG_PERSONA, $idUnicoMembresia, $persona);
        }

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $persona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return $id;
    }

    /**
     * [guardarDatosMenor description]
     * @param  [type] $idPersona [description]
     * @param  [type] $datos     [description]
     * @return [type]            [description]
     */
    public function guardarDatosMenor($idPersona, $datos)
    {
        settype($idPersona, 'integer');

        if ($idPersona > 0) {
            $edad = $this->edad($idPersona);

            if ($edad < 16) {
                $idUnicoMembresia = 0;
                $ci               = &get_instance();
                $ci->load->model('socio_model');

                $idSocio = $ci->socio_model->obtenIdSocio($idPersona);
                if ($idSocio > 0) {
                    $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
                }

                $datos['idPersona'] = $idPersona;

                $this->db->select('idMenorDetalle');
                $this->db->from('menordetalle');
                $this->db->where('idPersona', $idPersona);
                $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
                $query = $this->db->get();

                if ($query->num_rows > 0) {
                    $this->db->where('idPersona', $idPersona);
                    $this->db->update('menordetalle', $datos);

                    $total = $this->db->affected_rows();
                    if ($total > 0) {
                        $this->permisos_model->log('Actualizo informacion sobre menor de edad', LOG_PERSONA, $idUnicoMembresia);
                    }
                } else {
                    $this->db->insert('menordetalle', $datos);
                    $this->db->insert_id();

                    $total = $this->db->affected_rows();
                    if ($total > 0) {
                        $this->permisos_model->log('Ingreso informacion sobre menor de edad', LOG_PERSONA, $idUnicoMembresia);
                    }
                }
            }
        }
    }

    /**
     * Inserta o actualiza los datos de un domicilio
     *
     * @param integer $id            Identificador del domicilio
     * @param integer $persona       Identificador de la persona
     * @param integer $tipoDomicilio Registro del tipo de domicilio
     * @param string  $calle         Nombre de la calle
     * @param string  $numero        Numero exterior/interior del domicilio
     * @param string  $colonia       Nombre de la colonia
     * @param string  $cp            Codigo postal
     * @param integer $estado        Identificador del estado de la republica
     * @param integer $municipio     Identificador del municipio
     * @param string  $referencia    Referencia de ubicacion del domicilio
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function guardarDomicilio($id, $persona, $tipoDomicilio, $calle, $numero, $colonia, $cp, $estado, $municipio, $referencia = '', $fiscal, $RFC, $nombreFiscal) //, $bancario

    {
        settype($persona, 'integer');
        settype($id, 'integer');
        settype($tipoDomicilio, 'integer');
        settype($estado, 'integer');
        settype($municipio, 'integer');
        settype($fiscal, 'integer');

        if ($persona == 0) {
            return (-1);
        }

        if ($fiscal == '1') {
            $datos = array('fiscal' => '0');
            $this->db->where('idPersona', $persona);
            $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
            $this->db->update(TBL_DOMICILIO, $datos);
        }

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($persona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $datos = array(
            'idTipoDomicilio' => $tipoDomicilio,
            'calle'           => trim($calle),
            'numero'          => trim($numero),
            'colonia'         => trim($colonia),
            'cp'              => $cp,
            'idEstado'        => $estado,
            'idMunicipio'     => $municipio,
            'referencia'      => trim($referencia),
            'fiscal'          => $fiscal,
            'RFC'             => ($RFC),
            'nombreFiscal'    => $nombreFiscal,
        );

        if ($id > 0) {
            $this->db->where('idDomicilio', $id);
            $this->db->update(TBL_DOMICILIO, $datos);
            $domicilio = $id;
        } else {
            $datos['idPersona'] = $persona;
            $this->db->insert(TBL_DOMICILIO, $datos);
            $domicilio = $this->db->insert_id();
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return (-2);
        }

        if ($id > 0) {
            $this->permisos_model->log('Inserto una nuevo domicilio', LOG_PERSONA, $idUnicoMembresia, $persona);
        } else {
            $this->permisos_model->log('Modifico datos del domicilio', LOG_PERSONA, $idUnicoMembresia, $persona);
        }

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $persona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return $domicilio;
    }

    /**
     * Inserta o actualiza los datos una cuenta de correo electronico
     *
     * @param integer $id       Identificador de la cuenta de correo electronico
     * @param integer $persona  Identificador de persona
     * @param integer $tipoMail Tipo de mail
     * @param string  $mail     Cuenta de correo electronico
     * @param integer $bloqueo  Bloquear envio de correo electronio (0/1)
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function

    guardarMail($id, $persona, $tipoMail, $mail, $bloqueo) {
        settype($persona, 'integer');
        settype($id, 'integer');
        settype($tipoMail, 'integer');
        settype($bloqueo, 'integer');

        $mail = trim($mail);
        if ($mail == "") {
            return (-1);
        }

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($persona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $datos = array(
            'idTipoMail'  => $tipoMail,
            'mail'        => trim($mail),
            'bloqueoMail' => $bloqueo,
        );

        if ($id > 0) {
            $this->db->where('idMail', $id);
            $this->db->update(TBL_MAIL, $datos);
            $idMail = $id;
        } else {
            $datos['idPersona'] = $persona;
            $this->db->insert(TBL_MAIL, $datos);
            $idMail = $this->db->insert_id();
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return (-1);
        }

        if ($id > 0) {
            $this->permisos_model->log('Inserto una nueva cuenta de mail', LOG_PERSONA, $idUnicoMembresia, $persona);
        } else {
            $this->permisos_model->log('Modifico cuenta de mail', LOG_PERSONA, $idUnicoMembresia, $persona);
        }

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $persona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return $idMail;
    }

    /**
     * guardar mail empleado
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function guardaMailEmpleado($idPersona, $email, $tipoMail)
    {
        settype($idPersona, 'integer');
        settype($tipoMail, 'integer');

        $datos = array(
            'idPersona'   => $idPersona,
            'idTipoMail'  => $tipoMail,
            'mail'        => $email,
            'bloqueomail' => 0,
        );

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $this->db->select('idMail');
        $this->db->from(TBL_MAIL);
        $where = array(
            'idPersona'  => $idPersona,
            'idTipoMail' => $tipoMail,
            'eliminado'  => 0,
        );
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $this->db->where('idMail', $fila['idMail']);
            $this->db->update(TBL_MAIL, $datos);
        } else {
            $this->db->insert(TBL_MAIL, $datos);
        }
        $this->permisos_model->log(utf8_decode('Se registro mail empleado a (' . $idPersona . ')(' . date('Y-m-d') . ') '), LOG_PERSONA, $idUnicoMembresia);
        $cambio = $this->db->affected_rows();
        if ($cambio == 0) {
            return false;
        }

        return true;
    }

    /**
     * Inserta o actualiza los datos de una persona
     *
     * @param integer $persona Identificador de persona
     * @param array   $info    Array con los datos de la persona
     *
     * @author Jorge Cruz
     *
     * @return integer
     */
    public function guardarPersona($persona, $info = null)
    {
        settype($persona, 'integer');

        if ($info == null) {
            return false;
        }

        $datos = array();

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($persona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        if (isset($info['nombre'])) {
            $datos['nombre'] = trim($info['nombre']);
        }
        if (isset($info['paterno'])) {
            $datos['paterno'] = trim($info['paterno']);
        }
        if (isset($info['materno'])) {
            $datos['materno'] = trim($info['materno']);
        }
        if (isset($info['tipo'])) {
            $datos['idTipoPersona'] = $info['tipo'];
        }
        if (isset($info['fecha'])) {
            $datos['fechaNacimiento'] = $info['fecha'];
        }
        if (isset($info['sexo'])) {
            $datos['idTipoSexo'] = $info['sexo'];
        }
        if (isset($info['civil'])) {
            $datos['idTipoEstadoCivil'] = $info['civil'];
        }
        if (isset($info['curp'])) {
            $datos['CURP'] = trim($info['curp']);
        }
        if (isset($info['titulo'])) {
            $datos['idTipoTituloPersona'] = $info['titulo'];
        }
        if (isset($info['profesion'])) {
            $datos['idTipoProfesion'] = $info['profesion'];
        }
        if (isset($info['estado'])) {
            $datos['idEstado'] = $info['estado'];
        }
        if (isset($info['bloqueo'])) {
            $datos['bloqueoMail'] = $info['bloqueo'];
        }
        if (isset($info['idTipoNivelIngresos'])) {
            $datos['idTipoNivelIngresos'] = $info['idTipoNivelIngresos'];
        }
        if (isset($info['fallecido'])) {
            $datos['fallecido'] = $info['fallecido'];
        }
        if (isset($info['concesionario'])) {
            $datos['concesionario'] = $info['concesionario'];
        }
        if (isset($info['concesionarioValido'])) {
            $datos['concesionarioValido'] = $info['concesionarioValido'];
        }
        if (isset($info['producto'])) {
            $datos['producto'] = $info['producto'];
        }
        if (isset($info['tour'])) {
            $datos['tour']      = 1;
            $datos['fechaTour'] = date('Y-m-d H:i:s');
        }
        if (isset($info['escolaridad'])) {
            $datos['idTipoEscolaridad'] = $info['escolaridad'];
        }
        if (isset($info['ingresos'])) {
            $datos['idTipoNivelIngresos'] = $info['ingresos'];
        }
        if (isset($info['hijos'])) {
            $datos['hijos'] = $info['hijos'];
        }

        if (count($datos) == 0) {
            return false;
        }

        $ci = &get_instance();
        $ci->load->model('un_model');

        $datos['idEmpresaGrupo'] = $ci->un_model->obtenerEmpresaGrupo($this->session->userdata('idUn'));

        if ($persona > 0) {
            $this->db->where('idPersona', $persona);
            $this->db->update(TBL_PERSONA, $datos);

            $total = $this->db->affected_rows();
            if ($total > 0) {
                $this->permisos_model->log('Modifica datos generales de persona', LOG_PERSONA, $idUnicoMembresia, $persona);
            }
        } else {
            $datos['idPersona'] = $persona;
            $this->db->insert(TBL_PERSONA, $datos);
            $persona = $this->db->insert_id();

            $total = $this->db->affected_rows();
            if ($total > 0) {
                $this->permisos_model->log('Inserto una nueva persona', LOG_PERSONA, 0, $persona);
            }
        }

        if (isset($info['casoKidz'])) {
            if ($info['casoKidz'] == 1) {
                $this->asignaKidz($persona);
            } else {
                $this->eliminaKidz($persona);
            }
        } else {
            $this->eliminaKidz($persona);
        }

        $prospecto = array(
            'fechaActualizacion' => date('Y-m-d H:i:s'),
        );
        $this->db->where('idPersona', $persona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return $persona;
    }

    public function asignaKidz($idPersona)
    {
        $this->db->where('idPersona', $idPersona);
        $this->db->delete('personacasokidz');

        $info['idPersona'] = $idPersona;
        $this->db->insert('personacasokidz', $info);

        $total = $this->db->affected_rows();
        if ($total > 0) {
            $this->permisos_model->log('Asigna validacion kidz', LOG_PERSONA, 0, $idPersona);
        }
    }

    public function eliminaKidz($idPersona)
    {
        $this->db->where('idPersona', $idPersona);
        $this->db->delete('personacasokidz');

        $total = $this->db->affected_rows();
        if ($total > 0) {
            $this->permisos_model->log('Elimina validacion kidz', LOG_PERSONA, 0, $idPersona);
        }
    }

    /**
     * Inserta o actualiza los datos del numero telefonico
     *
     * @param integer $id           Identificador de telefono
     * @param integer $persona      Identificador de persona
     * @param integer $tipoTelefono Tipo de telefono a registrar
     * @param string  $telefono     Numero telefonico
     * @param string  $lada         Lada
     * @param string  $extension    Extension
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function guardarTelefono($id, $persona, $tipoTelefono, $telefono, $lada, $extension)
    {
        settype($persona, 'integer');
        settype($id, 'integer');
        settype($tipoTelefono, 'integer');

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($persona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $datos = array(
            'idTipoTelefono' => $tipoTelefono,
            'telefono'       => trim($telefono),
            'extension'      => trim($extension),
            'lada'           => trim($lada),
        );

        if ($id > 0) {
            $this->db->where('idTelefono', $id);
            $this->db->update(TBL_TELEFONO, $datos);
            $idTelefono = $id;
        } else {
            $datos['idPersona'] = $persona;
            $this->db->insert(TBL_TELEFONO, $datos);
            $idTelefono = $this->db->insert_id();
        }
        $total = $this->db->affected_rows();
        if ($total == 0) {
            return (-1);
        }

        if ($id > 0) {
            $this->permisos_model->log('Inserto un nuevo numero de telefono', LOG_PERSONA, $idUnicoMembresia);
        } else {
            $this->permisos_model->log('Modifica numero de telefono', LOG_PERSONA, $idUnicoMembresia);
        }

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $persona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);

        return $idTelefono;
    }

    /**
     * [insertaContacto description]
     *
     * @param  [type]  $idPersona    [description]
     * @param  [type]  $tipoContacto [description]
     * @param  [type]  $contacto     [description]
     * @param  integer $op           [description]
     *
     * @return [type]                [description]
     */
    public function insertaContacto($idPersona, $tipoContacto, $contacto, $op = 0)
    {
        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }
        $nombre = $this->nombre($idPersona);

        if ($op == 0) {
            $datos = array(
                'idPersona'         => $idPersona,
                'idTipoContacto'    => $tipoContacto,
                'idPersonaContacto' => $contacto,
            );
            $this->db->insert(TBL_CONTACTO, $datos);
            $total = $this->db->affected_rows();
            if ($total == 0) {
                return 0;
            } else {
                $this->permisos_model->log('Se agrego contacto de persona', LOG_PERSONA, $idUnicoMembresia);
                return true;
            }
        } else {
            $this->db->select('idContacto');
            $this->db->from(TBL_CONTACTO);
            $this->db->where('idContacto', $op);

            $query = $this->db->get();
            if ($query->num_rows() > 0) {
                $fila = $query->row_array();
                $this->db->where('idContacto', $fila['idContacto']);
                $dato = array('idTipoContacto' => $tipoContacto);
                $this->db->update(TBL_CONTACTO, $dato);
            } else {
                return false;
            }
            $total = $this->db->affected_rows();
            if ($total == 0) {
                return 0;
            } else {
                $this->permisos_model->log('Se actualizo contacto de persona', LOG_PERSONA, $idUnicoMembresia);
                return true;
            }
        }

        $prospecto = array(
            'fechaActualizacion' => date("Y-m-d H:i:s"),
        );
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('faltaVender', 1);
        $this->db->update(TBL_PROSPECTOVENDEDOR, $prospecto);
    }

    /**
     * [insertaFoto description]
     *
     * @param  [type] $idPersona [description]
     * @param  [type] $jpg       [description]
     *
     * @return [type]            [description]
     */
    public function insertaFoto($idPersona, $jpg)
    {
        $datos = array(
            'idPersona' => $idPersona,
            'imagen'    => $jpg,
            'tipo'      => 'img-jpg',
        );

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }
        $nombre = $this->nombre($idPersona);

        $this->db->select('idPersona');
        $this->db->from(TBL_TMPACCESOFOTO);
        $this->db->where('idPersona', $idPersona);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $id   = $fila['idPersona'];
            $this->db->where('idPersona', $id);
            $this->db->update(TBL_TMPACCESOFOTO, $datos);

            $this->permisos_model->log('Se actualiza Foto (' . $nombre . ')', LOG_PERSONA, $idUnicoMembresia);
        } else {
            $this->db->insert(TBL_TMPACCESOFOTO, $datos);
            $id = $this->db->insert_id();

            $this->permisos_model->log('Se ingresa Foto (' . $nombre . ')', LOG_PERSONA, $idUnicoMembresia);
        }
        return true;
    }

    /**
     * Inserta los valores cuando se registra el valor de la huella
     *
     * @param integer $idPersona identifica a la persona
     * @param string  $huella     cadena que tiene el contenido de la huella
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function insertaHuella($idPersona, $huella)
    {
        $datos = array(
            'idPersona'  => $idPersona,
            'biometrico' => $huella,
        );

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }
        $nombre = $this->nombre($idPersona);

        $this->db->select('idHuella');
        $this->db->from(TBL_HUELLA);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            $fila   = $query->row_array();
            $sql    = "UPDATE " . TBL_HUELLA . " SET biometrico = $huella WHERE idPersona = " . $idPersona . " AND fechaEliminacion='0000-00-00 00:00:00'";
            $query  = $this->db->query($sql);
            $cambio = $this->db->affected_rows();

            $this->permisos_model->log('Se actualiza Huella  (' . $nombre . ') huella (' . $huella . ')', LOG_PERSONA, $idUnicoMembresia);
        } else {
            $sql    = "INSERT INTO " . TBL_HUELLA . " (idPersona, biometrico) VALUE ($idPersona, $huella)";
            $query  = $this->db->query($sql);
            $cambio = $this->db->affected_rows();

            $this->permisos_model->log('Se ingresa Huella  (' . $nombre . ') huella (' . $huella . ')', LOG_PERSONA, $idUnicoMembresia);
        }

        if ($cambio == 0) {
            return false;
        }

        return true;
    }

    /**
     * Inserta los valores cuando se toma la foto a una persona
     *
     * @param integer $idPersona identifica a la persona
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function insertaIdFoto($idPersona)
    {
        $datos = array(
            'idPersona' => $idPersona,
        );

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }
        $nombre = $this->nombre($idPersona);

        $this->db->select('idFoto');
        $this->db->from(TBL_FOTO);
        $where = array('idPersona' => $idPersona);
        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            $this->db->where('idFoto', $fila['idFoto']);
            $this->db->update(TBL_FOTO, $datos);
            $cambio = $this->db->affected_rows();

            $this->permisos_model->log('Se actualiza Foto (' . $nombre . ')', LOG_FOTO, $idUnicoMembresia);
        } else {
            $this->db->insert(TBL_FOTO, $datos);
            $cambio = $this->db->affected_rows();

            $this->permisos_model->log('Se ingresa Foto (' . $nombre . ')', LOG_FOTO, $idUnicoMembresia);
        }

        if ($cambio == 0) {
            return false;
        }

        return true;
    }

    /**
     * [insertaPersonaFoto description]
     * @param  [type] $idPersona [description]
     * @return [type]            [description]
     */
    public function insertaPersonaFoto($idPersona)
    {
        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }
        $nombre = $this->nombre($idPersona);

        $datos = array('idPersona' => $idPersona);

        $this->db->select('idPersona');
        $this->db->from(TBL_FOTO);
        $this->db->where('idPersona', $idPersona);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
        } else {
            $this->db->insert(TBL_FOTO, $datos);
            $id = $this->db->insert_id();

            $this->permisos_model->log('Se ingresa Foto (' . $nombre . ')', LOG_PERSONA, $idUnicoMembresia);
        }
        return true;
    }

    /**
     * [insertaResponsable description]
     * @param  [type]  $idPersona            [description]
     * @param  [type]  $tipoContacto         [description]
     * @param  [type]  $idPersonaResponsable [description]
     * @param  integer $idResponsable        [description]
     * @return [type]                        [description]
     */
    public function insertaResponsable($idPersona, $tipoContacto, $idPersonaResponsable, $idResponsable = 0)
    {
        settype($idPersona, 'integer');
        settype($tipoContacto, 'integer');
        settype($idPersonaResponsable, 'integer');
        settype($idResponsable, 'integer');

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }
        $nombre = $this->nombre($idPersonaResponsable);

        $w_u = '';
        if ($idUnicoMembresia > 0) {
            $w_u = ' OR rm.idUnicoMembresia=' . $idUnicoMembresia;
        }

        $sql = "SELECT COUNT(*) AS total FROM responsablemenor rm
            WHERE (rm.idPersonaMenor=$idPersona $w_u)
                AND rm.fechaEliminacion='0000-00-00 00:00:00'
                AND rm.idPersonaResponsable=$idPersonaResponsable";
        $q_t  = $this->db->query($sql);
        $fila = $q_t->row_array();

        if ($fila['total'] == 0) {
            $datos = array(
                'idPersonaMenor'       => $idPersona,
                'idPersonaResponsable' => $idPersonaResponsable,
                'idUnicoMembresia'     => $idUnicoMembresia,
                'idTipoContacto'       => $tipoContacto,
            );

            if ($idResponsable == 0) {
                $this->db->insert('responsablemenor', $datos);
                $total = $this->db->affected_rows();
                if ($total == 0) {
                    return false;
                } else {
                    $this->permisos_model->log('Se agrego Responsable del menor (' . $nombre . ')', LOG_PERSONA, $idUnicoMembresia);
                    return true;
                }
            } else {
                $this->db->select('idResponsableMenor');
                $this->db->from('responsablemenor');
                $this->db->where('idResponsableMenor', $idResponsable);

                $query = $this->db->get();
                if ($query->num_rows() > 0) {
                    $this->db->where('idResponsableMenor', $idResponsable);
                    $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
                    $this->db->update('responsablemenor', $datos);
                } else {
                    return false;
                }
                $total = $this->db->affected_rows();
                if ($total == 0) {
                    return false;
                } else {
                    $this->permisos_model->log('Se actualizo Responsable del menor (' . $nombre . ')', LOG_PERSONA, $idUnicoMembresia);
                    return true;
                }
            }
        } else {
            return true;
        }
    }

    /**
     * Inserta o actualiza la relacion de persona con tipo concesionario
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function insertaTipoConcesionario($idPersona, $tipoConcesionario, $producto)
    {
        $datos = array(
            'idPersona'        => $idPersona,
            'concesionario'    => $tipoConcesionario,
            'idCuentaProducto' => $producto,
        );

        $this->db->select('idPersonaConcesionario, concesionario');
        $this->db->from(TBL_PERSONACONCESIONARIO);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        $tipo  = '';
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                $this->db->where('idPersonaConcesionario', $fila->idPersonaConcesionario);
                $dato = array('fechaEliminacion' => date("Y-m-d H:i:s"));
                $this->db->update(TBL_PERSONACONCESIONARIO, $dato);
            }
        }
        if ($tipoConcesionario == 3) {
            $dato1 = array(
                'idPersona'        => $idPersona,
                'concesionario'    => 1,
                'idCuentaProducto' => $producto,
            );
            $dato2 = array(
                'idPersona'        => $idPersona,
                'concesionario'    => 2,
                'idCuentaProducto' => $producto,
            );
            $this->db->insert(TBL_PERSONACONCESIONARIO, $dato1);
            $this->db->insert(TBL_PERSONACONCESIONARIO, $dato2);
        } else {
            $this->db->insert(TBL_PERSONACONCESIONARIO, $datos);
            $id = $this->db->insert_id();
        }

        return true;
    }

    /**
     * Genera un array con el criterio de busqueda indicado regresando el identificador de la persona, nobmbre, apellido
     * paterno y apellido materno
     *
     * @param string  $nombre          Nombre a buscar dentro del catalogo de personas
     * @param integer $numeroRegistros Numero de registros a regresar por busqueda
     * @param integer $edadMinima      Edad minima para criterio de busqueda
     * @param integer $edadMaxima      Edad maxima para criterio de busqueda
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public static function listaPersonas($nombre = "", $numeroRegistros = 25, $edadMinima = 0, $edadMaxima = 0)
    {
        settype($numeroRegistros, 'integer');
        settype($edadMinima, 'integer');
        settype($edadMaxima, 'integer');
        $membresia = (int) trim($nombre);
        $w_mem     = '';
        $tipoJoin  = 'LEFT';
        if ($membresia > 0) {
            $w_mem    = ' AND m.idMembresia=' . $membresia;
            $tipoJoin = 'INNER';
        }

        $idEmpresaGrupo = 1;
        if (isset($_SESSION['idEmpresaGrupo'])) {
            $idEmpresaGrupo = (int) $_SESSION['idEmpresaGrupo'];
            if ($idEmpresaGrupo == 0) {
                $idEmpresaGrupo = 1;
            }
        }

        $w_min = '';
        if ($edadMinima > 0) {
            $w_min = ' AND p.edad>=' . $edadMinima;
        }
        $w_max = '';
        if ($edadMaxima > 0) {
            $w_max = ' AND p.edad<=' . $edadMaxima;
        }

        $sql = '';
        if ($membresia > 0) {
            $sql = "SELECT p.idPersona,
                    UPPER(TRIM(p.nombre)) AS nombre,
                    UPPER(TRIM(p.paterno)) AS paterno,
                    UPPER(TRIM(p.materno)) AS materno,
                    m.idMembresia, u.clave
                FROM membresia m
                INNER JOIN un u ON m.idUn=u.idUn
                INNER JOIN socio s ON s.idunicomembresia=m.idUnicoMembresia
                    AND s.idTipoEstatusSocio=81 AND s.eliminado=0
                INNER JOIN persona p ON p.idPersona=s.idPersona
                    AND p.idEmpresaGrupo={$idEmpresaGrupo}
                    AND p.fechaEliminacion='0000-00-00 00:00:00' {$w_min} {$w_max}
                WHERE m.idMembresia={$membresia} AND m.idTipoEstatusMembresia=27
                    AND m.eliminado=0
                ORDER BY p.nombre, p.paterno, p.materno
                LIMIT {$numeroRegistros} ";
        } else {
            $nom    = trim($nombre);
            $nombre = DB::connection('crm')->raw(trim($nombre));

            $sql = "CREATE TEMPORARY TABLE tmp_lista_raza
                SELECT p1.idPersona
                FROM personalevenshtein l
                INNER JOIN persona p1 ON p1.idPersona=l.idPersona
                    AND p1.idEmpresaGrupo=1 AND p1.bloqueo=0
                   AND p1.fechaEliminacion='0000-00-00 00:00:00'
                WHERE MATCH(nombreCompleto) AGAINST ('{$nombre}' IN BOOLEAN MODE)";
            $query = DB::connection('crm')->select($sql);

            $sql   = "CREATE INDEX idx_tmp_lista_raza ON tmp_lista_raza (idPersona)";
            $query = DB::connection('crm')->select($sql);

            $sql = "
SELECT p.idPersona,
UPPER(TRIM(p.nombre)) AS nombre,
UPPER(TRIM(p.paterno)) AS paterno,
UPPER(TRIM(p.materno)) AS materno,
IFNULL(m.idMembresia,'') AS idMembresia, IFNULL(u.clave,'') AS clave,
IF(m.idMembresia IS NULL, 1, 0) AS tieneMembresia
FROM persona p
INNER JOIN tmp_lista_raza t ON t.idPErsona=p.idPersona
LEFT JOIN socio s ON s.idPersona=p.idPersona AND s.idTipoEstatusSocio=81 AND s.eliminado=0
LEFT JOIN membresia m ON m.idUnicoMembresia=s.idUnicoMembresia AND m.idTipoEstatusMembresia=27
    AND m.eliminado=0
LEFT JOIN un u ON u.idUn=m.idUn
WHERE CONCAT_WS(' ', p.nombre, p.paterno, p.materno) LIKE '%$nom%' $w_min $w_max
ORDER BY 7, p.nombre, p.paterno, p.materno
LIMIT $numeroRegistros";

        }
        $query = DB::connection('crm')->select($sql);

        if (count($query) > 0) {
            $res = array();
            $r   = [];
            $a   = array_map(function ($x) {return (array) $x;}, $query);
            foreach ($a as $row) {
                $r['idPersona']   = $row['idPersona'];
                $r['nombre']      = $row['nombre'];
                $r['paterno']     = $row['paterno'];
                $r['materno']     = $row['materno'];
                $r['idMembresia'] = $row['idMembresia'];
                $r['clave']       = $row['clave'];
                if (strlen($r['clave']) >= 5) {
                    $r['clave'] = substr($r['clave'], 2);
                }
                $res[] = $r;
            }
            return $res;
        } else {
            return null;
        }
    }

    /**
     * Genera una array con la lista de contactos por persona
     *
     * @param integer $persona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaContactos($persona)
    {
        settype($persona, 'integer');
        if ($persona == 0) {
            return null;
        }

        $sql = 'SELECT c.idContacto, concat(p.nombre,\' \',p.paterno,\' \',p.materno)as nombre,
                tc.descripcion  AS tipo, c.idPersonaContacto, c.idTipoContacto
            FROM contacto c
            INNER JOIN tipocontacto tc on tc.idTipoContacto = c.idTipoContacto
            INNER JOIN persona p on p.idPersona = c.idPersonaContacto
            WHERE c.idPersona= ' . $persona . ' AND c.fechaEliminacion="0000-00-00 00:00:00"';
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Obetner lista huellas personas bloqueadas
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function listaHuellasBloqueadas()
    {
        $this->db->select('h.idPersona,h.biometrico');
        $this->db->from(TBL_PERSONABLOQUEO . ' p');
        $this->db->join(TBL_HUELLA . ' h', 'h.idPersona = p.idPersona');
        $this->db->where('h.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('p.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            return $query->result_array();
        }
        return false;
    }

    /**
     * [listaResposanble description]
     *
     * @param  [type] $persona [description]
     *
     * @return [type]          [description]
     */
    public function listaResposanble($idPersona)
    {
        settype($idPersona, 'integer');

        if ($idPersona == 0) {
            return null;
        }

        $w_membresia      = '';
        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);

            if ($idUnicoMembresia > 0) {
                $w_membresia = ' OR c.idUnicoMembresia=' . $idUnicoMembresia;
            }
        }

        $sql = 'SELECT c.idResponsableMenor, CONCAT(p.nombre,\' \',p.paterno,\' \',p.materno) AS nombre,
            tc.descripcion AS tipo, c.idPersonaResponsable, c.idTipoContacto
            FROM responsablemenor c
            INNER JOIN ' . TBL_TIPOCONTACTO . ' tc on tc.idTipoContacto = c.idTipoContacto
            INNER JOIN ' . TBL_PERSONA . ' p on p.idPersona = c.idPersonaResponsable
            WHERE (c.idPersonaMenor=' . $idPersona . ' ' . $w_membresia . ') AND c.fechaEliminacion="0000-00-00 00:00:00" ';
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Genera un array con los datos generales de los domicilios registrados para una persona
     *
     * @param integer $persona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaDomicilios($persona)
    {
        settype($persona, 'integer');

        if ($persona == 0) {
            return null;
        }

        $this->db->select(
            'd.cp, d.idDomicilio, td.descripcion AS tipoDomicilio, d.calle, d.numero, d.colonia, ' .
            'e.descripcion AS estado, m.descripcion AS municipio, d.RFC, d.nombreFiscal, d.fiscal'
        );
        $this->db->from(TBL_DOMICILIO . ' d');
        $this->db->join(TBL_TIPODOMICILIO . ' td', 'td.idTipoDomicilio = d.idTipoDomicilio');
        $this->db->join(TBL_ESTADO . ' e', 'e.idEstado = d.idEstado', 'left');
        $this->db->join(TBL_MUNICIPIO . ' m', 'm.idMunicipio = d.idMunicipio', 'left');
        $this->db->where('d.fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('d.idPersona', $persona);

        $query = $this->db->get();
        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Genera una array con los datos generales de las cuentas de correo electronico registradas para una persona
     *
     * @param integer $persona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaMails($persona, $tipo = 0)
    {
        settype($persona, 'integer');
        if ($persona == 0) {
            return null;
        }
        $this->db->select('m.idMail, tm.descripcion AS tipoMail, m.mail, m.bloqueoMail AS bloqueo');
        $this->db->from(TBL_MAIL . ' m');
        $this->db->join(TBL_TIPOMAIL . ' tm', 'tm.idTipoMail = m.idTipoMail');
        $this->db->where('m.eliminado', 0);
        $this->db->where('m.idPersona', $persona);
        if ($tipo > 0) {
            $this->db->where('m.idTipoMail', $tipo);
        }
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * Genera una array con los datos generales de los numeros telefonicos registrados para una persona
     *
     * @param integer $persona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function listaTelefonos($persona, $tipo = 0)
    {
        settype($persona, 'integer');
        settype($tipo, 'integer');
        if ($persona == 0) {
            return null;
        }

        $this->db->select('t.idTelefono, tt.descripcion AS tipoTelefono, t.lada, t.telefono, t.extension ');
        $this->db->from(TBL_TELEFONO . ' t');
        $this->db->join(TBL_TIPOTELEFONO . ' tt', 'tt.idTipoTelefono = t.idTipoTelefono');
        $this->db->where('t.eliminado', 0);
        $this->db->where('t.idPersona', $persona);
        if ($tipo > 0) {
            $this->db->where('t.idTipoTelefono', $tipo);
        }
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return null;
        }
    }

    /**
     * obtener mail persona
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function mail($usuario, $tipoMail)
    {
        settype($tipoMail, 'integer');

        $this->db->select('m.mail');
        $this->db->from(TBL_MAIL . ' m');
        $this->db->join(TBL_USUARIOS . ' u', 'u.idPersona = m.idPersona and u.estatus=1 and u.fechaEliminacion="0000-00-00 00:00:00"');
        $this->db->where('u.NombreUsuario', $usuario);
        $this->db->where('m.idTipoMail', MAIL_TIPO_EMPLEADO);
        $this->db->where('m.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                return $fila->mail;
            }
        } else {
            return 0;
        }
    }

    /**
     * obtener mail persona
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function mailPersona($idPersona, $idTipoMail = 37)
    {
        settype($idPersona, 'integer');

        $this->db->select('m.mail');
        $this->db->from(TBL_MAIL . ' m');
        $this->db->join(TBL_PERSONA . ' p', 'p.idPersona = m.idPersona and p.fechaEliminacion="0000-00-00 00:00:00"');
        $this->db->where('m.idTipoMail', $idTipoMail);
        $this->db->where('m.idPersona', $idPersona);
        $this->db->where('m.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                return $fila->mail;
            }
        } else {
            return 0;
        }
    }

    /**
     * Regresa el nombre de la persona solicitada
     *
     * @param integer $persona Identificador de persona
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function nombre($persona)
    {
        settype($persona, 'integer');
        if ($persona == 0) {
            return null;
        }

        $this->db->select('nombre, paterno, materno');
        $this->db->from(TBL_PERSONA);
        $this->db->where('idPersona', $persona);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return trim($fila["nombre"] . ' ' . $fila["paterno"] . ' ' . $fila["materno"]);
        } else {
            return null;
        }
    }

    /**
     * Regresa el nombbre descriptivo al tipo de persona solicitado
     *
     * @param integer $tipo Tipo de persona
     *
     * @author Jorge Cruz
     *
     * @return string
     */
    public function nombreTipoCliente($tipo)
    {
        settype($tipo, 'integer');

        $ci = &get_instance();
        $ci->load->model('tipocliente_model');

        $descripcion = $ci->tipocliente_model->nombreRolCliente($tipo);

        return $descripcion;
    }

    /**
     * Obtiene el peso de la persona si esta registrado
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function peso($idPersona)
    {
        settype($idPersona, 'integer');

        if ($idPersona == 0) {
            return 0;
        }
        $sql = "select c.respuesta as peso
            from  " . TBL_RESPUESTA . " c
            WHERE c.idPersona = $idPersona and c.fechaEliminacion = '0000-00-00 00:00:00'
            and c.idPregunta = " . PESO . " order by 1 desc limit 1;";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila["peso"];
        }
        return 0;
    }

    /**
     * Regresa la profesion
     *
     * @param integer $idTipoProfesion Identificador de persona
     *
     * @author
     *
     * @return integer
     */
    public function profesion($idTipoProfesion)
    {
        settype($idTipoProfesion, 'integer');
        if ($idTipoProfesion == 0) {
            return false;
        }

        $this->db->select('descripcion');
        $this->db->from(TBL_TIPOPROFESION);
        $this->db->where('activo', 1);
        $this->db->where('idTipoProfesion', $idTipoProfesion);
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                return $fila->descripcion;
            }
        } else {
            return 0;
        }
    }

    /**
     * Regresa ocupacion de una persona
     *
     * @param integer $persona Identificador de persona
     *
     * @author Santa Garcia
     *
     * @return string
     */
    public function ocupacion($persona)
    {
        settype($persona, 'integer');
        if ($persona == 0) {
            return null;
        }

        $this->db->select('tp.descripcion');
        $this->db->from(TBL_PERSONA . ' p');
        $this->db->join(TBL_TIPOPROFESION . ' tp', 'tp.idTipoProfesion = p.idTipoProfesion and tp.activo = 1');
        $this->db->where('p.idPersona', $persona);
        $this->db->where('p.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila["descripcion"];
        } else {
            return null;
        }
    }

    /**
     * Obtiene datos fiscales de una persona
     *
     * @param integer $idPersona Identificado de persona
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenDatosFiscales($idPersona)
    {
        settype($idPersona, 'integer');
        $datos = array();

        if (!$idPersona) {
            return $datos;
        }
        $this->db->where("d.fiscal", 1);
        $this->db->where("d.fechaEliminacion", "0000-00-00 00:00:00");
        $this->db->where("d.idPersona", $idPersona);
        $this->db->join(TBL_TIPODOMICILIO . " td", "d.idTipoDomicilio = td.idTipoDomicilio AND td.activo = 1", "left");
        $this->db->join(TBL_ESTADO . " e", "d.idEstado = e.idEstado", "left");
        $this->db->join(TBL_MUNICIPIO . " m", "d.idMunicipio = m.idMunicipio", "left");
        $this->db->join(TBL_MAIL, "d.idPersona = mail.idPersona AND mail.fechaEliminacion = '0000-00-00 00:00:00' AND mail.idTipoMail = " . TIPO_MAIL_FISCAL . " AND mail.bloqueoMail = 0", "left");

        $query = $this->db->select(
            "d.idDomicilio, d.nombreFiscal AS razonSocial, mail.idMail,
              mail.mail, d.RFC, m.idMunicipio, m.descripcion AS municipio,
              e.idEstado, e.descripcion AS estado, d.calle, d.numero, d.colonia, d.cp"
        )->get(TBL_DOMICILIO . " d");

        if ($query->num_rows) {
            $datos = $query->row_array();
        }
        return $datos;
    }

    /**
     * [obtenEdadPersona description]
     * @param  [type] $idPersona [description]
     * @return [type]            [description]
     */
    public function obtenEdadPersona($idPersona)
    {
        settype($idPersona, 'integer');

        $sql = "SELECT p.edad
                FROM crm.persona p
                WHERE p.idPersona IN (" . $idPersona . ");";
        $query = $this->db->query($sql);
        $row   = $query->row();

        return $row->edad;
    }

    /**
     * Regresa lista de tipo de mails
     *
     * @param integer $activo Bandera de estatus
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenTiposMail($validaActivo = true)
    {
        settype($activo, 'integer');

        $datos = array('Seleccione');
        $where = array();

        if ($validaActivo) {
            $where = array('activo' => 1);
        }
        $query = $this->db->select(
            'idTipoMail, descripcion AS tipoMail', false
        )->get_where(TBL_TIPOMAIL, $where);

        if ($query->num_rows) {
            foreach ($query->result_array() as $fila) {
                $datos[$fila['idTipoMail']] = $fila['tipoMail'];
            }
        }
        return $datos;
    }

    /**
     * Obtiene el nombre de usuario web de una persona
     *
     * @param integer $idPersona
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function obtenUsuarioWeb($idPersona)
    {
        settype($idPersona, 'integer');

        $datos = array(
            'error'        => 1,
            'mensaje'      => 'Error faltan datos',
            'idUsuarioWeb' => 0,
            'usuario'      => '',
        );
        if (!$idPersona) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $where = array(
            'fechaEliminacion' => '0000-00-00 00:00:00',
            'idPersona'        => $idPersona,
        );
        $query = $this->db->select('idUsuarioWeb, usuario', false)->get_where(TBL_USUARIOWEB, $where, 1);

        if ($query->num_rows > 0) {
            $row                   = $query->row_array();
            $datos['idUsuarioWeb'] = $row['idUsuarioWeb'];
            $datos['usuario']      = $row['usuario'];
        }

        return $datos;
    }

    /**
     * [obtenerContacto description]
     * @param  [type]  $idPersona  [description]
     * @param  integer $idContacto [description]
     * @return [type]              [description]
     */
    public function obtenerContacto($idPersona, $idContacto = 0)
    {
        settype($persona, 'integer');

        if ($idPersona == 0 && $idContacto == 0) {
            return null;
        }

        $this->db->select('idPersonaContacto,idTipoContacto');
        $this->db->from(TBL_CONTACTO);
        if ($idContacto == 0) {
            $where = array('idPersona' => $idPersona);
        } else {
            $where = array('idContacto' => $idContacto);
        }

        $this->db->where($where);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $dato[] = $fila;
            }
        }
        if (isset($dato)) {
            return $dato;
        }
    }

    /**
     * Verifica si existe RFC guardado en la base de datos
     *
     * @param inetger $idUnicoMembresia    idUnicoMembresia
     * @author Antonio Sixtos
     *
     * @return void
     */
    public function obtenRFCDeBase($idPersona)
    {
        $this->db->select('RFC');
        $where = array('idPersona' => $idPersona, 'fiscal' => '1', 'fechaEliminacion' => '0000-00-00 00:00:00');
        $this->db->where($where);
        $this->db->from(TBL_DOMICILIO);
        $this->db->limit(1);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $fila) {
                return $fila->RFC;
            }
        } else {
            return 0;
        }
    }

    /**
     * [obtenerDatosFotos description]
     * @return [type] [description]
     */
    public function obtenerDatosFotos()
    {
        $this->db->select('idPersona');
        $this->db->from(TBL_PERSONA);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * Obetner huella persona
     *
     * @param integer $idPersona identifica a la persona
     *
     * @author Santa Garcia
     *
     * @return void
     */
    public function obtenerHuella($idPersona)
    {
        settype($idPersona, 'integer');

        $this->db->select('biometrico');
        $this->db->from(TBL_HUELLA);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            return $query->result_array();
        }
        return false;
    }

    /**
     *
     * @author Antonio Sixtos
     *
     * @return boolean
     */
    public function obtenUltimoTelefono($idPersona)
    {
        settype($idPersona, 'integer');

        $this->db->select('idTipoTelefono AS tipoTelefono, telefono, extension, lada');
        $this->db->from(TBL_TELEFONO);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', "0000-00-00 00:00:00");
        $this->db->order_by("idTelefono", "DESC");
        $this->db->limit(1);
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila;
        } else {
            return null;
        }
    }

    /**
     * Regresa un array con la lista de persona responsables de menor de edad indicado
     *
     * @param  integer $idPersona Identificador de persona del menor de edad
     *
     * @author Jorge Cruz
     *
     * @return array
     */
    public function responsablesKidz($idPersona)
    {
        settype($idPersona, 'integer');

        $r = array();

        if ($idPersona == 0) {
            return $r;
        }

        $ci = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);

        $w_unico = '';
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
            if ($idUnicoMembresia > 0) {
                $w_unico = ' OR rm.idUnicoMembresia=' . $idUnicoMembresia;
            }
        }

        $sql = "SELECT * FROM (
                SELECT c.idPersonaContacto AS idPersona, tc.descripcion AS tipo,
                    UPPER(CONCAT_WS(' ', TRIM(p.nombre), TRIM(p.paterno), TRIM(p.materno))) AS nombre
                FROM contacto c
                INNER JOIN persona p ON p.idPersona=c.idPersonaContacto
                INNER JOIN tipocontacto tc ON tc.idTipoContacto=c.idTipoContacto
                WHERE c.idPersona=" . $idPersona . " AND c.fechaEliminacion='0000-00-00 00:00:00'
                UNION ALL
                SELECT rm.idPersonaResponsable AS idPersona, tc.descripcion AS tipo,
                    UPPER(CONCAT_WS(' ', TRIM(p.nombre), TRIM(p.paterno), TRIM(p.materno))) AS nombre
                FROM responsablemenor rm
                INNER JOIN persona p ON p.idPersona=rm.idPersonaResponsable
                INNER JOIN tipocontacto tc ON tc.idTipoContacto=rm.idTipoContacto
                WHERE (rm.idPersonaMenor=" . $idPersona . " " . $w_unico . ") AND rm.fechaEliminacion='0000-00-00 00:00:00'
            ) a
            GROUP BY a.idPersona
            ORDER BY a.nombre";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                $temp['idPersona'] = $fila->idPersona;
                $temp['tipo']      = $fila->tipo;
                $temp['nombre']    = $fila->nombre;
                $r[]               = $temp;
            }
        }

        return $r;
    }

    /**
     * Regresa el sexo de la persona indicada
     *
     * @param integer $persona Identificador de persona
     *
     * @author
     *
     * @return integer
     */
    public static function sexo($persona)
    {
        settype($persona, 'integer');
        if ($persona == 0) {
            return false;
        }

        $query = DB::connection('crm')->table(TBL_PERSONA . ' as p')
            ->select('ts.descripcion')
            ->join(TBL_TIPOSEXO . ' as ts', 'ts.idTipoSexo', 'p.idTipoSexo')
            ->where('p.idPersona', $persona);

        if ($query->count() > 0) {
            $query = $query->get()->toArray();
            $fila  = $query[0];
            return $fila->descripcion;
        } else {
            return 0;
        }
    }

    /**
     * [strToHex description]
     *
     * @param  [type] $string [description]
     *
     * @return [type]         [description]
     */
    public function strToHex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }

    /**
     * [tipoPersona description]
     *
     * @param  [type] $idPersona [description]
     *
     * @return [type]            [description]
     */
    public function tipoPersona($idPersona)
    {
        settype($idPersona, 'integer');

        if ($idPersona == 0) {
            return 0;
        }

        $this->db->select('idTipoPersona');
        $this->db->from(TBL_PERSONA);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            return $fila["idTipoPersona"];
        } else {
            return 0;
        }
    }

    /**
     * Regresa el titulo de la Persona
     *
     * @param integer $persona Identificador de persona
     *
     * @author
     *
     * @return array
     */
    public function titulo($persona)
    {
        settype($persona, 'integer');

        if ($persona == 0) {
            return "";
        }

        $this->db->select('tp.descripcion, p.idTipoTituloPersona, p.idTipoSexo as sexo, p.idTipoEstadoCivil');
        $this->db->from(TBL_PERSONA . ' p');
        $this->db->join(TBL_TIPOTITULOPERSONA . ' tp', 'tp.idTipoTituloPersona = p.idTipoTituloPersona');
        $this->db->join(TBL_TIPOSEXO . ' ts', 'ts.idTipoSexo = p.idTipoSexo');
        $this->db->where('p.idPersona', $persona);
        $this->db->where('ts.activo', 1);
        $this->db->where('p.fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            foreach ($query->result() as $fila) {
                if ($fila->idTipoTituloPersona == 139 || $fila->idTipoTituloPersona == 0) {
                    if ($fila->sexo == 12) {
                        if ($fila->idTipoEstadoCivil == 14) {
                            return "Srita.";
                        } else {
                            return "Sra.";
                        }
                    } elseif ($fila->sexo == 13) {
                        return "Sr.";
                    } else {
                        return "";
                    }
                }
                return $fila->descripcion;
            }
        } else {
            return "";
        }
    }

    /**
     * Valida que se hayan actualizado datos personales de la persona
     *
     * @param integer $idUnicoMembresia Identificador unico de membresia
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaDatosActualizados($idUnicoMembresia)
    {
        $CI = &get_instance();
        $CI->load->model('socio_model');
        $CI->load->model('membresia_model');

        settype($idUnicoMembresia, 'integer');

        $datos = array(
            'mensaje'      => 'Error faltan datos',
            'error'        => 1,
            'actualizados' => false,
        );
        if (!$idUnicoMembresia) {
            return $datos;
        }
        $datos['mensaje'] = '';
        $datos['error']   = 0;
        $configExtras     = $CI->membresia_model->obtenExtrasConfiguracion(TIPO_EXTRA_FECHAACTUALIZACIONDATOS);
        $anioMesVigencia  = date('Ym');

        if ($configExtras['error'] == 0) {
            $anioMesVigencia = date('Ym', strtotime($configExtras['finVigencia']));
        }
        $sociosDatosArray = $CI->socio_model->obtenSocios($idUnicoMembresia);

        if ($sociosDatosArray) {
            foreach ($sociosDatosArray as $fila) {
                $sociosPersona[] = $fila->idPersona;
            }
            if ($sociosPersona) {
                $personas = implode(',', $sociosPersona);
                $sql      = "
                    SELECT
                    IF(
                        EXTRACT(YEAR_MONTH FROM IFNULL(MAX(p.fechaRegistro),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL(MAX(p.fechaActualizacion),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL((SELECT MAX(fechaRegistro) FROM " . TBL_MAIL . " m WHERE m.idPersona IN(" . $personas . ") AND m.fechaEliminacion = '0000-00-00 00:00:00'),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL((SELECT MAX(fechaRegistro) FROM " . TBL_TELEFONO . " t WHERE t.idPersona IN(" . $personas . ") AND t.fechaEliminacion = '0000-00-00 00:00:00'),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL((SELECT MAX(fechaRegistro) FROM " . TBL_CONTACTO . " c WHERE c.idPersona IN(" . $personas . ") AND c.fechaEliminacion = '0000-00-00 00:00:00'),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL((SELECT MAX(fechaRegistro) FROM " . TBL_DOMICILIO . " d WHERE d.idPersona IN(" . $personas . ") AND d.fechaEliminacion = '0000-00-00 00:00:00'),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL((SELECT MAX(fechaActualizacion) FROM " . TBL_MAIL . " m WHERE m.idPersona IN(" . $personas . ") AND m.fechaEliminacion = '0000-00-00 00:00:00'),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL((SELECT MAX(fechaActualizacion) FROM " . TBL_TELEFONO . " t WHERE t.idPersona IN(" . $personas . ") AND t.fechaEliminacion = '0000-00-00 00:00:00'),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL((SELECT MAX(fechaActulizacion) FROM " . TBL_CONTACTO . " c WHERE c.idPersona IN(" . $personas . ") AND c.fechaEliminacion = '0000-00-00 00:00:00'),'0000-00-00')) = '" . $anioMesVigencia . "' OR
                        EXTRACT(YEAR_MONTH FROM IFNULL((SELECT MAX(fechaActualizacion) FROM " . TBL_DOMICILIO . " d WHERE d.idPersona IN(" . $personas . ") AND d.fechaEliminacion = '0000-00-00 00:00:00'),'0000-00-00')) = '" . $anioMesVigencia . "',
                        1 , 0
                      ) AS actualizados
                    FROM
                    " . TBL_PERSONA . " p
                    WHERE p.fechaEliminacion = '0000-00-00 00:00:00'
                    AND p.idPersona IN(" . $personas . ")";

                $datos['actualizados'] = $this->db->query($sql)->row()->actualizados;
            }
        }
        return $datos;
    }

    /**
     * Funcion que inserta la anulacion de la validacion de la huella
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function validaHuella($idPersona)
    {
        $datos = array('validacion' => 0);

        $idUnicoMembresia = 0;
        $ci               = &get_instance();
        $ci->load->model('socio_model');

        $idSocio = $ci->socio_model->obtenIdSocio($idPersona);
        if ($idSocio > 0) {
            $idUnicoMembresia = $ci->socio_model->obtenUnicoMembresia($idSocio);
        }

        $this->db->select('idPersona');
        $this->db->from(TBL_HUELLA);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $fila = $query->row_array();
            $id   = $fila['idPersona'];
            $this->db->where('idPersona', $id);
            $this->db->update(TBL_HUELLA, $datos);

            $this->permisos_model->log('Realiza insesibilizacion de huella', LOG_PERSONA, $idUnicoMembresia);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Valida email empleado
     *
     * @param string  $mail      E-mail a a validar
     * @param integer $idPersona Identificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return string
     */
    public function validaMailRepetidoEmpleado($mail, $idPersona)
    {
        settype($mail, 'string');
        settype($idPersona, 'integer');

        $datos = array(
            'error'   => 1,
            'mensaje' => 'Error faltan datos',
            'total'   => -1,
        );
        if (!$mail or !$idPersona) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';
        $datos['mensaje'] = 0;
        $where            = array(
            'm.fechaEliminacion' => '0000-00-00 00:00:00',
            'm.mail'             => $mail,
            'm.idTipoMail'       => TIPO_MAIL_EMPLEADO,
            'm.idPersona <>'     => $idPersona,
        );
        $this->db->join(TBL_PERSONA . ' p', "p.idPersona = m.idPersona", "INNER");
        $this->db->join(TBL_EMPLEADO . ' e', "e.idPersona = p.idPersona AND e.idTipoEstatusEmpleado = " . ESTATUS_EMPLEADO_ACTIVO, "INNER");
        $query = $this->db->select(
            'COUNT(DISTINCT m.idMail)AS total', false
        )->get_where(TBL_MAIL . ' m', $where);

        if ($query->num_rows()) {
            $datos['total'] = $query->row()->total;
        }
        return $datos;
    }

    /**
     * Valida si una persona ya esta registrada en el sistema y/o es un empleado bloqueado
     *
     * @param string  $fechaNacimiento         Fecha de nacimiento
     * @param string  $nombre                  Nombre de la persona
     * @param string  $paterno                 Apellido Paterno
     * @param string  $materno                 Apellido Materno
     * @param integer $idPersona               Identificador de persona
     * @param boolean $validaEmpleadoBloqueado Bandera para validar empleado bloqueado
     *
     * @author Jonathan Alcantara Martinez
     *
     * @return array
     */
    public function validaPersonaRegistrada($fechaNacimiento, $nombre, $paterno, $materno = '', $idPersona = 0, $validaEmpleadoBloqueado = false)
    {
        settype($fechaNacimiento, 'string');
        settype($nombre, 'string');
        settype($paterno, 'string');
        settype($materno, 'string');
        settype($validaEmpleadoBloqueado, 'boolean');

        $datos = array(
            'error'     => 1,
            'mensaje'   => 'Error faltan datos',
            'registros' => 0,
            'personas'  => array(),
        );
        $innerEmpleadoBloqueado = "";

        if ($idPersona) {
            $datosPersona = $this->datosGenerales($idPersona);

            if ($datosPersona) {
                $nombre          = $datosPersona['nombre'];
                $paterno         = $datosPersona['paterno'];
                $materno         = $datosPersona['materno'];
                $fechaNacimiento = $datosPersona['fecha'];
            } else {
                return $datos;
            }
        } elseif (!$fecha and !$nombre and !$paterno) {
            return $datos;
        }
        if ($validaEmpleadoBloqueado) {
            $innerEmpleadoBloqueado = "
                INNER JOIN empleado e ON e.idPersona = p.idPersona
                INNER JOIN empleadobloqueado eb ON eb.idEmpleado = e.idEmpleado
            ";
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $this->db->query("SET @nombre = '" . $nombre . "';");
        $this->db->query("SET @paterno = '" . $paterno . "';");
        $this->db->query("SET @materno = '" . $materno . "';");

        $this->db->query("SET @name = CONCAT(@nombre, @paterno, @materno);");

        $this->db->query("SET @name = REPLACE(@name, '.', ' ');");
        $this->db->query("SET @name = REPLACE(@name, ',', ' ');");
        $this->db->query("SET @name = REPLACE(@name, ';', ' ');");
        $this->db->query("SET @name = REPLACE(@name, ':', ' ');");
        $this->db->query("SET @name = REPLACE(@name, '+', ' ');");
        $this->db->query("SET @name = REPLACE(@name, '-', ' ');");
        $this->db->query("SET @name = REPLACE(@name, ' ', '');");

        $this->db->query("SET @paterno = REPLACE(@paterno, '.', ' ');");
        $this->db->query("SET @paterno = REPLACE(@paterno, ',', ' ');");
        $this->db->query("SET @paterno = REPLACE(@paterno, ';', ' ');");
        $this->db->query("SET @paterno = REPLACE(@paterno, ':', ' ');");
        $this->db->query("SET @paterno = REPLACE(@paterno, '+', ' ');");
        $this->db->query("SET @paterno = REPLACE(@paterno, '-', ' ');");
        $this->db->query("SET @paterno = REPLACE(@paterno, ' ', '');");

        $this->db->query("SET @materno = REPLACE(@materno, '.', ' ');");
        $this->db->query("SET @materno = REPLACE(@materno, ',', ' ');");
        $this->db->query("SET @materno = REPLACE(@materno, ';', ' ');");
        $this->db->query("SET @materno = REPLACE(@materno, ':', ' ');");
        $this->db->query("SET @materno = REPLACE(@materno, '+', ' ');");
        $this->db->query("SET @materno = REPLACE(@materno, '-', ' ');");
        $this->db->query("SET @materno = REPLACE(@materno, ' ', '');");

        $this->db->query("SET @nombre = REPLACE(@nombre, '.', ' ');");
        $this->db->query("SET @nombre = REPLACE(@nombre, ',', ' ');");
        $this->db->query("SET @nombre = REPLACE(@nombre, ';', ' ');");
        $this->db->query("SET @nombre = REPLACE(@nombre, ':', ' ');");
        $this->db->query("SET @nombre = REPLACE(@nombre, '+', ' ');");
        $this->db->query("SET @nombre = REPLACE(@nombre, '-', ' ');");
        $this->db->query("SET @nombre = REPLACE(@nombre, ' ', '');");

        $this->db->query("SELECT LEFT(@paterno,1) INTO @p_p;");
        $this->db->query("SELECT LEFT(@materno,1) INTO @p_m;");
        $this->db->query("SELECT LEFT(@nombre,1) INTO @p_n;");

        $this->db->query("SET @p_p = CONCAT ('%',@p_p,'%');");
        $this->db->query("SET @p_n = CONCAT ('%',@p_n,'%');");

        $this->db->query("SELECT IF(@p_m='' OR @p_m IS NULL, '', CONCAT ('%',@p_m,'%')) INTO @p_m;");

        $this->db->query("SET @maximo = LENGTH(@name)+3;");
        $this->db->query("SET @minimo = LENGTH(@name)-3;");

        $query = $this->db->query("
            SELECT *, LEVENSHTEIN(pl.nombreCompleto, @name) AS dif
            FROM personalevenshtein pl
            INNER JOIN persona p ON p.idPersona=pl.idPersona AND p.fechaEliminacion='0000-00-00 00:00:00'
            AND p.nombre LIKE @p_n AND p.paterno LIKE @p_p AND p.materno LIKE @p_m AND p.fechaNacimiento = '" . $fechaNacimiento . "'
            " . $innerEmpleadoBloqueado . "
            WHERE  pl.longitud BETWEEN @minimo AND @maximo
            HAVING dif <=4;"
        );
        $datos['registros'] = $query->num_rows;

        if ($datos['registros']) {
            $datos['personas'] = $query->result_array();
        }
        return $datos;
    }

    /**
     * Valida si la persona tien un sexo registrado
     *
     * @param integer $idPersona Indentificador de persona
     *
     * @author Jonathan Alcantara
     *
     * @return array
     */
    public function validaSexo($idPersona)
    {
        settype($idPersona, 'integer');

        $datos = array(
            'error'      => 1,
            'mensaje'    => 'Error falta identificador de persona',
            'idTipoSexo' => 0,
        );

        if (!$idPersona) {
            return $datos;
        }
        $datos['error']   = 0;
        $datos['mensaje'] = '';

        $datosPersona = $this->datosGenerales($idPersona);

        if ($datosPersona != null) {
            if ($datosPersona['tipo'] == 44) {
                if ($datosPersona['sexo'] > 0) {
                    $datos['idTipoSexo'] = $datosPersona['sexo'];
                } else {
                    $datos['error']   = 2;
                    $datos['mensaje'] = 'Falta registrar sexo de la persona';
                }
            }
        } else {
            $datos['error']   = 3;
            $datos['mensaje'] = 'Error no se encontro registro de persona';
        }
        return $datos;
    }

    /**
     * valida unico email empleado
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function validaUnicoMailEmpleado($idPersona, $mail, $tipoMail)
    {
        settype($idPersona, 'integer');
        settype($tipoMail, 'integer');

        $this->db->select('idMail');
        $this->db->from(TBL_MAIL);
        $where = array('mail' => $mail, 'idTipoMail' => $tipoMail, 'fechaEliminacion' => '0000-00-00 00:00:00');
        $this->db->where($where);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * valida nombre de usuario para mail
     *
     * @author Santa Garcia
     *
     * @return array
     */
    public function validaUsuarioMail($nombre, $apellido)
    {
        $sql = "SELECT e.idEmpleado
            FROM persona p
            INNER JOIN empleado e on e.idPersona = p.idPersona
                and e.fechaEliminacion = '0000-00-00 00:00:00' and e.idTipoEstatusEmpleado = 196
            WHERE (p.nombre like '%" . strtoupper($nombre) . "%' and (REPLACE(p.paterno, ' ', '') like '%" . strtoupper($apellido) . "' or REPLACE(p.materno, ' ', '') like '%" . strtoupper($apellido) . "'))
            and p.fechaEliminacion
            = '0000-00-00 00:00:00' and p.idPersona =" . $this->session->userdata('idPersona') . " ";
        $query = $this->db->query($sql);
        if ($query->num_rows) {
            return $query->result_array();
        } else {
            return 0;
        }
    }

    /**
     * valida si no existe alguna persona con el mismo nombre y la misma fecha de nacimiento
     *
     * @param string $nombre
     * @param string $paterno
     * @param string $materno
     * @param string $fecha
     *
     * @author Antonio Sixtos
     *
     * @return boolean
     */
    public function validacionDuplicidadPersona($nombre, $paterno, $materno, $fecha)
    {
        $nombrecompleto = $nombre . ' ' . $paterno . ' ' . $materno;
        $nombreCompleto = strtoupper($nombrecompleto);
        $nombreCompleto = $this->db->escape_like_str($nombreCompleto);
        $fecha          = $this->db->escape_like_str($fecha);

        $sql = "SELECT p.idpersona, CONCAT_WS(' ', TRIM(p.nombre), TRIM(p.paterno), TRIM(p.materno)),
            LEVENSHTEIN(CONCAT_WS(' ', TRIM(nombre), TRIM(paterno), TRIM(materno)), '" . $nombreCompleto . "') AS diferencia
            FROM persona p
            WHERE fechaNacimiento='" . $fecha . "' AND fechaEliminacion='0000-00-00 00:00:00'
            HAVING diferencia<=2";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            $data = 1;
        } else {
            $data = 0;
        }

        return $data;
    }

    /**
     * [verificaBancario description]
     *
     * @param  [type] $idDomicilio [description]
     * @param  [type] $idPersona   [description]
     *
     * @return [type]              [description]
     */
    public function verificaBancario($idDomicilio, $idPersona)
    {
        settype($idDomicilio, 'integer');
        settype($idPersona, 'integer');

        if ($idDomicilio == 0) {
            $this->db->select('bancario');
            $this->db->from(TBL_DOMICILIO);
            $this->db->where('idPersona', $idPersona);
            $this->db->where('bancario', '1');
            $query = $this->db->get();

            if ($query->num_rows > 0) {
                $reg = 1;
            } else {
                $reg = 0;
            }
        } else {
            $this->db->select('bancario');
            $this->db->from(TBL_DOMICILIO);
            $this->db->where('idPersona', $idPersona);
            $this->db->where('bancario', '1');
            $query = $this->db->get();

            if ($query->num_rows > 0) {
                $this->db->select('bancario');
                $this->db->from(TBL_DOMICILIO);
                $this->db->where('idDomicilio', $idDomicilio);
                $this->db->where('bancario', '1');
                $query = $this->db->get();

                if ($query->num_rows > 0) {
                    $reg = 0;
                } else {
                    $reg = 1;
                }
            } else {
                $reg = 0;
            }
        }
        return $reg;
    }

    /**
     * [verificaFiscal description]
     *
     * @param  [type] $idDomicilio [description]
     * @param  [type] $idPersona   [description]
     *
     * @return [type]              [description]
     */
    public function verificaFiscal($idDomicilio, $idPersona)
    {
        settype($idDomicilio, 'integer');
        settype($idPersona, 'integer');

        if ($idDomicilio == 0) {
            $this->db->select('fiscal');
            $this->db->from(TBL_DOMICILIO);
            $this->db->where('idPersona', $idPersona);
            $this->db->where('fiscal', '1');
            $query = $this->db->get();
            if ($query->num_rows > 0) {
                $reg = 1;
            } else {
                $reg = 0;
            }
        } else {
            $this->db->select('fiscal');
            $this->db->from(TBL_DOMICILIO);
            $this->db->where('idPersona', $idPersona);
            $this->db->where('fiscal', '1');
            $query = $this->db->get();
            if ($query->num_rows > 0) {
                $this->db->select('fiscal');
                $this->db->from(TBL_DOMICILIO);
                $this->db->where('idDomicilio', $idDomicilio);
                $this->db->where('fiscal', '1');
                $query = $this->db->get();
                if ($query->num_rows > 0) {
                    $reg = 0;
                } else {
                    $reg = 1;
                }
            } else {
                $reg = 0;
            }
        }
        return $reg;
    }

    /**
     * Estado civil de la persona
     * @param int $persona
     * @return string
     */
    public static function obtenerEstadoCivil($persona)
    {
        $query = DB::connection('crm')->table(TBL_PERSONA . ' as p')
            ->select('p.idPersona', 'p.idTipoEstadoCivil', 'ec.descripcion')
            ->join(TBL_TIPOESTADOCIVIL . ' as ec', 'p.idTIpoEstadoCivil', 'ec.idTIpoEstadoCivil')
            ->where('p.idPersona', $persona);

        $descripcion = '';
        if ($query->count() > 0) {
            $result      = ($query->get()->toArray())[0];
            $descripcion = $result->descripcion;
        }
        return $descripcion;
    }

    /**
     * [crearArchivoDatosCredencial description]
     *
     * @param  [type] $datosArchivo  [description]
     * @param  [type] $pathMembresia [description]
     *
     * @return [type]                [description]
     */
    public function crearArchivoDatosCredencial($datosArchivo, $pathMembresia)
    {
        $aliasClub  = str_replace('SW', '', $datosArchivo->clave);
        $strArchivo = "Nombre: " . $datosArchivo->nombreCompleto
        . ",Club de origen: " . $aliasClub
        . ",Club de facturacion: " . $datosArchivo->clubFactura
        . ",Numero de membresia:" . $datosArchivo->idMembresia
        . ",codigo de barras: A" . $datosArchivo->idPersona;

        $flagArchivo   = 0;
        $nombreArchivo = $pathMembresia . $datosArchivo->idPersona . ".txt";
        file_put_contents($nombreArchivo, $strArchivo);
        if (file_exists($nombreArchivo)) {
            $flagArchivo = 1;
        }
        return $flagArchivo;
    }

    /**
     * [actualizaEnvioCredencial description]
     *
     * @param  [type] $idPersona    [description]
     * @param  [type] $idMovimiento [description]
     *
     * @return [type]               [description]
     */
    public function actualizaEnvioCredencial($idPersona, $idMovimiento)
    {
        if ($this->existeCargoCredencial($idPersona, $idMovimiento) == 0) {
            $now         = date('Y-m-d h:i:s');
            $insertEnvio = [
                'idMovimiento'  => $idMovimiento,
                'comentario'    => 'envio datos credencial para persona id:' . $idPersona . ' ' . $now,
                'enviado'       => 1,
                'fechaEnvio'    => $now,
                'fechaRegistro' => $now,
            ];
            $this->db->insert('credencialEnvio', $insertEnvio);
        } else {
            $this->db->where([
                'idPersona'    => $idPersona,
                'idMovimiento' => $idMovimiento,
            ]);
            $this->db->update('credencialEnvio', [
                'enviado' => 1,
            ]);
        }

    }

    /**
     * [existeCargoCredencial description]
     *
     * @param  [type] $idPersona    [description]
     * @param  [type] $idMovimiento [description]
     *
     * @return [type]               [description]
     */
    public function existeCargoCredencial($idPersona, $idMovimiento)
    {
        settype($idPersona, 'integer');
        settype($idMovimiento, 'integer');

        $query = $this->db->from('credencialEnvio')
            ->where('idMovimiento', $idMovimiento)
            ->where('enviado', 0)
            ->get();
        return $query->num_rows();
    }

    /**
     * [estatusValidacionHuella description]
     *
     * @param  [type] $idPersona [description]
     *
     * @author Jorge Cruz
     *
     * @return [type]            [description]
     */
    public function estatusValidacionHuella($idPersona)
    {
        settype($idPersona, 'integer');

        $res = true;

        if ($idPersona == 0) {
            return $res;
        }

        $this->db->select('validacion');
        $this->db->from(TBL_HUELLA);
        $this->db->where('idPersona', $idPersona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $query = $this->db->get();

        if ($query->num_rows > 0) {
            $fila = $query->row_array();
            if ($fila['validacion'] == 0) {
                $res = false;
            }
        }

        return $res;
    }

    /**
     * Regresa el hexadecimal del blob de la huella
     *
     * @author Luis Ruiz <luis.ruiz@sportsworld.com.mx>
     * @param   int    $persona   identificador de persona
     * @return  string            cadena en hexadecimal del blob
     */
    public function verificaHuella($persona)
    {
        settype($persona, 'integer');

        if ($persona == 0) {
            return false;
        }

        $this->db->select('HEX(biometrico)');
        $this->db->from(TBL_HUELLA);
        $this->db->where('idPersona', $persona);
        $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
        $this->db->where('OCTET_LENGTH(biometrico) >= 32');
        $query = $this->db->get();
        if ($query->num_rows > 0) {
            return $query->result_array();
        }
        return false;
    }

    /**
     * Verifica que no exista un mismo correo con el tipo web (36)
     *
     * @author Víctor Rodríguez <victor.leon@sportsworld.com.mx>
     * @param   string    $mail   mail a buscar
     * @param   int    $tipoMailEnvio   el tipo mail que se guarda
     * @param   int    $idTipoMail   id tipo de mail
     * @return  bool                Verdadero o falso si existe
     */
    public function verificaMailWeb($mail, $tipoMailEnvio, $idTipoMail = 36)
    {
        if ($tipoMailEnvio == $idTipoMail) {
            $this->db->select('idMail');
            $this->db->from(TBL_MAIL);
            $this->db->where('mail', $mail);
            $this->db->where('idTipoMail', $idTipoMail);
            $this->db->where('eliminado', 0);
            $this->db->where('fechaEliminacion', '0000-00-00 00:00:00');
            $query = $this->db->get();
            $sql   = $this->db->last_query();
            if ($query->num_rows > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Guarda el codigo generado para su validación de mail
     *
     * @author Víctor Rodríguez <victor.leon@sportsworld.com.mx>
     * @param   string    $codigo    codigo
     * @param   string    $mail mail a guardar
     * @param   int       $idPersona id de la persona para
     * @return  bool                Verdadero o falso si existe
     */

    public function guardaCodigoMail($codigo, $mail)
    {
        //busco si no existe ya la llave mail vs codigo
        $this->db->select('vigencia');
        $this->db->from(TBL_MAIL_CODIGO);
        $this->db->where('mail', $mail);
        //$this->db->where('codigo', $codigo);
        $query    = $this->db->get();
        $sql      = $this->db->last_query();
        $fechaHoy = date('Y-m-d H:i:s');
        $vigencia = strtotime('+15 day', strtotime($fechaHoy));
        $vigencia = date('Y-m-d H:i:s', $vigencia);

        if ($query->num_rows > 0) {
            //existe y solo tenemos que actualizar el codigo y la fecha de vigencia
            $data = ['codigo' => $codigo, 'vigencia' => $vigencia, 'usado' => 0];
            $this->db->where('mail', $mail);
            $exito = $this->db->update(TBL_MAIL_CODIGO, $data);
        } else {
            //no existe y tenemos que insertarlo
            $data  = ['codigo' => $codigo, 'vigencia' => $vigencia, 'fechaCreacion' => $fechaHoy, 'mail' => $mail];
            $exito = $this->db->insert(TBL_MAIL_CODIGO, $data);
        }
        if ($exito) {
            return true;
        }
        return false;
    }

    /**
     * Valida que exista el código y este vigente
     *
     * @author Víctor Rodríguez <victor.leon@sportsworld.com.mx>
     * @param   string    $codigo    codigo
     * @param   string    $mail mail a buscar
     * @return  bool                Verdadero o falso si existe
     */

    public function validaCodigoMail($codigo, $mail)
    {
        $fechaHoy = date('Y-m-d H:i:s');
        //valido que la vigencia este en tiempo y que exista el mail
        $this->db->select('vigencia');
        $this->db->from(TBL_MAIL_CODIGO);
        $this->db->where('mail', $mail);
        $this->db->where('codigo', $codigo);
        $this->db->where('usado', 0);
        $this->db->where('vigencia >=', $fechaHoy);
        $query = $this->db->get();
        //echo $this->db->last_query();
        if ($query->num_rows > 0) {
            //var_dump($query->row_array());
            $data = ['usado' => 1];
            $this->db->where('mail', $mail);
            $this->db->where('codigo', $codigo);
            $this->db->where('usado', 0);
            $this->db->where('vigencia >=', $fechaHoy);
            $this->db->update(TBL_MAIL_CODIGO, $data);
            //echo "si existe";
            return true;
        } else {
            //echo "no existe";
            return false;
        }
    }

    public function datosCadenaPersona($idPersona)
    {
        $sql = "select m.idUnicoMembresia, m.idMembresia, m.idUn, m.idProducto, d.cp, (SELECT count(*)
            FROM crm.socio s
            WHERE s.idUnicoMembresia IN (m.idUnicoMembresia)
                AND s.fechaEliminacion = '0000-00-00 00:00:00' AND s.idTipoEstatusSocio NOT IN (82,88)
            ) integrantes
            from membresia m, domicilio d
            where d.idPersona=m.idPersona
            and m.idPersona=" . $idPersona . " order by m.fechaRegistro DESC";

        $query = $this->db->query($sql);
        $row   = $query->row();

        return $row;
    }

    public function completaRegistroSocio($idUn, $idMembresia, $mail)
    {
        $sqlMembresia  = "select idUnicoMembresia from crm.membresia where idUn=" . $idUn . " and idMembresia=" . $idMembresia . "  limit 1";
        $resultMem     = $this->db->query($sqlMembresia);
        $sqlPersona    = "select idPersona from crm.mail where mail='" . $mail . "' limit 1";
        $resultPersona = $this->db->query($sqlPersona);

        $idUnicoMembresia = $resultMem->row()->idUnicoMembresia;

        $idPersona = $resultPersona->row()->idPersona;

        //inserto en activacion si no existe registro
        $sql = "select folio
            from  socios.activacion
            where idPersona=" . $idPersona . "
            and estatus=1 and fechaEliminacion = '0000-00-00 00:00:00'
            ";
        // echo $sql;
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $codigo = $query->row();
            $folio  = $codigo->folio;
        }

        if ($folio == '' || !$folio) {
            $random = rand();
            $string = sha1($idPersona . $random);
            $codigo = substr($string, 0, 10);
            $datos  = array(
                'folio'            => $codigo,
                'idPersona'        => $idPersona,
                'idUnicoMembresia' => $idUnicoMembresia,
            );
            //var_dump($datos);
            $this->db->insert('socios.activacion', $datos);
        }

    }
}
