<?php

namespace App\Models\Deportiva;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class InstalacionActividadProgramada extends Model
{
    use SoftDeletes;
    protected $connection = 'aws';
    protected $table      = 'deportiva.instalacionactividadprogramada';
    protected $primaryKey = 'idInstalacionActividad';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    public static function currentClass(int $idPersona): array
    {
        $sql = " SELECT
                    iap.idInstalacionActividadProgramada,
                    iap.inicioVigencia,
                    iap.horaInicio,
                    ia.capacidadIdeal,
                    ia.capacidadMaxima,
                    ad.descripcion as clase,
                    i.descripcion as salon,
                    u.nombre as club
                FROM deportiva.instalacionactividadprogramada AS iap
                JOIN deportiva.instalacionactividad AS ia ON ia.idInstalacionActividad=iap.idInstalacionActividad
                JOIN deportiva.uninstalacion ui on ui.idUnInstalacion=ia.idUnInstalacion
                JOIN deportiva.instalacion i on i.idInstalacion=ui.idUnInstalacion
                JOIN deportiva.un AS u ON u.idUn=ui.idUn
                JOIN deportiva.actividaddeportiva AS ad ON ad.idActividadDeportiva=ia.idActividadDeportiva
                WHERE iap.idPersona={$idPersona}
                AND   STR_TO_DATE(CONCAT(iap.inicioVigencia,' ',iap.horaInicio) ,'%Y-%m-%d %h:%i:%s') > NOW()
                order by STR_TO_DATE(CONCAT(iap.inicioVigencia,' ',iap.horaInicio) ,'%Y-%m-%d %h:%i:%s') ASC
                ";
        $class = DB::connection('aws')->select($sql);
        if (count($class) > 0) {
            $idsIAP        = implode(',', array_column($class, 'idInstalacionActividadProgramada'));
            $classRegister = [];
            foreach ($class as $value) {
                $classRegister[$value->idInstalacionActividadProgramada] = [
                    'idInstalacionActividadProgramada' => $value->idInstalacionActividadProgramada,
                    'inicioVigencia'                   => $value->inicioVigencia,
                    'horaInicio'                       => $value->horaInicio,
                    'capacidadIdeal'                   => $value->capacidadIdeal,
                    'capacidadMaxima'                  => $value->capacidadMaxima,
                    'clase'                            => $value->clase,
                    'salon'                            => $value->salon,
                    'club'                             => $value->club,
                    'inscritos'                        => [],
                ];
            }
            $sql = "  SELECT a.idInstalacionActividadProgramada,p.idPersona,p.nombre,p.paterno,p.materno,a.confirmado
            FROM deportiva.actividadasistencia AS a
            JOIN deportiva.persona AS p ON p.idPersona=a.idPersona
            where a.idInstalacionActividadProgramada IN ({$idsIAP})
            AND a.eliminado=0";
            $personAsistence = DB::connection('aws')->select($sql);
            $personsInfo     = [];
            if (count($personAsistence) > 0) {
                $idsPersona = implode(',', array_column($personAsistence, 'idPersona'));
                $sql        = "SELECT u.ID_USUARIO,u.ID_MEMBRESIA,u.ID_CLUB,u.ID_INVITADO,u.TIPO_INVITADO,u.ID_EMPLEADO , cr.NOMBRE AS tipoUsuario,cc.NOMBRE AS club
                FROM NEGOCIO.USUARIO AS u
                JOIN NEGOCIO.CAT_ROL AS cr ON cr.ID_ROL=u.ID_ROL
                LEFT JOIN NEGOCIO.CAT_CLUB AS cc ON cc.ID_CLUB=u.ID_CLUB
                WHERE u.ID_USUARIO IN ({$idsPersona})
                ";
                $personsInfo = DB::connection('app')->select($sql);
            }
            foreach ($personAsistence as $asistence) {
                $person = [
                    'idPersona'   => $asistence->idPersona,
                    'nombre'      => $asistence->nombre,
                    'paterno'     => $asistence->paterno,
                    'materno'     => $asistence->materno,
                    'confirmado'  => $asistence->materno,
                    'registerApp' => false,
                ];
                foreach ($personsInfo as $valueApp) {
                    if ($valueApp->ID_USUARIO == $asistence->idPersona) {
                        $person['registerApp']  = true;
                        $person['idMembresia']  = $valueApp->ID_MEMBRESIA;
                        $person['idInvitado']   = $valueApp->ID_INVITADO;
                        $person['tipoinvitado'] = $valueApp->TIPO_INVITADO;
                        $person['idEmpleado']   = $valueApp->ID_EMPLEADO;
                        $person['tipoUsuario']  = $valueApp->tipoUsuario;
                        $person['club']         = $valueApp->club;
                    }
                }
                $classRegister[$asistence->idInstalacionActividadProgramada]['inscritos'][] = $person;
            }
            return array_values($classRegister);
        } else {
            return [];
        }
    }
}
