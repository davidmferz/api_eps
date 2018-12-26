<?php

namespace API_EPS\Http\Controllers;

use Illuminate\Http\Request;

class EpsController extends Controller
{
    public function perrito($id)
    {
            $retval = [
                'code'    => 200,
                'message' => 'response',
                'data'    => $id,
            ];
            return response()->json($retval, 200);
     }
}
