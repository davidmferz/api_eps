<?php

namespace App\Http\Controllers;

use App\Http\Requests\updateProfileRequest;
use App\Models\BD_App\CatClub;
use App\Models\BD_App\CatDisciplina;
use App\Models\BD_App\Usuario;
use App\Models\BD_App\UsuarioCoach;
use App\Models\BD_App\UsuarioCoachCatClub;
use App\Models\BD_App\UsuarioCoachCatDisciplina;
use App\Models\UsuariosMigracion;
use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/crm2/v1/clubs",
     *     tags={"Profile"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function clubs()
    {
        $clubs = CatClub::select('ID_CLUB as idUn', 'NOMBRE as name')->where('ESTATUS', 1)->get();

        return $this->successResponse($clubs, 'clubs', 1);
    }

    /**
     * @OA\Get(
     *     path="/api/crm2/v1/disciplines",
     *     tags={"Profile"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function disciplines()
    {
        $disciplines = CatDisciplina::select('ID_DISCIPLINA as idDicipline', 'NOMBRE as name')->where('ESTATUS', 1)->get();

        return $this->successResponse($disciplines, 'disciplines', 1);
    }
    /**
     * @OA\Get(
     *     path="/api/crm2/v1/profileApp/{mail}",
     *     tags={"Profile"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="mail",in="path",@OA\Schema(type="string",default="correo@sports")),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function profileApp(Request $request, $mail)
    {
        $validUser = Usuario::where('EMAIL', $mail)->first();
        if ($validUser == null) {
            return $this->successResponse(null, 'sin registro en la app', -1);
        } else {
            $profileEp   = UsuarioCoach::profile($mail);
            $disciplines = [];
            $clubs       = [];
            if ($profileEp != null) {

                $disciplines            = UsuarioCoachCatDisciplina::disciplinesTrainer($validUser->ID_USUARIO);
                $clubs                  = UsuarioCoachCatClub::clubsTrainer($validUser->ID_USUARIO);
                $profileEp->clubs       = $clubs;
                $profileEp->disciplines = $disciplines;
            } else {
                $profileEp = [
                    'apodo'           => 'Sin informacion',
                    'certificaciones' => 'Sin informacion',
                    'descripcion'     => 'Sin informacion',
                    'clubs'           => [],
                    'disciplines'     => [],
                ];
            }
            return $this->successResponse($profileEp, 'profile', 1);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/crm2/v1/reportByUsers",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\RequestBody(ref="#/components/requestBodies/updateProfileRequest"),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function updateProfile(updateProfileRequest $request, $mail)
    {

        $user = Usuario::where('EMAIL', $mail)->first();
        if ($user == null) {
            $usuarioMigracion = UsuariosMigracion::where('email', $mail)->first();
            if ($usuarioMigracion == null) {
                return $this->successResponse(null, 'sin registro en la app', -1);
            } else {
                $user                   = new Usuario();
                $user->ID_USUARIO       = $usuarioMigracion->idPersona;
                $user->EMAIL            = $mail;
                $user->NOMBRE           = $usuarioMigracion->nombre;
                $user->APELLIDO_PATERNO = $usuarioMigracion->paterno;
                $user->APELLIDO_MATERNO = $usuarioMigracion->materno;
                $user->ID_EMPLEADO      = $usuarioMigracion->idEmpleado;
                $user->save();
                $usuarioMigracion->actualizar = 1;
                $usuarioMigracion->save();
            }
        }
        $validCoach = UsuarioCoach::where('ID_USUARIO', $user->ID_USUARIO)->first();
        if ($validCoach == null) {
            $usuarioCoach             = new UsuarioCoach();
            $usuarioCoach->ID_USUARIO = $user->ID_USUARIO;
        }
        $usuarioCoach->APODO           = $request->input('apodo');
        $usuarioCoach->CERTIFICACIONES = $request->input('certificaciones');
        $usuarioCoach->DESCRIPCION     = $request->input('descripcion');
        $usuarioCoach->ESTATUS         = 1;
        $usuarioCoach->ID_EMPLEADO     = $usuarioMigracion->idEmpleado;
        $usuarioCoach->save();
        $clubs = UsuarioCoachCatClub::where('ID_USUARIO_COACH', $usuarioCoach->ID_USUARIO_COACH)->get()->pluck('ID_CLUB')->toArray();

        foreach ($request->input('clubs') as $key => $value) {
            if (in_array($value['idUn'], $clubs)) {

            } else {

            }
        }
        $disciplines = UsuarioCoachCatDisciplina::where('ID_USUARIO_COACH', $usuarioCoach->ID_USUARIO_COACH)->get();

    }
}
