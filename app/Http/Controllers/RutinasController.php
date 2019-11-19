<?php

namespace API_EPS\Http\Controllers;

use API_EPS\Http\Controllers\ApiController;
use API_EPS\Models\EP;
use API_EPS\Models\Menu;
use API_EPS\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RutinasController extends ApiController
{
    public function getRutinasEntrenadores(Request $request)
    {
        $customMessages = [
        ];
        $validator = Validator::make($request->all(), [
            'idUn' => 'required|integer|min:1',

        ], $customMessages);
        if ($validator->fails()) {
            return $this->errorResponse($validator->messages()->first(), 400);
        }

        $idUn            = $request->input('idUn');
        $entrenadores    = EP::obtenEntrenadores($idUn);
        $entrenadoresIds = array_column($entrenadores, 'idPersona');
        $rutinas         = Menu::RutinasEntrenadores($entrenadoresIds);

        $rutinasResponse = [];
        foreach ($entrenadores as $keyEnt => $entrenador) {
            foreach ($rutinas as $key => $value) {
                if ($entrenador['idPersona'] == $value['idEmpleado']) {
                    $rutinasResponse[$entrenador['nombre'] . '-' . $entrenador['idPersona']][] = $value;
                }
            }
        }
        if (COUNT($rutinasResponse) > 0) {
            return $this->successResponse($rutinasResponse);
        } else {
            return $this->errorResponse('Sin rutinas', 402);
        }

    }
    public function historyRutinas($idPersonaEmpleado)
    {

        $result = Menu::getHistoryRutinas($idPersonaEmpleado);
        return $this->successResponse($result, 200);

    }
    public function readMenuActividad(Request $request, $idPersona)
    {
        $persona = Persona::find($idPersona);
        $result  = Menu::ReadMenuActividad($idPersona);
        if ($result['estatus']) {
            $nombre                   = $persona->nombre . ' ' . $persona->paterno . ' ' . $persona->materno;
            $result['data']['nombre'] = $nombre;
            return $this->successResponse($result['data'], 'Rutina completa');
        }
        return $this->errorResponse('Sin rutinas', 402);

    }
    public function getHistoricoCliente($idPersona)
    {
        $result = Menu::historicoCliente($idPersona);
        return $this->successResponse($result, 'historico de rutinas');

    }

}
