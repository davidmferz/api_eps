<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\CRM2\MsAuth\AuthUser;
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
        $username = $resquest->input('username');
        $password = $resquest->input('password');
        $client   = new Client();
        $body     = [
            'userName' => $username,
            'password' => $password,
        ];
        $header = [
            'Content-Type' => 'application/json',
        ];
        try {
            $endPoint = "/api/auth/get-security-spaces";
            $response = $client->request('POST', env('CRM2_API_URL') . $endPoint, [
                'headers' => $header,
                'body'    => json_encode($body),
            ]);
            if ($response->getBody()) {
                $bodyResponse     = $response->getBody();
                $clubsHabilitados = json_decode($bodyResponse->getContents());
                if (count($clubsHabilitados->ssp) > 1) {
                    $ssp  = $clubsHabilitados->ssp;
                    $body = [
                        'userName' => $username,
                        'password' => $password,
                        'ssp'      => $clubsHabilitados->ssp[0],
                    ];
                    $endPoint         = "/api/auth/client-token";
                    $responseAuthClub = $client->request('POST', env('CRM2_API_URL') . $endPoint, [
                        'headers' => $header,
                        'body'    => json_encode($body),
                    ]);
                    if ($responseAuthClub->getBody()) {

                        $bodyAuth = $responseAuthClub->getBody();

                        $authBody = json_decode($bodyAuth->getContents());
                        $soporte  = UsuariosSoporte::where('userId', $authBody->user_id)->first();
                        $userId   = $authBody->user_id;
                        if ($soporte != null) {
                            $userId = $soporte->userIdEmpleado;
                            $ssp    = AuthUser::ssp($userId);
                            if (count($ssp) <= 0) {
                                return $this->errorResponse('Usuario sin clubs configurados');

                            }
                        }
                        $user     = AuthUser::getUser($userId);
                        $puestos  = EpsPuestosCrm2::all();
                        $trainers = AuthUser::getUsersPuestos($puestos, $ssp[0]->externalId);
                        $clubBase = EpsClubBase::where('idUsuario', $userId)->first();
                        if (count($ssp) == 1) {
                            $clubBase            = new EpsClubBase();
                            $clubBase->idUsuario = $userId;
                            $clubBase->idClub    = $ssp[0]->id;
                            $clubBase->save();
                        }
                        foreach ($trainers as &$trainer) {
                            foreach ($puestos as $value) {
                                if ($trainer->idPuesto == $value->idPuesto) {
                                    $trainer->typeTrainer = $value;
                                }
                            }
                        }

                        $result = [
                            'access_token' => $authBody->access_token,
                            'user'         => $user,
                            'ssp'          => $ssp,
                            'trainers'     => $trainers,
                            'clubBase'     => $clubBase,
                            //'employeePositions' => $puestos,
                        ];
                        $bodyToken = [
                            'userId'            => $authBody->user_id,
                            'refresh_signature' => Carbon::now()->addSeconds(999),
                            'access_token'      => $authBody->access_token,
                            'refresh_token'     => $authBody->refresh_token,
                            'domain'            => $authBody->domain,
                            'sspId'             => $ssp[0]->id,
                        ];
                        Cache::put($authBody->access_token, json_encode($bodyToken), 3600);
                        return $this->successResponse($result, 'ok', 1);
                    } else {
                        return $this->errorResponse('Ocurrio un error inesperado');
                    }
                } else {
                    return $this->successResponse(null, 'sin club registrados', -1);
                }
            } else {
                return $this->errorResponse('Ocurrio un error inesperado');
            }
        } catch (ClientException $e) {
            Log::debug(print_r([$e->getMessage(), $e->getResponse()->getStatusCode()], true));
            return $this->errorResponse('Ocurrio un error inesperado');

        } catch (\Exception $exception) {
            return $this->errorResponse('Ocurrio un error inesperado');
        }
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
        $clubBaseUser = EpsClubBase::where('idUsuario', $request->input('userId'))->first();
        if ($clubBaseUser == null) {
            $clubBaseUser            = new EpsClubBase();
            $clubBaseUser->idUsuario = $request->input('userId');
            $clubBaseUser->idClub    = $idClub;
            $clubBaseUser->save();
        } else {
            EpsClubBase::where('idUsuario', $request->input('userId'))->update(['idClub' => $idClub]);
        }

        return $this->successResponse(null, 'ok', 1);
    }
}
