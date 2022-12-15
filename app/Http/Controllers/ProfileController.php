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
        $disciplines = CatDisciplina::select('ID_DISCIPLINA as idDiscipline', 'NOMBRE as name')->where('ESTATUS', 1)->get();

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
     *     path="/api/crm2/v1/updateProfile",
     *     tags={"Profile"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\RequestBody(ref="#/components/requestBodies/updateProfileRequest"),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function updateProfile(updateProfileRequest $request)
    {
        $mail             = $request->input('email');
        $user             = Usuario::where('EMAIL', $mail)->first();
        $usuarioMigracion = UsuariosMigracion::where('mail', $mail)->first();
        if ($user == null) {
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
        $usuarioCoach = UsuarioCoach::where('ID_USUARIO', $user->ID_USUARIO)->first();
        if ($usuarioCoach == null) {
            $usuarioCoach             = new UsuarioCoach();
            $usuarioCoach->ID_USUARIO = $user->ID_USUARIO;
        }
        $usuarioCoach->APODO           = $request->input('apodo');
        $usuarioCoach->CERTIFICACIONES = $request->input('certificaciones');
        $usuarioCoach->DESCRIPCION     = $request->input('descripcion');
        $usuarioCoach->ESTATUS         = 1;
        $usuarioCoach->ID_EMPLEADO     = $usuarioMigracion->idEmpleado;
        $usuarioCoach->save();
        self::addDeleteClubsTrainerApp($usuarioCoach->ID_USUARIO_COACH, $request->input('clubs'));
        self::addDeleteDisciplineTrainerApp($usuarioCoach->ID_USUARIO_COACH, $request->input('disciplines'));
        return self::profileApp($request, $mail);
    }

    public function addDeleteClubsTrainerApp(Int $idUsuarioCoach, array $clubsInput): void
    {
        $clubs   = UsuarioCoachCatClub::where('ID_USUARIO_COACH', $idUsuarioCoach)->get()->pluck('ID_CLUB')->toArray();
        $clubsBd = array_column($clubsInput, 'idUn');

        foreach ($clubsBd as $value) {
            if (!in_array($value, $clubs)) {
                $usuarioClub                   = new UsuarioCoachCatClub();
                $usuarioClub->ID_USUARIO_COACH = $idUsuarioCoach;
                $usuarioClub->ID_CLUB          = $value;
                $usuarioClub->save();
            }
        }
        foreach ($clubs as $idClub) {
            if (!in_array($idClub, $clubsBd)) {
                UsuarioCoachCatClub::where('ID_CLUB', $idClub)
                    ->where('ID_USUARIO_COACH', $idUsuarioCoach)
                    ->delete();

            }
        }
    }

    public function addDeleteDisciplineTrainerApp(Int $idUsuarioCoach, array $disciplinesInput): void
    {
        $disciplines = UsuarioCoachCatDisciplina::where('ID_USUARIO_COACH', $idUsuarioCoach)
            ->get()
            ->pluck('ID_DISCIPLINA')
            ->toArray();
        $disciplinesBd = array_column($disciplinesInput, 'idDiscipline');
        foreach ($disciplinesBd as $value) {
            if (!in_array($value, $disciplines)) {
                $usuarioClub                   = new UsuarioCoachCatDisciplina();
                $usuarioClub->ID_USUARIO_COACH = $idUsuarioCoach;
                $usuarioClub->ID_DISCIPLINA    = $value;
                $usuarioClub->save();
            }
        }
        foreach ($disciplines as $idClub) {
            if (!in_array($idClub, $disciplinesBd)) {
                $clubUsuario = UsuarioCoachCatDisciplina::where('ID_DISCIPLINA', $idClub)
                    ->where('ID_USUARIO_COACH', $idUsuarioCoach)
                    ->delete();

            }
        }
    }
}
