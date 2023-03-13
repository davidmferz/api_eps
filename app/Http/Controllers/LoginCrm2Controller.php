<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\CRM2\MsAuth\AuthUser;
use App\Models\CRM2\msclub\Club;
use App\Models\Deportiva\EpsClubBase;
use App\Models\Deportiva\EpsPuestosCrm2;
use App\Models\UsuariosSoporte;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoginCrm2Controller extends ApiController
{

    public function clubsCallCenter()
    {
        $clubs       = Club::where('activo', 1)->orderBy('nombre', 'ASC')->get();
        $clubsFormat = [];
        foreach ($clubs as $key => $club) {
            $clubsFormat[] = [
                'idUn' => $club->club_id,
                'name' => $club->nombre,
            ];

        }
        return $this->successResponse($clubsFormat, 'ok', 1);

    }

    /**
     * @OA\Post(
     *     path="/api/crm2/v1/auth",
     *     tags={"USUARIO"},
     *     summary="USUARIO",
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\RequestBody(ref="#/components/requestBodies/LoginRequest"),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function auth(LoginRequest $resquest)
    {
        try {
            $username = $resquest->input('username');
            $password = $resquest->input('password');
            $client   = new Client();
            $idPuesto = 0;
            $body     = [
                'userName' => $username,
                'password' => $password,
            ];
            $header = [
                'Content-Type' => 'application/json',
            ];
            $endPoint = env('CRM2_API_URL') . "/api/auth/get-security-spaces";
            $response = $client->request('POST', $endPoint, [
                'headers' => $header,
                'body'    => json_encode($body),
            ]);

            if ($response->getBody()) {
                $bodyResponse     = $response->getBody();
                $clubsHabilitados = json_decode($bodyResponse->getContents());
                $ssp              = $clubsHabilitados->ssp;
                if (count($clubsHabilitados->ssp) > 0) {

                    $body = [
                        'userName' => $username,
                        'password' => $password,
                        'ssp'      => $clubsHabilitados->ssp[0],
                    ];
                } else {
                    $body = [
                        'userName' => $username,
                        'password' => $password,
                        'ssp'      => ['id' => -1, 'userName' => ""],
                    ];
                }
                $endPoint         = "/api/auth/client-token";
                $responseAuthClub = $client->request('POST', env('CRM2_API_URL') . $endPoint, [
                    'headers' => $header,
                    'body'    => json_encode($body),
                ]);
                if ($responseAuthClub->getBody()) {
                    $bodyAuth = $responseAuthClub->getBody();

                    $authBody   = json_decode($bodyAuth->getContents());
                    $user       = AuthUser::find($authBody->user_id);
                    $soporte    = UsuariosSoporte::where('idEmpleado', $user->numero_empleado)->first();
                    $idEmpleado = $user->numero_empleado;
                    if ($soporte != null) {
                        $idEmpleado = $soporte->idEmpleadoSuplantar;
                        $ssp        = AuthUser::ssp($idEmpleado);
                        if (count($ssp) <= 0) {
                            return $this->errorResponse('Usuario sin clubs configurados');

                        }
                    }
                    $user = AuthUser::getUser($idEmpleado);

                    $puestos     = EpsPuestosCrm2::all();
                    $excepciones = [];
                    foreach ($puestos as $key => $value) {
                        if ($value->tipoEmpleado == 'idEmpleado') {
                            $excepciones[] = [
                                'idEmpleado' => $value->idPuesto,
                                'meta'       => $value->meta,
                            ];
                            unset($puestos[$key]);

                        }
                    }
                    $clubBase = EpsClubBase::where('idEmpleado', $idEmpleado)->first();
                    if (count($ssp) >= 1 && $clubBase == null) {
                        $clubBase             = new EpsClubBase();
                        $clubBase->idEmpleado = $idEmpleado;
                        $clubBase->idClub     = $ssp[0]->externalId;
                        $clubBase->save();
                    }
                    $trainers = AuthUser::getUsersPuestos($puestos, $clubBase->idClub);

                    foreach ($trainers as &$trainer) {
                        foreach ($puestos as $value) {
                            if ($trainer->idPuesto == $value->idPuesto) {
                                $trainer->typeTrainer = $value;
                            }
                        }
                        foreach ($excepciones as $exc) {
                            if ($exc['idEmpleado'] == $trainer->numeroEmpleado) {
                                $trainer->typeTrainer->meta = $exc['meta'];
                            }
                        }

                    }
                    $user->rol = $this->setRol($idEmpleado, $user->idPuesto);
                    $result    = [
                        'access_token' => $authBody->access_token,
                        'user'         => $user,
                        'ssp'          => $ssp,
                        'trainers'     => $trainers,
                        'clubBase'     => $clubBase,
                        'idEmpleado'   => $idEmpleado,

                    ];
                    $bodyToken = [
                        'userId'            => $authBody->user_id,
                        'refresh_signature' => Carbon::now()->addSeconds(999),
                        'idEmpleado'        => $idEmpleado,
                        'access_token'      => $authBody->access_token,
                        'refresh_token'     => $authBody->refresh_token,
                        'domain'            => $authBody->domain,
                        'sspId'             => isset($ssp[0]->id) ? $ssp[0]->id : null,
                    ];
                    Cache::put($authBody->access_token, json_encode($bodyToken), 3600);
                    return $this->successResponse($result, 'ok', 1);
                } else {
                    return $this->errorResponse('Ocurrio un error inesperado 3');
                }

            } else {
                return $this->errorResponse('Ocurrio un error inesperado 2');
            }
        } catch (ClientException $e) {
            return $this->errorResponse('Usuario o contraseña inválido');

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return $this->errorResponse('Ocurrio un error inesperado');
        }
    }

    public static function setRol($idEmpleado, $idPuesto)
    {
        if (in_array($idEmpleado, ['15430']) || in_array($idPuesto, [155, 156])) {
            return 'root';
        }
        if (in_array($idPuesto, [4, 39, 50, 131, 142, 151, 72, 163, 2])) {
            return 'coordinador';
        }
        if (in_array($idPuesto, [62, 66, 67, 69, 77, 81, 99, 95, 104, 141, 101])) {
            return 'trainer';
        }

        if (in_array($idPuesto, [57, 87, 105])) {
            return 'groupFitness';
        }

        if (in_array($idPuesto, [31, 32, 160, 42])) {
            return 'callCenter';
        }
        return 'NA';

    }

    public static function refresToken($token)
    {

        $body = [
            'refreshToken'    => $token->refresh_token,
            'domain'          => $token->domain,
            'securitySpaceId' => $token->sspId,
        ];

        $header = [
            'Content-Type' => 'application/json',
        ];
        $client = new Client();
        try {
            $endPoint = env('CRM2_API_URL') . "/api/auth/refresh-token";
            Log::debug($endPoint);
            $response = $client->request('POST', $endPoint, [
                'headers' => $header,
                'body'    => json_encode($body),
            ]);
            if ($response->getBody()) {
                $bodyResponse = $response->getBody();
                $authBody     = json_decode($bodyResponse->getContents());
                Log::debug(json_encode($authBody));
                $bodyToken = [
                    'userId'            => $authBody->user_id,
                    'refresh_signature' => Carbon::now()->addSeconds(999),
                    'access_token'      => $authBody->access_token,
                    'refresh_token'     => $authBody->refresh_token,
                    'domain'            => $authBody->domain,
                    'sspId'             => $token->sspId,
                    'idEmpleado'        => $authBody->idEmpleado,
                ];
                Cache::put($token->access_token, json_encode($bodyToken), 3600);

                return $bodyToken;
            } else {
                return false;
            }

        } catch (ClientException $e) {
            Log::debug(print_r([$e->getMessage(), $e->getResponse()->getStatusCode()], true));
            return false;

        } catch (\Exception $exception) {
            return false;
        }
    }
    /**
     * @OA\Get(
     *     path="/api/crm2/v1/getTrainers/{idClub}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="idClub",in="path",@OA\Schema(type="integer",default=75)),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function getTrainers(Request $request, $idClub)
    {
        $puestos  = EpsPuestosCrm2::all();
        $trainers = AuthUser::getUsersPuestos($puestos, $idClub);

        foreach ($trainers as &$trainer) {
            foreach ($puestos as $value) {
                if ($trainer->idPuesto == $value->idPuesto) {
                    $trainer->typeTrainer = $value;
                }
            }
        }
        return $this->successResponse($trainers, 'ok', 1);
    }

    /**
     * @OA\Get(
     *     path="/api/crm2/v1/search",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="tipo",in="query",@OA\Schema(type="string",default="SOCIO")),
     *     @OA\Parameter(name="nombre",in="query",@OA\Schema(type="string",default="luis")),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function search(Request $request)
    {

        $query = [
            'activos' => true,
            'tipo'    => $request->tipo,
            'nombre'  => $request->nombre,
        ];
        $header = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $request->input('access_token'),
        ];
        $client = new Client();
        try {
            $endPoint = env('CRM2_API_URL') . "/api/open/clients";
            Log::debug($endPoint);
            $response = $client->request('GET', $endPoint, [
                'headers' => $header,
                'query'   => $query,
            ]);
            if ($response->getBody()) {
                $bodyResponse = $response->getBody();
                $result       = json_decode($bodyResponse->getContents());

                return $this->successResponse($result, 'ok', 1);
            } else {
                return $this->successResponse([], 'ok', -1);
            }

        } catch (ClientException $e) {

            Log::debug(print_r([$e->getMessage(), $e->getResponse()->getStatusCode()], true));
            return $this->successResponse([], 'ok', -2);

        } catch (\Exception $exception) {
            Log::debug(print_r($exception->getMessage(), true));
            return $this->successResponse([], 'ok', -3);
        }

    }

    /**
     * @OA\Put(
     *     path="/api/crm2/v1/changeClubBase/{idClub}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="idClub",in="path",@OA\Schema(type="integer",default=75)),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function changeClubBase(Request $request, int $idClub)
    {
        $clubBaseUser = EpsClubBase::where('idEmpleado', $request->input('idEmpleado'))->first();
        if ($clubBaseUser == null) {
            $clubBaseUser             = new EpsClubBase();
            $clubBaseUser->idEmpleado = $request->input('idEmpleado');
            $clubBaseUser->idClub     = $idClub;
            $clubBaseUser->save();
        } else {
            EpsClubBase::where('idEmpleado', $request->input('idEmpleado'))->update(['idClub' => $idClub]);
        }

        return $this->successResponse(null, 'ok', 1);
    }
    public function validSession(Request $request)
    {
        return $this->successResponse(null, 'ok', 1);

    }
}
