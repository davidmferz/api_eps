<?php

function mensaje($code)
{
    $mensajes = [
        10000 => ['Correcto', 200],
        # Errores generales para el sistema
        10001 => ['Formato de la petición incorrecto', 400],
        10002 => ['Parametros erroneos', 400],
        10003 => ['Parametros incompletos', 400],
        10004 => ['Método no permitido', 405],
        10005 => ['Sin autorización', 401],
        50001 => ['Ya existe registro', 400],
        50002 => ['Ya existe el nombre y debe ser único', 400],
        50003 => ['No existe registro', 400],
    ];
    if (is_numeric($code)) {
        return [
            'code' => $mensajes[$code][1],
            'message' => $mensajes[$code][0],
            'data' => [],
        ];
    } else {
        return [
            'code' => $code[1],
            'message' => $code[0],
            'data' => [],
        ];
    }
}