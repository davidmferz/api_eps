<?php
namespace App\Models\BD_App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UsuarioCoach extends Model
{

    protected $connection = 'app';
    protected $table      = "NEGOCIO.USUARIO_COACH";
    protected $primaryKey = "ID_USUARIO_COACH";
    public $timestamps    = false;

    public static function profile($mail)
    {
        $sql = "SELECT uc.APODO as apodo,uc.CERTIFICACIONES as certificaciones,uc.DESCRIPCION as descripcion
                FROM USUARIO AS u
                JOIN USUARIO_COACH AS uc ON u.ID_USUARIO=uc.ID_USUARIO
                WHERE u.EMAIL='{$mail}'";
        $res = DB::connection('app')->select($sql);

        if (count($res) > 0) {
            return $res[0];
        } else {
            return null;
        }
    }
}
