<?php

namespace App\Models\CRM2\MsAuth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuthUser extends Model
{

    protected $connection = 'crm2';
    protected $table      = 'msauth.auth_user';
    protected $primaryKey = 'user_id';
    public $timestamps    = false;

    public static function ssp($idEmpleado)
    {
        $sql = "SELECT
                        DISTINCT name,
                        id,
                        externalId
                FROM
                        (
                                SELECT
                                        ssp.name,
                                        ssp.space_id as id,
                                        ssp.external_id as externalId
                                from
                                        msauth.user_security_space_group AS uss
                                        JOIN msauth.auth_user AS au ON au.user_id = uss.user_id
                                        JOIN msauth.security_space ssp ON ssp.space_id = uss.space_id
                                WHERE
                                        au.numero_empleado = {$idEmpleado}
                                UNION
                                SELECT
                                        ssp.name,
                                        ssp.space_id as id,
                                        ssp.external_id as externalId
                                FROM
                                        msauth.auth_user AS a
                                        join msauth.user_group ug ON ug.user_id = a.user_id
                                        JOIN msauth.user_security_space_group AS uss ON uss.group_id = ug.group_id
                                        JOIN msauth.security_space ssp ON ssp.space_id = uss.space_id
                                WHERE
                                        a.numero_empleado = {$idEmpleado}
                        ) AS a";
        $query = DB::connection('crm2')->select($sql);
        if (count($query) > 0) {
            return $query;
        } else {
            return [];
        }
    }
    public static function getUser($userId)
    {
        $sql = "SELECT au.user_id as userId,au.name,au.numero_empleado as numeroEmpleado,au.email,au.first_surname as lastName,au.second_surname as secondLastname,p.nombre AS puesto ,p.id_puesto as idPuesto,p.clave
         FROM msauth.auth_user AS au
        JOIN msauth.auth_user_puestos AS ap ON ap.id_user=au.user_id
        JOIN msauth.auth_puestos AS p ON p.id_puesto=ap.id_puesto
        WHERE au.numero_empleado={$userId} ";
        $query = DB::connection('crm2')->select($sql);
        if (count($query) > 0) {
            return $query[0];
        } else {
            return null;
        }
    }

    public static function getUsersPuestos($puestos, $idClub)
    {
        $idsPuestos = implode(',', $puestos->pluck('idPuesto')->toArray());
        $sql        = "SELECT au.user_id as userId,au.name,au.numero_empleado as numeroEmpleado,au.email,au.first_surname as lastName,au.second_surname as secondLastname,p.nombre AS puesto ,p.id_puesto as idPuesto,p.clave
         FROM msauth.auth_user AS au
        JOIN msauth.auth_user_puestos AS ap ON ap.id_user=au.user_id
        JOIN msauth.auth_puestos AS p ON p.id_puesto=ap.id_puesto
        JOIN msauth.user_security_space_group AS uss ON uss.user_id=au.user_id
        JOIN msauth.security_space  ssp ON  ssp.space_id=uss.space_id
        WHERE ap.id_puesto IN ({$idsPuestos})
        AND ssp.external_id={$idClub}";

        $query = DB::connection('crm2')->select($sql);
        if (count($query) > 0) {
            return $query;
        } else {
            return [];
        }
    }

}
