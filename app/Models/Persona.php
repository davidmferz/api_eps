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

    public static function getMail($idPersona)
    {
        $sql = "SELECT  mail FROM crm.persona as p
        JOIN crm.mail as m ON p.idPersona=m.idPersona
        where p.idPersona={$idPersona}
        order by m.idTipoMail";
        $query = DB::connection('crm')->select($sql);
        if (count($query) > 0) {
            return $query[0]->mail;
        } else {
            return false;
        }

    }
    public function scopeQueryPersonaMem($query, $nombre, $tipoUsuario)
    {
        try {
            $membresia = (int) trim($nombre);
            $sql       = '';
            if ($membresia > 0) {
                $sql = "SELECT p.idPersona,
                    UPPER(TRIM(p.nombre)) AS nombre,
                    UPPER(TRIM(p.paterno)) AS paterno,
                    UPPER(TRIM(p.materno)) AS materno,
                    m.idMembresia,
                    IFNULL(u.idUn,'') AS idUn,
                    IFNULL(u.clave,'') AS clave,
                    IF(m.idMembresia IS NULL, 0, 1) AS tieneMembresia,
                    CONCAT_WS(' ', TRIM(p.nombre), TRIM(p.paterno), TRIM(p.materno)) nombreCompleto
                FROM membresia m
                INNER JOIN un u ON m.idUn=u.idUn
                INNER JOIN socio s ON s.idunicomembresia=m.idUnicoMembresia
                    AND s.idTipoEstatusSocio=81
                    AND s.eliminado=0
                INNER JOIN persona p ON p.idPersona=s.idPersona
                    AND p.idEmpresaGrupo=1
                    AND p.fechaEliminacion='0000-00-00 00:00:00'
                WHERE m.idMembresia={$membresia}
                    AND m.idTipoEstatusMembresia=27
                    AND m.eliminado=0
                ORDER BY nombreCompleto
                LIMIT 20";
                $res = DB::connection('crm')->select($sql);
            } else {
                $nombre = trim(utf8_encode($nombre));
                $find   = array('á', 'é', 'í', 'ó', 'ú', 'â', 'ê', 'î', 'ô', 'û', 'ã', 'õ', 'ç', 'ñ', 'Ñ', 'Á');
                $repl   = array('a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'a', 'o', 'c', 'n', 'N', 'A');
                $nombre = str_replace($find, $repl, $nombre);
                //$nombre = $this->db->escape_like_str($nombre);
                $nombre      = htmlspecialchars($nombre);
                $nombre      = strtoupper($nombre);
                $nombreAux   = explode(' ', $nombre);
                $nombreMatch = '';
                foreach ($nombreAux as $key => $value) {
                    $nombreMatch .= '+' . $value . ' ';
                }
                switch ($tipoUsuario) {
                    case 'socio':
                        $res = $this->buscaSocioNombre($nombreMatch);
                        break;
                    case 'invitado':
                        $res = $this->buscaInvitadoEspecialPorNombre($nombreMatch);
                        break;
                    case 'empleado':
                        $res = $this->buscaEmpleadoPorNombre($nombreMatch);
                        break;

                    default:
                        # code...
                        break;
                }
                return $res;

                $nombre = str_replace('*', '%', $nombre);

                $w_nombre = " AND tp.nombreCompleto LIKE '%" . $nombre . "%' ";

                $sql = "SELECT
                tp.nombre,
                tp.paterno,
                tp.materno,
                tp.idPersona,
                IFNULL(m.idMembresia, '') AS idMembresia,
                IFNULL(m.idUnicoMembresia, '') AS idUnicoMembresia,
                IFNULL(u.idUn, '') AS idUn,
                IFNULL(u.clave, '') AS clave,
                IF(m.idMembresia IS NULL, 0, 1) AS tieneMembresia,
                IF(e.idEmpleado IS NULL, 0, 1) AS empleado,
                IF(ie.idInvitadoEspecial IS NULL, 0, 1) AS invitado,
                IF(g.idgympass IS NULL, 0, 1) AS gympass
            FROM(
                SELECT l.nombreCompleto,p1.*
                FROM personalevenshtein l
                INNER JOIN persona p1 ON p1.idPersona=l.idPersona AND p1.bloqueo=0
                    WHERE MATCH(nombreCompleto) AGAINST ('{$nombreMatch}' IN BOOLEAN MODE)
                    order by l.idPersona desc
                    limit 1000
                ) AS tp
                    LEFT JOIN
                socio s ON s.idPersona = tp.idPersona
                    AND s.idTipoEstatusSocio NOT IN  (82,86)
                    AND s.eliminado = 0
                    LEFT  JOIN
                membresia m ON m.idUnicoMembresia = s.idUnicoMembresia
                    AND m.idTipoEstatusMembresia = 27
                    AND m.eliminado = 0
                    LEFT JOIN
                empleado e ON e.idPersona = tp.idPersona
                    AND e.fechaEliminacion = 0
                    LEFT JOIN
                invitadoespecial ie ON ie.idPersona = tp.idPersona
                    AND ie.fechaEliminacion = 0
                    AND NOW() BETWEEN ie.fechaInicio AND ie.fechaFin
                    LEFT JOIN
                gympass g ON g.idPersona = tp.idPersona
                    LEFT JOIN
                un u ON u.idUn = m.idUn
            where 1 {$w_nombre}
            ORDER BY tieneMembresia DESC
            LIMIT {$numeroRegistros}";
                $respuesta = DB::connection('crm')->select($sql);
                $aux       = [];
                $res       = [];
                foreach ($respuesta as $key => $value) {
                    if (!array_key_exists($value->idPersona, $aux)) {
                        $flag = false;
                        if ($value->tieneMembresia) {
                            $value->tipo = 'SOCIO';
                            $flag        = true;
                        } elseif ($value->invitado || $value->gympass) {
                            if ($value->invitado) {
                                $value->tipo = 'INV';
                            } else {
                                $value->tipo = 'GYMPASS';
                            }
                            $flag = true;

                        } elseif ($value->empleado) {
                            $value->tipo = 'EMP';
                            $flag        = true;
                        }
                        if ($flag) {
                            $aux[$value->idPersona] = $value;
                            $res[]                  = $value;
                        }
                    }
                }
            }
            return $res;
        } catch (\Exception $ex) {
            Log::debug("ErrMsg: " . $ex->getMessage() . " File: " . $ex->getFile() . " Line: " . $ex->getLine());
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, 500);
        }

    }

    private function buscaSocioNombre($nombreMatch)
    {
        $sql = "SELECT
                    tp.nombre,
                    tp.paterno,
                    tp.materno,
                    tp.idPersona, IFNULL(m.idMembresia, '') AS idMembresia,
                    IFNULL(m.idUnicoMembresia, '') AS idUnicoMembresia,
                    IFNULL(u.idUn, '') AS idUn,
                    IFNULL(u.clave, '') AS clave,
                    'SOCIO' as tipo
                FROM
                    socio s
                JOIN
                    membresia m ON m.idUnicoMembresia = s.idUnicoMembresia AND m.idTipoEstatusMembresia = 27 AND m.eliminado = 0
                JOIN un u ON u.idUn = m.idUn
                JOIN (
                    SELECT l.nombreCompleto,p1.*
                    FROM personalevenshtein l
                    INNER JOIN persona p1 ON p1.idPersona=l.idPersona AND p1.bloqueo=0
                    WHERE MATCH(nombreCompleto) AGAINST ('{$nombreMatch}' IN BOOLEAN MODE)
                    ORDER BY l.idPersona DESC
                    LIMIT 500
                ) AS tp ON tp.idPErsona=s.idPersona
                WHERE 1 AND s.idTipoEstatusSocio NOT IN (82,86) AND s.eliminado = 0
                ORDER BY tp.idPersona DESC
                limit 20";

        $respuesta = DB::connection('crm')->select($sql);
        return $respuesta;
    }

    private function buscaEmpleadoPorNombre($nombreMatch)
    {
        $sql = "SELECT
                    tp.nombre,
                    tp.paterno,
                    tp.materno,
                    tp.idPersona,
                    e.idEmpleado
                    'EMP' as tipo
                FROM
                    empleado e

                JOIN (
                    SELECT l.nombreCompleto,p1.*
                    FROM personalevenshtein l
                    INNER JOIN persona p1 ON p1.idPersona=l.idPersona AND p1.bloqueo=0
                    WHERE MATCH(nombreCompleto) AGAINST ('{$nombreMatch}' IN BOOLEAN MODE)
                    ORDER BY l.idPersona DESC
                    LIMIT 500
                ) AS tp ON tp.idPErsona=e.idPersona
                WHERE  e.idTipoEstatusEmpleado=196
                    AND e.eliminado = 0
                ORDER BY tp.idPersona DESC
                limit 20";

        $respuesta = DB::connection('crm')->select($sql);
        return $respuesta;
    }

    private function buscaInvitadoEspecialPorNombre($nombreMatch)
    {
        $sql = "SELECT
                    tp.nombre,
                    tp.paterno,
                    tp.materno,
                    tp.idPersona,
                    i.idinvitadoEspecial as idInvitado,
                    'INV' as tipo

                    'INV ESP' as tipoinvitado
                FROM
                    invitadoespecial i
                JOIN (
                    SELECT l.nombreCompleto,p1.*
                    FROM personalevenshtein l
                    INNER JOIN persona p1 ON p1.idPersona=l.idPersona AND p1.bloqueo=0
                    WHERE MATCH(nombreCompleto) AGAINST ('{$nombreMatch}' IN BOOLEAN MODE)
                    ORDER BY l.idPersona DESC
                    LIMIT 500
                ) AS tp ON tp.idPErsona=i.idPersona
                WHERE i.activo=1 and now() between i.fechaInicio and i.fechaFin
                ORDER BY tp.idPersona DESC
                limit 12";
        $query = DB::connection('crm')->select($sql);

        $sql = "SELECT  tp.nombre,
                        tp.paterno,
                        tp.materno,
                        tp.idPersona,
                        g.idgympass as idInvitado,
                        CASE
                            WHEN idPersonaGympass = 1 THEN 'TOTALPASS'
                            WHEN idPersonaGympass = 2 THEN 'FITPASS'
                            WHEN idPersonaGympass = 3 THEN 'INVITADO ESPECIAL'
                        ELSE 'GYMPASS'
                        END AS tipoinvitado
                FROM crm.gympass AS g
                JOIN (
                    SELECT l.nombreCompleto,p1.*
                    FROM personalevenshtein l
                    INNER JOIN persona p1 ON p1.idPersona=l.idPersona AND p1.bloqueo=0
                    WHERE MATCH(nombreCompleto) AGAINST ('{$nombreMatch}' IN BOOLEAN MODE)
                    ORDER BY l.idPersona DESC
                    LIMIT 500
                ) AS tp ON tp.idPErsona=g.idPersona
                WHERE g.eliminado=0
                limit 12
                ";
        $query2    = DB::connection('crm')->select($sql);
        $respuesta = array_merge($query, $query2);
        return $respuesta;
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

        $sql = "SELECT p.nombre, p.paterno, p.materno, p.idTipoPersona AS tipo,
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
            GROUP BY p.idPersona
        ";
        $query = DB::connection('crm')->select($sql);

        if (count($query) > 0) {
            $query = array_map(function ($x) {return (array) $x;}, $query);
            $fila = $query[0];
            return $fila;
        } else {
            return null;
        }
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

}
