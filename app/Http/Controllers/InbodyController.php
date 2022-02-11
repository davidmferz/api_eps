<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Http\Requests\InbodyCoordinadorRequest;
use App\Mail\MailEntrenador;
use App\Mail\MailPersona;
use App\Models\AgendaInbody;
use App\Models\Empleado;
use App\Models\EP;
use App\Models\InBody;
use App\Models\Menu;
use App\Models\Persona;
use App\Models\Un;
use App\Models\Vo2Max\CatEjercicioPreferencia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Extraído desde el controller /crm/system/application/controllers/ep.php
 * y desde el model /crm/system/application/models/ep_model.php
 */
class InbodyController extends ApiController
{

    public function agendaInbodyCoordinador(InbodyCoordinadorRequest $request)
    {

        $idPersonaSocio      = $request->input('idPersonaSocio');
        $idPersonaEntrenador = $request->input('idPersonaEntrenador');
        $idUn                = $request->input('idUn');
        $nombreSocio         = $request->input('nombreSocio');
        $nombreCoordinador   = $request->input('nombreCoordinador');
        $nombreUn            = $request->input('nombreUn');

        $fechaSolicitud = new Carbon($request->input('fechaSolicitud'));
        $fecha          = $fechaSolicitud->format('Y-m-d');
        $hora           = $fechaSolicitud->format('H:i:s');
        $empleado       = Empleado::ObtenDatosEmpleado($idPersonaEntrenador);

        if (count($empleado) == null) {
            return $this->errorResponse('Empleado no existe ', 404);
        }
        $idEmpleado     = $empleado[0]['idEmpleado'];
        $nombreEmpleado = $empleado[0]['nombre'];
        $datos          = EP::clase($idEmpleado, $idUn, $fecha, $hora);
        $inbodys        = AgendaInbody::ConsultaInbodyEmpleado($idEmpleado, $idUn, $fechaSolicitud->format('Y-m-d H:i:s'));

        $arrayMerge = array_merge($datos, $inbodys);
        if (is_array($arrayMerge) && count($arrayMerge) > 0) {

            return $this->errorResponse('El horario ya esta ocupado', 400);
        } else {
            $inbody                 = new AgendaInbody();
            $inbody->idPersona      = $idPersonaSocio;
            $inbody->idEmpleado     = $idEmpleado;
            $inbody->idUn           = $idUn;
            $inbody->fechaSolicitud = $fechaSolicitud->format('Y-m-d H:i:s');
            $fechaAuxCorreo         = new Carbon($request->input('fechaSolicitud'));
            $inbody->horario        = $hora;
            $inbody->save();
            if ($inbody->idAgenda) {
                $correo = Empleado::GetEmail($idPersonaEntrenador);
                //Log::debug($correo);
                //$correo                        = 'luis01cosio@gmail.com';
                $datosMail                     = new \stdClass();
                $datosMail->nombreEntrenador   = $nombreEmpleado;
                $datosMail->nombreCoordinador  = $nombreCoordinador;
                $datosMail->fechaSolicitud_str = fechaStringES($fechaSolicitud);
                $datosMail->nombreClub         = $nombreUn;
                $datosMail->hora               = 'de ' . $fechaSolicitud->format('H:i:s') . ' hasta ' . $fechaSolicitud->addMinutes(30)->format('H:i:s');
                $datosMail->nombreSocio        = $nombreSocio;
                //Log::debug($correo->mail);

                Mail::to($correo->mail)->send(new MailEntrenador($datosMail));

                $correoPersona = Persona::getMail($idPersonaSocio);

                //$correo                        = 'luis01cosio@gmail.com';
                $datosMailPersona                     = new \stdClass();
                $datosMailPersona->nombreEntrenador   = $nombreEmpleado;
                $datosMailPersona->fechaSolicitud_str = fechaStringES($fechaSolicitud);
                $datosMailPersona->nombreClub         = $nombreUn;
                $datosMailPersona->hora               = 'de ' . $fechaAuxCorreo->format('H:i:s') . ' hasta ' . $fechaAuxCorreo->addMinutes(30)->format('H:i:s');
                $datosMailPersona->nombreSocio        = $nombreSocio;
                Mail::to($correoPersona)->send(new MailPersona($datosMailPersona));
                //Log::debug($correoPersona);

                return $this->successResponse($inbody, 'Agenda ');
            } else {
                return $this->errorResponse('Error al guardar  ', 500);
            }
        }
    }

    /**
     * [agendaEps description]
     *
     * @param  [type] $idEntrenador [description]
     * @param  [type] $idUn         [description]
     *
     * @return [type]               [description]
     */
    public function agendaEps($idEntrenador, $idUn)
    {
        session_write_close();
        $idEmpleado = Empleado::obtenIdEmpleado($idEntrenador);

        $datos      = EP::clase($idEmpleado, $idUn);
        $inbodys    = AgendaInbody::ConsultaInbodyEmpleado($idEmpleado, $idUn);
        $arrayMerge = array_merge($datos, $inbodys);
        if (is_array($arrayMerge)) {

            return $this->successResponse($arrayMerge, 'Agenda ');
        } else {
            return $this->errorResponse('No se encontraron datos', 400);
        }
    }

