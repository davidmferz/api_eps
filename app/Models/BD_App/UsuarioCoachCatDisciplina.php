<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UsuarioCoachCatDisciplina extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.USUARIO_COACH_CAT_DISCIPLINA";
    protected $primaryKey = ["ID_USUARIO_COACH", "ID_DISCIPLINA"];
    public $timestamps    = false;
    public $incrementing  = false;
    public static function disciplinesTrainer($idUsuario)
    {
        $sql = "SELECT cd.ID_DISCIPLINA as idDiscipline,cd.NOMBRE as name
            FROM USUARIO_COACH AS u
            JOIN USUARIO_COACH_CAT_DISCIPLINA as uc on uc.ID_USUARIO_COACH=u.ID_USUARIO_COACH
            JOIN CAT_DISCIPLINA AS cd ON uc.ID_DISCIPLINA = cd.ID_DISCIPLINA
            WHERE u.ID_USUARIO = {$idUsuario} AND cd.ESTATUS = 1";
        $res = DB::connection('app')->select($sql);
        if (count($res) > 0) {
            return $res;
        } else {
            return [];
        }
    }
}
