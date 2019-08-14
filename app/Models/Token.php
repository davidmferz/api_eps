<?php

namespace API_EPS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

use API_EPS\Models\Permiso;

class Token extends Model
{

    protected $connection = 'aws';
    protected $table = 'piso.token_crm';
    protected $primaryKey = 'id';

    public function scopeValidaToken($query,$token){
         $info=$query->where('token',$token)->first();
         if($info == null){
             return false ;
         }else{
             $fecha= Carbon::now();
             $fechaToken=new Carbon ($info->fecha);
             if($fechaToken->diff($fecha)->days>1 || $info->status == 0 ){
                 return false ;
             }else{

                 return true;
             }
         }
    }



}
