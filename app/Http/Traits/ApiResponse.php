<?php

namespace API_EPS\Traits;

trait ApiResponse
{
    protected function errorResponse($mensaje, $codigo = 404)
    {
        return response()->json(
            [
                'message' => $mensaje,
                'code'    => $codigo,
                'data'    => [],
            ],
            $codigo);
    }

    public function successResponse($data, $mensaje = 'OK', $code = 200)
    {
        $retval = [
            'code'    => $code,
            'message' => $mensaje,
            'data'    => $data,
        ];

        return response()->json($retval);
    }
}
