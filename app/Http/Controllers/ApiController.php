<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;

class ApiController extends Controller
{
    use ApiResponse;
    public static function notificacionError($mensaje)
    {
        curl_setopt_array($ch = curl_init(), array(
            CURLOPT_URL            => "https://api.pushover.net/1/messages.json",
            CURLOPT_POSTFIELDS     => array(
                "token"   => env("PUSH_OVER_TOKEN"),
                "user"    => env("PUSH_OVER_KEY"),
                "title"   => env("APP_NAME") . ' ' . env('APP_ENV'),
                "message" => json_encode($mensaje),
            ),
            CURLOPT_SAFE_UPLOAD    => true,
            CURLOPT_RETURNTRANSFER => true,
        ));
        curl_exec($ch);
        curl_close($ch);
    }
}
