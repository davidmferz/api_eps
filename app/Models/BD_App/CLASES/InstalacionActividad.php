<?php

namespace App\Models\BD_App\CLASES;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class InstalacionActividad extends Model
{
    use SoftDeletes;
    protected $connection = 'app';
    protected $table      = 'CLASES.instalacionActividad';
    protected $primaryKey = 'idInstalacionActividad';
    protected $hidden     = [
        'created_at', 'updated_at', 'deleted_at',
    ];
    public static function classSizeClub($idClub)
    {
        $sql = "SELECT ia.idInstalacionActividad ,i.nombre AS salon,c.club_id ,c.nombre AS nombreClub,
                    cd.NOMBRE as nombreDisciplina ,ia.capacidadIdeal,ia.capacidadMaxima
                FROM CLASES.instalacionActividad AS ia
                JOIN CLASES.unInstalacion AS ui ON ui.idUnInstalacion=ia.idUnInstalacion
                JOIN CLASES.instalacion AS i ON i.idInstalacion=ui.idInstalacion
                JOIN CLASES.club AS c ON c.club_id=ui.idUn AND c.migrado_crm2=1
                JOIN CLASES.actividadDeportiva AS ad ON ad.idActividadDeportiva=ia.idActividadDeportiva
                left JOIN NEGOCIO.CAT_DISCIPLINA AS cd ON cd.CLAVE=ad.clave
                WHERE c.club_id={$idClub}
                ORDER BY i.idInstalacion";
        $res = DB::connection('app')->select($sql);
        if (count($res) > 0) {
            return $res;
        } else {
            return [];
        }
    }

}
