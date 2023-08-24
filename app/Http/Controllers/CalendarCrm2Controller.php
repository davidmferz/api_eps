<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignClassRequest;
use App\Models\BD_App\CLASES\InstalacionActividadProgramada as CLASESInstalacionActividadProgramada;
use App\Models\BD_App\Usuario;
use App\Models\CRM2\MsAuth\EventoClases;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CalendarCrm2Controller extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/crm2/v1/events/{idUsuario}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="idUsuario",in="path",@OA\Schema(type="integer",default=75)),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function events(Request $request, $idUsuario)
    {
        $header = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $request->input('access_token'),
        ];
        $client = new Client();
        try {
            $endPoint = env('CRM2_API_URL') . "/api/open/trainers/events/{$idUsuario}";
            $response = $client->request('GET', $endPoint, [
                'headers' => $header,

            ]);
            if ($response->getBody()) {
                $bodyResponse = $response->getBody();
                $products     = json_decode($bodyResponse->getContents());

                return $this->successResponse($products, 'ok', 1);

            } else {
                return $this->successResponse([], 'ok', -1);
            }

        } catch (ClientException $e) {
            Log::debug($e->getMessage());
            return $this->successResponse([], 'ok', -1);

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return $this->successResponse([], 'ok', -2);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/crm2/v1/unassignedClasses/{type}/{idUsuario}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="type",in="path",@OA\Schema(type="string",default="trainer")),
     *     @OA\Parameter(name="idUsuario",in="path",@OA\Schema(type="integer",default=75)),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function unassignedClasses(Request $request, $type, $idUsuario)
    {

        $unassigned = EventoClases::unassignedClasses($type, $idUsuario);
        return $this->successResponse($unassigned, 'ok', 1);

    }
    /**
     * @OA\Post(
     *     path="/api/crm2/v1/asingClass/trainer",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\RequestBody(ref="#/components/requestBodies/AssignClassRequest"),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function asingClass(AssignClassRequest $request)
    {
        $header = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $request->input('access_token'),
        ];
        $body = [
            "id"           => $request->input('id'),
            "instructorId" => $request->input('instructorId'),
            "fechaClase"   => $request->input('fechaClase'),
            "horaClase"    => $request->input('horaClase'),
            "asistentes"   => $request->input('asistentes'),
        ];
        $client = new Client();
        try {
            $endPoint = env('CRM2_API_URL') . "/api/open/trainers/lessons";
            $response = $client->request('POST', $endPoint, [
                'headers' => $header,
                'body'    => json_encode($body),
            ]);
            if ($response->getBody()) {
                $bodyResponse = $response->getBody();
                $products     = json_decode($bodyResponse->getContents());
                return $this->successResponse($products, 'ok', 1);
            } else {
                return $this->successResponse([], 'ok', -1);
            }

        } catch (ClientException $e) {
            Log::debug(print_r([$e->getMessage(), $e->getResponse()->getStatusCode()], true));
            return $this->successResponse([], 'ok', -1);

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage(), true);
            return $this->successResponse([], 'ok', -2);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/crm2/v1/groupClass/{mail}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="mail",in="path",@OA\Schema(type="string",default="trainer@mail")),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function groupClass(Request $request, $mail)
    {
        $user  = Usuario::where('EMAIL', $mail)->first();
        $class = CLASESInstalacionActividadProgramada::currentClass($user->ID_USUARIO);
        return $this->successResponse($class, 'ok', 1);
    }
}
