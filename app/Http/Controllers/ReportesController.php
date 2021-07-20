<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\AgendaInbody;
use App\Models\Menu;
use App\Models\Un;

class ReportesController extends ApiController
{

    public function getRegiones()
    {
        $catalogo = Un::GetClubsRegiones();
        $clubs    = [];
        foreach ($catalogo['clubs'] as $key => $value) {
            $clubs[$key] = array_column($value, 'value');
        }
        $inbodysAgendamientos = AgendaInbody::getReporteInbodysRegion($clubs);
        $rutinasClientes      = Menu::getConteoRutinasRegion($clubs);
        $rankingEntrenadores  = Menu::rakingEntrenadores();
        $response             = [
            'catalogos'            => $catalogo,
            'inbodysAgendamientos' => $inbodysAgendamientos,
            'rutinasClientes'      => $rutinasClientes,
            'rankingEntrenadores'  => $rankingEntrenadores,
        ];
        return $this->successResponse($response, 'catalogo de regiones y datos.');
    }

    public function getReporteRegion($idRegion)
    {
        $clubs    = Un::GetClubsRegiones($idRegion);
        $clubs    = $clubs['clubs'][$idRegion];
        $idsClubs = implode(',', array_column($clubs, 'value'));

        $inbodysAgendamientos = AgendaInbody::getReporteInbodysClub($idsClubs);

        $rutinasClientes = Menu::getConteoRutinasClub($idsClubs);
        $response        = [
            'inbodysAgendamientos' => $inbodysAgendamientos,
            'rutinasClientes'      => $rutinasClientes,
        ];
        return $this->successResponse($response, 'catalogo de regiones y datos.');
    }

    public function getReporteClub($idUn)
    {
        $rutinas  = Menu::getConteoRutinasEntrenadores($idUn);
        $response = [
            'rutinas' => $rutinas,
        ];
        return $this->successResponse($response, 'catalogo rutinas por club.');

    }

}
