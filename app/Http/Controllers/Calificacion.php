<?php

namespace API_EPS\Http\Controllers;

use API_EPS\Http\Controllers\ApiController;
use API_EPS\Models\CalificacionEntrenador;
use API_EPS\Models\EP;
use API_EPS\Models\EventoCalificacion;
use API_EPS\Models\EventoInscripcion;
use API_EPS\Models\TokenEncuestas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Calificacion extends ApiController
{

    public function getCalificacionEntrenadores($idUn)
    {
        $entrenadores = EP::obtenEntrenadores($idUn);

        if (count($entrenadores) > 0) {
            return $this->successResponse($entrenadores);

        } else {
            return $this->errorResponse('Sin entrenadores ', 404);

        }
    }
    public function getInfoCalificacion($idEventoInscripciones, $token)
    {
        $validToken = TokenEncuestas::where('token', $token)->where('valid', 1)->first();
        if ($validToken == null) {
            return $this->errorResponse('Token invalido ', 404);
        }

        $info = EventoInscripcion::infoEvento($idEventoInscripciones);

        if ($info == null || count($info) < 0) {
            return $this->errorResponse('Error en la base ', 404);

        }
        return $this->successResponse($info);
    }
    /**
     * calificacion - Obtener/poner la calificación de un EP
     * @return HTTP status
     *
     */
    public function setCalificacion(Request $request)
    {
        $customMessages = [
        ];
        $validator = Validator::make($request->all(), [
            'idInscripcion' => 'required|integer',
            'idEmpleado'    => 'required|integer',
            'token'         => 'required|string',
            'calificacion'  => 'required|integer|min:1',
            'question1'     => 'required|integer',
            'question2'     => 'required|integer',
            'question3'     => 'required|integer',
            'question4'     => 'required|integer',
            //'question5'     => 'required|integer',
            //'question6'     => 'required|integer',

        ], $customMessages);
        if ($validator->fails()) {
            return $this->errorResponse($validator->messages()->first(), 400);
        }
        $token      = $request->input('token');
        $validToken = TokenEncuestas::where('token', $token)->where('valid', 1)->first();
        if ($validToken == null) {
            return $this->errorResponse('Token invalido ', 404);
        }
        $idInscripcion = $request->input('idInscripcion');
        $idEmpleado    = $request->input('idEmpleado');
        $calificacion  = $request->input('calificacion');
        $question1     = $request->input('question1');
        $question2     = $request->input('question2');
        $question3     = $request->input('question3');
        $question4     = $request->input('question4');
        //$question5                        = $request->input('question5');
        //$question6                        = $request->input('question6');
        $newRegistro                      = new EventoCalificacion();
        $newRegistro->idEventoInscripcion = $idInscripcion;
        $newRegistro->idEmpleado          = $idEmpleado;
        $newRegistro->calificacion        = $calificacion;
        $newRegistro->q1                  = $question1 ? $calificacion * 20 : 0;
        $newRegistro->q2                  = $question2 ? $calificacion * 20 : 0;
        $newRegistro->q3                  = $question3 ? $calificacion * 20 : 0;
        $newRegistro->q4                  = $question4 ? $calificacion * 20 : 0;
        //$newRegistro->q5                  = $question5;
        //$newRegistro->q6                  = $question6;
        $newRegistro->fechaRegistro = Carbon::now();
        $newRegistro->save();

        if (isset($newRegistro->idEventoCalificacion)) {
            //$tokenDelete = TokenEncuestas::where('token', $token)->first();

            if ($question1) {
                $status = CalificacionEntrenador::guardaPromedio($idEmpleado, 'q1');
            } elseif ($question2) {
                $status = CalificacionEntrenador::guardaPromedio($idEmpleado, 'q2');
            } elseif ($question3) {
                $status = CalificacionEntrenador::guardaPromedio($idEmpleado, 'q3');
            } else {
                $status = CalificacionEntrenador::guardaPromedio($idEmpleado, 'q4');
            }

            if (!$status) {
                Log::debug('Error ------');
                Log::debug([$token,
                    $idInscripcion,
                    $idEmpleado,
                    $calificacion,
                    $question1,
                    $question2,
                    $question3,
                    $question4]
                );
            }
            $validToken->valid = 0;
            $validToken->save();
            return $this->successResponse($newRegistro->idEventoCalificacion);

        } else {
            return $this->errorResponse('error en el servidor ', 500);

        }
    }
}
