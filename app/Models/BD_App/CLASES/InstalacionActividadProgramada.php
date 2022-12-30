<?php

namespace App\Models\BD_APP\CLASES;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class InstalacionActividadProgramada extends Model
{
    use SoftDeletes;
    protected $connection = 'app';
    protected $table      = 'CLASES.instalacionActividadProgramada';
    protected $primaryKey = 'idInstalacionActividadProgramada';
    public static function currentClass(int $idPersona): array
    {
        $sql = " SELECT
                    iap.idInstalacionActividadProgramada,
                    iap.fecha,
                    iap.horaInicio,
                    ia.capacidadIdeal,
                    ia.capacidadMaxima,
                    ad.nombre as clase,
                    i.nombre as salon,
                    u.nombre as club
                FROM CLASES.instalacionActividadProgramada AS iap
                JOIN CLASES.instalacionActividad AS ia ON ia.idInstalacionActividad=iap.idInstalacionActividad
                JOIN CLASES.unInstalacion ui on ui.idUnInstalacion=ia.idUnInstalacion
                JOIN CLASES.instalacion i on i.idInstalacion=ui.idInstalacion
                JOIN CLASES.club AS u ON u.club_id=ui.idUn AND u.migrado_crm2=1
                JOIN CLASES.actividadDeportiva AS ad ON ad.idActividadDeportiva=ia.idActividadDeportiva
                WHERE iap.idPersona={$idPersona}

            #    AND   STR_TO_DATE(CONCAT(iap.fecha,' ',iap.horaInicio) ,'%Y-%m-%d %h:%i:%s') > NOW()
                order by STR_TO_DATE(CONCAT(iap.fecha,' ',iap.horaInicio) ,'%Y-%m-%d %h:%i:%s') ASC
                ";
        $class = DB::connection('app')->select($sql);
        if (count($class) > 0) {
            $idsIAP        = implode(',', array_column($class, 'idInstalacionActividadProgramada'));
            $classRegister = [];
            foreach ($class as $value) {
                $classRegister[$value->idInstalacionActividadProgramada] = [
                    'idInstalacionActividadProgramada' => $value->idInstalacionActividadProgramada,
                    'inicioVigencia'                   => $value->fecha,
                    'horaInicio'                       => $value->horaInicio,
                    'capacidadIdeal'                   => $value->capacidadIdeal,
                    'capacidadMaxima'                  => $value->capacidadMaxima,
                    'clase'                            => $value->clase,
                    'salon'                            => $value->salon,
                    'club'                             => $value->club,
                    'inscritos'                        => [],
                ];
            }
            $sql = "  SELECT
            a.idActividadAsistencia,
            a.idInstalacionActividadProgramada,
            u.ID_USUARIO as idPersona,
            u.NOMBRE as nombre,
            u.APELLIDO_PATERNO as paterno,
            u.APELLIDO_MATERNO as materno,
            a.confirmado,
            u.ID_MEMBRESIA,
            u.ID_CLUB,
            u.ID_INVITADO,
            u.TIPO_INVITADO,
            u.ID_EMPLEADO ,
            cr.NOMBRE AS tipoUsuario,
            cc.NOMBRE AS club
            FROM CLASES.actividadAsistencia AS a
            JOIN NEGOCIO.USUARIO AS u ON u.ID_USUARIO=a.idPersona
            JOIN NEGOCIO.CAT_ROL AS cr ON cr.ID_ROL=u.ID_ROL
                LEFT JOIN NEGOCIO.CAT_CLUB AS cc ON cc.ID_CLUB=u.ID_CLUB
            where a.idInstalacionActividadProgramada IN ({$idsIAP})
            ";
            $personAsistence = DB::connection('app')->select($sql);

            foreach ($personAsistence as $asistence) {
                $person = [
                    'idActividadAsistencia' => $asistence->idActividadAsistencia,
                    'idPersona'             => $asistence->idPersona,
                    'nombre'                => $asistence->nombre,
                    'paterno'               => $asistence->paterno,
                    'materno'               => $asistence->materno,
                    'confirmado'            => $asistence->confirmado == 1 ? true : false,
                    'idMembresia'           => $asistence->ID_MEMBRESIA,
                    'idInvitado'            => $asistence->ID_INVITADO,
                    'tipoinvitado'          => $asistence->TIPO_INVITADO,
                    'idEmpleado'            => $asistence->ID_EMPLEADO,
                    'tipoUsuario'           => $asistence->tipoUsuario,
                    'club'                  => $asistence->club,
                    'registerApp'           => true,
                ];

                $classRegister[$asistence->idInstalacionActividadProgramada]['inscritos'][] = $person;
            }
            return array_values($classRegister);
        } else {
            return [];
        }
    }
}
