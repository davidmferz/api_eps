<?php

namespace App\Http\Middleware;

use App\Http\Controllers\LoginCrm2Controller;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class AuthUserEPS
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->headers->get('secret-key');

        if (Cache::has($token)) {

            $data       = json_decode(Cache::get($token));
            $now        = Carbon::now();
            $validToken = Carbon::parse($data->refresh_signature);
            if ($now->isAfter($validToken)) {
                $estatusRefresh = LoginCrm2Controller::refresToken($data);
                Log::debug(print_r($estatusRefresh, true));
                if (!$estatusRefresh) {
                    $respuesta = [
                        'code'    => 401,
                        'message' => 'Autorización inválida paso 1',
                    ];
                    return Response::json($respuesta, 401);
                } else {
                    Cache::put($token, json_encode($estatusRefresh), 3600);
                    $request->request->add(['userId' => $estatusRefresh['userId']]);
                    $request->request->add(['idEmpleado' => $estatusRefresh['idEmpleado']]);
                    $request->request->add(['access_token' => $estatusRefresh['access_token']]);
                    return $next($request);
                }
            }
        } else {
            $respuesta = [
                'code'    => 401,
                'message' => 'Autorización inválida paso 2',
            ];
            return Response::json($respuesta, 401);
        }

        Cache::put($token, json_encode($data), 6200);
        $request->request->add(['userId' => $data->userId]);
        $request->request->add(['access_token' => $data->access_token]);
        if (isset($data->idEmpleado)) {

            $request->request->add(['idEmpleado' => $data->idEmpleado]);
        }
        return $next($request);

    }
}
