<?php

namespace API_EPS\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class InBody extends Model
{
    // use SoftDeletes;
    protected $connection = "aws";
    protected $table      = "piso.personainbody";
    protected $primaryKey = "idPersonaInBody";
    public $timestamps    = false;
    const CREATED_AT      = 'fechaRegistro';
    const DELETED_AT      = 'fechaEliminacion';
    const UPDATED_AT      = 'fechaActualizacion';

    public function scopeDatosInBody($query, $idPersona)
    {
        $original = $query->where('fechaEliminacion', '0000-00-00 00:00:00')
            ->where('idPersona', $idPersona)
            ->groupBy(DB::raw('YEAR(fechaRegistro)'), DB::raw('MONTH(fechaRegistro)'))
            ->select(DB::raw('MAX(idPersonaInBody) AS id'))
            ->get();
        return $query->select(
            'peso', 'estatura', 'RCC', 'PGC', 'IMC', 'MME', 'MCG', 'ACT', 'minerales', 'proteina', 'fcresp', 'fechaRegistro', DB::raw('date(fechaRegistro) as fecha '), 'personainbody.fechaEliminacion')
            ->whereIn('idPersonaInBody', $original)
            ->limit(5)
            ->get();
    }

    public function scopeLastInBody($query, $idPersona)
    {
        try {
            $result = $query->select(
                'peso', 'estatura', 'RCC', 'PGC', 'IMC', 'MME', 'MCG', 'ACT', 'minerales', 'proteina', 'fcresp', DB::raw('date(fechaRegistro) as fecha '), 'personainbody.fechaEliminacion')
                ->from('piso.personainbody')
                ->where('personainbody.fechaEliminacion', '=', '0000-00-00 00:00:00')
                ->where('personainbody.idPersona', '=', $idPersona)
                ->orderBy('personainbody.fechaRegistro', 'desc')->limit(1)->get();

            // echo print_r($result, true); exit;

            if (count($result) > 0) {
                $retval = [
                    'status'  => 'ok',
                    'message' => 'InBodyById',
                    'data'    => $result,
                ];
            } else {
                $retval = [
                    'status'  => 'ok',
                    'message' => 'No se encontron registros de esta persona',
                    'data'    => [
                        [
                            "peso"             => "0",
                            "estatura"         => "0",
                            "RCC"              => "0",
                            "PGC"              => "0",
                            "IMC"              => "0",
                            "MME"              => "0",
                            "MCG"              => "0",
                            "ACT"              => "0",
                            "minerales"        => "0",
                            "proteina"         => "0",
                            "fcresp"           => "0",
                            "fecha"            => "0000-00-00",
                            "fechaEliminacion" => "0000-00-00 00:00:00",
                        ],
                    ],
                ];
            }

            return $retval;
        } catch (\Illuminate\Database\QueryException $ex) {
            Log::debug("QueryException: " . $ex->getMessage());
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, 400);
        } catch (\Exception $ex) {
            Log::debug("ErrMsg: " . $ex->getMessage() . " File: " . $ex->getFile() . " Line: " . $ex->getLine());
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, 400);
        }
    }

    public function scopeCreateInBody($query, $datos)
    {
        try {
            if (!empty($datos)) {
                $idPersonaInBody = DB::connection('aws')->table('piso.personainbody', 'idPersonaInBody')->insertGetId($datos);
            } else {
                return false;
            }
            return $res = ['idPersonaInBody' => $idPersonaInBody];
        } catch (\Illuminate\Database\QueryException $ex) {
            Log::debug("QueryException: " . $ex->getMessage());
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, 400);
        } catch (\Exception $ex) {
            Log::debug("ErrMsg: " . $ex->getMessage() . " File: " . $ex->getFile() . " Line: " . $ex->getLine());
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, 400);
        }
    }

    public static function getHistory($idPersonaEmpleado)
    {
        $sql = "SELECT  p.idPersona, concat_ws(' ',p.nombre,p.paterno,p.materno) AS nombre
                FROM piso.personainbody as pi
                JOIN deportiva.persona as p ON pi.idPersona =p.idPersona
                where  pi.idPersonaEmpleado={$idPersonaEmpleado}
                order by idPersonaInBody desc
                limit 5";
        $query = DB::connection('aws')->select($sql);
        if (count($query) > 0) {
            return $query;
        } else {
            return [];

        }
    }

}
