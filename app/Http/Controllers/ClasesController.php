<?php

namespace App\Http\Controllers;

use App\Models\BD_APP\CLASES\InstalacionActividad;
use App\Models\BD_APP\CLASES\InstalacionActividadProgramada;
use App\Models\BD_App\Usuario;
use Illuminate\Http\Request;

class ClasesController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/crm2/v1/groupClass/{mail}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="mail",in="path",@OA\Schema(type="string",default="trainer@mail")),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function groupClass(Request $request, $mail)
    {
        $user  = Usuario::where('EMAIL', $mail)->first();
        $class = InstalacionActividadProgramada::currentClass($user->ID_USUARIO);
        return $this->successResponse($class, 'ok', 1);
    }
    /**
     * @OA\Get(
     *     path="/api/crm2/v1/classSize/{idClub}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="idClub",in="path",@OA\Schema(type="integer",default="75")),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */
    public function classSize(Request $request, $idClub)
    {
        $class = InstalacionActividad::classSizeClub($idClub);
        return $this->successResponse($class, 'ok', 1);
    }
    /**
     * @OA\Put(
     *     path="/api/crm2/v1/updateSizeClass/{idActividadInstalacion}/{newSize}",
     *     tags={"Trainers"},
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(name="idActividadInstalacion",in="path",@OA\Schema(type="integer",default="75")),
     *     @OA\Parameter(name="newSize",in="path",@OA\Schema(type="integer",default="10")),
     *     @OA\Response(response=200,description="ok"),
     *     @OA\Response(response=401, description="Autorización inválida"),
     * )
     */

    public function updateSizeClass($idActividadInstalacion, $newSize)
    {
        $instalacionActividad = InstalacionActividad::find($idActividadInstalacion);
        if ((int) $newSize < 0) {
            return $this->successResponse(null, 'capacidad invalida', -1);

        }
        if ($instalacionActividad == null) {
            return $this->successResponse(null, 'invalido', -1);
        }
        $instalacionActividad->capacidadIdeal  = (int) $newSize;
        $instalacionActividad->capacidadMaxima = (int) $newSize;
        $instalacionActividad->save();
        return $this->successResponse($instalacionActividad, 'ok', 1);

    }

}
