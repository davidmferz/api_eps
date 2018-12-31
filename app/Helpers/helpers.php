<?php

function userByMail($mail)
{
    $parts = explode("@", $mail);
    return $parts[0];
}

// Obtenido de la fuente de CRM
// /system/application/helpers/general_helper.php
function funModuloBase10($ref) {
    #estupida longitud!!
    $ref = str_repeat("0",19-strlen($ref)).$ref;

    $largo = strlen($ref);
    $total = 0;
    //se dividen cada uno de los digitos de la referencia
    for($cont = 0; $cont<=$largo; $cont++) {
        if((($cont+1)%2)==0) {
            $valor = intval(substr($ref,$cont,1))*1;
        } else {
            $valor = intval(substr($ref,$cont,1))*2;
        }
        if($valor>=10) {
            $valor -=9;
        }
        $total+=$valor;
    }
    $modulo = $total%10;
    $referencia = 10-$modulo;
    if ($referencia>=10) {
        $referencia = "0";
    }
    return $referencia;
}