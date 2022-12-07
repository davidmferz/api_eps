<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportsUsersRequest;
use App\Http\Requests\SellPackageRequest;
use App\Models\CRM2\MsAuth\EventoClases;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SellCrm2Controller extends ApiController
{

    /**
     * @OA\Get(
     *     path="/api/crm2/v1/products/{idClub}/{idUsuario}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="idClub",in="path",@OA\Schema(type="integer",default=75)),
     *     @OA\Parameter(name="idUsuario",in="path",@OA\Schema(type="integer",default=75)),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function products(Request $request, $idClub, $idUsuario)
    {
        $query = [
            'clubId'    => $idClub,
            'personaId' => $idUsuario,
        ];
        $header = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $request->input('access_token'),
        ];
        $client = new Client();
        try {
            $endPoint = env('CRM2_API_URL') . "/api/open/products/lessons";
            $response = $client->request('GET', $endPoint, [
                'headers' => $header,
                'query'   => $query,

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
            return $this->successResponse([], 'ok', -2);
        }
    }

    public function sellPackage(SellPackageRequest $request)
    {
        $body = [
            'clientId'      => $request->input('clientId'),
            'instructorId'  => $request->input('instructorId'),
            'productoId'    => $request->input('productoId'),
            'clubId'        => $request->input('clubId'),
            'responsableId' => $request->input('responsableId'),
            'precioId'      => $request->input('precioId'),
            'unidades'      => $request->input('unidades'),
        ];
        $header = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $request->input('access_token'),
        ];
        $client = new Client();
        try {
            $endPoint = env('CRM2_API_URL') . "/api/open/products/lessons";
            $response = $client->request('POST', $endPoint, [
                'headers' => $header,
                'body'    => json_encode($body),

            ]);
            if ($response->getBody()) {
                $bodyResponse   = $response->getBody();
                $detailPurchase = json_decode($bodyResponse->getContents());
                if ($detailPurchase->error) {
                    return $this->successResponse($detailPurchase, 'ocurrio un error al generar el movimiento', -1);
                }
                return $this->successResponse($detailPurchase, 'ok', 1);

            } else {
                return $this->successResponse(null, 'ocurrio un error inesperado ', -1);
            }

        } catch (ClientException $e) {
            Log::debug(print_r([$e->getMessage(), $e->getResponse()->getStatusCode()], true));
            return $this->successResponse([], 'ok', -3);

        } catch (\Exception $exception) {
            return $this->successResponse([], 'ok', -2);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/crm2/v1/reportByUsers",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\RequestBody(ref="#/components/requestBodies/ReportsUsersRequest"),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function reportByUsers(ReportsUsersRequest $request)
    {

        $dataReport   = EventoClases::reportSellUsers($request->input('users'));
        $usersReports = [];
        foreach ($dataReport as $value) {
            if (!isset($usersReports[$value->userId])) {
                $usersReports[$value->userId] = [
                    'userId'       => $value->userId,
                    'avg'          => $value->ventaMes,
                    'ultimosMeses' => [
                        [
                            'mes'      => $value->mes,
                            'ventaMes' => $value->ventaMes,
                        ],
                    ],
                ];
            } else {
                if (count($usersReports[$value->userId]['ultimosMeses']) < 3) {
                    $usersReports[$value->userId]['ultimosMeses'][] = [
                        'mes'      => $value->mes,
                        'ventaMes' => $value->ventaMes,
                    ];
                }
                $usersReports[$value->userId]['avg'] = ($usersReports[$value->userId]['avg'] + $value->ventaMes) / 2;
            }
        }
        return $this->successResponse(array_values($usersReports), 'report', 1);
    }

}
