<?php

namespace API_EPS\Http\Controllers;

use API_EPS\Http\Controllers\ApiController;
use API_EPS\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PersonaController extends ApiController
{

    public function queryPersonaMem(Request $request)
    {
        try {
            if (empty($request->queryStr)) {
                $retval = array(
                    'status'  => 'error',
                    'data'    => array(),
                    'message' => 'No se proporcionó cadena de consulta',
                );
                return response()->json($retval, 400);
            }
            $result = Persona::QueryPersonaMem($request->queryStr);

            return $result;
        } catch (\Illuminate\Database\QueryException $ex) {
            Log::debug("QueryException: " . $ex->getMessage());
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, 500);
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
}
