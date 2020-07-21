<?php

namespace API_EPS\Http\Controllers;

use API_EPS\Http\Controllers\ApiController;
use API_EPS\Http\Requests\CreaRutinaRequest;
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

    public function creaRutina(CreaRutinaRequest $request)
    {
        $idUn          = $request->input('idUn');
        $idPersona     = $request->input('idPersona');
        $idEmpleado    = $request->input('idEmpleado');
        $idRutina      = $request->input('idRutina');
        $fechaInicio   = $request->input('fechaInicio');
        $fechaFin      = $request->input('fechaFin');
        $observaciones = $request->input('observaciones');
        $actividades   = $request->input('actividades');
        $interval      = date_diff(date_create($fechaFin), date_create($fechaInicio))->format('%a');
        if ($interval < 27 || count($actividades) < 28) {
            return $this->errorResponse('fechas o actividades son menor de  28 días ', 422);
        }

        $result = Menu::InsertMenu($idUn, $idPersona, $idRutina, $fechaInicio, $fechaFin, $observaciones, $actividades, $idEmpleado);

        if ($result['estatus'] == true) {
            return $this->successResponse($result, 'Se creó rutina');

        } else {
            return $this->errorResponse($result['mensaje'], 422);
        }
    }
}