    /**
     * [agendaEps description]
     *
     * @param  [type] $idEntrenador [description]
     * @param  [type] $idUn         [description]
     *
     * @return [type]               [description]
     */
    public function asignaEntrenador($idEmpleado, $idUn, $idAgenda, $nombreCoordinador)
    {
        session_write_close();
        if ($idEmpleado == 0) {
            return $this->errorResponse('Selecciona un Entrenador', 400);
        }
        $inbody   = AgendaInbody::findOrFail($idAgenda);
        $fechaAux = explode(' ', $inbody->fechaSolicitud);
        $fecha    = $fechaAux[0];
        $hora     = $fechaAux[1];
        $datos    = EP::clase($idEmpleado, $idUn, $fecha, $hora);
        $inbodys  = AgendaInbody::ConsultaInbodyEmpleado($idEmpleado, $idUn, $inbody->fechaSolicitud);

        $arrayMerge = array_merge($datos, $inbodys);
        if (is_array($arrayMerge) && count($arrayMerge) > 0) {

            return $this->errorResponse('El horario ya esta ocupado', 400);
        } else {
            $inbody->idEmpleado = $idEmpleado;
            $inbody->save();
            //$inbodys = AgendaInbody::ConsultaInbodyEmpleado($idEmpleado, $idUn);
            $datos  = AgendaInbody::getDatosMailAgendainbody($idAgenda);
            $correo = Empleado::GetEmail($datos->idPersona_empleado);
            if ($correo != null) {

                $fecha = new Carbon($inbody->fechaSolicitud);
                //$correo                        = 'luis01cosio@gmail.com';
                $datosMail                     = new \stdClass();
                $datosMail->nombreEntrenador   = $datos->nombreEmpleado;
                $datosMail->nombreCoordinador  = $nombreCoordinador;
                $datosMail->fechaSolicitud_str = fechaStringES($fecha);
                $datosMail->nombreClub         = $datos->nombreClub;
                $datosMail->hora               = 'de ' . $fecha->format('H:i:s') . ' hasta ' . $fecha->addMinutes(30)->format('H:i:s');
                $datosMail->nombreSocio        = $datos->nombreSocio;

                $mailEntrenador = $correo->mail;
                if (strtolower(env('APP_ENV')) != 'production') {
                    $mailEntrenador = env('EMAIL_DEVELOPER');
                }

                Mail::to($mailEntrenador)->queue(new MailEntrenador($datosMail));
            }

            return $this->successResponse($inbody, 'Asignado correctamente');
        }
    }

    public function lastInBody($idPersona = 0)
    {
        try {

            $idPersona = intval($idPersona);

            $result = InBody::LastInBody($idPersona);
            return response()->json($result, 200);
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

    public function historyInbodys($idPersonaEmpleado)
    {

        $result = InBody::getHistory($idPersonaEmpleado);
        return $this->successResponse($result, 200);
    }

    public function agendaCoordinadorInbody($idUn)
    {
        $res = AgendaInbody::getAgendaCoordinador($idUn);
        return $this->successResponse($res, 'Agenda ');
    }

    public function getInfoInbodies($idPersona)
    {
        $idTipoSexo      = 13;
        $lastInBody      = InBody::LastInBody($idPersona);
        $persona         = Persona::find($idPersona);
        $idTipoSexo      = $persona->idTipoSexo ?? 13;
        $fechaNacimiento = $persona->fechaNacimiento ?? Carbon::now()->subYears(20)->format('Y-m-d');
        $mensajeMenu     = "";

        $agendasInbodyPasadas = AgendaInbody::where('idPersona', $idPersona)
            ->whereNull('fechaConfirmacion')
            ->whereNull('fechaCancelacion')
            ->whereRaw('CURRENT_DATE() > fechaSolicitud')
            ->get();

        foreach ($agendasInbodyPasadas as $key => $value) {
            $value->delete();
        }

        $agendaInbody = AgendaInbody::where('idPersona', $idPersona)
            ->whereNull('fechaConfirmacion')
            ->whereNull('fechaCancelacion')
            ->first();

        $unNombre = "";

        if ($agendaInbody) {
            $isUn     = Un::where('idUn', $agendaInbody->idUn)->first();
            $unNombre = $isUn->nombre ?? "No hay registro";
        }

        $menuPersona = Menu::whereRaw("CURRENT_DATE() between  fecha_inicio and fecha_fin")
            ->where('idPersona', $idPersona)
            ->whereNull('fechaCancelacion')
            ->latest()
            ->first();

        $menuEstatus = false;
        $idMenu      = 0;
        if ($menuPersona) {

            $idMenu      = $menuPersona->id;
            $menuEstatus = true;
        } else {
            $menuEstatus = true;
            $mensajeMenu = "Cliente sin rutina activa";
        }

        if (!$agendaInbody) {
            $menuEstatus = true;
            $mensajeMenu = "No existe una cita agendada";
        }

        return $this->successResponse(
            [
                'lastInBody'      => $lastInBody,
                'idTipoSexo'      => $idTipoSexo,
                'fechaNacimiento' => $fechaNacimiento,
                'unNombre'        => $unNombre,
                'agendaInbody'    => $agendaInbody,
                'menuPersona'     => $menuPersona,
                'mensajeMenu'     => $mensajeMenu,
                'idMenu'          => $idMenu,
                'menuEstatus'     => $menuEstatus,
            ]
        );
    }

    public function referenciasEjercicio()
    {
        $catEjercicio = CatEjercicioPreferencia::select(['id', 'nombre'])->get();
        return $this->successResponse(['catEjercicio' => $catEjercicio]);
    }

}
