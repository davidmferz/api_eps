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
    public function scopeQueryPersonaMem($query, $nombre, $numeroRegistros = 20, $edadMinima = null, $edadMaxima = null)
    {
        try {

            $membresia = (int) trim($nombre);

            $w_mem    = '';
            $tipoJoin = 'LEFT';
            if ($membresia > 0) {
                $w_mem    = ' AND m.idMembresia=' . $membresia;
                $tipoJoin = 'INNER';
            }

            // Obtenido desde \crm\system\application\models\persona_model.php public function listaPersonas
            $idEmpresaGrupo = 1;

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
                $sql = "
                SELECT p.idPersona,
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
                    AND p.idEmpresaGrupo={$idEmpresaGrupo}
                    AND p.fechaEliminacion='0000-00-00 00:00:00' {$w_min} {$w_max}
                WHERE m.idMembresia={$membresia}
                    AND m.idTipoEstatusMembresia=27
                    AND m.eliminado=0
                ORDER BY nombreCompleto
                LIMIT {$numeroRegistros}";
                $res = DB::connection('crm')->select($sql);
            } else {
                $nombres    = explode(' ', trim($nombre));
                $strNombres = '';
                foreach ($nombres as $key => $value) {
                    $strNombres .= '+' . $value . '* ';
                }

                $sql = "SELECT
                tp.nombre,
                tp.paterno,
                tp.materno,
                tp.idPersona,
                tp.nombreCompleto,
                IFNULL(m.idMembresia, '') AS idMembresia,
                IFNULL(u.idUn, '') AS idUn,
                IFNULL(u.clave, '') AS clave,
                IF(m.idMembresia IS NULL, 0, 1) AS tieneMembresia,
                IF(e.idEmpleado IS NULL, 0, 1) AS empleado,
                IF(ie.idInvitadoEspecial IS NULL, 0, 1) AS invitado,
                IF(g.idgympass IS NULL, 0, 1) AS gympass
            FROM(
                    SELECT
                        p.idPersona,
                        UPPER(TRIM(p.nombre)) AS nombre,
                        UPPER(TRIM(p.paterno)) AS paterno,
                        UPPER(TRIM(p.materno)) AS materno,
                        nombreCompleto
                    FROM personalevenshtein l
                    INNER JOIN persona p
                        ON p.idPersona=l.idPersona
                            AND p.bloqueo=0
                            AND p.fechaEliminacion='0000-00-00 00:00:00'
                    WHERE MATCH(nombreCompleto) AGAINST ('{$strNombres}' IN BOOLEAN MODE)
                    order by l.idPersona desc
                    LIMIT 500
                ) AS tp
                    LEFT JOIN
                socio s ON s.idPersona = tp.idPersona
                    AND s.idTipoEstatusSocio = 81
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
            ORDER BY tieneMembresia DESC , nombreCompleto
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

}
