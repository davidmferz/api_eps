<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UsuarioCoachCatClub extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.USUARIO_COACH_CAT_CLUB";
    protected $primaryKey = ["ID_USUARIO_COACH", "ID_CLUB"];
    public $incrementing  = false;
    public $timestamps    = false;

    public static function clubsTrainer($idUsuario)
    {
        $sql = "SELECT cc.ID_CLUB as idUn,cc.NOMBRE as name
                    FROM USUARIO_COACH AS u
            JOIN USUARIO_COACH_CAT_CLUB as uc on uc.ID_USUARIO_COACH=u.ID_USUARIO_COACH
            JOIN CAT_CLUB AS cc ON uc.ID_CLUB=cc.ID_CLUB
            WHERE u.ID_USUARIO={$idUsuario} AND cc.ESTATUS = 1";
        $res = DB::connection('app')->select($sql);
        if (count($res) > 0) {
            return $res;
        } else {
            return [];
        }
    }
}
