<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Http\Requests\CreateInbodyRequest;
use App\Http\Requests\InbodyCoordinadorRequest;
use App\Http\Requests\NuevoFitnessTestRequest;
use App\Mail\MailEntrenador;
use App\Mail\MailPersona;
use App\Models\AgendaInbody;
use App\Models\CatRutinas;
use App\Models\Empleado;
use App\Models\EP;
use App\Models\InBody;
use App\Models\Menu;
use App\Models\Persona;
use App\Models\Un;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
    /**
     * inbody - Recibe en el GET el idPersona y en el POST los datos del inbody para almacenar.
     *
     * @return json
     */
    public function inbody($idPersona = null, $cantidad = null)
    {
        session_write_close();

        try {
            $retval = array();
            if (!is_null($idPersona) && !is_null($cantidad)) {
                settype($idPersona, 'int');
                settype($cantidad, 'int');
                $retval  = EP::obtenInBody($idPersona, $cantidad);
                $code    = 200;
                $message = 'OK';
            } else {
                $request = json_decode(trim(file_get_contents('php://input')), true);
                foreach ($request as $k => $value) {
                    if (!is_numeric($value)) {
                        throw new \RuntimeException('El valor de ' . $k . ' no es numerico');
                    }

                    if ($k != 'idPersona' && ($value > 999.99 || $value < 0)) {
                        throw new \RuntimeException('Valor ' . $k . ' fuera de rango');
                    }
                }
                if (!isset($request['idPersona'])) {
                    throw new \RuntimeException('El idPersona no definido');
                }

                settype($request['idPersona'], 'int');
                if (!is_int($request['idPersona'])) {
                    throw new \RuntimeException('El idPersona es invalido');
                }

                /*
                 * La estatura va en centimetros
                 */
                #$request['estatura'] = $request['estatura']*100;
                EP::ingresaInBody($request);
                $code    = 201;
                $message = 'Created';
            }
            $retval = array(
                'status'  => 'success',
                'data'    => $retval,
                'code'    => $code,
                'message' => $message,
            );
            return response()->json($retval, $retval['code']);
        } catch (\RuntimeException $ex) {
            header('Bad Request', true, 400);
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'code'    => 400,
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, $retval['code']);
        }
    }
    /**
     * Funcion para enviar correo electrónico posterior a la Agenda InBody Wellness
     * @author Oscar Sanchez V. <oscar.villavicencio@sportsworld.com.mx>
     * @version 1.0.0
     * @api
     * @param  string JSON
     * @return string JSON
     */
    public function mailAgendaInbody($data)
    {
        $sql = "SELECT idPersona, fechaSolicitud, horario, idUn
            FROM piso.agenda_inbody WHERE idAgenda = " . $data['idAgenda'];
        $res                = DB::connection('aws')->select($sql);
        $idPersona          = $res[0]->idPersona;
        $fechaSolicitud_sql = $res[0]->fechaSolicitud;
        $horario            = $res[0]->horario;
        $idUn               = $res[0]->idUn;

        $newLocale                 = setlocale(LC_TIME, 'Spanish');
        $fechaSolicitud            = new Carbon;
        $fechaSolicitud->timestamp = strtotime($fechaSolicitud_sql);
        $fechaSolicitud_str        = ucfirst($fechaSolicitud->formatLocalized('%A %d de %B %Y'));

        $fechaHasta            = new Carbon;
        $fechaHasta->timestamp = strtotime($fechaSolicitud->format('Y-m-d') . ' ' . $horario);
        $fechaHasta->addMinutes(AgendaInbody::MINUTOS_INBODY);

        $horario_desde = $horario;
        $horario_hasta = $fechaHasta->format('H:i:s');

        $horario_str = 'De ' . $horario_desde . ' a ' . $horario_hasta . ' horas';

        $sql_0 = "SELECT * FROM un WHERE idUn = {$idUn}";
        $club  = DB::connection('crm')->select($sql_0);

        $sql_1 = "SELECT
            CONCAT(p.nombre,' ',p.paterno,' ',p.materno) nombre_socio,
            ma.mail
            FROM persona p
            INNER JOIN mail ma ON ma.idPersona = p.idPersona
            WHERE p.idPersona = {$idPersona}
            ORDER BY FIND_IN_SET(ma.idTipoMail, '34,36,37,36,35,3')
            LIMIT 1 ";
        $persona = DB::connection('crm')->select($sql_1);

        if (count($persona) > 0) {
            $data['persona']            = $persona[0];
            $data['fechaSolicitud_str'] = $fechaSolicitud_str;
            $data['horario_str']        = $horario_str;
            $data['club']               = $club[0];

            $data['subject_socio'] = '¡Tu Wellness Test está agendado!';
            Mail::send('emails.wellness_confirm', ['data' => $data], function ($message) use ($data) {
                $message->to($data['persona']->mail, $data['persona']->nombre_socio)->subject($data['subject_socio']);
            });

            // check for failures
            if (Mail::failures()) {
                throw new \Exception("Error en el envío de correo");
            }
        } else {
            throw new \Exception("No se encontró email del socio");
        }

        return [];
    }

    public function createinBody(CreateInbodyRequest $request)
    {
        try {
            // $validated = $request->validated();
            $retval = array();
            if (!empty($retval)) {
                return response()->json($retval, 400);
            }

            $datos = [
                'idPersona'         => trim($request->input('persona')),
                'tipoCuerpo'        => trim($request->input('tipoCuerpo')),
                'numComidas'        => trim($request->input('numComidas')),
                'RCC'               => trim($request->input('rcc')),
                'PGC'               => trim($request->input('pgc')),
                'IMC'               => trim($request->input('imc')),
                'MME'               => trim($request->input('mme')),
                'MCG'               => trim($request->input('mcg')),
                'minerales'         => trim($request->input('minerales')),
                'proteina'          => trim($request->input('proteina')),
                'ACT'               => trim($request->input('act')),
                'peso'              => trim($request->input('peso')),
                'estatura'          => trim($request->input('estatura')),
                'fcresp'            => trim($request->input('fcresp')),
                'idPersonaEmpleado' => trim($request->input('idPersonaEmpleado')),
            ];

            $result = InBody::CreateInBody($datos);
            if ($request->input('idAgenda') > 0) {
                $hoy                       = Carbon::now();
                $agenda                    = AgendaInbody::find($request->input('idAgenda'));
                $agenda->fechaConfirmacion = $hoy->format('Y-m-d');
                $agenda->save();
            }
            if (!empty($result)) {
                $retval = [
                    'status'  => 'ok',
                    'message' => 'Created',
                    'data'    => $result,
                ];
                return response()->json($retval, 201);
            } else {
                $retval = [
                    'status'  => 'error',
                    'message' => 'El usuario no se pudo registrar',
                    'data'    => [],
                ];
                return response()->json($retval, 204);
            }
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
        $nombre = null;
        $idTipoSexo = 13;
        $lastInBody = InBody::LastInBody($idPersona);
        $persona = Persona::find($idPersona);
        $idTipoSexo = $persona->idTipoSexo ??  13;
        $fechaNacimiento = $persona->fechaNacimiento ??  Carbon::now()->subYears(20)->format('Y-m-d');
        $actividad  = Menu::ReadMenuActividad($idPersona);
        $rutinas = CatRutinas::getFullCatalogv2();
        $mensajeMenu = "";
        if ($actividad['estatus']) {
            $nombre = $persona->nombre . ' ' . $persona->paterno . ' ' . $persona->materno;
        }

        $agendaInbody = AgendaInbody::where('idPersona', $idPersona)
            ->whereNull('fechaConfirmacion')
            ->whereNull('fechaCancelacion')
            ->first();

        $unNombre = "";

        if ($agendaInbody) {
            $isUn = Un::where('idUn', $agendaInbody->idUn)->first();
            $unNombre = $isUn->nombre ?? "No hay registro";
        }

        $menuPersona = Menu::whereRaw("now() between  fecha_inicio and fecha_fin")->where('idPersona', $idPersona)->whereNotNull('fechaCancelacion')->first();

        if ($menuPersona) {
            $mensajeMenu = "Ya cuenta con una rutina asignada hasta el día {$menuPersona->fecha_fin}, para cancelar debera el cliente cancelar primero todo el menu, una vez finalizado ya podrá generar uno nuevo";
        }

        return $this->successResponse(
            [
                'nombre' => $nombre,
                'lastInBody' => $lastInBody,
                'actividad' => $actividad,
                'idTipoSexo' => $idTipoSexo,
                'fechaNacimiento' => $fechaNacimiento,
                'rutinas' => $rutinas,
                'unNombre' => $unNombre,
                'agendaInbody' => $agendaInbody,
                'menuPersona' => $menuPersona,
                'mensajeMenu' => $mensajeMenu
            ]
        );
    }
}
